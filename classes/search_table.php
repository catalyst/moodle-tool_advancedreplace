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
 * Table to display search history.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_table extends \table_sql {

    /** Columns to be displayed. */
    const COLUMNS = [
        'id',
        'search',
        'name',
        'options',
        'timestart',
        'progress',
        'duration',
        'actions',
    ];

    /** Columns to be displayed as options. */
    const OPTIONS = [
        'regex',
        'prematch',
        'tables',
        'skiptables',
        'skipcolumns',
        'summary',
    ];

    /** Columns to be displayed, but not sorted. */
    const NOSORT_COLUMNS = [
        'options',
        'actions',
    ];

    /**
     * Defines the columns for this table.
     *
     * @throws \coding_exception
     */
    public function make_columns(): void {
        $headers = [];
        $columns = $this->get_columns();
        foreach ($columns as $column) {
            $headers[] = get_string('field_' . $column, 'tool_advancedreplace');
        }

        foreach (self::NOSORT_COLUMNS as $column) {
            $this->no_sorting($column);
        }

        $this->define_columns($columns);
        $this->column_class('progress', 'text-right');
        $this->define_headers($headers);
    }

    /**
     * returns the columns defined for the table.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = self::COLUMNS;
        return $columns;
    }

    /**
     * Overrides felxible_table::setup() to do some extra setup.
     *
     * @return false|\type|void
     */
    public function setup() {
        $this->set_sql(
            '*',
            '{' . search::TABLE .'}',
            '1=1',
        );
        $retvalue = parent::setup();
        $this->set_attribute('class', $this->attributes['class'] . ' table-sm');
        return $retvalue;
    }

    /**
     * Generate content for progress column.
     *
     * @param object $row object
     * @return string html used to display the manage column field.
     */
    public function col_progress($row): string {
        return get_string('percents', 'moodle', round($row->progress, 2));
    }

    /**
     * Generate content for timestart column.
     *
     * @param object $row object
     * @return string html used to display the manage column field.
     */
    public function col_timestart($row): string {
        if (empty($row->timestart)) {
            return '';
        }
        $format = get_string('strftimedatetime', 'langconfig');
        return userdate($row->timestart, $format);
    }

    /**
     * Generate content for duration column.
     *
     * @param object $row object
     * @return string html used to display the manage column field.
     */
    public function col_duration($row): string {
        if (empty($row->timeend)) {
            return '';
        }
        $duration = $row->timeend - $row->timestart;
        if (empty($duration)) {
            // The format_time function returns 'now' when the difference is exactly 0.
            return '0 ' . get_string('secs', 'moodle');
        }
        return format_time($duration);
    }

    /**
     * Generate content for options column.
     *
     * @param object $row object
     * @return string html used to display the manage column field.
     */
    public function col_options($row): string {
        $options = [];
        $bool = ['regex', 'summary'];
        foreach (self::OPTIONS as $option) {
            if (!empty($row->$option)) {
                $name = get_string('field_' . $option, 'tool_advancedreplace');
                $options[] = in_array($option, $bool) ? $name : $name . ': ' . $row->$option;
            }
        }
        return implode(',' . PHP_EOL, $options);
    }

    /**
     * Generate content for actions column.
     *
     * @param object $row object
     * @return string html used to display the manage column field.
     */
    public function col_actions($row): string {
        global $OUTPUT;

        $actions = '';

        if (!empty($row->timeend)) {
            $filename = \tool_advancedreplace\search::get_filename($row);
            $downloadurl = \moodle_url::make_pluginfile_url(
                \context_system::instance()->id,
                'tool_advancedreplace',
                'search',
                $row->id,
                '/',
                $filename
            )->out();
            $downloadicon = $OUTPUT->render(new \pix_icon('t/download', get_string('download')));
            $actions .= \html_writer::link($downloadurl, $downloadicon, ['class' => 'action-icon']);
        }

        return $actions;
    }
}
