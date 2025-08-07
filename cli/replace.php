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
--type=db                     Type of replace database or in files. Default = 'db'
-h, --help                    Print out this help.

Example:
\$ sudo -u www-data /usr/bin/php admin/tool/advancedreplace/cli/replace.php --input=/tmp/result.csv
";

list($options, $unrecognized) = cli_get_params(
    [
        'input'        => null,
        'type'         => 'db',
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

$type = $options['type'] ?? 'db';

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
$progress = new progress_bar();
$progress->create();
helper::handle_replace_csv($data, $progress, $type);
exit(0);
