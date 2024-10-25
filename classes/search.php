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

    /** @var string File area for output files */
    protected $filearea = '';

    /** @var string Class for the adhoc task */
    protected $adhoctask = '';

    /** @var array Records of child shards */
    protected $shards = null;

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
    public function copy_data($shard = false): \stdClass {
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
    public function get_pluginfile_url($temp = false) {
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
    public function get_filename($temp = false): string {
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
     * Checks whether a search is finished running
     * @return bool whether the search is finished
     */
    public function is_finished(): bool {
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

        $this->shards = static::get_records(['origin' => $this->get('id')], 'id');
        return $this->shards;
    }
}
