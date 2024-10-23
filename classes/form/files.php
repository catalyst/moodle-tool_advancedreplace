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
    /** @var string Name of this plugin. */
    const PLUGIN = 'tool_advancedreplace';

    /** @var string Persistent class name. */
    protected static $persistentclass = self::PLUGIN .'\\files';

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

        $mform->addElement('text', 'pattern', get_string('field_pattern', self::PLUGIN), $fullwidth);
        $mform->setType('pattern', PARAM_RAW);
        $mform->addRule('pattern', get_string('required'), 'required', null, 'client');
        $mform->addElement('static', 'pattern_help', '', get_string('field_pattern_help', self::PLUGIN));

        $mform->addElement('text', 'name', get_string('field_name', self::PLUGIN), $fullwidth);
        $mform->setType('name', PARAM_RAW);
        $mform->setDefault('name', '');
        $mform->addElement('static', 'name_help', '', get_string('field_name_help', self::PLUGIN));

        $mform->addElement('textarea', 'components', get_string("field_components", self::PLUGIN), $textareasize);
        $mform->setType('components', PARAM_RAW);
        $mform->setDefault('components', '');
        $mform->addElement('static', 'components_help', '', get_string( 'field_components_help', self::PLUGIN));

        $mform->addElement('textarea', 'skipcomponents', get_string("field_skipcomponents", self::PLUGIN), $textareasize);
        $mform->setType('skipcomponents', PARAM_RAW);
        $mform->setDefault('skipcomponents', '');
        $mform->addElement('static', 'skipcomponents_help', '', get_string( 'field_skipcomponents_help', self::PLUGIN));

        $mform->addElement('textarea', 'mimetypes', get_string("field_mimetypes", self::PLUGIN), $textareasize);
        $mform->setType('mimetypes', PARAM_RAW);
        $mform->setDefault('mimetypes', '');
        $mform->addElement('static', 'mimetypes_help', '', get_string( 'field_mimetypes_help', self::PLUGIN));

        $mform->addElement('textarea', 'skipmimetypes', get_string("field_skipmimetypes", self::PLUGIN), $textareasize);
        $mform->setType('skipmimetypes', PARAM_RAW);
        $mform->setDefault('skipmimetypes', '');
        $mform->addElement('static', 'skipmimetypes_help', '', get_string( 'field_skipmimetypes_help', self::PLUGIN));

        $mform->addElement('textarea', 'filenames', get_string("field_filenames", self::PLUGIN), $textareasize);
        $mform->setType('filenames', PARAM_RAW);
        $mform->setDefault('filenames', '');
        $mform->addElement('static', 'filenames_help', '', get_string( 'field_filenames_help', self::PLUGIN));

        $mform->addElement('textarea', 'skipfilenames', get_string("field_skipfilenames", self::PLUGIN), $textareasize);
        $mform->setType('skipfilenames', PARAM_RAW);
        $mform->setDefault('skipfilenames', '');
        $mform->addElement('static', 'skipfilenames_help', '', get_string( 'field_skipfilenames_help', self::PLUGIN));

        $mform->addElement('textarea', 'skipareas', get_string("field_skipareas", self::PLUGIN), $textareasize);
        $mform->setType('skipareas', PARAM_RAW);
        $mform->setDefault('skipareas', '');
        $mform->addElement('static', 'skipareas_help', '', get_string( 'field_skipareas_help', self::PLUGIN));

        $mform->addElement('checkbox', 'openzips', get_string('field_openzips', self::PLUGIN));
        $mform->addHelpButton('openzips', 'field_openzips', 'tool_advancedreplace');

        $mform->addElement('textarea', 'zipfilenames', get_string("field_zipfilenames", self::PLUGIN), $textareasize);
        $mform->setType('zipfilenames', PARAM_RAW);
        $mform->setDefault('zipfilenames', '');
        $mform->addElement('static', 'zipfilenames_help', '', get_string( 'field_zipfilenames_help', self::PLUGIN));

        $mform->addElement('textarea', 'skipzipfilenames', get_string("field_skipzipfilenames", self::PLUGIN), $textareasize);
        $mform->setType('skipzipfilenames', PARAM_RAW);
        $mform->setDefault('skipzipfilenames', '');
        $mform->addElement('static', 'skipzipfilenames_help', '', get_string( 'field_skipzipfilenames_help', self::PLUGIN));

        $this->add_action_buttons(true, get_string('search'));
    }
}
