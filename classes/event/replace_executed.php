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
 * Event triggered when a bulk replacement (database or files) has been executed from a CSV.
 *
 * The replacement pairs and outcome counts are recorded; full content is not stored.
 *
 * @property-read array $other {
 *      - string type: search_base::TYPE_DB or search_base::TYPE_FILES
 *      - int rows: number of CSV data rows processed
 *      - int success: number of successful replacements
 *      - int skipped: number of rows skipped (empty replace value)
 *      - int replacematch: number of rows already replaced
 *      - int error: number of rows that errored
 *      - bool completed: false if processing was interrupted by an exception
 *      - array replacements: replacement pairs and their database or file targets
 * }
 *
 * @package    tool_advancedreplace
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class replace_executed extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->context = \context_system::instance();
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_replace_executed', 'tool_advancedreplace');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $o = $this->other;
        $target = $o['type'] === search_base::TYPE_DB ? 'database' : 'files';
        $status = empty($o['completed']) ? 'an interrupted' : 'a';
        $description = "The user with id '$this->userid' executed {$status} bulk {$target} replacement of {$o['rows']} rows: " .
            "{$o['success']} replaced, {$o['skipped']} skipped, {$o['replacematch']} already replaced, {$o['error']} errors.";

        $replacements = [];
        foreach ($o['replacements'] as $replacement) {
            if ($o['type'] === search_base::TYPE_DB) {
                $location = "table '{$replacement['table']}', column '{$replacement['column']}'";
            } else {
                $location = "file '{$replacement['filepath']}{$replacement['filename']}'";
            }
            $replacements[] = "replace '{$replacement['match']}' with '{$replacement['replace']}' in {$location}";
        }

        if (empty($replacements)) {
            return $description . ' No replacement rows were processed.';
        }
        return $description . ' Requested replacements: ' . implode('; ', $replacements) . '.';
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        $page = $this->other['type'] === search_base::TYPE_DB ? 'db_replace.php' : 'file_replace.php';
        return new \moodle_url('/admin/tool/advancedreplace/' . $page);
    }

    /**
     * Return the replacement pairs stored in this event.
     *
     * @return array
     */
    public function get_replacements(): array {
        return $this->other['replacements'];
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        foreach (['type', 'rows', 'success', 'skipped', 'replacematch', 'error', 'completed', 'replacements'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '$key' value must be set in other.");
            }
        }
        if (!is_array($this->other['replacements'])) {
            throw new \coding_exception("The 'replacements' value must be an array.");
        }
        if (!in_array($this->other['type'], [search_base::TYPE_DB, search_base::TYPE_FILES], true)) {
            throw new \coding_exception("The 'type' value must be '" . search_base::TYPE_DB . "' or '" .
                search_base::TYPE_FILES . "'.");
        }
    }

    /**
     * No mapping required for other data.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
