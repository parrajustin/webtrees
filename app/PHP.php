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

namespace Fisharebest\Webtrees;

use RuntimeException;

use function preg_last_error_msg;
use function preg_match;
use function preg_replace;

/**
 * Wrappers around PHP functions that throw exceptions instead of returning error values.
 * Eliminates many false-positives during static analysis.
 */
class PHP
{
    /**
     * @template T of 0|256|512|768
     *
     * @param string             $pattern
     * @param string             $subject
     * @param array<string>|null $matches
     * @param T                  $flags
     * @param int                $offset
     *
     * @return int
     */
    public static function pregMatch(string $pattern, string $subject, array &$matches = null, int $flags = 0, int $offset = 0): int
    {
        $result = preg_match($pattern, $subject, $matches, $flags, $offset);

        if ($result === false) {
            throw new RuntimeException(preg_last_error_msg());
        }

        return $result;
    }

    /**
     * @template T of string|array<int,string>
     *
     * @param string|array<string> $pattern
     * @param string|array<string> $replacement
     * @param T                    $subject
     * @param int                  $limit
     * @param int|null             $count
     *
     * @return T
     */
    public static function pregReplace(
        string|array $pattern,
        string|array $replacement,
        string|array $subject,
        int $limit = -1,
        int &$count = null
    ): string|array {
        $result = preg_replace($pattern, $replacement, $subject, $limit, $count);

        if ($result === null) {
            throw new RuntimeException(preg_last_error_msg());
        }

        return $result;
    }
}
