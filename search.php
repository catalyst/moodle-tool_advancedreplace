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
 * Advanced search and replace strings throughout all texts in the whole database
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$url = new moodle_url('/admin/tool/advancedreplace/search.php');
$PAGE->set_url($url);

admin_externalpage_setup('tool_advancedreplace_search');

$id = optional_param('id', null, PARAM_INT);

if (isset($id)) {
    $newurl = new moodle_url('/admin/tool/advancedreplace/search.php');
    $newurl->param('id', $id);

    $customdata = [
        'persistent' => null,
        'userid' => $USER->id,
    ];
    $form = new \tool_advancedreplace\form\search($newurl->out(false), $customdata);
    if ($form->is_cancelled()) {
        redirect($url);
    } else if ($data = $form->get_data()) {
        if (empty($data->id)) {
            $search = new \tool_advancedreplace\search(0, $data);
            $search->create();

            $searchid = $search->get('id');
            $adhoctask = new \tool_advancedreplace\task\search_db();
            $adhoctask->set_custom_data([
                'searchid' => $searchid,
            ]);
            \core\task\manager::queue_adhoc_task($adhoctask);

            \core\notification::success(get_string('searchqueued', 'tool_advancedreplace'));;
            redirect($url);
        } else {
            // Should not be here. TODO: Better handling.
            redirect($url);
        }
    } else {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('searchpageheader', 'tool_advancedreplace'));
        echo $OUTPUT->notification(get_string('excludedtables', 'tool_advancedreplace'), core\output\notification::NOTIFY_INFO);
        $form->display();
    }
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('searchpageheader', 'tool_advancedreplace'));

    $table = new \tool_advancedreplace\search_table('tool_advancedreplace');
    $table->sortable(true, 'id', SORT_DESC);
    $table->define_baseurl($url);
    $table->make_columns();
    $table->out(40, false);

    $url->param('id', 0);
    $newurl = new \moodle_url($url, ['id' => 0]);
    $newbtton = new \single_button($newurl, get_string('newsearch', 'tool_advancedreplace'), 'GET');
    echo $OUTPUT->render($newbtton);
}

echo $OUTPUT->footer();
