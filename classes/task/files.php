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

namespace tool_advancedreplace\task;

/**
 * Ad-hoc task to search for regular expression matches in Moodle files.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files extends \core\task\adhoc_task {

    /**
     * Action of task.
     */
    public function execute() {
        // Get the custom data.
        $data = $this->get_custom_data();
        if (empty($data->searchid)) {
            return;
        }

        $record = new \tool_advancedreplace\files($data->searchid);
        if (empty($record)) {
            return;
        }

        \tool_advancedreplace\file_search::files($record);
    }
}
