<?php
// This file is part of Moodle - https://moodle.org/
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

/**
 * Search history for advanced replace.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class db_search extends search {

    /** The name of the database table. */
    public const TABLE = 'tool_advancedreplace_search';

    /** Fields to copy when copying a record. */
    public const COPY_COLUMNS = [
        'name',
        'search',
        'regex',
        'prematch',
        'tables',
        'skiptables',
        'skipcolumns',
        'summary',
    ];

    /** How many seconds to wait before marking a search as stale. */
    public const STALE = HOURSECS;

    /** @var string File area for output files */
    protected $filearea = 'search';

    /** @var string Class for the adhoc task */
    protected $adhoctask = \tool_advancedreplace\task\search_db::class;

    /** @var array includetables from config. */
    protected $includetables = null;

    /** @var array excludetables from config. */
    protected $excludetables = null;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'userid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'name' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'search' => [
                'type' => PARAM_RAW,
            ],
            'regex' => [
                'type' => PARAM_BOOL,
                'default' => 0,
            ],
            'prematch' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'tables' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skiptables' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipcolumns' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'summary' => [
                'type' => PARAM_BOOL,
                'default' => 0,
            ],
            'origin' => [
                'type' => PARAM_TEXT,
            ],
            'timestart' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'timeend' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'progress' => [
                'type' => PARAM_FLOAT,
                'default' => 0,
            ],
            'matches' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Loads config tables and stores the results.
     * @param string $name
     * @return array
     */
    protected function get_config_table(string $name): array {
        if (isset($this->$name)) {
            return $this->$name;
        }
        $value = get_config('tool_advancedreplace', $name);
        $matches = preg_split('/[\n,]+/', $value);
        $this->$name = array_filter(array_map('trim', $matches));
        return $this->$name;
    }

    /**
     * A custom list of tables to be searched. If no options are set, use tables from config.
     * @return array tables to be searched
     */
    public function get_all_searchtables(): array {
        $tables = array_filter(array_map('trim', explode(',', $this->get('tables'))));
        return !empty($tables) ? $tables : $this->get_config_table('includetables');
    }

    /**
     * A custom list of tables that should be skipped. This combines options, config and custom skip tables.
     * Tables that are skipped by core as part of db_should_replace() are handled elsewhere.
     * @return array tables that should be skipped
     */
    public function get_all_skiptables(): array {
        return array_merge($this->get_config_table('excludetables'), helper::SKIP_TABLES, explode(',', $this->get('skiptables')));
    }

    /**
     * A custom list of columns that should be skipped.
     * @return array columns that should be skipped
     */
    public function get_all_skipcolumns(): array {
        return explode(',', $this->get('skipcolumns'));
    }

    /**
     * Calculates the minimum search length
     * @return int minimum search length
     */
    public function get_min_search_length(): int {
        // For regex, use prematch as a rough estimate, otherwise use no minimum.
        $minsearch = empty($this->get('regex')) ? $this->get('search') : $this->get('prematch');
        return strlen($minsearch);
    }
}
