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

use tool_advancedreplace\file_search;

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/adminlib.php');

$help =
    "Search for text in moodle files.

Options:
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
--mimetypes=mimetype,mimetype   Mimetypes to be searched. Separate multipler type with commas.
                                If empty, all mimetypes will be considred.
--skip-mimetypes=mimetype,mimetype Mimetypes to be skipped.
--filenames=filename            Cooma-separated list of file names to be searched.
--skip-filenames=filename       Comma-separated list of file names to be omitted from the search.
--skip-areas=areaname        Areas to skip. Separate multiple areas with a comma.
--openzips=
--zip-filenames=regex
--skip-zip-filenames=regex
--zip-mimetypes=regex
--skip-zip-mimetypes=regex
                                 Example:
                                    --skip-areas=draft,export
-h, --help                       Print out this help.

Example:
\$ php find_in_files.php --regex-match=thelostsoul\\d+ --output=/tmp/result.csv
\$ php find_in_files.php --regex-match='https:(.*).com' --output=/tmp/result.csv --mimetype=application/zip.h5p
";

list($options, $unrecognized) = cli_get_params(
    [
        'regex-match'   => null,
        'output'        => null,
        'components'    => '',
        'skip-components'   => '',
        'mimetypes'     => '',
        'skip-mimetypes' => '',
        'filenames'     => '',
        'skip-filenames' => '',
        'skip-areas'    => '',
        'open-zips'     => '1',
        'zip-filenames' => '',
        'skip-zip-filenames' => '',
        'zip-mimetypes' => '',
        'skip-zip-mimetypes' => '',
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
        || empty($options['regex-match'])
        || empty($options['output'])
    ) {
    echo $help;
    exit(0);
}

try {
    $data = new stdClass;
    $data->pattern = validate_param($options['regex-match'], PARAM_RAW);
    $data->components = validate_param($options['components'], PARAM_RAW);
    $data->skipcomponents = validate_param($options['skip-components'], PARAM_RAW);
    $data->mimetypes = validate_param($options['mimetypes'], PARAM_RAW);
    $data->skipmimetypes = validate_param($options['skip-mimetypes'], PARAM_RAW);
    $data->filenames = validate_param($options['filenames'], PARAM_RAW);
    $data->skipfilenames = validate_param($options['skip-filenames'], PARAM_RAW);
    $data->skipareas = validate_param($options['skip-areas'], PARAM_RAW);
    $data->openzips = validate_param($options['open-zips'], PARAM_RAW);
    $data->zipfilenames = validate_param($options['zip-filenames'], PARAM_RAW);
    $data->skipzipfilenames = validate_param($options['skip-zip-filenames'], PARAM_RAW);
    $data->zipmimetypes = validate_param($options['zip-mimetypes'], PARAM_RAW);
    $data->skipzipmimetypes = validate_param($options['skip-zip-mimetypes'], PARAM_RAW);
    $output = validate_param($options['output'], PARAM_RAW);
} catch (invalid_parameter_exception $e) {
    cli_error(get_string('errorinvalidparam', 'tool_advancedreplace'));
}

// Set other fields.
$data->userid = $USER->id;
$data->name = ucfirst(pathinfo($output, PATHINFO_FILENAME));
$data->origin = 'cli';

// Run search.
$files = new \tool_advancedreplace\files(0, $data);
$files->create();
$searcher = new file_search($files, $output);
exit(0);
