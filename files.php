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
 * Advanced search and replace strings throughout moodle files
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$url = new moodle_url('/admin/tool/advancedreplace/files.php');
$PAGE->set_url($url);

admin_externalpage_setup('tool_advancedreplace_files');

$id = optional_param('id', null, PARAM_INT);
$delete = optional_param('delete', null, PARAM_INT);
$copy = optional_param('copy', null, PARAM_INT);

if (isset($copy)) {
    $id = 0;
}

if (isset($delete)) {
    require_sesskey();
    $files = new \tool_advancedreplace\files($delete);
    $files->delete();
    \core\notification::success(get_string('searchdeleted', 'tool_advancedreplace'));;
    redirect($url);
}

if (isset($id)) {
    $newurl = new moodle_url('/admin/tool/advancedreplace/files.php');
    $newurl->param('id', $id);

    $customdata = [
        'persistent' => null,
        'userid' => $USER->id,
    ];
    $form = new \tool_advancedreplace\form\files($newurl->out(false), $customdata);
    if ($form->is_cancelled()) {
        redirect($url);
    } else if ($data = $form->get_data()) {
        if (empty($data->id)) {
            $files = new \tool_advancedreplace\files(0, $data);
            $files->create();
            $files->queue_task();

            \core\notification::success(get_string('searchqueued', 'tool_advancedreplace'));;
            redirect($url);
        } else {
            // This should never modify an existing search..
            redirect($url);
        }
    } else {
        // Display form.
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('filespageheader', 'tool_advancedreplace'));
        if (isset($copy)) {
            // Load the original data without setting the persistent.
            $files = new \tool_advancedreplace\files($copy);
            $data = $files->copy_data();
            $form->set_data($data);
            echo $OUTPUT->notification(get_string('searchcopy', 'tool_advancedreplace'), core\output\notification::NOTIFY_SUCCESS);
        }
        $form->display();
    }
} else {
    // Display search table.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('filespageheader', 'tool_advancedreplace'));

    $table = new \tool_advancedreplace\files_table('tool_advancedreplace');
    $table->sortable(true, 'id', SORT_DESC);
    $table->define_baseurl($url);
    $table->make_columns();
    $table->out(40, false);

    $url->param('id', 0);
    $newurl = new \moodle_url($url, ['id' => 0]);
    $newbutton = new \single_button($newurl, get_string('newsearch', 'tool_advancedreplace'), 'GET');
    echo $OUTPUT->render($newbutton);
}

echo $OUTPUT->footer();
