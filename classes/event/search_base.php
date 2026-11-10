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

use core\event\base;
use tool_advancedreplace\search;

/**
 * Base class for events relating to a search record (database or files).
 *
 * Search criteria are included in event descriptions for audit purposes.
 *
 * @package    tool_advancedreplace
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class search_base extends base {
    /** Search type for database searches and replacements. */
    public const TYPE_DB = 'db';

    /** Search type for file searches and replacements. */
    public const TYPE_FILES = 'files';

    /**
     * The crud value for this event.
     *
     * @return string one of c, r, u, d
     */
    abstract protected static function get_crud(): string;

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = static::get_crud();
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = static::TABLE;
        $this->context = \context_system::instance();
    }

    /**
     * Create the event from a search persistent.
     *
     * @param search $search
     * @return static
     */
    abstract public static function create_from_search(search $search): self;

    /**
     * Create an event from a search persistent and additional event data.
     *
     * @param search $search
     * @param array $other Additional event data.
     * @return self
     */
    protected static function create_from_search_base(search $search, array $other = []): self {
        $other['name'] = (string) $search->get('name');
        $other['origin'] = (string) $search->get('origin');
        $event = static::create([
            'objectid' => $search->get('id'),
            'relateduserid' => (int) $search->get('userid'),
            'other' => $other,
        ]);
        $event->add_record_snapshot($search::TABLE, $search->to_record());
        return $event;
    }

    /**
     * Human readable label for the search type, used in descriptions.
     *
     * @return string
     */
    abstract protected function get_type_label(): string;

    /**
     * Return the search name stored in this event.
     *
     * @return string
     */
    public function get_search_name(): string {
        return $this->other['name'];
    }

    /**
     * Return the search pattern stored in this event.
     *
     * @return string
     */
    abstract public function get_search_pattern(): string;

    /**
     * Return the search scope value stored in this event.
     *
     * @return string
     */
    abstract protected function get_search_scope(): string;

    /**
     * Return the label for this search scope.
     *
     * @return string
     */
    abstract protected function get_search_scope_label(): string;

    /**
     * Return search details for inclusion in the event description.
     *
     * @return string
     */
    protected function get_search_details(): string {
        return " named '{$this->get_search_name()}' with pattern '{$this->get_search_pattern()}' and " .
            "{$this->get_search_scope_label()} '{$this->get_search_scope()}'.";
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (empty($this->objectid)) {
            throw new \coding_exception('The \'objectid\' must be set.');
        }
    }

    /**
     * Search records are not backed up or restored.
     *
     * @return int
     */
    public static function get_objectid_mapping() {
        return self::NOT_MAPPED;
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
