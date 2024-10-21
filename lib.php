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

/**
 * Helper class to search and replace text throughout the whole database.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serves test files for advancedreplace.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param context $context Context
 * @param string $filearea File area for data privacy
 * @param array $args Arguments
 * @param bool $forcedownload If we are forcing the download
 * @param array $options More options
 * @return bool Returns false if we don't find a file.
 */
function tool_advancedreplace_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }
    require_admin();

    $filename = $args[1];
    if ($args[1] === 'temp') {
        // If we have a temp file, check if the finished file exists.
        $filename = str_replace('-temp.csv', '.csv', $args[2]);
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'tool_advancedreplace', $filearea, $args[0], '/', $filename);
    if (!$file) {
        // Check if there's an in progress version.
        $tempfile = "$CFG->tempdir/tool_advancedreplace/$filearea-$args[0]";
        if (file_exists($tempfile)) {
            // Send temp file without caching.
            send_file($tempfile, $args[2], 0, 0, false, $forcedownload, '', false, $options);
            return true;
        }

        return false;
    }
    send_stored_file($file, null, 0, $forcedownload, $options);
    return true;
}
