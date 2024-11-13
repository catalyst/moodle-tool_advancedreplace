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

use stdClass;

/**
 * Table to display search history.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_table extends \table_sql {

    /**
     * Identifies the class used to interact with the database table.
     *
     * @var string
     */
    protected $dbclass = db_search::class;

    /**
     * Part of the url used to interact with these searches.
     *
     * @var string
     */
    protected $urlfragment = 'db_search.php';

    /** @var array of persistent searches */
    protected $persistent = [];

    /** Columns to be displayed. */
    const COLUMNS = [
        'id',
        'name',
        'userid',
        'search',
        'options',
        'timestart',
        'duration',
        'progress',
        'matches',
        'output',
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
        'matches',
        'output',
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

        foreach (static::NOSORT_COLUMNS as $column) {
            $this->no_sorting($column);
        }

        $this->define_columns($columns);
        $this->column_class('progress', 'text-right');
        $this->column_class('matches', 'text-right');
        $this->column_style('options', 'max-width', '400px');
        $this->define_headers($headers);
    }

    /**
     * returns the columns defined for the table.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = static::COLUMNS;
        return $columns;
    }

    /**
     * Gets the where SQL.
     * @return string where SQL.
     */
    protected function get_where_sql(): string {
        return '1=1';
    }

    /**
     * Overrides felxible_table::setup() to do some extra setup.
     *
     * @return false|\type|void
     */
    public function setup() {
        $table = $this->dbclass::TABLE;
        $duration = 'CASE WHEN timeend - timestart > 0 THEN timeend - timestart ELSE 0 END AS duration';
        $this->set_sql(
            "*, $duration",
            "{{$table}}",
            $this->get_where_sql(),
        );
        $retvalue = parent::setup();
        $this->set_attribute('class', $this->attributes['class'] . ' table-sm mb-3');
        return $retvalue;
    }

    /**
     * Returns the search persistent for the record.
     * @param \stdClass $record
     * @return search
     */
    public function get_persistent(stdClass $record): search {
        if (empty($this->persistent[$record->id])) {
            $this->persistent[$record->id] = new $this->dbclass(0, $record);
        }
        return $this->persistent[$record->id];
    }

    /**
     * Returns the shards for the record.
     * @param \stdClass $record
     * @return array
     */
    protected function get_shards(stdClass $record): array {
        $persistent = $this->get_persistent($record);
        if ($persistent->has_shards() && !$persistent->is_finished()) {
            return $persistent->get_all_shards();
        }
        return [];
    }

    /**
     * Generic function to format a row that may contain shards. Column output for
     * child searches will be combined into the parent until the task is finished.
     * @param stdClass $record
     * @param string $column column being formatted
     * @return string formatted text
     */
    protected function format_with_shards(stdClass $record, string $column): string {
        $function = 'format_' . $column;
        $shards = $this->get_shards($record);
        if (empty($shards)) {
            return $this->$function($record);
        }
        $output = '';
        foreach ($shards as $shard) {
            $output .= \html_writer::span($this->$function($shard->to_record()), 'text-nowrap') . '<br>';
        }
        return $output;
    }

    /**
     * Displays the name of the search.
     *
     * @param stdClass $record
     * @return string
     */
    public function col_name(stdClass $record): string {
        // Add a generic name fallback.
        if (empty($record->name)) {
            return get_string('search') . ' ' . $record->id;
        }

        return $record->name;
    }

    /**
     * Displays the full name of the user.
     *
     * @param stdClass $record
     * @return string
     */
    public function col_userid(stdClass $record): string {
        if (empty($record->userid)) {
            return '';
        }

        $user = \core_user::get_user($record->userid);
        $display = !empty($user) ? fullname($user) : $record->userid;
        return \html_writer::link(new \moodle_url('/user/profile.php', ['id' => $record->userid]), $display);
    }

    /**
     * Formats content for the search column.
     *
     * @param stdClass $record
     * @return string
     */
    public function col_search(stdClass $record): string {
        $class = 'border p-1 d-inline';
        $style = 'white-space: pre-wrap;';
        return \html_writer::tag('pre', $record->search, ['class' => $class, 'style' => $style]);
    }

    /**
     * Generate content for progress column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function col_progress($record): string {
        return $this->format_with_shards($record, 'progress');
    }

    /**
     * Formats content for the progress column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function format_progress($record): string {
        $progress = get_string('percents', 'moodle', round($record->progress, 1));
        $badge = 'badge badge-secondary';
        $search = $this->get_persistent($record);
        if ($search->is_finished()) {
            $badge = 'badge badge-success';
        } else if ($search->is_stale()) {
            $badge = 'badge badge-danger';
        } else if ($search->in_progress()) {
            $badge = 'badge badge-warning';
        }

        $attributes = $this->get_common_attributes($record);
        // Add a slight margin to badges to make them line up nicely on boost theme.
        $attributes['style'] = 'margin: 0.05em';
        return \html_writer::span($progress, $badge, $attributes);
    }

    /**
     * Generate content for timestart column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function col_timestart($record): string {
        return $this->format_with_shards($record, 'timestart');
    }

    /**
     * Formats content for timestart column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function format_timestart($record): string {
        if (empty($record->timestart)) {
            return '';
        }
        $format = get_string('strftimedatetimemonthshort', 'tool_advancedreplace');
        return userdate($record->timestart, $format);
    }

    /**
     * Generate content for duration column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function col_duration($record): string {
        return $this->format_with_shards($record, 'duration');
    }


    /**
     * Formats content for duration column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function format_duration($record): string {
        if (empty($record->timestart)) {
            return '';
        }

        $search = $this->get_persistent($record);
        $duration = $search->get_duration();
        if (empty($duration)) {
            // The format_time function returns 'now' when the difference is exactly 0.
            return '0 ' . get_string('secs', 'moodle');
        }

        $attributes = $this->get_common_attributes($record);
        return \html_writer::span(format_time($duration), '', $attributes);
    }

    /**
     * Generate content for matches column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function col_matches($record): string {
        return $this->format_with_shards($record, 'matches');
    }

    /**
     * Formats content for matches column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function format_matches($record): string {
        return $record->matches;
    }

    /**
     * Generate content for options column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function col_options($record): string {
        $options = [];
        $bool = ['regex', 'summary'];
        foreach (static::OPTIONS as $option) {
            if (!empty($record->$option)) {
                $name = get_string('field_' . $option, 'tool_advancedreplace');
                // Add a space between comma seperated values.
                $value = preg_replace('/,(?!\s)/', ', ', $record->$option);
                $options[] = in_array($option, $bool) ? $name : $name . ': ' . $value;
            }
        }
        $search = $this->get_persistent($record);
        $shardids = $search->get_shard_ids();
        if (!empty($shardids)) {
            $shardids = $search->get_shard_ids();
            $options[] = get_string('field_shards', 'tool_advancedreplace') . ': ' . implode(', ', $shardids);
        }

        return format_text(implode(PHP_EOL, $options));
    }

    /**
     * Generate content for output column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function col_output($record): string {
        return $this->format_with_shards($record, 'output');
    }

    /**
     * Generate content for output column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function format_output($record): string {
        $output = '';
        $output .= self::get_download_link($record);
        return $output;
    }

    /**
     * Generate content for actions column.
     *
     * @param stdClass $record
     * @return string html used to display the manage column field.
     */
    public function col_actions($record): string {
        $actions = '';
        $actions .= self::get_copy_link($record);
        $actions .= self::get_delete_link($record);
        return $actions;
    }

    /**
     * Returns a download link for a pluginfile.
     *
     * @param stdClass $record
     * @return string html for download link, or an empty string.
     */
    protected function get_download_link($record): string {
        global $OUTPUT;

        // Load the persistent to get records.
        $search = $this->get_persistent($record);
        $file = $search->get_file();
        $tempname = false;

        // Make sure search is finished and we have a file.
        if (empty($record->timeend) || !$file) {
            // If we have any matches, we may still be able to provide a temporary file.
            $temppath = $search->get_temp_filepath();
            if (!$record->matches || !file_exists($temppath)) {
                return '';
            }
            if (!$search->is_shard() || !$search->is_finished()) {
                $tempname = true;
            }
        }

        $fileurl = $search->get_pluginfile_url($tempname);
        $filename = $search->get_filename($tempname);
        $filesize = $file ? $file->get_filesize() : filesize($temppath);
        $filesize = display_size($filesize);

        $download = \html_writer::link($fileurl, $filename);
        return "$download ($filesize)";
    }

    /**
     * Returns a delete link for a search.
     *
     * @param stdClass $record
     * @return string html for delete link, or an empty string.
     */
    protected function get_delete_link($record): string {
        global $OUTPUT;

        $url = new \moodle_url('/admin/tool/advancedreplace/' . $this->urlfragment,
            ['delete' => $record->id, 'sesskey' => sesskey()]);
        $action = new \confirm_action(get_string('confirm_delete', 'tool_advancedreplace'));
        $deleteicon = $OUTPUT->render(new \pix_icon('t/delete', get_string('delete')));

        $actionlink = new \action_link($url, $deleteicon, $action);
        return $OUTPUT->render($actionlink);
    }


    /**
     * Returns a copy link for a search.
     *
     * @param stdClass $record
     * @return string html for copy link, or an empty string.
     */
    protected function get_copy_link($record): string {
        global $OUTPUT;

        $url = new \moodle_url('/admin/tool/advancedreplace/' . $this->urlfragment, ['copy' => $record->id]);
        $copyicon = $OUTPUT->render(new \pix_icon('t/copy', get_string('copyoptions', 'tool_advancedreplace')));
        return \html_writer::link($url, $copyicon, ['class' => 'action-icon']);
    }

    /**
     * Calculates the ETA for a search.
     * @param stdClass $record
     * @return string ETA, or blank string
     */
    protected function get_eta($record): string {
        global $OUTPUT;

        if (!empty($record->timeend) || empty($record->progress) || $record->progress < 2) {
            return '';
        }

        // Calculate ETA.
        $duration = time() - $record->timestart;
        $estduration = $duration / ($record->progress / 100);
        $remaining = $record->timestart + $estduration;

        $format = get_string('strftimedatetime', 'langconfig');
        return get_string('eta', 'tool_advancedreplace', userdate($remaining, $format));
    }

    /**
     * Gets common attributes for rows, such as ETA
     * @param stdClass $record
     * @return array attributes
     */
    protected function get_common_attributes($record): array {
        $attributes = [];
        $search = $this->get_persistent($record);
        if ($search->is_stale()) {
            $lastupdated = time() - $record->timemodified;
            $attributes['title'] = get_string('lastupdated', 'tool_advancedreplace', format_time($lastupdated));
        } else if ($search->in_progress()) {
            $eta = $this->get_eta($record);
            if (!empty($eta)) {
                $attributes['title'] = $eta;
            }
        }
        return $attributes;
    }
}
