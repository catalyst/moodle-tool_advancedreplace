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
 * Paginated report view of a search result CSV file.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

$id   = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);

$url = new moodle_url('/admin/tool/advancedreplace/db_search_report.php', ['id' => $id]);
$PAGE->set_url($url);

admin_externalpage_setup('tool_advancedreplace_search_report', '', [], $url->out(false));

$search = new \tool_advancedreplace\db_search($id);
if (!$search->get('id')) {
    throw new \moodle_exception('invalidrecordid');
}

$searchname = $search->get('name');
if (empty($searchname)) {
    $searchname = get_string('search') . ' ' . $id;
}
$searchname .= ': ' . $search->get('search');

// Manually build the full breadcrumb trail since admin_externalpage_setup
// only marks the node active in settingsnav but doesn't populate navbar.
$PAGE->navbar->add(
    get_string('pluginname', 'tool_advancedreplace'),
    new moodle_url('/admin/category.php', ['category' => 'advancereplacefolder'])
);
$PAGE->navbar->add(
    get_string('searchpagename', 'tool_advancedreplace'),
    new moodle_url('/admin/tool/advancedreplace/db_search.php')
);
$PAGE->navbar->add($searchname);

// Resolve the CSV file path (stored pluginfile or in-progress temp file).
$csvpath = null;
$file = $search->get_file();
if ($file) {
    // Copy stored file to a temp location so we can use fgetcsv on it.
    $csvpath = make_temp_directory('tool_advancedreplace') . '/report-' . $id . '.csv';
    $file->copy_content_to($csvpath);
    $cleanup = true;
} else {
    $temppath = $search->get_temp_filepath();
    if (file_exists($temppath)) {
        $csvpath = $temppath;
        $cleanup = false;
    }
}

// Output the page header before any early-exit notifications.
$title = get_string('searchreporttitle', 'tool_advancedreplace', $searchname);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$backurl = new moodle_url('/admin/tool/advancedreplace/db_search.php');
echo \html_writer::div(
    \html_writer::link($backurl, get_string('searchreportback', 'tool_advancedreplace')),
    'mb-3'
);

$requeueurl = new moodle_url('/admin/tool/advancedreplace/db_search.php', [
    'requeue' => $id,
    'sesskey' => sesskey(),
]);
if ($search->is_finished()) {
    $requeuelink = new \action_link(
        $requeueurl,
        get_string('requeueoptions', 'tool_advancedreplace'),
        new \confirm_action(get_string('confirm_requeue', 'tool_advancedreplace')),
        ['class' => 'btn btn-secondary'],
        new \pix_icon('t/reload', '')
    );
    echo $OUTPUT->render($requeuelink);
}

// Summary info box.
$record = $search->to_record();
$dtformat = get_string('strftimedatetimemonthshort', 'tool_advancedreplace');
$headerrow = '';
$valuerow = '';

// Regex or plain text.
$regexlabel = $record->regex
    ? get_string('field_regex', 'tool_advancedreplace')
    : get_string('searchreportplaintext', 'tool_advancedreplace');
$headerrow .= \html_writer::tag('th', get_string('field_search', 'tool_advancedreplace'));
$valuerow  .= \html_writer::tag('td', $regexlabel);

// Progress badge.
$progress = get_string('percents', 'moodle', round($record->progress, 1));
$badge = 'badge badge-secondary';
if ($search->is_finished()) {
    $badge = 'badge badge-success';
} else if ($search->is_stale()) {
    $badge = 'badge badge-danger';
} else if ($search->in_progress()) {
    $badge = 'badge badge-warning';
}
$headerrow .= \html_writer::tag('th', get_string('field_progress', 'tool_advancedreplace'));
$valuerow  .= \html_writer::tag('td', \html_writer::span($progress, $badge));

// Time started.
$headerrow .= \html_writer::tag('th', get_string('field_timestart', 'tool_advancedreplace'));
$headerrow .= \html_writer::tag('th', get_string('field_duration', 'tool_advancedreplace'));
if (!empty($record->timestart)) {
    $valuerow .= \html_writer::tag('td', userdate($record->timestart, $dtformat));
    // Duration.
    $duration = $search->get_duration();
    $durationstr = empty($duration) ? '0 ' . get_string('secs', 'moodle') : format_time($duration);
    $valuerow .= \html_writer::tag('td', $durationstr);
} else {
    $valuerow .= \html_writer::tag('td', '-');
    $valuerow .= \html_writer::tag('td', '-');
}

// Matches.
$headerrow .= \html_writer::tag('th', get_string('field_matches', 'tool_advancedreplace'));
$valuerow  .= \html_writer::tag('td', number_format($record->matches));

// Options.
$optionscols = ['regex', 'prematch', 'tables', 'skiptables', 'skipcolumns', 'summary'];
$boolcols = ['regex', 'summary'];
$options = [];
foreach ($optionscols as $opt) {
    if (!empty($record->$opt)) {
        $label = get_string('field_' . $opt, 'tool_advancedreplace');
        $val = preg_replace('/,(?!\s)/', ', ', $record->$opt);
        $options[] = in_array($opt, $boolcols) ? $label : $label . ': ' . $val;
    }
}
$headerrow .= \html_writer::tag('th', get_string('field_options', 'tool_advancedreplace'));
$valuerow  .= \html_writer::tag('td', !empty($options) ? format_text(implode(PHP_EOL, $options)) : '-');

echo \html_writer::tag(
    'table',
    \html_writer::tag('thead', \html_writer::tag('tr', $headerrow)) .
    \html_writer::tag('tbody', \html_writer::tag('tr', $valuerow)),
    ['class' => 'table table-sm generaltable w-auto mb-4']
);

if ($csvpath === null) {
    if ($search->is_finished()) {
        echo $OUTPUT->notification(get_string('searchreportempty', 'tool_advancedreplace'), 'info');
    } else if ($search->in_progress()) {
        echo $OUTPUT->notification(get_string('searchreportinprogress', 'tool_advancedreplace'), 'warning');
    } else {
        echo $OUTPUT->notification(get_string('searchreportpending', 'tool_advancedreplace'), 'info');
    }
    echo $OUTPUT->footer();
    exit;
}

// Read the CSV (streaming, to support large files).
$fp = fopen($csvpath, 'r');
if (!$fp) {
    throw new \moodle_exception('errorfilenotfound', 'tool_advancedreplace');
}

// Read header row.
$headers = fgetcsv($fp, 0, ',', '"', '\\');
if ($headers === false) {
    fclose($fp);
    throw new \moodle_exception('errorinvalidfile', 'tool_advancedreplace');
}

// Count total data rows and collect the page's rows efficiently.
// Use the stored match count to avoid scanning the entire file just for pagination.
$totalrows = (int)$search->get('matches');
$pagerows  = [];
$start     = $page * $perpage;
$end       = $start + $perpage;
$rownum    = 0;

while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
    if ($rownum >= $start && $rownum < $end) {
        $pagerows[] = $row;
    }
    $rownum++;
    // Stop reading once we have collected the page rows and passed the end offset.
    if ($rownum >= $end && $rownum <= $totalrows) {
        break;
    }
}
fclose($fp);

if (!empty($cleanup) && file_exists($csvpath)) {
    @unlink($csvpath);
}

// Output.

if ($totalrows === 0) {
    echo $OUTPUT->notification(get_string('searchreportempty', 'tool_advancedreplace'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Pager.
echo $OUTPUT->paging_bar($totalrows, $page, $perpage, $url);

// Search term to highlight — use prematch for regex searches.
$highlightneedle = $search->get('regex') ? $search->get('prematch') : $search->get('search');

// Columns to hide entirely (rendered elsewhere or always empty).
$hiddencols = ['replace', 'link'];

// Determine which column indexes to skip or merge.
$hiddenindexes = [];
$linkindex = null;
$matchindex = null;
$courseidindex = null;
$shortnameindex = null;
foreach ($headers as $i => $h) {
    if (in_array($h, $hiddencols)) {
        $hiddenindexes[] = $i;
    }
    if ($h === 'link') {
        $linkindex = $i;
    }
    if ($h === 'match') {
        $matchindex = $i;
    }
    if ($h === 'courseid') {
        $courseidindex = $i;
    }
    if ($h === 'shortname') {
        $shortnameindex = $i;
    }
}

// Column label map.
$columnsmap = [
    'table'     => get_string('field_table', 'tool_advancedreplace'),
    'column'    => get_string('field_column', 'tool_advancedreplace'),
    'courseid'  => get_string('field_id', 'tool_advancedreplace') . ' (course)',
    'shortname' => get_string('shortnamecourse'),
    'id'        => get_string('field_id', 'tool_advancedreplace'),
    'match'     => get_string('field_search', 'tool_advancedreplace'),
];

// Build table header cells — the match column absorbs link.
$headercells = [];
foreach ($headers as $i => $h) {
    if (in_array($i, $hiddenindexes)) {
        continue;
    }
    $label = $columnsmap[$h] ?? $h;
    $headercells[] = \html_writer::tag('th', $label, ['scope' => 'col']);
}

// Build table rows.
$tablerows = '';
foreach ($pagerows as $row) {
    $cells = '';
    $linkval = ($linkindex !== null && isset($row[$linkindex])) ? $row[$linkindex] : '';
    $courseid = ($courseidindex !== null && isset($row[$courseidindex])) ? (int)$row[$courseidindex] : 0;
    // Append a text fragment to the link so the browser scrolls to and highlights the match.
    $linkedurl = $linkval;
    if (!empty($linkval) && !$search->get('regex') && !empty($highlightneedle)) {
        $linkedurl = $linkval . '#:~:text=' . rawurlencode($highlightneedle);
    }
    foreach ($row as $i => $cell) {
        if (in_array($i, $hiddenindexes)) {
            continue;
        }
        $header = $headers[$i] ?? '';
        if ($header === 'match') {
            // Link first, then matched text with search term highlighted.
            $content = '';
            if (!empty($linkval)) {
                $content .= \html_writer::div(
                    \html_writer::link($linkedurl, $linkval, ['target' => '_blank']),
                    'mb-1 small'
                );
            }
            $escaped = htmlspecialchars($cell, ENT_QUOTES, 'UTF-8');
            if (!empty($highlightneedle)) {
                $escaped = highlight($highlightneedle, $escaped, false, '<mark>', '</mark>');
            }
            $content .= \html_writer::tag(
                'pre',
                $escaped,
                ['class' => 'mb-0 p-1 border rounded',
                    'style' => 'white-space:pre-wrap;max-width:40em;max-height:15em;overflow-y:auto;font-size:0.85em']
            );
            $cell = $content;
        } else if ($header === 'shortname' && !empty($cell) && !empty($courseid)) {
            $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
            $cell = \html_writer::link($courseurl, htmlspecialchars($cell, ENT_QUOTES, 'UTF-8'));
        } else {
            $cell = htmlspecialchars($cell, ENT_QUOTES, 'UTF-8');
        }
        $cells .= \html_writer::tag('td', $cell);
    }
    $tablerows .= \html_writer::tag('tr', $cells);
}

$thead = \html_writer::tag('thead', \html_writer::tag('tr', implode('', $headercells)));
$tbody = \html_writer::tag('tbody', $tablerows);
$table = \html_writer::tag(
    'table',
    $thead . $tbody,
    ['class' => 'table table-striped table-sm generaltable', 'style' => 'width:auto']
);

echo \html_writer::div($table, 'table-responsive');

echo $OUTPUT->footer();
