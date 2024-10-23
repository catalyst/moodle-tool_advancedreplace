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
    ];

    /** @var string File area for output files */
    protected $filearea = 'files';

    /** @var string Class for the adhoc task */
    protected $adhoctask = \tool_advancedreplace\task\files::class;

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
}
