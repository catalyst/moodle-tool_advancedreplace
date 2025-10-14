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

/**
 * Advanced site wide search-replace form.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_advancedreplace\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Site wide search-replace form.
 */
class replace extends moodleform {
    /**
     * Form definition
     *
     * @return void
     */
    public function definition(): void {
        global $CFG, $DB;

        $mform = $this->_form;
        $textareasize = ['rows' => 3, 'cols' => 50];
        $fullwidth = ['style' => 'width: 100%'];

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        $mform->setConstant('userid', $this->_customdata['userid']);

        $mform->addElement('hidden', 'origin');
        $mform->setType('origin', PARAM_TEXT);
        $mform->setConstant('origin', 'web');

        // File upload.
        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('selectfile', 'tool_advancedreplace'),
            null,
            ['accepted_types' => ['.csv']]
        );
        $mform->addRule('csvfile', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(true, get_string('replace', 'tool_advancedreplace'));
    }
}
