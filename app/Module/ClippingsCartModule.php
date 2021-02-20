<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2021 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Module;

use Aura\Router\Route;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaPage;
use Fisharebest\Webtrees\Http\RequestHandlers\NotePage;
use Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SourcePage;
use Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Note;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\GedcomExportService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Submitter;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

use function app;
use function array_filter;
use function array_keys;
use function array_map;
use function array_search;
use function assert;
use function fopen;
use function in_array;
use function is_string;
use function preg_match_all;
use function redirect;
use function rewind;
use function route;
use function str_replace;
use function stream_get_meta_data;
use function tmpfile;
use function uasort;
use function view;

use const PREG_SET_ORDER;

/**
 * Class ClippingsCartModule
 */
class ClippingsCartModule extends AbstractModule implements ModuleMenuInterface
{
    use ModuleMenuTrait;

    // Routes that have a record which can be added to the clipboard
    private const ROUTES_WITH_RECORDS = [
        'Family'     => FamilyPage::class,
        'Individual' => IndividualPage::class,
        'Media'      => MediaPage::class,
        'Note'       => NotePage::class,
        'Repository' => RepositoryPage::class,
        'Source'     => SourcePage::class,
        'Submitter'  => SubmitterPage::class,
    ];

    /** @var int The default access level for this module.  It can be changed in the control panel. */
    protected $access_level = Auth::PRIV_USER;

    /** @var GedcomExportService */
    private $gedcom_export_service;

    /** @var UserService */
    private $user_service;

    /**
     * ClippingsCartModule constructor.
     *
     * @param GedcomExportService $gedcom_export_service
     * @param UserService         $user_service
     */
    public function __construct(GedcomExportService $gedcom_export_service, UserService $user_service)
    {
        $this->gedcom_export_service = $gedcom_export_service;
        $this->user_service          = $user_service;
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “Clippings cart” module */
        return I18N::translate('Select records from your family tree and save them as a GEDCOM file.');
    }

    /**
     * The default position for this menu.  It can be changed in the control panel.
     *
     * @return int
     */
    public function defaultMenuOrder(): int
    {
        return 6;
    }

    /**
     * A menu, to be added to the main application menu.
     *
     * @param Tree $tree
     *
     * @return Menu|null
     */
    public function getMenu(Tree $tree): ?Menu
    {
        /** @var ServerRequestInterface $request */
        $request = app(ServerRequestInterface::class);

        $route = $request->getAttribute('route');
        assert($route instanceof Route);

        $cart  = Session::get('cart', []);
        $count = count($cart[$tree->name()] ?? []);
        $badge = view('components/badge', ['count' => $count]);

        $submenus = [
            new Menu($this->title() . ' ' . $badge, route('module', [
                'module' => $this->name(),
                'action' => 'Show',
                'tree'   => $tree->name(),
            ]), 'menu-clippings-cart', ['rel' => 'nofollow']),
        ];

        $action = array_search($route->name, self::ROUTES_WITH_RECORDS, true);
        if ($action !== false) {
            $xref = $route->attributes['xref'];
            assert(is_string($xref));

            $add_route = route('module', [
                'module' => $this->name(),
                'action' => 'Add' . $action,
                'xref'   => $xref,
                'tree'   => $tree->name(),
            ]);

            $submenus[] = new Menu(I18N::translate('Add to the clippings cart'), $add_route, 'menu-clippings-add', ['rel' => 'nofollow']);
        }

        if (!$this->isCartEmpty($tree)) {
            $submenus[] = new Menu(I18N::translate('Empty the clippings cart'), route('module', [
                'module' => $this->name(),
                'action' => 'Empty',
                'tree'   => $tree->name(),
            ]), 'menu-clippings-empty', ['rel' => 'nofollow']);

            $submenus[] = new Menu(I18N::translate('Download'), route('module', [
                'module' => $this->name(),
                'action' => 'DownloadForm',
                'tree'   => $tree->name(),
            ]), 'menu-clippings-download', ['rel' => 'nofollow']);
        }

        return new Menu($this->title(), '#', 'menu-clippings', ['rel' => 'nofollow'], $submenus);
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module */
        return I18N::translate('Clippings cart');
    }

    /**
     * @param Tree $tree
     *
     * @return bool
     */
    private function isCartEmpty(Tree $tree): bool
    {
        $cart     = Session::get('cart', []);
        $contents = $cart[$tree->name()] ?? [];

        return $contents === [];
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getDownloadFormAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $user  = $request->getAttribute('user');
        $title = I18N::translate('Family tree clippings cart') . ' — ' . I18N::translate('Download');

        return $this->viewResponse('modules/clippings/download', [
            'is_manager' => Auth::isManager($tree, $user),
            'is_member'  => Auth::isMember($tree, $user),
            'module'     => $this->name(),
            'title'      => $title,
            'tree'       => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postDownloadAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $data_filesystem = Registry::filesystem()->data();

        $params = (array) $request->getParsedBody();

        $privatize_export = $params['privatize_export'] ?? 'none';

        if ($privatize_export === 'none' && !Auth::isManager($tree)) {
            $privatize_export = 'member';
        }

        if ($privatize_export === 'gedadmin' && !Auth::isManager($tree)) {
            $privatize_export = 'member';
        }

        if ($privatize_export === 'user' && !Auth::isMember($tree)) {
            $privatize_export = 'visitor';
        }

        $convert = (bool) ($params['convert'] ?? false);

        $cart = Session::get('cart', []);

        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $xrefs = array_map('strval', $xrefs); // PHP converts numeric keys to integers.

        // Create a new/empty .ZIP file
        $temp_zip_file  = stream_get_meta_data(tmpfile())['uri'];
        $zip_adapter    = new ZipArchiveAdapter($temp_zip_file);
        $zip_filesystem = new Filesystem($zip_adapter);

        $media_filesystem = $tree->mediaFilesystem($data_filesystem);

        // Media file prefix
        $path = $tree->getPreference('MEDIA_DIRECTORY');

        $encoding = $convert ? 'ANSI' : 'UTF-8';

        $records = new Collection();

        switch ($privatize_export) {
            case 'gedadmin':
                $access_level = Auth::PRIV_NONE;
                break;
            case 'user':
                $access_level = Auth::PRIV_USER;
                break;
            case 'visitor':
                $access_level = Auth::PRIV_PRIVATE;
                break;
            case 'none':
            default:
                $access_level = Auth::PRIV_HIDE;
                break;
        }

        foreach ($xrefs as $xref) {
            $object = Registry::gedcomRecordFactory()->make($xref, $tree);
            // The object may have been deleted since we added it to the cart....
            if ($object instanceof GedcomRecord) {
                $record = $object->privatizeGedcom($access_level);
                // Remove links to objects that aren't in the cart
                preg_match_all('/\n1 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[2-9].*)*/', $record, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if (!in_array($match[1], $xrefs, true)) {
                        $record = str_replace($match[0], '', $record);
                    }
                }
                preg_match_all('/\n2 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[3-9].*)*/', $record, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if (!in_array($match[1], $xrefs, true)) {
                        $record = str_replace($match[0], '', $record);
                    }
                }
                preg_match_all('/\n3 ' . Gedcom::REGEX_TAG . ' @(' . Gedcom::REGEX_XREF . ')@(\n[4-9].*)*/', $record, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if (!in_array($match[1], $xrefs, true)) {
                        $record = str_replace($match[0], '', $record);
                    }
                }

                if ($object instanceof Individual || $object instanceof Family) {
                    $records->add($record . "\n1 SOUR @WEBTREES@\n2 PAGE " . $object->url());
                } elseif ($object instanceof Source) {
                    $records->add($record . "\n1 NOTE " . $object->url());
                } elseif ($object instanceof Media) {
                    // Add the media files to the archive
                    foreach ($object->mediaFiles() as $media_file) {
                        $from = $media_file->filename();
                        $to   = $path . $media_file->filename();
                        if (!$media_file->isExternal() && $media_filesystem->has($from) && !$zip_filesystem->has($to)) {
                            $zip_filesystem->writeStream($to, $media_filesystem->readStream($from));
                        }
                    }
                    $records->add($record);
                } else {
                    $records->add($record);
                }
            }
        }

        $base_url = $request->getAttribute('base_url');

        // Create a source, to indicate the source of the data.
        $record = "0 @WEBTREES@ SOUR\n1 TITL " . $base_url;
        $author = $this->user_service->find((int) $tree->getPreference('CONTACT_USER_ID'));
        if ($author !== null) {
            $record .= "\n1 AUTH " . $author->realName();
        }
        $records->add($record);

        $stream = fopen('php://temp', 'wb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        // We have already applied privacy filtering, so do not do it again.
        $this->gedcom_export_service->export($tree, $stream, false, $encoding, Auth::PRIV_HIDE, $path, $records);
        rewind($stream);

        // Finally add the GEDCOM file to the .ZIP file.
        $zip_filesystem->writeStream('clippings.ged', $stream);

        // Need to force-close ZipArchive filesystems.
        $zip_adapter->getArchive()->close();

        // Use a stream, so that we do not have to load the entire file into memory.
        $stream = app(StreamFactoryInterface::class)->createStreamFromFile($temp_zip_file);

        /** @var ResponseFactoryInterface $response_factory */
        $response_factory = app(ResponseFactoryInterface::class);

        return $response_factory->createResponse()
            ->withBody($stream)
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Disposition', 'attachment; filename="clippings.zip');
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getEmptyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $cart                = Session::get('cart', []);
        $cart[$tree->name()] = [];
        Session::put('cart', $cart);

        $url = route('module', [
            'module' => $this->name(),
            'action' => 'Show',
            'tree'   => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postRemoveAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $cart = Session::get('cart', []);
        unset($cart[$tree->name()][$xref]);
        Session::put('cart', $cart);

        $url = route('module', [
            'module' => $this->name(),
            'action' => 'Show',
            'tree'   => $tree->name(),
        ]);

        return redirect($url);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getShowAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        return $this->viewResponse('modules/clippings/show', [
            'module'  => $this->name(),
            'records' => $this->allRecordsInCart($tree),
            'title'   => I18N::translate('Family tree clippings cart'),
            'tree'    => $tree,
        ]);
    }

    /**
     * Get all the records in the cart.
     *
     * @param Tree $tree
     *
     * @return GedcomRecord[]
     */
    private function allRecordsInCart(Tree $tree): array
    {
        $cart = Session::get('cart', []);

        $xrefs = array_keys($cart[$tree->name()] ?? []);
        $xrefs = array_map('strval', $xrefs); // PHP converts numeric keys to integers.

        // Fetch all the records in the cart.
        $records = array_map(static function (string $xref) use ($tree): ?GedcomRecord {
            return Registry::gedcomRecordFactory()->make($xref, $tree);
        }, $xrefs);

        // Some records may have been deleted after they were added to the cart.
        $records = array_filter($records);

        // Group and sort.
        uasort($records, static function (GedcomRecord $x, GedcomRecord $y): int {
            return $x->tag() <=> $y->tag() ?: GedcomRecord::nameComparator()($x, $y);
        });

        return $records;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddFamilyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $family = Registry::familyFactory()->make($xref, $tree);
        $family = Auth::checkFamilyAccess($family);
        $name   = $family->fullName();

        $options = [
            'record'      => $name,
            /* I18N: %s is a family (husband + wife) */
            'members'     => I18N::translate('%s and their children', $name),
            /* I18N: %s is a family (husband + wife) */
            'descendants' => I18N::translate('%s and their descendants', $name),
        ];

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'record'  => $family,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddFamilyAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();

        $xref   = $params['xref'] ?? '';
        $option = $params['option'] ?? '';

        $family = Registry::familyFactory()->make($xref, $tree);
        $family = Auth::checkFamilyAccess($family);

        switch ($option) {
            case 'self':
                $this->addFamilyToCart($family);
                break;

            case 'members':
                $this->addFamilyAndChildrenToCart($family);
                break;

            case 'descendants':
                $this->addFamilyAndDescendantsToCart($family);
                break;
        }

        return redirect($family->url());
    }


    /**
     * @param Family $family
     *
     * @return void
     */
    protected function addFamilyAndChildrenToCart(Family $family): void
    {
        $this->addFamilyToCart($family);

        foreach ($family->children() as $child) {
            $this->addIndividualToCart($child);
        }
    }

    /**
     * @param Family $family
     *
     * @return void
     */
    protected function addFamilyAndDescendantsToCart(Family $family): void
    {
        $this->addFamilyAndChildrenToCart($family);

        foreach ($family->children() as $child) {
            foreach ($child->spouseFamilies() as $child_family) {
                $this->addFamilyAndDescendantsToCart($child_family);
            }
        }
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddIndividualAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual);
        $name       = $individual->fullName();

        if ($individual->sex() === 'F') {
            $options = [
                'record'            => $name,
                'parents'           => I18N::translate('%s, her parents and siblings', $name),
                'spouses'           => I18N::translate('%s, her spouses and children', $name),
                'ancestors'         => I18N::translate('%s and her ancestors', $name),
                'ancestor_families' => I18N::translate('%s, her ancestors and their families', $name),
                'descendants'       => I18N::translate('%s, her spouses and descendants', $name),
            ];
        } else {
            $options = [
                'record'            => $name,
                'parents'           => I18N::translate('%s, his parents and siblings', $name),
                'spouses'           => I18N::translate('%s, his spouses and children', $name),
                'ancestors'         => I18N::translate('%s and his ancestors', $name),
                'ancestor_families' => I18N::translate('%s, his ancestors and their families', $name),
                'descendants'       => I18N::translate('%s, his spouses and descendants', $name),
            ];
        }

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'record'  => $individual,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddIndividualAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();

        $xref   = $params['xref'] ?? '';
        $option = $params['option'] ?? '';

        $individual = Registry::individualFactory()->make($xref, $tree);
        $individual = Auth::checkIndividualAccess($individual);

        switch ($option) {
            case 'self':
                $this->addIndividualToCart($individual);
                break;

            case 'parents':
                foreach ($individual->childFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
                break;

            case 'spouses':
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndChildrenToCart($family);
                }
                break;

            case 'ancestors':
                $this->addAncestorsToCart($individual);
                break;

            case 'ancestor_families':
                $this->addAncestorFamiliesToCart($individual);
                break;

            case 'descendants':
                foreach ($individual->spouseFamilies() as $family) {
                    $this->addFamilyAndDescendantsToCart($family);
                }
                break;
        }

        return redirect($individual->url());
    }

    /**
     * @param Individual $individual
     *
     * @return void
     */
    protected function addAncestorsToCart(Individual $individual): void
    {
        $this->addIndividualToCart($individual);

        foreach ($individual->childFamilies() as $family) {
            $this->addFamilyToCart($family);

            foreach ($family->spouses() as $parent) {
                $this->addAncestorsToCart($parent);
            }
        }
    }

    /**
     * @param Individual $individual
     *
     * @return void
     */
    protected function addAncestorFamiliesToCart(Individual $individual): void
    {
        foreach ($individual->childFamilies() as $family) {
            $this->addFamilyAndChildrenToCart($family);

            foreach ($family->spouses() as $parent) {
                $this->addAncestorFamiliesToCart($parent);
            }
        }
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddMediaAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $media = Registry::mediaFactory()->make($xref, $tree);
        $media = Auth::checkMediaAccess($media);
        $name  = $media->fullName();

        $options = [
            'record' => $name,
        ];

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'record'  => $media,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddMediaAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $media = Registry::mediaFactory()->make($xref, $tree);
        $media = Auth::checkMediaAccess($media);

        $this->addMediaToCart($media);

        return redirect($media->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddNoteAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $note = Registry::noteFactory()->make($xref, $tree);
        $note = Auth::checkNoteAccess($note);
        $name = $note->fullName();

        $options = [
            'record' => $name,
        ];

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'record'  => $note,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddNoteAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $note = Registry::noteFactory()->make($xref, $tree);
        $note = Auth::checkNoteAccess($note);

        $this->addNoteToCart($note);

        return redirect($note->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddRepositoryAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $repository = Registry::repositoryFactory()->make($xref, $tree);
        $repository = Auth::checkRepositoryAccess($repository);
        $name       = $repository->fullName();

        $options = [
            'record' => $name,
        ];

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'record'  => $repository,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddRepositoryAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $repository = Registry::repositoryFactory()->make($xref, $tree);
        $repository = Auth::checkRepositoryAccess($repository);

        $this->addRepositoryToCart($repository);

        foreach ($repository->linkedSources('REPO') as $source) {
            $this->addSourceToCart($source);
        }

        return redirect($repository->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddSourceAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $source = Registry::sourceFactory()->make($xref, $tree);
        $source = Auth::checkSourceAccess($source);
        $name   = $source->fullName();

        $options = [
            'record' => $name,
            'linked' => I18N::translate('%s and the individuals that reference it.', $name),
        ];

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'record'  => $source,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddSourceAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();

        $xref   = $params['xref'] ?? '';
        $option = $params['option'] ?? '';

        $source = Registry::sourceFactory()->make($xref, $tree);
        $source = Auth::checkSourceAccess($source);

        $this->addSourceToCart($source);

        if ($option === 'linked') {
            foreach ($source->linkedIndividuals('SOUR') as $individual) {
                $this->addIndividualToCart($individual);
            }
            foreach ($source->linkedFamilies('SOUR') as $family) {
                $this->addFamilyToCart($family);
            }
        }

        return redirect($source->url());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAddSubmitterAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $submitter = Registry::submitterFactory()->make($xref, $tree);
        $submitter = Auth::checkSubmitterAccess($submitter);
        $name      = $submitter->fullName();

        $options = [
            'record' => $name,
        ];

        $title = I18N::translate('Add %s to the clippings cart', $name);

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'record'  => $submitter,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddSubmitterAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'] ?? '';

        $submitter = Registry::submitterFactory()->make($xref, $tree);
        $submitter = Auth::checkSubmitterAccess($submitter);

        $this->addSubmitterToCart($submitter);

        return redirect($submitter->url());
    }

    /**
     * @param Family $family
     */
    protected function addFamilyToCart(Family $family): void
    {
        $cart = Session::get('cart', []);
        $tree = $family->tree()->name();
        $xref = $family->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            foreach ($family->spouses() as $spouse) {
                $this->addIndividualToCart($spouse);
            }

            $this->addMediaLinksToCart($family);
            $this->addNoteLinksToCart($family);
            $this->addSourceLinksToCart($family);
            $this->addSubmitterLinksToCart($family);
        }
    }

    /**
     * @param Individual $individual
     */
    protected function addIndividualToCart(Individual $individual): void
    {
        $cart = Session::get('cart', []);
        $tree = $individual->tree()->name();
        $xref = $individual->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addMediaLinksToCart($individual);
            $this->addNoteLinksToCart($individual);
            $this->addSourceLinksToCart($individual);
        }
    }

    /**
     * @param Media $media
     */
    protected function addMediaToCart(Media $media): void
    {
        $cart = Session::get('cart', []);
        $tree = $media->tree()->name();
        $xref = $media->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addNoteLinksToCart($media);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    protected function addMediaLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d OBJE @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $media = Registry::mediaFactory()->make($xref, $record->tree());

            if ($media instanceof Media && $media->canShow()) {
                $this->addMediaToCart($media);
            }
        }
    }

    /**
     * @param Note $note
     */
    protected function addNoteToCart(Note $note): void
    {
        $cart = Session::get('cart', []);
        $tree = $note->tree()->name();
        $xref = $note->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    protected function addNoteLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d NOTE @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $note = Registry::noteFactory()->make($xref, $record->tree());

            if ($note instanceof Note && $note->canShow()) {
                $this->addNoteToCart($note);
            }
        }
    }

    /**
     * @param Source $source
     */
    protected function addSourceToCart(Source $source): void
    {
        $cart = Session::get('cart', []);
        $tree = $source->tree()->name();
        $xref = $source->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addNoteLinksToCart($source);
            $this->addRepositoryLinksToCart($source);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    protected function addSourceLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d SOUR @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $source = Registry::sourceFactory()->make($xref, $record->tree());

            if ($source instanceof Source && $source->canShow()) {
                $this->addSourceToCart($source);
            }
        }
    }

    /**
     * @param Repository $repository
     */
    protected function addRepositoryToCart(Repository $repository): void
    {
        $cart = Session::get('cart', []);
        $tree = $repository->tree()->name();
        $xref = $repository->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addNoteLinksToCart($repository);
        }
    }


    /**
     * @param GedcomRecord $record
     */
    protected function addRepositoryLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d REPO @(' . Gedcom::REGEX_XREF . '@)/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $repository = Registry::repositoryFactory()->make($xref, $record->tree());

            if ($repository instanceof Repository && $repository->canShow()) {
                $this->addRepositoryToCart($repository);
            }
        }
    }

    /**
     * @param Submitter $submitter
     */
    protected function addSubmitterToCart(Submitter $submitter): void
    {
        $cart = Session::get('cart', []);
        $tree = $submitter->tree()->name();
        $xref = $submitter->xref();

        if (($cart[$tree][$xref] ?? false) === false) {
            $cart[$tree][$xref] = true;

            Session::put('cart', $cart);

            $this->addNoteLinksToCart($submitter);
        }
    }

    /**
     * @param GedcomRecord $record
     */
    protected function addSubmitterLinksToCart(GedcomRecord $record): void
    {
        preg_match_all('/\n\d SUBM @(' . Gedcom::REGEX_XREF . ')@/', $record->gedcom(), $matches);

        foreach ($matches[1] as $xref) {
            $submitter = Registry::submitterFactory()->make($xref, $record->tree());

            if ($submitter instanceof Submitter && $submitter->canShow()) {
                $this->addSubmitterToCart($submitter);
            }
        }
    }
}
