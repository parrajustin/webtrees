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

use Fisharebest\Webtrees\DB\Schema\Schema;
use Fisharebest\Webtrees\DB\Schema\Column;
use Fisharebest\Webtrees\DB\Schema\Table;

use function array_filter;

/**
 * Driver for MySQL
 */
class MySQLDriver extends AbstractDriver implements DriverInterface
{
    public function introspectSchema(string $schema_name = null): Schema
    {
        $schema_name ?? $schema_name = $this->query('SELECT DATABASE() AS schema_name')[0]->schema_name;

        $pattern = $this->escapeLike($this->prefix) . '%';

        $sql =
            'SELECT    TABLE_NAME, ENGINE, AUTO_INCREMENT, TABLE_COLLATION' .
            ' FROM     INFORMATION_SCHEMA.TABLES' .
            ' WHERE    TABLE_TYPE   =    :table_type' .
            '   AND    TABLE_SCHEMA =    :table_schema' .
            '   AND    TABLE_NAME   LIKE :pattern' .
            ' ORDER BY TABLE_NAME';

        $table_rows = $this->query($sql, [
            'table_type'   => 'BASE TABLE',
            'pattern'      => $pattern,
            'table_schema' => $schema_name,
        ]);

        $sql =
            'SELECT    TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, DATETIME_PRECISION, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_TYPE, COLUMN_KEY, EXTRA, COLUMN_COMMENT, GENERATION_EXPRESSION, SRS_ID' .
            ' FROM     INFORMATION_SCHEMA.COLUMNS' .
            ' WHERE    TABLE_SCHEMA =    :table_schema' .
            '   AND    TABLE_NAME   LIKE :pattern' .
            ' ORDER BY ORDINAL_POSITION';

        $column_rows = $this->query($sql, ['pattern' => $pattern, 'table_schema' => $schema_name]);

        $sql =
            'SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE' .
            ' FROM  INFORMATION_SCHEMA.TABLE_CONSTRAINTS' .
            ' WHERE TABLE_SCHEMA =    :table_schema' .
            '   AND TABLE_NAME   LIKE :pattern';

        $table_constraints_rows = $this->query($sql, ['pattern' => $pattern, 'table_schema' => $schema_name]);

        $sql =
            'SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, POSITION_IN_UNIQUE_CONSTRAINT,' .
            '       REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME' .
            ' FROM  INFORMATION_SCHEMA.KEY_COLUMN_USAGE' .
            ' WHERE TABLE_SCHEMA = :table_schema' .
            '   AND TABLE_NAME LIKE :pattern';

        $key_column_usage_rows = $this->query($sql, ['pattern' => $pattern, 'table_schema' => $schema_name]);

        foreach ($table_rows as $table_row) {
            $table = new Table($table_row->TABLE_NAME);

            $this_column_rows = array_filter($column_rows, static fn (object $row): bool => $row->TABLE_NAME === $table_row->TABLE_NAME);

            foreach ($this_column_rows as $this_column_row) {
                $column = new Column($this_column_row->COLUMN_NAME, $this_column_row->COLUMN_TYPE, $this_column_row->COLUMN_DEFAULT);
            }

        }

        var_dump($pattern, $schema_name, $table_rows, $column_rows, $table_constraints_rows, $key_column_usage_rows);exit;
    }
}
