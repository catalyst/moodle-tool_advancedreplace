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
 * Replace strings using uploaded CSV file.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_advancedreplace\helper;

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/lib/csvlib.class.php');
$help =
    "Replace strings using uploaded CSV file..

Options:
--input=FILE                  Required. Input CSV file produced by find.php in detail mode.
-h, --help                    Print out this help.

Example:
\$ sudo -u www-data /usr/bin/php admin/tool/advancedreplace/cli/replace.php --input=/tmp/result.csv
";

list($options, $unrecognized) = cli_get_params(
    [
        'input'        => null,
        'help'         => false,
    ],
    [
        'h' => 'help',
    ]
);
core_php_time_limit::raise();

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

// Ensure that we have required parameters.
if ($options['help'] || empty($options['input'])) {
    echo $help;
    exit(0);
}

try {
    $file = validate_param($options['input'], PARAM_PATH);
} catch (invalid_parameter_exception $e) {
    cli_error(get_string('errorinvalidparam', 'tool_advancedreplace'));
}

if (!file_exists($file)) {
    cli_error(get_string('errorfilenotfound', 'tool_advancedreplace'));
}

// Open the file for reading.
$fp = fopen($file, 'r');
$data = fread($fp, filesize($file));
fclose($fp);

// Load the CSV content.
$iid = csv_import_reader::get_new_iid('tool_advancedreplace');
$csvimport = new csv_import_reader($iid, 'tool_advancedreplace');
$contentcount = $csvimport->load_csv_content($data, 'utf-8', 'comma');

if ($contentcount === false) {
    cli_error(get_string('errorinvalidfile', 'tool_advancedreplace'));
}

// Read the header.
$header = $csvimport->get_columns();
if (empty($header)) {
    cli_error(get_string('errorinvalidfile', 'tool_advancedreplace'));
}

// Check if all required columns are present, and show which ones are missing.
$requiredcolumns = ['table', 'column', 'id', 'match', 'replace'];
$missingcolumns = array_diff($requiredcolumns, $header);

if (!empty($missingcolumns)) {
    cli_error(get_string('errormissingfields', 'tool_advancedreplace', implode(', ', $missingcolumns)));
}

// Column indexes.
$tableindex = array_search('table', $header);
$columnindex = array_search('column', $header);
$idindex = array_search('id', $header);
$matchindex = array_search('match', $header);
$replaceindex = array_search('replace', $header);

// Progress bar.
$progress = new progress_bar();
$progress->create();

// Read the data and replace the strings.
$csvimport->init();
$rowcount = 0;
$rowskip = 0;
while ($record = $csvimport->next()) {
    if (empty($record[$replaceindex])) {
        // Skip if 'replace' is empty.
        $rowskip++;
    } else {
        // Replace the string.
        helper::replace_text_in_a_record($record[$tableindex], $record[$columnindex],
            $record[$matchindex], $record[$replaceindex], $record[$idindex]);
    }

    // Update the progress bar.
    $rowcount++;
    $progress->update_full(100 * $rowcount / $contentcount, "Processed $rowcount records. Skipped $rowskip records.");
}

// Show progress.
$progress->update_full('100', "Processed $rowcount records. Skipped $rowskip records.");

$csvimport->cleanup();
$csvimport->close();

exit(0);
