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

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Site wide files-replace form.
 */
class files extends \core\form\persistent {
    /** @var string Persistent class name. */
    protected static $persistentclass = 'tool_advancedreplace\\files';

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
        $mform->setConstant('userid', $this->_customdata['userid']);

        $mform->addElement('hidden', 'origin');
        $mform->setConstant('origin', 'web');

        $mform->addElement('text', 'pattern', get_string('field_pattern', 'tool_advancedreplace'), $fullwidth);
        $mform->setType('pattern', PARAM_RAW);
        $mform->addRule('pattern', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'name', get_string('field_name', 'tool_advancedreplace'), $fullwidth);
        $mform->addHelpButton('name', 'field_name', 'tool_advancedreplace');
        $mform->setType('name', PARAM_RAW);
        $mform->setDefault('name', '');

        $mform->addElement('textarea', 'components', get_string("field_components", "tool_advancedreplace"), $textareasize);
        $mform->addHelpButton('components', 'field_components', 'tool_advancedreplace');
        $mform->setType('components', PARAM_RAW);
        $mform->setDefault('components', '');

        $mform->addElement('textarea', 'skipcomponents', get_string("field_skipcomponents", "tool_advancedreplace"), $textareasize);
        $mform->addHelpButton('skipcomponents', 'field_skipcomponents', 'tool_advancedreplace');
        $mform->setType('skipcomponents', PARAM_RAW);
        $mform->setDefault('skipcomponents', '');

        $mform->addElement('textarea', 'mimetypes', get_string("field_mimetypes", "tool_advancedreplace"), $textareasize);
        $mform->addHelpButton('mimetypes', 'field_mimetypes', 'tool_advancedreplace');
        $mform->setType('mimetypes', PARAM_RAW);
        $mform->setDefault('mimetypes', '');

        $mform->addElement('textarea', 'skipmimetypes', get_string("field_skipmimetypes", "tool_advancedreplace"), $textareasize);
        $mform->addHelpButton('skipmimetypes', 'field_skipmimetypes', 'tool_advancedreplace');
        $mform->setType('skipmimetypes', PARAM_RAW);
        $mform->setDefault('skipmimetypes', '');

        $mform->addElement('textarea', 'filenames', get_string("field_filenames", "tool_advancedreplace"), $textareasize);
        $mform->addHelpButton('filenames', 'field_filenames', 'tool_advancedreplace');
        $mform->setType('filenames', PARAM_RAW);
        $mform->setDefault('filenames', '');

        $mform->addElement('textarea', 'skipfilenames', get_string("field_skipfilenames", "tool_advancedreplace"), $textareasize);
        $mform->addHelpButton('skipfilenames', 'field_skipfilenames', 'tool_advancedreplace');
        $mform->setType('skipfilenames', PARAM_RAW);
        $mform->setDefault('skipfilenames', '');

        $mform->addElement('textarea', 'skipareas', get_string("field_skipareas", "tool_advancedreplace"), $textareasize);
        $mform->addHelpButton('skipareas', 'field_skipareas', 'tool_advancedreplace');
        $mform->setType('skipareas', PARAM_RAW);
        $mform->setDefault('skipareas', '');

        $this->add_action_buttons(true, get_string('search'));
    }
}
