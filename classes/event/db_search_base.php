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

namespace tool_advancedreplace\event;

use tool_advancedreplace\db_search;
use tool_advancedreplace\search;

/**
 * Base class for events relating to a database search.
 *
 * @package    tool_advancedreplace
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class db_search_base extends search_base {
    /** The database table storing these searches. */
    public const TABLE = db_search::TABLE;

    /**
     * Human readable label for this search type, used in event descriptions.
     *
     * @return string
     */
    protected function get_type_label(): string {
        return 'database search';
    }

    /**
     * Returns the URL relevant to database search events.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/admin/tool/advancedreplace/db_search.php');
    }

    /**
     * Return the search pattern stored in this database search event.
     *
     * @return string
     */
    public function get_search_pattern(): string {
        return $this->other['search'];
    }

    /**
     * Return the table selection stored in this database search event.
     *
     * @return string
     */
    protected function get_search_scope(): string {
        return $this->other['tables'];
    }

    /**
     * Return the label for the database search scope.
     *
     * @return string
     */
    protected function get_search_scope_label(): string {
        return 'tables';
    }

    /**
     * Create a database search event from a search persistent.
     *
     * @param search $search
     * @return search_base
     */
    public static function create_from_search(search $search): search_base {
        $other = [
            'search' => (string) $search->get('search'),
            'tables' => (string) $search->get('tables'),
        ];
        return static::create_from_search_base($search, $other);
    }
}
