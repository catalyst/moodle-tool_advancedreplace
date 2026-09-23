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

namespace tool_advancedreplace\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for filtering by date and time.
 *
 * @package   tool_advancedreplace\form
 * @copyright 2026 Catalyst IT
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class date_time_filter_form extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setConstant('id', $this->_customdata['id']);

        if (isset($this->_customdata['page'])) {
            $mform->addElement('hidden', 'page');
            $mform->setType('page', PARAM_INT);
            $mform->setConstant('page', $this->_customdata['page']);
        }

        if (isset($this->_customdata['perpage'])) {
            $mform->addElement('hidden', 'perpage');
            $mform->setType('perpage', PARAM_INT);
            $mform->setConstant('perpage', $this->_customdata['perpage']);
        }

        $mform->addElement(
            'date_time_selector',
            'reportfilterfrom',
            get_string('reportfilterfrom', 'tool_advancedreplace'),
            ['optional' => true]
        );
        if (isset($this->_customdata['timefrom'])) {
            $mform->setDefault('reportfilterfrom', $this->_customdata['timefrom']);
        }
        $mform->addElement(
            'date_time_selector',
            'reportfilterto',
            get_string('reportfilterto', 'tool_advancedreplace'),
            ['optional' => true]
        );
        if (isset($this->_customdata['timeto'])) {
            $mform->setDefault('reportfilterto', $this->_customdata['timeto']);
        }

        $buttons = [];
        $buttons[] = &$mform->createElement('submit', 'submitbutton', get_string('filter'));
        $buttons[] = &$mform->createElement('submit', 'clearfilter', get_string('clear'));
        $mform->addGroup($buttons, 'filterbuttons', '', ' ', false);
    }

    /**
     * Custom validate the form data.
     *
     * @param $data
     * @param $files
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (
            empty($data['clearfilter']) &&
            !empty($data['reportfilterfrom']) &&
            !empty($data['reportfilterto']) &&
            $data['reportfilterfrom'] > $data['reportfilterto']
        ) {
            $errors['reportfilterto'] = get_string('reportfilterinvalidrange', 'tool_advancedreplace');
        }
        return $errors;
    }
}
