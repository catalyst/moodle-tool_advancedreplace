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
 * Site wide search-replace form.
 */
class search extends \core\form\persistent {
    /** @var string Persistent class name. */
    protected static $persistentclass = 'tool_advancedreplace\\search';

    /**
     * Form definition
     *
     * @return void
     */
    public function definition(): void {
        global $CFG, $DB;

        $mform = $this->_form;
        $sizeoptions = ['rows' => 5, 'cols' => 50];

        $mform->addElement('hidden', 'userid');
        $mform->setConstant('userid', $this->_customdata['userid']);

        $mform->addElement('hidden', 'origin');
        $mform->setConstant('origin', 'web');

        $mform->addElement('text', 'search', get_string('field_search', 'tool_advancedreplace'));
        $mform->setType('search', PARAM_RAW);
        $mform->addRule('search', get_string('required'), 'required', null, 'client');

        $mform->addElement('checkbox', 'regex', get_string('field_regex', 'tool_advancedreplace'));

        $mform->addElement('text', 'prematch', get_string('field_prematch', 'tool_advancedreplace'));
        $mform->setType('prematch', PARAM_RAW);

        $mform->addElement('textarea', 'tables', get_string("field_tables", "tool_advancedreplace"), $sizeoptions);
        $mform->setType('tables', PARAM_RAW);
        $mform->setDefault('tables', '');

        $mform->addElement('textarea', 'skiptables', get_string("field_skiptables", "tool_advancedreplace"), $sizeoptions);
        $mform->setType('skiptables', PARAM_RAW);
        $mform->setDefault('skiptables', '');

        $mform->addElement('textarea', 'skipcolumns', get_string("field_skipcolumns", "tool_advancedreplace"), $sizeoptions);
        $mform->setType('skipcolumns', PARAM_RAW);
        $mform->setDefault('skipcolumns', '');

        $mform->addElement('text', 'name', get_string('field_name', 'tool_advancedreplace'));
        $mform->setType('name', PARAM_RAW);
        $mform->setDefault('name', '');

        $mform->addElement('checkbox', 'summary', get_string('field_summary', 'tool_advancedreplace'));

        $this->add_action_buttons(true, get_string('search'));
    }
}
