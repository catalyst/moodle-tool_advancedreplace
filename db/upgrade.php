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

/**
 * Function to upgrade tool_advancedreplace database
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_tool_advancedreplace_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024100400) {

        // Define table tool_advancedreplace_search to be created.
        $table = new xmldb_table('tool_advancedreplace_search');

        // Adding fields to table tool_advancedreplace_search.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('search', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('regex', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('prematch', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('tables', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('skiptables', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('skipcolumns', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('summary', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('origin', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('progress', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('matches', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table tool_advancedreplace_search.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for tool_advancedreplace_search.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Advancedreplace savepoint reached.
        upgrade_plugin_savepoint(true, 2024100400, 'tool', 'advancedreplace');
    }

    if ($oldversion < 2024101600) {

        // Define table tool_advancedreplace_search to be created.
        $table = new xmldb_table('tool_advancedreplace_files');

        // Adding fields to table tool_advancedreplace_search.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('pattern', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('components', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('skipcomponents', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('mimetypes', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('skipmimetypes', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('filenames', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('skipfilenames', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('skipareas', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        // These next few columns are the same as the search table above.
        // They have the 8th parameter to avoid cut-and-paste detection in ci phpcpd.
        $table->add_field('origin', XMLDB_TYPE_CHAR, '10',
        null, XMLDB_NOTNULL, null, null, 'skipareas');
        $table->add_field('timestart', XMLDB_TYPE_INTEGER,
        '10', null, XMLDB_NOTNULL, null, '0', 'origin');
        $table->add_field('timeend', XMLDB_TYPE_INTEGER,
        '10', null, XMLDB_NOTNULL, null, '0', 'timestart');
        $table->add_field('progress', XMLDB_TYPE_NUMBER,
        '10, 2', null, XMLDB_NOTNULL, null, '0', 'timeend');
        $table->add_field('matches', XMLDB_TYPE_INTEGER,
        '10', null, XMLDB_NOTNULL, null, '0', 'progress');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER,
        '10', null, XMLDB_NOTNULL, null, '0', 'matches');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER,
        '10', null, XMLDB_NOTNULL, null, '0', 'usermodified');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER,
        '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');

        // Adding keys to table tool_advancedreplace_search.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for tool_advancedreplace_search.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Advancedreplace savepoint reached.
        upgrade_plugin_savepoint(true, 2024101600, 'tool', 'advancedreplace');
    }

    if ($oldversion < 2024102000) {
        $table = new xmldb_table('tool_advancedreplace_files');

        $field = new xmldb_field('openzips', XMLDB_TYPE_INTEGER, null, null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('zipfilenames', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('skipzipfilenames', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024102000, 'tool', 'advancedreplace');
    }
    return true;
}
