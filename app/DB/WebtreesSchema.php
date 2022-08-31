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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Exception;

/**
 * Definitions for the webtrees database.
 */
class WebtreesSchema
{
    public function foo(): void
    {
        switch ('webtrees_schema') {
            case 1: // webtrees 1.0.0 - 1.0.3
            case 2: // webtrees 1.0.4
            case 3:
            case 4: // webtrees 1.0.5
            case 5: // webtrees 1.0.6
            case 6:
            case 7:
            case 8:
            case 9: // webtrees 1.1.0 - 1.1.1
            case 10: // webtrees 1.1.2
            case 11: // webtrees 1.2.0
            case 12: // webtrees 1.2.1 - 1.2.3
            case 13:
            case 14:
            case 15: // webtrees 1.2.4 - 1.2.5
            case 16: // webtrees 1.2.7
            case 17:
            case 18: // webtrees 1.3.0
            case 19: // webtrees 1.3.1
            case 20: // webtrees 1.3.2
            case 21:
            case 22:
            case 23: // webtrees 1.4.0 - 1.4.1
            case 24:
            case 25: // webtrees 1.4.2 - 1.4.4, 1.5.0
            case 26: // webtrees 1.4.5 - 1.4.6
            case 27: // webtrees 1.5.1 - 1.6.0
            case 28:
            case 29: // webtrees 1.6.1 - 1.6.2
            case 30:
            case 31: // webtrees 1.7.0 - 1.7.1
            case 32: // webtrees 1.7.2
            case 33:
            case 34: // webtrees 1.7.3 - 1.7.4
            case 35:
            case 36: // webtrees 1.7.5 - 1.7.7
            case 37: // webtrees 1.7.8 - 2.0.0
            case 38:
            case 39:
            case 40: // webtrees 2.0.1 - 2.1.15
        }
    }

    /**
     * @param Connection $connection
     *
     * @return void
     *
     * @throws SchemaException
     * @throws \Doctrine\DBAL\Exception
     */
    public static function migrate(Connection $connection): void
    {
        $prefix   = $connection->prefix;
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof AbstractMySQLPlatform) {
            $ascii_bin = ['collation' => 'ascii_bin'];
            $utf8_bin  = ['collation' => 'utf8mb4_bin'];
            //$ascii_bin = [];
            //$utf8_bin  = [];
        } else {
            $ascii_bin = [];
            $utf8_bin  = [];
        }

        // Existing MySQL databases may have enum columns.
        $platform->registerDoctrineTypeMapping('enum', 'string');

        // Timestamp
        if (!Type::hasType(Timestamp::NAME)) {
            Type::addType(Timestamp::NAME, Timestamp::class);
        }

        $schema = new Schema($prefix);

        $table_gedcom = $schema->createTable('gedcom');
        $table_gedcom->addColumn('gedcom_id', Types::INTEGER, ['autoincrement' => true]);
        $table_gedcom->addColumn('gedcom_name', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_gedcom->addColumn('sort_order', Types::INTEGER, ['default' => 0]);
        $table_gedcom->setPrimaryKey(['gedcom_id']);
        $table_gedcom->addUniqueIndex(['gedcom_name']);
        $table_gedcom->addIndex(['sort_order']);

        $table_user = $schema->createTable('user');
        $table_user->addColumn('user_id', Types::INTEGER, ['autoincrement' => true]);
        $table_user->addColumn('user_name', Types::STRING, ['length' => 32, 'platformOptions' => $utf8_bin]);
        $table_user->addColumn('real_name', Types::STRING, ['length' => 64, 'platformOptions' => $utf8_bin]);
        $table_user->addColumn('email', Types::STRING, ['length' => 64, 'platformOptions' => $utf8_bin]);
        $table_user->addColumn('password', Types::STRING, ['length' => 128, 'platformOptions' => $utf8_bin]);
        $table_user->setPrimaryKey(['user_id']);
        $table_user->addUniqueIndex(['user_name']);
        $table_user->addUniqueIndex(['email']);

        $table_module = $schema->createTable('module');
        $table_module->addColumn('module_name', Types::STRING, ['length' => 32, 'platformOptions' => $utf8_bin]);
        $table_module->addColumn('status', Types::STRING, ['length' => 8, 'platformOptions' => $ascii_bin]);
        $table_module->addColumn('tab_order', Types::INTEGER, ['notnull' => false]);
        $table_module->addColumn('menu_order', Types::INTEGER, ['notnull' => false]);
        $table_module->addColumn('sidebar_order', Types::INTEGER, ['notnull' => false]);
        $table_module->addColumn('footer_order', Types::INTEGER, ['notnull' => false]);
        $table_module->setPrimaryKey(['module_name']);

        $table_block = $schema->createTable('block');
        $table_block->addColumn('block_id', Types::INTEGER, ['autoincrement' => true]);
        $table_block->addColumn('gedcom_id', Types::INTEGER, ['notnull' => false]);
        $table_block->addColumn('user_id', Types::INTEGER, ['notnull' => false]);
        $table_block->addColumn('xref', Types::STRING, ['length' => 20, 'notnull' => false, 'platformOptions' => $ascii_bin]);
        $table_block->addColumn('location', Types::STRING, ['length' => 4, 'notnull' => false, 'platformOptions' => $ascii_bin]);
        $table_block->addColumn('block_order', Types::INTEGER);
        $table_block->addColumn('module_name', Types::STRING, ['length' => 32, 'platformOptions' => $utf8_bin]);
        $table_block->setPrimaryKey(['block_id']);
        $table_block->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        $table_block->addForeignKeyConstraint($table_user->getName(), ['user_id'], ['user_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        $table_block->addForeignKeyConstraint($table_module->getName(), ['module_name'], ['module_name'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_block_setting = $schema->createTable('block_setting');
        $table_block_setting->addColumn('block_id', Types::INTEGER);
        $table_block_setting->addColumn('setting_name', Types::STRING, ['length' => 32, 'platformOptions' => $ascii_bin]);
        $table_block_setting->addColumn('setting_value', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_block_setting->setPrimaryKey(['block_id', 'setting_name']);
        $table_block_setting->addForeignKeyConstraint($table_block->getName(), ['block_id'], ['block_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_change = $schema->createTable('change');
        $table_change->addColumn('change_id', Types::INTEGER, ['autoincrement' => true]);
        $table_change->addColumn('change_time', Types::DATETIME_MUTABLE, ['default' => 'CURRENT_TIMESTAMP']);
        $table_change->addColumn('status', Types::STRING, ['length' => 8, 'default' => 'pending', 'platformOptions' => $ascii_bin]);
        $table_change->addColumn('gedcom_id', Types::INTEGER);
        $table_change->addColumn('xref', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_change->addColumn('old_gedcom', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_change->addColumn('new_gedcom', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_change->addColumn('user_id', Types::INTEGER);
        $table_change->setPrimaryKey(['change_id']);
        $table_change->addIndex(['gedcom_id', 'status', 'xref']);
        $table_change->addIndex(['user_id']);
        $table_change->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        $table_change->addForeignKeyConstraint($table_user->getName(), ['user_id'], ['user_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_dates = $schema->createTable('dates');
        $table_dates->addColumn('d_day', Types::SMALLINT);
        $table_dates->addColumn('d_month', Types::STRING, ['length' => 5, 'fixed' => true, 'platformOptions' => $ascii_bin]);
        $table_dates->addColumn('d_mon', Types::SMALLINT);
        $table_dates->addColumn('d_year', Types::SMALLINT);
        $table_dates->addColumn('d_julianday1', Types::INTEGER);
        $table_dates->addColumn('d_julianday2', Types::INTEGER);
        $table_dates->addColumn('d_fact', Types::STRING, ['length' => 15, 'platformOptions' => $ascii_bin]);
        $table_dates->addColumn('d_gid', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_dates->addColumn('d_file', Types::INTEGER);
        $table_dates->addColumn('d_type', Types::STRING, ['length' => 13, 'platformOptions' => $ascii_bin]);
        $table_dates->addIndex(['d_day']);
        $table_dates->addIndex(['d_month']);
        $table_dates->addIndex(['d_mon']);
        $table_dates->addIndex(['d_year']);
        $table_dates->addIndex(['d_julianday1']);
        $table_dates->addIndex(['d_julianday2']);
        $table_dates->addIndex(['d_gid']);
        $table_dates->addIndex(['d_file']);
        $table_dates->addIndex(['d_type']);
        $table_dates->addIndex(['d_fact', 'd_gid']);

        $table_default_resn = $schema->createTable('default_resn');
        $table_default_resn->addColumn('default_resn_id', Types::INTEGER, ['autoincrement' => true]);
        $table_default_resn->addColumn('gedcom_id', Types::INTEGER);
        $table_default_resn->addColumn('xref', Types::STRING, ['length' => 20, 'notNull' => false, 'platformOptions' => $ascii_bin]);
        $table_default_resn->addColumn('tag_type', Types::STRING, ['length' => 15, 'notNull' => false, 'platformOptions' => $ascii_bin]);
        $table_default_resn->addColumn('resn', Types::STRING, ['length' => 12, 'platformOptions' => $ascii_bin]);
        $table_default_resn->setPrimaryKey(['default_resn_id']);
        $table_default_resn->addIndex(['gedcom_id', 'xref', 'tag_type']);
        $table_default_resn->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_families = $schema->createTable('families');
        $table_families->addColumn('f_id', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_families->addColumn('f_file', Types::INTEGER);
        $table_families->addColumn('f_husb', Types::STRING, ['length' => 20, 'notNull' => false, 'platformOptions' => $ascii_bin]);
        $table_families->addColumn('f_wife', Types::STRING, ['length' => 20, 'notNull' => false, 'platformOptions' => $ascii_bin]);
        $table_families->addColumn('f_gedcom', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_families->addColumn('f_numchil', Types::INTEGER);
        $table_families->setPrimaryKey(['f_id', 'f_file']);
        $table_families->addUniqueIndex(['f_file', 'f_id']);
        $table_families->addIndex(['f_husb']);
        $table_families->addIndex(['f_wife']);

        $table_favorite = $schema->createTable('favorite');
        $table_favorite->addColumn('favorite_id', Types::INTEGER, ['autoincrement' => true]);
        $table_favorite->addColumn('user_id', Types::INTEGER, ['notNull' => false]);
        $table_favorite->addColumn('xref', Types::STRING, ['length' => 20, 'notNull' => false, 'platformOptions' => $ascii_bin]);
        $table_favorite->addColumn('favorite_type', Types::STRING, ['length' => 4, 'platformOptions' => $ascii_bin]);
        $table_favorite->addColumn('url', Types::STRING, ['length' => 255, 'notNull' => false, 'platformOptions' => $utf8_bin]);
        $table_favorite->addColumn('title', Types::STRING, ['length' => 255, 'notNull' => false, 'platformOptions' => $utf8_bin]);
        $table_favorite->addColumn('note', Types::STRING, ['length' => 1000, 'notNull' => false, 'platformOptions' => $utf8_bin]);
        $table_favorite->addColumn('gedcom_id', Types::INTEGER);
        $table_favorite->setPrimaryKey(['favorite_id']);
        $table_favorite->addIndex(['user_id']);
        $table_favorite->addIndex(['gedcom_id', 'user_id']);
        $table_favorite->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        $table_favorite->addForeignKeyConstraint($table_user->getName(), ['user_id'], ['user_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_gedcom_chunk = $schema->createTable('gedcom_chunk');
        $table_gedcom_chunk->addColumn('gedcom_chunk_id', Types::INTEGER, ['autoincrement' => true]);
        $table_gedcom_chunk->addColumn('gedcom_id', Types::INTEGER);
        $table_gedcom_chunk->addColumn('chunk_data', Types::BLOB);
        $table_gedcom_chunk->addColumn('imported', Types::INTEGER, ['default' => 0]);
        $table_gedcom_chunk->setPrimaryKey(['gedcom_chunk_id']);
        $table_gedcom_chunk->addIndex(['gedcom_id', 'imported']);
        $table_gedcom_chunk->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_gedcom_setting = $schema->createTable('gedcom_setting');
        $table_gedcom_setting->addColumn('gedcom_id', Types::INTEGER);
        $table_gedcom_setting->addColumn('setting_name', Types::STRING, ['length' => 32, 'platformOptions' => $ascii_bin]);
        $table_gedcom_setting->addColumn('setting_value', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_gedcom_setting->setPrimaryKey(['gedcom_id', 'setting_name']);
        $table_gedcom_setting->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_hit_counter = $schema->createTable('hit_counter');
        $table_hit_counter->addColumn('gedcom_id', Types::INTEGER);
        $table_hit_counter->addColumn('page_name', Types::STRING, ['length' => 32, 'platformOptions' => $ascii_bin]);
        $table_hit_counter->addColumn('page_parameter', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_hit_counter->addColumn('page_count', Types::INTEGER);
        $table_hit_counter->setPrimaryKey(['gedcom_id', 'page_name', 'page_parameter']);
        $table_hit_counter->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_individuals = $schema->createTable('individuals');
        $table_individuals->addColumn('i_id', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_individuals->addColumn('i_file', Types::INTEGER);
        $table_individuals->addColumn('i_rin', Types::STRING, ['length' => 20, 'platformOptions' => $utf8_bin]);
        $table_individuals->addColumn('i_sex', Types::STRING, ['length' => 1, 'platformOptions' => $ascii_bin]);
        $table_individuals->addColumn('i_gedcom', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_individuals->setPrimaryKey(['i_id', 'i_file']);
        $table_individuals->addUniqueIndex(['i_file', 'i_id']);

        $table_link = $schema->createTable('link');
        $table_link->addColumn('l_file', Types::INTEGER);
        $table_link->addColumn('l_from', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_link->addColumn('l_type', Types::STRING, ['length' => 15, 'platformOptions' => $ascii_bin]);
        $table_link->addColumn('l_to', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_link->setPrimaryKey(['l_from', 'l_file', 'l_type', 'l_to']);
        $table_link->addUniqueIndex(['l_to', 'l_file', 'l_type', 'l_from']);

        $table_log = $schema->createTable('log');
        $table_log->addColumn('log_id', Types::INTEGER, ['autoincrement' => true]);
        $table_log->addColumn('log_time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'precision' => 0]);
        $table_log->addColumn('log_type', Types::STRING, ['length' => 6, 'platformOptions' => $ascii_bin]);
        $table_log->addColumn('log_message', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_log->addColumn('ip_address', Types::STRING, ['length' => 45]);
        $table_log->addColumn('user_id', Types::INTEGER, ['notNull' => false]);
        $table_log->addColumn('gedcom_id', Types::INTEGER, ['notNull' => false]);
        $table_log->setPrimaryKey(['log_id']);
        $table_log->addIndex(['log_time']);
        $table_log->addIndex(['log_type']);
        $table_log->addIndex(['ip_address']);
        $table_log->addIndex(['user_id']);
        $table_log->addIndex(['gedcom_id']);
        $table_log->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        $table_log->addForeignKeyConstraint($table_user->getName(), ['user_id'], ['user_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_media = $schema->createTable('media');
        $table_media->addColumn('m_id', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_media->addColumn('m_file', Types::INTEGER);
        $table_media->addColumn('m_gedcom', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_media->setPrimaryKey(['m_file', 'm_id']);
        $table_media->addUniqueIndex(['m_id', 'm_file']);

        $table_media_file = $schema->createTable('media_file');
        $table_media_file->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table_media_file->addColumn('m_id', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_media_file->addColumn('m_file', Types::INTEGER);
        $table_media_file->addColumn('multimedia_file_refn', Types::STRING, ['length' => 246, 'platformOptions' => $utf8_bin]);
        $table_media_file->addColumn('multimedia_format', Types::STRING, ['length' => 4, 'platformOptions' => $utf8_bin]);
        $table_media_file->addColumn('source_media_type', Types::STRING, ['length' => 15, 'platformOptions' => $utf8_bin]);
        $table_media_file->addColumn('descriptive_title', Types::STRING, ['length' => 248, 'platformOptions' => $utf8_bin]);
        $table_media_file->setPrimaryKey(['id']);
        $table_media_file->addIndex(['m_id', 'm_file']);
        $table_media_file->addIndex(['m_file', 'm_id']);
        $table_media_file->addIndex(['m_file', 'multimedia_file_refn']);
        $table_media_file->addIndex(['m_file', 'multimedia_format']);
        $table_media_file->addIndex(['m_file', 'source_media_type']);
        $table_media_file->addIndex(['m_file', 'descriptive_title']);

        $table_message = $schema->createTable('message');
        $table_message->addColumn('message_id', Types::INTEGER, ['autoincrement' => true]);
        $table_message->addColumn('sender', Types::STRING, ['length' => 64, 'platformOptions' => $utf8_bin]);
        $table_message->addColumn('ip_address', Types::STRING, ['length' => 45, 'platformOptions' => $ascii_bin]);
        $table_message->addColumn('user_id', Types::INTEGER);
        $table_message->addColumn('subject', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_message->addColumn('body', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_message->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'precision' => 0]);
        $table_message->addIndex(['user_id']);
        $table_message->setPrimaryKey(['message_id']);
        $table_message->addForeignKeyConstraint($table_user->getName(), ['user_id'], ['user_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_module_privacy = $schema->createTable('module_privacy');
        $table_module_privacy->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table_module_privacy->addColumn('module_name', Types::STRING, ['length' => 32, 'platformOptions' => $utf8_bin]);
        $table_module_privacy->addColumn('gedcom_id', Types::INTEGER);
        $table_module_privacy->addColumn('interface', Types::STRING, ['length' => 255, 'platformOptions' => $ascii_bin]);
        $table_module_privacy->addColumn('access_level', Types::SMALLINT);
        $table_module_privacy->addUniqueIndex(['gedcom_id', 'module_name', 'interface']);
        $table_module_privacy->addUniqueIndex(['module_name', 'gedcom_id', 'interface']);
        $table_module_privacy->setPrimaryKey(['id']);
        $table_module_privacy->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        $table_module_privacy->addForeignKeyConstraint($table_module->getName(), ['module_name'], ['module_name'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_module_setting = $schema->createTable('module_setting');
        $table_module_setting->addColumn('module_name', Types::STRING, ['length' => 32, 'platformOptions' => $utf8_bin]);
        $table_module_setting->addColumn('setting_name', Types::STRING, ['length' => 32, 'platformOptions' => $ascii_bin]);
        $table_module_setting->addColumn('setting_value', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_module_setting->setPrimaryKey(['module_name', 'setting_name']);
        $table_module_setting->addForeignKeyConstraint($table_module->getName(), ['module_name'], ['module_name'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_name = $schema->createTable('name');
        $table_name->addColumn('n_file', Types::INTEGER);
        $table_name->addColumn('n_id', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_name->addColumn('n_num', Types::INTEGER);
        $table_name->addColumn('n_type', Types::STRING, ['length' => 15, 'platformOptions' => $ascii_bin]);
        $table_name->addColumn('n_sort', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_name->addColumn('n_full', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_name->addColumn('n_surname', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_name->addColumn('n_surn', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_name->addColumn('n_givn', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_name->addColumn('n_soundex_givn_std', Types::STRING, ['length' => 255, 'notNull' => false, 'platformOptions' => $ascii_bin]);
        $table_name->addColumn('n_soundex_surn_std', Types::STRING, ['length' => 255, 'notNull' => false, 'platformOptions' => $ascii_bin]);
        $table_name->addColumn('n_soundex_givn_dm', Types::STRING, ['length' => 255, 'notNull' => false, 'platformOptions' => $ascii_bin]);
        $table_name->addColumn('n_soundex_surn_dm', Types::STRING, ['length' => 255, 'notNull' => false, 'platformOptions' => $ascii_bin]);
        $table_name->setPrimaryKey(['n_id', 'n_file', 'n_num']);
        $table_name->addIndex(['n_full', 'n_id', 'n_file']);
        $table_name->addIndex(['n_surn', 'n_file', 'n_type', 'n_id']);
        $table_name->addIndex(['n_givn', 'n_file', 'n_type', 'n_id']);

        $table_news = $schema->createTable('news');
        $table_news->addColumn('news_id', Types::INTEGER, ['autoincrement' => true]);
        $table_news->addColumn('user_id', Types::INTEGER, ['notNull' => false]);
        $table_news->addColumn('gedcom_id', Types::INTEGER, ['notNull' => false]);
        $table_news->addColumn('subject', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_news->addColumn('body', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_news->addColumn('updated', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'precision' => 0]);
        $table_news->setPrimaryKey(['news_id']);
        $table_news->addIndex(['user_id', 'updated']);
        $table_news->addIndex(['gedcom_id', 'updated']);
        $table_news->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        $table_news->addForeignKeyConstraint($table_user->getName(), ['user_id'], ['user_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_other = $schema->createTable('other');
        $table_other->addColumn('o_id', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_other->addColumn('o_file', Types::INTEGER);
        $table_other->addColumn('o_type', Types::STRING, ['length' => 15, 'platformOptions' => $ascii_bin]);
        $table_other->addColumn('o_gedcom', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_other->setPrimaryKey(['o_id', 'o_file']);
        $table_other->addUniqueIndex(['o_file', 'o_id']);

        $table_places = $schema->createTable('places');
        $table_places->addColumn('p_id', Types::INTEGER, ['autoincrement' => true]);
        $table_places->addColumn('p_place', Types::STRING, ['length' => 150, 'platformOptions' => $utf8_bin]);
        $table_places->addColumn('p_parent_id', Types::INTEGER, ['notNull' => false]);
        $table_places->addColumn('p_file', Types::INTEGER);
        $table_places->addColumn('p_std_soundex', Types::TEXT, ['platformOptions' => $ascii_bin]);
        $table_places->addColumn('p_dm_soundex', Types::TEXT, ['platformOptions' => $ascii_bin]);
        $table_places->setPrimaryKey(['p_id']);
        $table_places->addUniqueIndex(['p_parent_id', 'p_file', 'p_place']);
        $table_places->addIndex(['p_file', 'p_place']);

        $table_placelinks = $schema->createTable('placelinks');
        $table_placelinks->addColumn('pl_p_id', Types::INTEGER);
        $table_placelinks->addColumn('pl_gid', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_placelinks->addColumn('pl_file', Types::INTEGER);
        $table_placelinks->setPrimaryKey(['pl_p_id', 'pl_gid', 'pl_file']);
        $table_placelinks->addIndex(['pl_p_id']);
        $table_placelinks->addIndex(['pl_gid']);
        $table_placelinks->addIndex(['pl_file']);

        $table_place_location = $schema->createTable('place_location');
        $table_place_location->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table_place_location->addColumn('parent_id', Types::INTEGER, ['notNull' => false]);
        $table_place_location->addColumn('place', Types::STRING, ['length' => 120, 'platformOptions' => $utf8_bin]);
        $table_place_location->addColumn('latitude', Types::FLOAT, ['notNull' => false]);
        $table_place_location->addColumn('longitude', Types::FLOAT, ['notNull' => false]);
        $table_place_location->setPrimaryKey(['id']);
        $table_place_location->addUniqueIndex(['parent_id', 'place']);
        $table_place_location->addUniqueIndex(['place', 'parent_id']);
        $table_place_location->addIndex(['latitude']);
        $table_place_location->addIndex(['longitude']);
        $table_place_location->addForeignKeyConstraint($table_place_location->getName(), ['parent_id'], ['id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_sources = $schema->createTable('session');
        $table_sources->addColumn('session_id', Types::STRING, ['length' => 32, 'platformOptions' => $ascii_bin]);
        $table_sources->addColumn('session_time', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'precision' => 0]);
        $table_sources->addColumn('user_id', Types::INTEGER);
        $table_sources->addColumn('ip_address', Types::STRING, ['length' => 45, 'platformOptions' => $ascii_bin]);
        $table_sources->addColumn('session_data', Types::BLOB);
        $table_sources->setPrimaryKey(['session_id']);
        $table_sources->addIndex(['session_time']);
        $table_sources->addIndex(['user_id', 'ip_address']);

        $table_site_setting = $schema->createTable('site_setting');
        $table_site_setting->addColumn('setting_name', Types::STRING, ['length' => 32, 'platformOptions' => $ascii_bin]);
        $table_site_setting->addColumn('setting_value', Types::STRING, ['length' => 2000, 'platformOptions' => $utf8_bin]);
        $table_site_setting->setPrimaryKey(['setting_name']);

        $table_sources = $schema->createTable('sources');
        $table_sources->addColumn('s_id', Types::STRING, ['length' => 20, 'platformOptions' => $ascii_bin]);
        $table_sources->addColumn('s_file', Types::INTEGER);
        $table_sources->addColumn('s_name', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_sources->addColumn('s_gedcom', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $table_sources->setPrimaryKey(['s_id', 's_file']);
        $table_sources->addUniqueIndex(['s_file', 's_id']);
        $table_sources->addIndex(['s_name']);

        $table_user_gedcom_setting = $schema->createTable('user_gedcom_setting');
        $table_user_gedcom_setting->addColumn('user_id', Types::INTEGER);
        $table_user_gedcom_setting->addColumn('gedcom_id', Types::INTEGER);
        $table_user_gedcom_setting->addColumn('setting_name', Types::STRING, ['length' => 32, 'platformOptions' => $ascii_bin]);
        $table_user_gedcom_setting->addColumn('setting_value', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_user_gedcom_setting->setPrimaryKey(['user_id', 'gedcom_id', 'setting_name']);
        $table_user_gedcom_setting->addIndex(['gedcom_id']);
        $table_user_gedcom_setting->addForeignKeyConstraint($table_gedcom->getName(), ['gedcom_id'], ['gedcom_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        $table_user_gedcom_setting->addForeignKeyConstraint($table_user->getName(), ['user_id'], ['user_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        $table_user_setting = $schema->createTable('user_setting');
        $table_user_setting->addColumn('user_id', Types::INTEGER);
        $table_user_setting->addColumn('setting_name', Types::STRING, ['length' => 32, 'platformOptions' => $ascii_bin]);
        $table_user_setting->addColumn('setting_value', Types::STRING, ['length' => 255, 'platformOptions' => $utf8_bin]);
        $table_user_setting->setPrimaryKey(['user_id', 'setting_name']);
        $table_user_setting->addForeignKeyConstraint($table_user->getName(), ['user_id'], ['user_id'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);

        /*
        $site = $schema->createTable('site');
        $site->addColumn('uuid', Types::STRING, ['length' => 36, 'platformOptions' => $ascii]);
        $site->addColumn('db_schema', Types::STRING, ['length' => 20, 'platformOptions' => $ascii]);
        $site->addColumn('created_at', Types::TIME_MUTABLE);
        $site->addColumn('updated_at', Types::TIME_MUTABLE);
        $site->setPrimaryKey(['uuid']);

        $job = $schema->createTable('job');
        $job->addColumn('uuid', Types::STRING, ['length' => 36, 'platformOptions' => $ascii]);
        $job->addColumn('failures', Types::INTEGER, ['default' => 0]);
        $job->addColumn('job', Types::STRING, ['length' => 255, 'platformOptions' => $ascii]);
        $job->addColumn('parameters', Types::TEXT, ['platformOptions' => $utf8_bin]);
        $job->addColumn('error', Types::TEXT, ['notnull' => false, 'platformOptions' => $utf8_bin]);
        $job->addColumn('created_at', Types::TIME_MUTABLE);
        $job->addColumn('updated_at', Types::TIME_MUTABLE);
        $job->setPrimaryKey(['uuid']);

        $job_dependency = $schema->createTable('job_dependency');
        $job_dependency->addColumn('uuid1', Types::STRING, ['length' => 36, 'platformOptions' => $ascii]);
        $job_dependency->addColumn('uuid2', Types::STRING, ['length' => 36, 'platformOptions' => $ascii]);
        $job_dependency->addUniqueIndex(['uuid1', 'uuid2']);
        $job_dependency->addUniqueIndex(['uuid2', 'uuid1']);
        $job_dependency->addForeignKeyConstraint($job->getName(), ['uuid1'], ['uuid'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        $job_dependency->addForeignKeyConstraint($job->getName(), ['uuid2'], ['uuid'], ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']);
        */

        $current_schema = $connection
            ->createSchemaManager()
            ->introspectSchema();

        $schema_diff = $connection
            ->createSchemaManager()
            ->createComparator()
            ->compareSchemas($current_schema, $schema);

        $queries = $connection
            ->getDatabasePlatform()
            ->getAlterSchemaSQL($schema_diff);

         function f(string $query): int {
            if (str_contains($query, 'DROP FOREIGN KEY')) {
                return 1;
            }
            if (str_contains($query, 'ADD CONSTRAINT FK_')) {
                return 3;
            }
            return 2;
        };

//        usort($queries, static fn(string $x, string $y): int => f($x) <=> f($y));

        foreach ($queries as $query) {
            echo '<p>', $query, '</p>';
            try {
                $connection->executeStatement($query);
            } catch (Exception $ex) {
                echo '<p>', $ex->getMessage(), '</p>';
            }
        }
        exit;
    }
}
