<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_advancedreplace;

use xmldb_field;

/**
 * Finds the column used by each table to store the time a row was last modified.
 *
 * The map of table => column is generated from the install.xml table definitions and cached.
 * All caches are purged on install and upgrade, so the map is rebuilt whenever the schema changes.
 *
 * The map is stored as a single value in the cache under the key CACHE_KEY. This is because the map is essentially a
 * constant value, and only needs to be updated during a upgrade.
 *
 * This cache is not guaranteed to be perfect, but is good enough.
 *
 * @package    tool_advancedreplace
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class timemodified_columns {
    /** @var string Key the map is stored under in the cache. **/
    const CACHE_KEY = 'map';

    /**
     * @var array Column names known to hold a last modified timestamp.
     *
     * In order of preference, for tables that have more than one of these columns.
     **/
    const COLUMN_NAMES = [
        'timemodified',
        'modified',
        'lastmodified',
        'timeupdated',
        'updated',
        'lastupdated',
        'lastupdate',
        'dateupdated',
    ];

    /** @var array Known table => columns that match COLUMN_NAMES but are not modification times. **/
    const EXCLUDED_COLUMNS = [
        // Flag for whether the string has been modified. Technically, this is redundant, but is put here as a example.
        'tool_customlang' => ['modified'],
    ];

    /** @var int Minimum integer length (in digits) able to hold a unix timestamp. **/
    const MIN_LENGTH = 10;

    /**
     * Returns the name of the column holding the time a row was last modified.
     *
     * @param string $table The table to check.
     * @return string|null The column name, or null if the table has no such column.
     */
    public static function get_column(string $table): ?string {
        return self::get_map()[$table] ?? null;
    }

    /**
     * Returns the map of table => last modified column, generating it if it does not exist.
     *
     * @return array
     */
    public static function get_map(): array {
        $cache = \cache::make('tool_advancedreplace', 'timemodifiedcolumns');
        $map = $cache->get(self::CACHE_KEY);
        if ($map === false) {
            $map = self::generate_map();
            $cache->set(self::CACHE_KEY, $map);
        }
        return $map;
    }

    /**
     * Generates the map of table => last modified column from the table definitions.
     *
     * @return array
     */
    public static function generate_map(): array {
        global $DB;

        $map = [];
        $schema = $DB->get_manager()->get_install_xml_schema();
        foreach ($schema->getTables() as $table) {
            $tablename = $table->getName();
            $excluded = self::EXCLUDED_COLUMNS[$tablename] ?? [];
            foreach (self::COLUMN_NAMES as $columnname) {
                // Do not include blacklisted columns.
                if (in_array($columnname, $excluded)) {
                    continue;
                }
                $field = $table->getField($columnname);
                // If the field exists and is compatible with unix timestamps, then add it.
                if ($field && self::is_timestamp_field($field)) {
                    $map[$tablename] = $columnname;
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * Checks if the field is compatible with unix timestamps.
     *
     * @param xmldb_field $field
     * @return bool Returns true if the field is an integer type big enough to hold a unix timestamp.
     */
    private static function is_timestamp_field(xmldb_field $field): bool {
        return $field->getType() == XMLDB_TYPE_INTEGER && $field->getLength() >= self::MIN_LENGTH;
    }
}
