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

use tool_advancedreplace\files;
use tool_advancedreplace\search;

/**
 * Base class for events relating to a files search.
 *
 * @package    tool_advancedreplace
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class files_search_base extends search_base {
    /** The database table storing these searches. */
    public const TABLE = files::TABLE;

    /**
     * Human readable label for this search type, used in event descriptions.
     *
     * @return string
     */
    protected function get_type_label(): string {
        return 'files search';
    }

    /**
     * Returns the URL relevant to files search events.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/admin/tool/advancedreplace/files.php');
    }

    /**
     * Return the search pattern stored in this files search event.
     *
     * @return string
     */
    public function get_search_pattern(): string {
        return $this->other['pattern'];
    }

    /**
     * Return the filename selection stored in this files search event.
     *
     * @return string
     */
    protected function get_search_scope(): string {
        return $this->other['filenames'];
    }

    /**
     * Return the label for the files search scope.
     *
     * @return string
     */
    protected function get_search_scope_label(): string {
        return 'filenames';
    }

    /**
     * Create a files search event from a search persistent.
     *
     * @param search $search
     * @return search_base
     */
    public static function create_from_search(search $search): search_base {
        $other = [
            'pattern' => (string) $search->get('pattern'),
            'filenames' => (string) $search->get('filenames'),
        ];
        return static::create_from_search_base($search, $other);
    }
}
