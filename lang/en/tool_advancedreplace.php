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
 * Strings for component 'tool_advancedreplace', language 'en', branch 'MOODLE_22_STABLE'
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['cleancache'] = 'Some replacements may not take effect until after a cache purge, would you like to do that now?';
$string['cleancachebutton'] = 'Purge all caches';
$string['confirm_delete'] = 'Are you sure you want to delete the search? This will also delete the output.';
$string['copyoptions'] = 'Copy search options';
$string['errorcolumntypenotsupported'] = 'Column type is not supported.';
$string['errorfilenotfound'] = 'File not found.';
$string['errorinvalidfile'] = 'The file is not valid.';
$string['errorinvalidparam'] = 'Invalid parameter.';
$string['errormissingfields'] = 'The following fields are missing: {$a}';
$string['errorregexnotsupported'] = 'Regular expression searches are not supported by this database.';
$string['errorreplacingcontentzip'] = 'Could not load file contents from zip';
$string['errorreplacingfile'] = 'New file was not created';
$string['errorreplacingfilenotfound'] = 'File not found';
$string['errorreplacingfilenotfoundzip'] = 'File not found in zip';
$string['errorreplacingopenzip'] = 'Could not open zip file';
$string['errorreplacingstring'] = 'Search string not found in record';
$string['errorreplacingstringnorecord'] = 'Record does not exist in database';
$string['errorsearchmethod'] = 'Please choose one of the search methods: plain text or regular expression.';
$string['eta'] = 'ETA: {$a}';
$string['excludedtables'] = 'Several tables that don\'t support replacements are not searched. These include configuration, log, events, and session tables.';
$string['field_actions'] = 'Actions';
$string['field_column'] = 'Column';
$string['field_component'] = 'Component';
$string['field_components'] = 'Components';
$string['field_components_help'] = 'Restrict the search to these components. Separate components with commas. eg core_h5p,mod_hvp,mod_assign';
$string['field_duration'] = 'Duration';
$string['field_error'] = 'Error';
$string['field_filearea'] = 'Filearea';
$string['field_filename'] = 'Filename';
$string['field_filenames'] = 'Filenames';
$string['field_filenames_help'] = 'Restrict the search to these file names. Separate file names with commas. eg chapter1.html,chapter2.html';
$string['field_id'] = 'ID';
$string['field_matches'] = 'Found';
$string['field_mimetypes'] = 'Mimetypes';
$string['field_mimetypes_help'] = 'Restrict the search to these mime types. Separate mime types with commas. eg application/zip.h5p,application/json';
$string['field_name'] = 'Name';
$string['field_name_help'] = 'Optional name to identify the search. Will also be used as filename.';
$string['field_openzips'] = 'Open zips';
$string['field_openzips_help'] = 'Open zip files, and search the sub files within.';
$string['field_options'] = 'Options';
$string['field_output'] = 'Output';
$string['field_pattern'] = 'Regular Expression';
$string['field_pattern_help'] = 'Regular expression to be matched in the files.';
$string['field_prematch'] = 'Prematch filter';
$string['field_prematch_help'] = 'Optional filter to prematch a search before regex. This may help speed up the search, but the performance will depend on the DB engine, the complexity of the regex and table indexes.';
$string['field_progress'] = 'Progress';
$string['field_regex'] = 'Use regex';
$string['field_row'] = 'Row num';
$string['field_search'] = 'Search';
$string['field_shards'] = 'Shards';
$string['field_shards_help'] = 'Split the file search into a number of shards to potentially speed up processing. Each shard will run as a separate adhoc task. It is generally recommended to leave this at 1 unless you have increased memory.';
$string['field_skipareas'] = 'Skip areas';
$string['field_skipareas_help'] = 'Omit these file areas from the search. Separate areas with commas. eg legacy,submission_files';
$string['field_skipcolumns'] = 'Skip columns';
$string['field_skipcolumns_help'] = 'Columns to skip. Separate multiple columns with a comma.';
$string['field_skipcomponents'] = 'Skip components';
$string['field_skipcomponents_help'] = 'Omit these components from the search. Separate components with commas. eg backup,calendar,mod_zoom';
$string['field_skipfilenames'] = 'Skip file names';
$string['field_skipfilenames_help'] = 'Omit these file names from the search. Separate file names with commas. eg quotes.html,welcome.html';
$string['field_skipmimetypes'] = 'Skip mime types';
$string['field_skipmimetypes_help'] = 'Omit these mime types from the search. Separate mime types with commas. eg image/jpeg,image/png';
$string['field_skiptables'] = 'Skip tables';
$string['field_skiptables_help'] = 'Tables to skip. Separate multiple tables with a comma.';
$string['field_skipzipfilenames'] = 'Skip Zip filenames';
$string['field_skipzipfilenames_help'] = 'Regular expression to reject subfiles within a zip.';
$string['field_summary'] = 'Summary mode';
$string['field_summary_help'] = 'Summary mode only outputs column/table where the text is found.';
$string['field_table'] = 'Table';
$string['field_tables'] = 'Tables';
$string['field_tables_help'] = 'Tables and columns to search. Separate multiple tables/columns with a comma. If not specified, search all tables and columns. Example format: <code>user,assign_submission:submission</code>';
$string['field_timeend'] = 'End';
$string['field_timestart'] = 'Start';
$string['field_userid'] = 'User';
$string['field_zipfilenames'] = 'Zip filenames';

$string['field_zipfilenames_help'] = 'Regular expression to match subfiles within a zip.';

$string['filespageheader'] = 'Search for text in Moodle files';
$string['filespagename'] = 'Search in Files';
$string['lastupdated'] = 'Last updated {$a} ago';
$string['newreplace'] = 'New replace';
$string['newsearch'] = 'New search';
$string['pluginname'] = 'Advanced Search and Replace';
$string['privacy:metadata:tool_advancedreplace_files'] = 'Advanced files search';
$string['privacy:metadata:tool_advancedreplace_files:userid'] = 'User who created the files search';
$string['privacy:metadata:tool_advancedreplace_files:usermodified'] = 'User who modified the files search';
$string['privacy:metadata:tool_advancedreplace_search'] = 'Advanced db search';
$string['privacy:metadata:tool_advancedreplace_search:userid'] = 'User who created the db search';
$string['privacy:metadata:tool_advancedreplace_search:usermodified'] = 'User who modified the db search';
$string['replace'] = 'Replace';
$string['replace_warning'] = 'Replace via UI not enabled. To enable, please set in config.php:<br><code>{$a->file}</code><br> or via CLI command: <br><code>{$a->command}</code>';
$string['replacecheckdb'] = 'Are you sure you want to replace strings in the Database using the uploaded csv file?';
$string['replacecheckfiles'] = 'Are you sure you want to replace strings in Files using the uploaded csv file?';
$string['replacefilespageheader'] = 'Replace text stored in files';
$string['replacefilespagename'] = 'Replace strings in Files';
$string['replacepageheader'] = 'Replace text stored in the DB';
$string['replacepagename'] = 'Replace strings in the Database';
$string['searchcopy'] = 'Search options have been copied from a previous search. This will be treated as a new search.';
$string['searchdeleted'] = 'The selected search was deleted.';
$string['searchpageheader'] = 'Search for text stored in the DB';
$string['searchpagename'] = 'Search in the Database';
$string['searchqueued'] = 'Your search has been queued as an adhoc task.';
$string['searchreportback'] = '← Back to search list';
$string['searchreportempty'] = 'No results found in this search.';
$string['searchreportinprogress'] = 'This search is still in progress, no results are available yet.';
$string['searchreportpending'] = 'This search has not started yet, no results are available.';
$string['searchreportplaintext'] = 'Plain text';
$string['searchreporttitle'] = 'Search results: {$a}';
$string['searchreportview'] = 'View report';
$string['selectfile'] = 'Select file';
$string['settings:excludetables'] = 'Exclude tables';
$string['settings:excludetables_help'] = 'Custom list of tables that will always be excluded from searches. Each table should be on a new line.';
$string['settings:includetables'] = 'Include tables';
$string['settings:includetables_help'] = 'When set all searches will be restricted to only the specified tables and those defined in search options. Each table should be on a new line, and can include a column by formatting it as <code>tablename:columnname</code>.';
$string['settings:logduration'] = 'Search logging';
$string['settings:logduration_help'] = 'Display log information for columns with no matches that take longer than the specified duration.';
$string['strftimedatetimemonthshort'] = '%d %b %Y, %I:%M %p';
