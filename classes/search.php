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
class search extends \core\persistent {

    /** The name of the database table. */
    public const TABLE = 'tool_advancedreplace_search';

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
     * Gets the file name of the search output
     * @param \stdClass $record
     * @return string filename
     */
    public static function get_filename(\stdClass $record): string {
        // The hardcoded default filename should not be changed.
        $name = !empty($record->name) ? $record->name : 'searchresult-' . $record->id;
        return strtolower($name) . '.csv';
    }
}
