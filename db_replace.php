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
define('NO_OUTPUT_BUFFERING', true); // Progress bar is used here.

use tool_advancedreplace\helper;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/lib/csvlib.class.php');

global $CFG;
$replace       = optional_param('delete', 0, PARAM_INT);
$confirm      = optional_param('confirm', '', PARAM_BOOL);
$draftid      = optional_param('draftid', '', PARAM_TEXT);

$url = new moodle_url('/admin/tool/advancedreplace/db_replace.php');
$PAGE->set_url($url);

admin_externalpage_setup('tool_advancedreplace_search');

$redirect = new moodle_url('/admin/tool/advancedreplace/db_search.php');

$customdata = [
    'userid' => $USER->id,
];
$form = new \tool_advancedreplace\form\replace($url->out(false), $customdata);
echo $OUTPUT->header();
if ($form->is_cancelled()) {
    redirect($redirect);
} else if (!(get_config('tool_advancedreplace', 'allowuireplace'))) {
    echo $OUTPUT->heading(get_string('replacepageheader', 'tool_advancedreplace'));
    echo html_writer::div(get_string('replace_warning', 'tool_advancedreplace',
        '$CFG->forced_plugin_settings[\'tool_advancedreplace\'][\'allowuireplace\'] = 1;'), 'alert alert-warning');
} else if ($data = $form->get_data()) {
    $returnurl = new moodle_url('/admin/tool/advancedreplace/db_replace.php');
    $optionsyes = array('replace' => $replace, 'confirm' => 1, 'sesskey' => sesskey(), 'draftid' => $data->csvfile);
    $deleteurl = new moodle_url($url, $optionsyes);
    $deletebutton = new single_button($deleteurl, get_string('replace', 'tool_advancedreplace'), 'post');
    echo $OUTPUT->confirm(get_string('replacecheck', 'tool_advancedreplace'), $deletebutton, $returnurl);
} else if ($confirm && !empty($draftid)) {
    require_sesskey();
    $contents = helper::get_replace_csv_content($draftid);
    helper::handle_replace_csv($contents);
} else {
    // Display form.
    echo $OUTPUT->heading(get_string('replacepageheader', 'tool_advancedreplace'));
    $form->display();
}

echo $OUTPUT->footer();
