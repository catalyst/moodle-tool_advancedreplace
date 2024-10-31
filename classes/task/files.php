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

namespace tool_advancedreplace\task;

/**
 * Ad-hoc task to search for regular expression matches in Moodle files.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files extends \core\task\adhoc_task {

    /**
     * Action of task.
     */
    public function execute() {
        // Get the custom data.
        $data = $this->get_custom_data();
        if (empty($data->searchid)) {
            return;
        }

        $record = \tool_advancedreplace\files::get_record(['id' => $data->searchid]);
        if (empty($record)) {
            // This may occur if the row has been deleted by the UI before the adhoc task has run.
            // Or if a failed adhoc task is being re-run after the row is deleted.
            // We want to silently do nothing and "succeed" so there will be no more re-runs.
            return;
        }

        // If the record is meant to have shards, the task only needs to spawn tasks.
        if ($record->has_shards()) {
            return self::spawn_shards($record);
        }

        \tool_advancedreplace\file_search::files($record, '', $data->startid ?? 0, $data->endid ?? 0, $data->finalshard ?? false);
    }

    /**
     * Spawns multiple tasks for each shard.
     * @param \tool_advancedreplace\files $record
     * @return void
     */
    public static function spawn_shards(\tool_advancedreplace\files $record): void {
        global $DB;

        $searchid = $record->get('id');
        $numshards = $record->get('shards');
        $timestart = time();

        // The sharding will be controlled by ranges of the id column of the mdl_files table.
        // Here we implement a simple division. In future we could use WHERE clause to choose more even break points.

        $maxid = $DB->get_field_sql('SELECT MAX(id) FROM {files}');

        // Create and spawn new tasks.
        $search = new \tool_advancedreplace\files($searchid);
        $basedata = $search->copy_data(true);
        $shardsize = ceil($maxid / $numshards);
        $startid = 0;
        $shardnum = 0;
        while ($shardnum < $numshards) {
            $shardnum++;
            $startid = ($shardnum - 1) * $shardsize;
            $endid = $shardnum * $shardsize - 1;
            $finalshard = ($shardnum === $numshards);
            $basedata->shardnum = $shardnum;
            $shard = new \tool_advancedreplace\files(0, $basedata);
            $shard->create();
            $shard->queue_task($startid, $endid, $finalshard);
        }

        // Update the start time on the parent.
        $record->set('timestart', $timestart);
        $record->update();
    }
}
