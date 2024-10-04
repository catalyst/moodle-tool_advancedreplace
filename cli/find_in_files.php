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
 * Search strings throughout all texts in the whole database.
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

$help =
    "Search for text in moodle files.

Options:
--search=STRING                  Required if --regex-match is not specified. String to search for.
--regex-match=STRING             Required if --search is not specified. Use regular expression to match the search string.
--output=FILE                    Required output file. If not specified, output to stdout.
--components=componentname:areaname    Components and areas to search. Separate multiple components/areas with a comma.
                                 If not specified, search all tables and columns.
                                 If specify table only, search all columns in the table.
                                 Example:
                                    --components=core_h5p:content
                                    --components=core_h5p,assign_submission:submission
--skip-components=componentname   Components to skip. Separate multiple components with a comma.
                                 Example:
                                    --skip-components=core_h5p,assign_submission
--skip-areas=areaname        Areas to skip. Separate multiple areas with a comma.
                                 Example:
                                    --skip-areas=draft,export
--summary                        Summary mode, only shows column/table where the text is found.
                                 If not specified, run in detail mode, which shows the full text where the search string is found.
-h, --help                       Print out this help.

Example:
\$ sudo -u www-data /usr/bin/php admin/tool/advancedreplace/cli/find_in_files.php --search=thelostsoul --output=/tmp/result.csv
\$ sudo -u www-data /usr/bin/php admin/tool/advancedreplace/cli/find_in_files.php --regex-match=thelostsoul\\d+ --output=/tmp/result.csv
";

list($options, $unrecognized) = cli_get_params(
    [
        'search'        => null,
        'regex-match'   => null,
        'output'        => null,
        'components'    => '',
        'skip-components'   => '',
        'skip-areas'    => '',
        'summary'       => false,
        'help'          => false,
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
if ($options['help']
        || (!is_string($options['search']) && empty($options['regex-match']))
        || empty($options['output'])
    ) {
    echo $help;
    exit(0);
}

// Ensure we only have one search method.
if (!empty($options['regex-match']) && !empty($options['search'])) {
    cli_error(get_string('errorsearchmethod', 'tool_advancedreplace'));
}

try {
    if (!empty($options['search'])) {
        $search = validate_param($options['search'], PARAM_RAW);
    } else {
        $search = validate_param($options['regex-match'], PARAM_RAW);
    }
    $output = validate_param($options['output'], PARAM_RAW);
    $summary = validate_param($options['summary'], PARAM_RAW);
    $components = validate_param($options['components'], PARAM_RAW);
    $skipcomponents = validate_param($options['skip-components'], PARAM_RAW);
    $skipareas = validate_param($options['skip-areas'], PARAM_RAW);

} catch (invalid_parameter_exception $e) {
    cli_error(get_string('errorinvalidparam', 'tool_advancedreplace'));
}

// Start output.
$stream = fopen($output, 'w');

$columnheaders=['contextid', 'component', 'filearea', 'itemid', 'filepath', 'filename'];
if ( ! $options['summary']) {
    $columnheaders[] = 'offset';
    $columnheaders[] = 'match';
    $columnheaders[] = 'replace';
}
fputcsv($stream, $columnheaders);

//$progress = new progress_bar();
//$progress->create();

$pattern = '/' . $search . '/i';
global $DB;

[$whereclause, $params] = helper::make_whereclause_for_components($components, $skipcomponents, $skipareas);
print " WHERE: $whereclause \n";
print_r ($params);

$files = $DB->get_recordset_select('files', $whereclause, $params, 'component, filearea, contextid, itemid' );
foreach ($files as $file_record) {
    helper::search_file($file_record, $pattern, $stream);
}
$files->close();

//$progress->update_full(100, "Finished searching into $output");
fclose($stream);
exit(0);
