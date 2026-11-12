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
class files extends search {
    /** The name of the database table. */
    public const TABLE = 'tool_advancedreplace_files';

    /** Event type prefix. */
    public const EVENT_TYPE = event\search_base::TYPE_FILES;

    /** Class of the adhoc task which runs the search. */
    public const ADHOC_TASK = \tool_advancedreplace\task\files::class;

    /** File area for output files. */
    public const FILEAREA = 'files';

    /** Fields to copy when copying a record. */
    public const COPY_COLUMNS = [
        'name',
        'pattern',
        'components',
        'skipcomponents',
        'mimetypes',
        'skipmimetypes',
        'filenames',
        'skipfilenames',
        'skipareas',
        'openzips',
        'zipfilenames',
        'skipzipfilenames',
        'shards',
    ];

    /** How many seconds to wait before marking a search as stale. */
    public const STALE = MINSECS;

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
            'pattern' => [
                'type' => PARAM_RAW,
            ],
            'components' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipcomponents' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'mimetypes' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipmimetypes' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'filenames' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipfilenames' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipareas' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'openzips' => [
                'type' => PARAM_BOOL,
                'default' => 0,
            ],
            'zipfilenames' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipzipfilenames' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'shards' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'shardnum' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
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
     * Validates a regular expression field.
     *
     * @param string|null $value
     * @param bool $required whether an empty value is an error
     * @return true|\lang_string
     */
    protected function validate_regex_field(?string $value, bool $required) {
        $value = trim((string) $value);
        if ($value === '') {
            return $required ? new \lang_string('errorinvalidregex', 'tool_advancedreplace') : true;
        }
        if (!helper::is_valid_regex($value, file_search::REGEX_MODIFIERS)) {
            return new \lang_string('errorinvalidregex', 'tool_advancedreplace');
        }
        return true;
    }

    /**
     * Validate the search pattern.
     *
     * @param string|null $value
     * @return true|\lang_string
     */
    protected function validate_pattern($value) {
        return $this->validate_regex_field($value, true);
    }

    /**
     * Validate the zip filenames pattern.
     *
     * @param string|null $value
     * @return true|\lang_string
     */
    protected function validate_zipfilenames($value) {
        return $this->validate_regex_field($value, false);
    }

    /**
     * Validate the skip zip filenames pattern.
     *
     * @param string|null $value
     * @return true|\lang_string
     */
    protected function validate_skipzipfilenames($value) {
        return $this->validate_regex_field($value, false);
    }
}
