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

$string['confirm_delete'] = 'Are you sure you want to delete the search? This will also delete the output.';
$string['copyoptions'] = 'Copy search options';
$string['errorcolumntypenotsupported'] = 'Column type is not supported.';
$string['errorfilenotfound'] = 'File not found.';
$string['errorinvalidfile'] = 'The file is not valid.';
$string['errorinvalidparam'] = 'Invalid parameter.';
$string['errormissingfields'] = 'The following fields are missing: {$a}';
$string['errorregexnotsupported'] = 'Regular expression searches are not supported by this database.';
$string['errorreplacetextnotsupported'] = 'Replace all text is not supported by this database.';
$string['errorsearchmethod'] = 'Please choose one of the search methods: plain text or regular expression.';
$string['excludedtables'] = 'Several tables that don\'t support replacements are not searched. These include configuration, log, events, and session tables.';
$string['field_actions'] = 'Actions';
$string['field_duration'] = 'Duration';
$string['field_id'] = 'ID';
$string['field_matches'] = 'Found';
$string['field_name'] = 'Name';
$string['field_options'] = 'Options';
$string['field_output'] = 'Output';
$string['field_prematch'] = 'Prematch filter';
$string['field_progress'] = 'Progress';
$string['field_regex'] = 'Use regex';
$string['field_search'] = 'Search';
$string['field_skipcolumns'] = 'Skip columns';
$string['field_skiptables'] = 'Skip tables';
$string['field_summary'] = 'Summary mode';
$string['field_tables'] = 'Tables';
$string['field_timestart'] = 'Start';
$string['field_timeend'] = 'End';
$string['field_userid'] = 'User';
$string['field_name_help'] = 'Optional name to identify the search. Will also be used as filename.';
$string['field_tables_help'] = 'Tables and columns to search. Separate multiple tables/columns with a comma. If not specified, search all tables and columns. Example: user,assign_submission:submission';
$string['field_skiptables_help'] = 'Tables to skip. Separate multiple tables with a comma.';
$string['field_skipcolumns_help'] = 'Columns to skip. Separate multiple columns with a comma.';
$string['field_summary_help'] = 'Summary mode only outputs column/table where the text is found.';
$string['newsearch'] = 'New search';
$string['searchdeleted'] = 'The selected search was deleted.';
$string['searchpagename'] = 'Search';
$string['searchpageheader'] = 'Search for text stored in the DB';
$string['searchqueued'] = 'Your search has been queued as an adhoc task.';
$string['searchcopy'] = 'Search options have been copied from a previous search. This will be treated as a new search.';
$string['settings:excludetables'] = 'Exclude tables';
$string['settings:excludetables_help'] = 'Custom list of tables that will always be excluded from searches. Each table should be on a new line.';
$string['settings:includetables'] = 'Include tables';
$string['settings:includetables_help'] = 'When set all searches will be restricted to only the specified tables and those defined in search options. Each table should be on a new line, and can include a column by formatting it as <code>tablename:columnname</code>.';
$string['pluginname'] = 'Advanced DB search and replace';
$string['privacy:metadata'] = 'The plugin does not store any personal data.';
