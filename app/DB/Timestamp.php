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

namespace Fisharebest\Webtrees\DB;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\PhpDateTimeMappingType;
use Doctrine\DBAL\Types\Type;

/**
 * Custom type for DBAL.
 */
class Timestamp extends Type implements PhpDateTimeMappingType
{
    public const NAME = 'timestamp';

    /**
     * Not needed in 4.0?
     */
    public function getName()
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $precision = (int) $column['precision'];

        if ($precision === 0) {
            $precision_sql = '';
        } else {
            $precision_sql = '(' . $precision . ')';
        }

        if ($platform instanceof SqlitePlatform) {
            return 'DATETIME' . $precision_sql;
        }

        if ($platform instanceof SQLServerPlatform) {
            return 'DATETIME2' . $precision_sql;
        }

        // MySQL, Postgres
        return 'TIMESTAMP' . $precision_sql;
    }
}
