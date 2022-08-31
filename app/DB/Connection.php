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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as BaseConnection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

/**
 * Extend the Doctrine/DBAL database connection.
 */
class Connection extends BaseConnection
{
    public readonly string $prefix;

    public function __construct(
        array $params,
        Driver $driver,
        ?Configuration $config = null
    ) {
        $this->prefix = $params['prefix'];

        unset($params['prefix']);

        parent::__construct($params, $driver, $config);
    }

    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this, $this->prefix);
    }

    public function createSchemaManager(): AbstractSchemaManager
    {
        return $this->getDatabasePlatform()
            ->createSchemaManager($this);
    }
}
