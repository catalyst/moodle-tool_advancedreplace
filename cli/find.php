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
    "Search text throughout the whole database.

Options:
--search=STRING                  Required if --regex-match is not specified. String to search for.
--regex-match=STRING             Required if --search is not specified. Use regular expression to match the search string.
--output=FILE                    Required. .Output file. If not specified, output to stdout.
--tables=tablename:columnname    Tables and columns to search. Separate multiple tables/columns with a comma.
                                 If not specified, search all tables and columns.
                                 If specify table only, search all columns in the table.
                                 Example:
                                    --tables=user:username,user:email
                                    --tables=user,assign_submission:submission
                                    --tables=user,assign_submission
--summary                        Summary mode, only shows column/table where the text is found.
                                 If not specified, run in detail mode, which shows the full text where the search string is found.
-h, --help                       Print out this help.

Example:
\$ sudo -u www-data /usr/bin/php admin/tool/advancedreplace/cli/find.php --search=thelostsoul --output=/tmp/result.csv
\$ sudo -u www-data /usr/bin/php admin/tool/advancedreplace/cli/find.php --regex-match=thelostsoul\\d+ --output=/tmp/result.csv
";

list($options, $unrecognized) = cli_get_params(
    [
        'search'  => null,
        'regex-match'  => null,
        'output'  => null,
        'tables' => '',
        'summary' => false,
        'help'    => false,
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
    $tables = validate_param($options['tables'], PARAM_RAW);
    $output = validate_param($options['output'], PARAM_RAW);
} catch (invalid_parameter_exception $e) {
    cli_error(get_string('invalidcharacter', 'tool_advancedreplace'));
}

// Start output.
$fp = fopen($output, 'w');

// Show header.
if (!$options['summary']) {
    fputcsv($fp, ['Table', 'Column', 'courseid', 'idnumber', 'ID', 'Match']);
} else {
    fputcsv($fp, ['Table', 'Column']);
}

// Perform the search.
$searchlist = helper::build_searching_list($tables, );

// Output the result for each table.
foreach ($searchlist as $table => $columns) {
    foreach ($columns as $column) {
        // Show the table and column being searched.
        $colname = $column->name;
        echo "Searching in table: $table, column: $colname\n";

        // Perform the search.
        if (!empty($options['regex-match'])) {
            helper::regular_expression_search($search, $table, $column, $options['summary'], $fp);
        } else {
            helper::plain_text_search($search, $table, $column, $options['summary'], $fp);
        }
    }
}

fclose($fp);
exit(0);
