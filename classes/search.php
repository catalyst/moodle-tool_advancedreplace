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
 * Abstract search history class for advanced replace.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class search extends \core\persistent {

    /** Fields to copy when copying a record. */
    public const COPY_COLUMNS = [];

    /** How many seconds to wait before marking a search as stale. */
    public const STALE = MINSECS * 5;

    /** @var string File area for output files */
    protected $filearea = '';

    /** @var string Class for the adhoc task */
    protected $adhoctask = '';

    /**
     * @var \stdClass tracking of current status */
    protected $status = null;

    /** @var array Records of child shards */
    protected $shards = null;

    /**
     * Hook to execute after a delete
     *
     * @param bool $result Whether or not the delete was successful.
     * @return void
     */
    protected function after_delete($result): void {
        if ($result) {
            // Delete output pluginfiles.
            if ($file = $this->get_file()) {
                $file->delete();
            }
            // Delete remaining temp files.
            $tempfile = $this->get_temp_filepath();
            if (!empty($tempfile) && file_exists($tempfile)) {
                @unlink($tempfile);
            }
            // Delete shards.
            $shards = $this->get_all_shards();
            foreach ($shards as $shard) {
                $shard->delete();
            }
        }
    }

    /**
     * Queues a search task to be run
     * @param int $limitfrom where to start the search sql
     * @param int $limitnum limit the number of sql results
     * @return bool true if the task was queued
     */
    public function queue_task(int $limitfrom = 0, int $limitnum = 0): bool {
        $adhoctask = new $this->adhoctask;
        $customdata = [
            'searchid' => $this->get('id'),
        ];
        // If we have either limits we should include both.
        if (!empty($limitfrom) || !empty($limitnum)) {
            $customdata['limitfrom'] = $limitfrom;
            $customdata['limitnum'] = $limitnum;
        }
        $adhoctask->set_custom_data($customdata);
        return \core\task\manager::queue_adhoc_task($adhoctask);
    }

    /**
     * Returns a clean copy of data that can be used to rerun a search.
     * @param int $shard if the copied data will be used for a shard.
     * @return \stdClass
     */
    public function copy_data(int $shard = 0): \stdClass {
        $data = new \stdClass();
        foreach (static::COPY_COLUMNS as $column) {
            $data->$column = $this->get($column);
        }
        if ($shard) {
            $data->shardnum = 1;
            $data->origin = $this->get('id');
            $data->userid = $this->get('userid');
        }
        return $data;
    }

    /**
     * Check if the search still exists
     * @return bool if the search still exists
     */
    public function search_exists(): bool {
        if (!$this->record_exists($this->get('id'))) {
            return false;
        }
        return true;
    }

    /**
     * Updates a progress bar using the current status.
     * @param string $message progress message
     * @return void
     */
    public function update_progress_bar(string $message): void {
        if (isset($this->status) && isset($this->status->progressbar)) {
            $this->status->progressbar->update($this->status->itemcount, $this->status->totalcount, $message);
        }
    }

    /**
     * Updates the tracking status of a search.
     * @param int $itemcount number of items that have been searched
     * @param int $matches matches found
     * @throws \coding_exception
     * @return bool whether we should continue searching
     */
    public function update_status(int $itemcount, int $matches): bool {
        if (!isset($this->status)) {
            throw new \coding_exception('Status has not been initalised');
        }

        // Update item count.
        $this->status->itemcount = $itemcount;

        // Only update update search progress every 10 seconds or 5 percent.
        $time = time();
        $percent = round(100 * $itemcount / $this->status->totalcount, 2);
        if ($time > $this->status->prevtime + 10 || $percent > $this->status->prevpercent + 5) {
            // If record has gone, exit the job.
            if (!$this->search_exists()) {
                return false;
            }

            $this->set('progress', $percent);
            $this->set('matches', $matches);
            $this->update();
            $this->status->prevtime = $time;
            $this->status->prevpercent = $percent;
        }
        return true;
    }

    /**
     * Marks a search as having started and initialises tracking.
     * @param int $totalcount estimate of total being searched
     * @return void
     */
    public function mark_started(int $totalcount): void {
        $this->set('timestart', time());
        $this->update();

        // Setup tracking.
        $status = new \stdClass();
        $status->prevtime = time();
        $status->prevpercent = 0;
        $status->itemcount = 0;
        $status->totalcount = $totalcount;
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
        if (isset($this->status) && isset($this->status->progressbar)) {
            $this->status->progressbar->update_full(100, "Finished saving searches into $output");
        }

        $this->set('timeend', time());
        $this->set('progress', 100);
        $this->set('matches', $matches);
        $this->update();
    }

    /**
     * Gets the duration of a search
     * @return int duration
     */
    public function get_duration(): int {
        $timestart = $this->get('timestart');
        if (empty($timestart)) {
            return 0;
        }

        $timeend = $this->get('timeend');
        if (empty($timeend)) {
            // If stale, use time modified, otherwise it's ongoing so use current time.
            $timeend = $this->is_stale() ? $this->get('timemodified') : time();
        }
        return $timeend - $timestart;
    }

    /**
     * Gets the file name for temporary search output.
     * @return string filename of temporary output
     */
    public function get_temp_filename(): string {
        return $this->filearea . '-' . $this->get('id');
    }

    /**
     * Gets the file path for the temporary search output.
     * @return string filepath of temporary output
     */
    public function get_temp_filepath(): string {
        global $CFG;
        return $CFG->tempdir . '/tool_advancedreplace/' . $this->get_temp_filename();
    }

    /**
     * Returns the pluginfile url for both files and temp files.
     * @param bool $temp add temp identifier to pluginfile
     * @return string pluginfile url
     */
    public function get_pluginfile_url(bool $temp = false): string {
        $pathname = $temp ? '/temp/' : '/';
        return \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            'tool_advancedreplace',
            $this->filearea,
            $this->get('id'),
            $pathname,
            $this->get_filename($temp)
        )->out();
    }

    /**
     * Saves search output as a pluginfile
     * @param string $output path
     * @return void
     */
    public function save_pluginfile(string $output): void {
        if (empty($this->get('matches')) || $this->is_shard()) {
            return;
        }

        $fs = get_file_storage();
        $fileinfo = [
            'contextid' => \context_system::instance()->id,
            'component' => 'tool_advancedreplace',
            'filearea'  => $this->filearea,
            'itemid'    => $this->get('id'),
            'filepath'  => '/',
            'filename'  => $this->get_filename(),
        ];
        $fs->create_file_from_pathname($fileinfo, $output);
    }

    /**
     * Gets the file for the search output
     * @return bool|\stored_file
     */
    public function get_file() {
        $fs = get_file_storage();
        return $fs->get_file(
            \context_system::instance()->id,
            'tool_advancedreplace',
            $this->filearea,
            $this->get('id'),
            '/',
            $this->get_filename()
        );
    }

    /**
     * Gets the file name of the search output
     * @param bool $temp add temp identifier to filename
     * @return string filename
     */
    public function get_filename(bool $temp = false): string {
        // The hardcoded default filename should not be changed.
        $name = $this->get('name');
        $shard = $this->is_shard();
        $id = $this->is_shard() ? $this->get('origin') : $this->get('id');
        $filename = !empty($name) ? strtolower($name) : 'searchresult-' . $id;
        if ($shard) {
            $filename .= '-' . $this->get('shardnum') . '-of-' . $this->get('shards');
        }
        if ($temp) {
            $filename .= '-temp';
        }
        return $filename . '.csv';
    }

    /**
     * Checks whether a search is stale
     * @return bool whether the search is stale
     */
    public function is_stale(): bool {
        if ($this->is_finished()) {
            return false;
        }

        $lastupdated = time() - $this->get('timemodified');
        return !empty($this->get('timestart') && $lastupdated > static::STALE);
    }

    /**
     * Checks whether a search is in progress
     * @return bool whether the search is in progress
     */
    public function in_progress(): bool {
        if ($this->is_finished()) {
            return false;
        }

        // Having timestart should be enough, but the extra check on progress won't hurt.
        return !empty($this->get('timestart')) || $this->get('progress') > 0;
    }

    /**
     * Checks whether a search is finished running
     * @return bool whether the search is finished
     */
    public function is_finished(): bool {
        // Check both progress and time end for finished in case progress is rounded up.
        return !empty($this->get('timeend')) && $this->get('progress') == 100;
    }

    /**
     * Checks whether a search is a shard
     * @return bool whether a search is a shard
     */
    public function is_shard(): bool {
        return $this->has_property('shardnum') && !empty($this->get('shardnum'));
    }

    /**
     * Checks whether a search has child shards
     * @return bool whether a search has child shards
     */
    public function has_shards(): bool {
        return $this->has_property('shards') && $this->get('shards') > 1 && !$this->is_shard();
    }

    /**
     * Gets the parent of a shard
     * @return search|null parent, or null if no parent is found
     */
    public function get_parent(): ?search {
        if (!$this->is_shard()) {
            return null;
        }

        $parent = $this->get('origin');
        return new static($parent);
    }

    /**
     * Checks whether all child shards have finished running.
     * @return bool if all child shards are finished
     */
    public function shards_finished(): bool {
        $shards = $this->get_all_shards();
        foreach ($shards as $shard) {
            if (!$shard->is_finished()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Gets all shard ids of a parent
     * @return array of ids
     */
    public function get_shard_ids(): array {
        $ids = [];
        $shards = $this->get_all_shards();

        foreach ($shards as $shard) {
            $ids[] = $shard->get('id');
        }
        return $ids;
    }

    /**
     * Gets all sharded records
     * @return array sharded records
     */
    public function get_all_shards(): array {
        if (!$this->has_shards()) {
            return [];
        }

        if (isset($this->shards)) {
            return $this->shards;
        }

        $this->shards = $this->get_records(['origin' => $this->get('id')], 'id');
        return $this->shards;
    }
}
