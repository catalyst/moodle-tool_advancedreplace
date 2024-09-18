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

namespace tool_advancedreplace;

use core\check\performance\debugging;
use core\exception\moodle_exception;

/**
 * Helper class to search and replace text throughout the whole database.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /** @var string ALL_COLUMNS Flag to indicate we search all columns in a table **/
    const ALL_COLUMNS = 'all columns';

    /**
     * Get columns to search for in a table.
     *
     * @param string $table The table to search.
     * @param string $column The column to search.
     * @return array The columns to search.
     */
    public static function get_columns(string $table, string $column = ''): array {
        global $DB;
        $columns = $DB->get_columns($table);

        // Make sure the table has id field.
        $hasid = false;
        foreach ($columns as $col) {
            if ($col->name == 'id') {
                $hasid = true;
                break;
            }
        }

        // Do not search if the table does not have id field.
        if (!$hasid) {
            return [];
        }

        if ($column !== self::ALL_COLUMNS) {
            // Only search the specified column.
            $columns = array_filter($columns, function($col) use ($column) {
                return $col->name == $column;
            });
        }

        return $columns;
    }

    /**
     * Find course field in the table.
     *
     * @param string $table The table to search.
     * @return string The course field name.
     */
    public static function find_course_field(string $table): string {
        global $DB;

        // Potential course field names.
        $coursefields = ['course', 'courseid'];

        $columns = $DB->get_columns($table);
        $coursefield = '';

        foreach ($columns as $column) {
            if (in_array($column->name, $coursefields)) {
                $coursefield = $column->name;
                break;
            }
        }

        return $coursefield;
    }

    /**
     * Perform a plain text search on a table and column.
     *
     * @param string $search The text to search for.
     * @param string $table The table to search.
     * @param string $column The column to search.
     * @param int $limit The maximum number of results to return.
     * @return array The results of the search.
     */
    private static function plain_text_search(string $search, string $table,
                                              string $column = self::ALL_COLUMNS, $limit = 0): array {
        global $DB;

        $results = [];

        $columns = self::get_columns($table, $column);

        // Potential course field in the table.
        $coursefield = self::find_course_field($table);

        // Table alias.
        $tablealias = 't';

        foreach ($columns as $column) {
            $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);

            $searchsql = $DB->sql_like("$tablealias." . $columnname, '?', false);
            $searchparam = '%'.$DB->sql_like_escape($search).'%';

            if (!empty($coursefield)) {
                $sql = "SELECT $tablealias.id,
                               $tablealias.$columnname,
                               $tablealias.$coursefield as courseid,
                               c.idnumber as courseidnumber
                          FROM {".$table."} t
                     LEFT JOIN {course} c ON c.id = t.$coursefield
                         WHERE $searchsql";
            } else {
                $sql = "SELECT id, $columnname FROM {".$table."} $tablealias WHERE $searchsql";
            }

            if ($column->meta_type === 'X' || $column->meta_type === 'C') {
                $records = $DB->get_records_sql($sql, [$searchparam], 0, $limit);
                if ($records) {
                    $results[$table][$column->name] = $records;
                }
            }
        }

        return $results;
    }

    /**
     * Perform a regular expression search on a table and column.
     * This function is only called if the database supports regular expression searches.
     *
     * @param string $search The regular expression to search for.
     * @param string $table The table to search.
     * @param string $column The column to search.
     * @param int $limit The maximum number of results to return.
     * @return array
     */
    private static function regular_expression_search(string $search, string $table,
                                                      string $column = self::ALL_COLUMNS, int $limit = 0): array {
        global $DB;

        // Check if the database supports regular expression searches.
        if (!$DB->sql_regex_supported()) {
            throw new moodle_exception(get_string('errorregexnotsupported', 'tool_advancedreplace'));
        }

        $results = [];

        $columns = self::get_columns($table, $column);

        // Find Potential course field in the table.
        $coursefield = self::find_course_field($table);

        // Table alias.
        $tablealias = 't';

        foreach ($columns as $column) {
            $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);

            $select = "$tablealias." . $columnname . ' ' . $DB->sql_regex() . ' :pattern ';
            $params = ['pattern' => $search];

            if ($column->meta_type === 'X' || $column->meta_type === 'C') {

                if (!empty($coursefield)) {
                    $sql = "SELECT $tablealias.id,
                                   $tablealias.$columnname,
                                   $tablealias.$coursefield as courseid,
                                   c.idnumber as courseidnumber
                              FROM {".$table."} $tablealias
                         LEFT JOIN {course} c ON c.id = $tablealias.$coursefield
                             WHERE $select";
                } else {
                    $sql = "SELECT id, $columnname FROM {".$table."} $tablealias WHERE $select";
                }

                $records = $DB->get_records_sql($sql, $params, 0, $limit);

                if ($records) {
                    $results[$table][$column->name] = $records;
                }
            }
        }

        return $results;
    }

    /**
     * Perform a search on a table and column.
     *
     * @param string $search The text to search for.
     * @param bool $regex Whether to use regular expression search.
     * @param string $tables A comma separated list of tables and columns to search.
     * @param int $limit The maximum number of results to return.
     * @return array
     */
    public static function search(string $search, bool $regex = false, string $tables = '', int $limit = 0): array {
        global $DB;

        // Build a list of tables and columns to search.
        $tablelist = explode(',', $tables);
        $searchlist = [];
        foreach ($tablelist as $table) {
            $tableandcols = explode(':', $table);
            $tablename = $tableandcols[0];
            $columnname = $tableandcols[1] ?? '';

            // Check if the table already exists in the list.
            if (array_key_exists($tablename, $searchlist)) {
                // Skip if the table has already been flagged to search all columns.
                if (in_array(self::ALL_COLUMNS, $searchlist[$tablename])) {
                    continue;
                }

                // Skip if the column already exists in the list for that table.
                if (!in_array($columnname, $searchlist[$tablename])) {
                    continue;
                }
            }

            // Add the table to the list.
            if ($columnname == '') {
                // If the column is not specified, search all columns in the table.
                $searchlist[$tablename][] = self::ALL_COLUMNS;
            } else {
                // Add the column to the list.
                $searchlist[$tablename][] = $columnname;
            }
        }

        // If no tables are specified, search all tables and columns.
        if (empty($tables)) {
            $tables = $DB->get_tables();
            // Mark all columns in each table to be searched.
            foreach ($tables as $table) {
                $searchlist[$table] = [self::ALL_COLUMNS];
            }
        }

        // Perform the search for each table and column.
        $results = [];
        foreach ($searchlist as $table => $columns) {
            foreach ($columns as $column) {
                // Perform the search on this column.
                if ($regex) {
                    $results = array_merge($results, self::regular_expression_search($search, $table, $column, $limit));
                } else {
                    $results = array_merge($results, self::plain_text_search($search, $table, $column, $limit));
                }
            }
        }

        return $results;
    }
}
