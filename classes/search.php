<?php
// This file is part of Moodle - https://moodle.org/
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
 * Search history for advanced replace.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search extends \core\persistent {

    /** The name of the database table. */
    public const TABLE = 'tool_advancedreplace_search';

    /** Fields to copy when copying a record. */
    public const COPY_COLUMNS = [
        'name',
        'search',
        'regex',
        'prematch',
        'tables',
        'skiptables',
        'skipcolumns',
        'summary',
    ];

    /** @var array includetables from config. */
    protected $includetables = null;

    /** @var array excludetables from config. */
    protected $excludetables = null;

    /**
     * @var \stdClass tracking of current status */
    protected $status = null;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'userid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'name' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'search' => [
                'type' => PARAM_RAW,
            ],
            'regex' => [
                'type' => PARAM_BOOL,
                'default' => 0,
            ],
            'prematch' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'tables' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skiptables' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipcolumns' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'summary' => [
                'type' => PARAM_BOOL,
                'default' => 0,
            ],
            'origin' => [
                'type' => PARAM_TEXT,
            ],
            'timestart' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'timeend' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'progress' => [
                'type' => PARAM_FLOAT,
                'default' => 0,
            ],
            'matches' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Hook to execute before a delete.
     *
     * @return void
     */
    protected function before_delete(): void {
        // TODO: Clean up any remaining adhoc tasks.
    }

    /**
     * Hook to execute after a delete
     *
     * @param bool $result Whether or not the delete was successful.
     * @return void
     */
    protected function after_delete($result): void {
        if ($result) {
            // Delete output pluginfiles.
            if ($file = $this->get_file(self::to_record())) {
                $file->delete();
            }
        }
    }

    /**
     * Queues a search task to be run
     * @return bool true if the task was queued
     */
    public function queue_task(): bool {
        $adhoctask = new \tool_advancedreplace\task\search_db();
        $adhoctask->set_custom_data([
            'searchid' => $this->get('id'),
        ]);
        return \core\task\manager::queue_adhoc_task($adhoctask);
    }

    /**
     * Returns a clean copy of data that can be used to rerun a search.
     * @return \stdClass
     */
    public function copy_data(): \stdClass {
        $data = new \stdClass();
        foreach (self::COPY_COLUMNS as $column) {
            $data->$column = $this->get($column);
        }
        return $data;
    }

    /**
     * Loads config tables and stores the results.
     * @param string $name
     * @return array
     */
    protected function get_config_table(string $name): array {
        if (isset($this->$name)) {
            return $this->$name;
        }
        $value = get_config('tool_advancedreplace', $name);
        $matches = preg_split('/[\n,]+/', $value);
        $this->$name = array_filter(array_map('trim', $matches));
        return $this->$name;
    }

    /**
     * A custom list of tables to be searched. If no options are set, use tables from config.
     * @return array tables to be searched
     */
    public function get_all_searchtables(): array {
        $tables = array_filter(array_map('trim', explode(',', $this->get('tables'))));
        return !empty($tables) ? $tables : $this->get_config_table('includetables');
    }

    /**
     * A custom list of tables that should be skipped. This combines options, config and custom skip tables.
     * Tables that are skipped by core as part of db_should_replace() are handled elsewhere.
     * @return array tables that should be skipped
     */
    public function get_all_skiptables(): array {
        return array_merge($this->get_config_table('excludetables'), helper::SKIP_TABLES, explode(',', $this->get('skiptables')));
    }

    /**
     * A custom list of columns that should be skipped.
     * @return array columns that should be skipped
     */
    public function get_all_skipcolumns(): array {
        return explode(',', $this->get('skipcolumns'));
    }

    /**
     * Calculates the minimum search length
     * @return int minimum search length
     */
    public function get_min_search_length(): int {
        // For regex, use prematch as a rough estimate, otherwise use no minimum.
        $minsearch = empty($this->get('regex')) ? $this->get('search') : $this->get('prematch');
        return strlen($minsearch);
    }

    /**
     * Updates a progress bar using the current status.
     * @param string $table table being searched
     * @param string $colname column being searched
     * @return void
     */
    public function update_progress_bar(string $table, string $colname): void {
        if (isset($this->status) && isset($this->status->progressbar)) {
            $message = "Searching in $table:$colname";
            $this->status->progressbar->update($this->status->rowcount, $this->status->totalrows, $message);
        }
    }

    /**
     * Updates the tracking status of a search.
     * @param int $rowcount number of rows that have been searched
     * @param int $matches matches found
     * @throws \coding_exception
     * @return void
     */
    public function update_status(int $rowcount, int $matches): void {
        if (!isset($this->status)) {
            throw new \coding_exception('Status has not been initalised');
        }

        // Update row count.
        $this->status->rowcount = $rowcount;

        // Only save update search progress every 10 seconds or 5 percent.
        $time = time();
        $percent = round(100 * $rowcount / $this->status->totalrows, 2);
        if ($time > $this->status->prevtime + 10 || $percent > $this->status->prevpercent + 5) {
            $this->set('progress', $percent);
            $this->set('matches', $matches);
            $this->save();
            $this->status->prevtime = $time;
            $this->status->prevpercent = $percent;
        }
    }

    /**
     * Marks a search as having started and initialises tracking.
     * @param int $totalrows estimate of total rows being searched
     * @return void
     */
    public function mark_started(int $totalrows): void {
        $this->set('timestart', time());
        $this->save();

        // Setup tracking.
        $status = new \stdClass();
        $status->prevtime = time();
        $status->prevpercent = 0;
        $status->rowcount = 0;
        $status->totalrows = $totalrows;
        $status->progressbar = null;

        // If called from CLI, add a progress bar.
        if ($this->get('origin') === 'cli') {
            $status->progressbar = new \progress_bar();
            $status->progressbar->create();
        }
        $this->status = $status;
    }

    /**
     * Saves the final values and marks a search as finished.
     *
     * @param int $matches matches found
     * @param string $output
     * @return void
     */
    public function mark_finished(int $matches, string $output = ''): void {
        // Update progress bar.
        if (isset($this->status) && isset($this->status->progress)) {
            $this->status->progress->update_full(100, "Finished saving searches into $output");
        }

        $this->set('timeend', time());
        $this->set('progress', 100);
        $this->set('matches', $matches);
        $this->save();
    }

    /**
     * Gets the file for the search output
     * @param \stdClass $record
     * @return bool|\stored_file
     */
    public static function get_file(\stdClass $record) {
        $filename = self::get_filename($record);
        $fs = get_file_storage();
        return $fs->get_file(
            \context_system::instance()->id,
            'tool_advancedreplace',
            'search',
            $record->id,
            '/',
            $filename
        );
    }

    /**
     * Gets the file name of the search output
     * @param \stdClass $record
     * @return string filename
     */
    public static function get_filename(\stdClass $record): string {
        // The hardcoded default filename should not be changed.
        $name = !empty($record->name) ? $record->name : 'searchresult-' . $record->id;
        return strtolower($name) . '.csv';
    }
}
