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

namespace Fisharebest\Webtrees\DB\Drivers;

use PDO;
use PDOException;
use RuntimeException;

use function is_bool;
use function is_int;

/**
 * Common functionality for all drivers.
 */
abstract class AbstractDriver
{
    /**
     * @param PDO    $pdo
     * @param string $prefix
     */
    public function __construct(protected readonly PDO $pdo, protected readonly string $prefix)
    {
    }

    /**
     * Prepare, bind and execute a select query.
     *
     * @param string                            $sql
     * @param array<null|bool|int|float|string> $bindings
     *
     * @return array<object>
     */
    public function query(string $sql, array $bindings = []): array
    {
        try {
            $statement = $this->pdo->prepare($sql);
        } catch (PDOException) {
            $statement = false;
        }

        if ($statement === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $sql);
        }

        foreach ($bindings as $param => $value) {
            $type = match (true) {
                $value === null => PDO::PARAM_NULL,
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value)  => PDO::PARAM_INT,
                default         => PDO::PARAM_STR,
            };

            if (is_int($param)) {
                // Positional parameters are numeric, starting at 1.
                $statement->bindValue($param + 1, $value, $type);
            } else {
                // Named parameters are (optionally) prefixed with a colon.
                $statement->bindValue(':' . $param, $value, $type);
            }
        }

        if ($statement->execute()) {
            return $statement->fetchAll(PDO::FETCH_OBJ);
        }

        throw new RuntimeException('Failed to execute statement: ' . $sql);
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function escapeLike(string $string): string
    {
        return strtr($string, [
            '%'  => '\\%',
            '_'  => '\\_',
            '\\' => '\\\\',
        ]);
    }
}
