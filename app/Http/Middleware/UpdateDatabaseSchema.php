<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2022 webtrees development team
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

namespace Fisharebest\Webtrees\Http\Middleware;

use Doctrine\DBAL\DriverManager;
use Fisharebest\Webtrees\DB\Connection;
use Fisharebest\Webtrees\DB\WebtreesSchema;
use Fisharebest\Webtrees\Services\MigrationService;
use Fisharebest\Webtrees\Webtrees;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function parse_ini_file;

/**
 * Middleware to update the database automatically, after an upgrade.
 */
class UpdateDatabaseSchema implements MiddlewareInterface
{
    private MigrationService $migration_service;

    /**
     * @param MigrationService $migration_service
     */
    public function __construct(MigrationService $migration_service)
    {
        $this->migration_service = $migration_service;
    }

    /**
     * Update the database schema, if necessary.
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->migration_service
            ->updateSchema('\Fisharebest\Webtrees\WebtreesSchema', 'WT_SCHEMA_VERSION', Webtrees::SCHEMA_VERSION);

        $config = parse_ini_file('data/config.ini.php');

        $connection = DriverManager::getConnection([
            'wrapperClass' => Connection::class,
            'dbname'       => $config['dbname'],
            'user'         => $config['dbuser'],
            'password'     => $config['dbpass'],
            'host'         => $config['dbhost'],
            'driver'       => 'pdo_' . $config['dbtype'],
            'prefix'       => $config['tblpfx'],
        ]);

        WebtreesSchema::migrate($connection, 'wt_');

        return $handler->handle($request);
    }
}
