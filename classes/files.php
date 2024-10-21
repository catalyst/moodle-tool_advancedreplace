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
class files extends \core\persistent {

    /** The name of the database table. */
    public const TABLE = 'tool_advancedreplace_files';

    /** Fields to copy when copying a record. */
    public const COPY_COLUMNS = [
        'name',
        'pattern',
        'components',
        'skipcomponents',
        'mimetypes',
        'skipmimetypes',
        'filenames',
        'skipfilenames',
        'skipareas',
        'openzips',
        'zipfilenames',
        'skipzipfilenames',
        'zipmimetypes',
        'skipzipmimetypes',
    ];

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
            'pattern' => [
                'type' => PARAM_RAW,
            ],
            'components' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipcomponents' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'mimetypes' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipmimetypes' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'filenames' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipfilenames' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipareas' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'openzips' => [
                'type' => PARAM_BOOL,
                'default' => 0,
            ],
            'zipfilenames' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipzipfilenames' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'zipmimetypes' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'skipzipmimetypes' => [
                'type' => PARAM_RAW,
                'default' => '',
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
        $adhoctask = new \tool_advancedreplace\task\files();
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
            'files',
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
