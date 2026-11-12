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

namespace tool_advancedreplace\event;

/**
 * Event triggered when a database search is requeued.
 *
 * @package    tool_advancedreplace
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class db_search_requeued extends db_search_base {
    /**
     * The crud value for this event.
     *
     * @return string
     */
    protected static function get_crud(): string {
        return 'u';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_db_search_requeued', 'tool_advancedreplace');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' requeued the " . $this->get_type_label() . " with id '$this->objectid'" .
            $this->get_search_details();
    }
}
