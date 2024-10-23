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

/**
 * Table to display history of searches in files.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files_table extends search_table {
    /**
     * Identifies the class used to interact with the database table.
     *
     * @var string
     */
    protected $dbclass = \tool_advancedreplace\files::class;

    /**
     * Part of the url used to interact with these searches.
     *
     * @var string
     */
    protected $urlfragment = 'files.php';

    /** Columns to be displayed. */
    const COLUMNS = [
        'id',
        'name',
        'userid',
        'pattern',
        'options',
        'timestart',
        'duration',
        'progress',
        'matches',
        'output',
        'actions',
    ];

    /** Columns to be displayed as options. */
    const OPTIONS = [
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

}
