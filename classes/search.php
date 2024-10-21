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
        }
    }

    /**
     * Queues a search task to be run
     * @return bool true if the task was queued
     */
    public function queue_task(): bool {
        $adhoctask = new $this->adhoctask;
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
        foreach (static::COPY_COLUMNS as $column) {
            $data->$column = $this->get($column);
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
        $filename = !empty($name) ? $name : 'searchresult-' . $this->get('id');
        $temp = $temp ? '-temp' : '';
        return strtolower($filename) . $temp . '.csv';
    }
}
