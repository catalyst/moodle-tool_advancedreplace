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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

use core\exception\moodle_exception;
use core_text;
use database_column_info;

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
     * @param array $searchingcolumns The columns to search.
     * @param array $skiptables The tables to skip.
     * @param array $skipcolumns The columns to skip.
     * @param string $searchstring The string to search for.
     * @return array The columns to search.
     */
    private static function get_columns(string $table, array $searchingcolumns = [],
                                       array $skiptables = [], array $skipcolumns = [], string $searchstring = ''): array {
        global $DB;

        // Skip tables that are in the skip list.
        if (in_array($table, $skiptables)) {
            return [];
        }

        // Get the columns in the table.
        $columns = $DB->get_columns($table);

        // Make sure the table has id field.
        // There could be some custom tables that do not have id field.
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

        // Skip columns that are in the skip list.
        $columns = array_filter($columns, function($col) use ($skipcolumns) {
            return !in_array($col->name, $skipcolumns);
        });

        // Only search the specified columns.
        foreach ($searchingcolumns as $column) {
            if ($column !== self::ALL_COLUMNS) {
                $columns = array_filter($columns, function($col) use ($column) {
                    return $col->name == $column;
                });
            }
        }

        // Check if we need to skip some columns.
        $columns = array_filter($columns, function($col) use ($table) {
            return db_should_replace($table, $col->name);
        });

        // Only search columns that are of type text or char.
        $columns = array_filter($columns, function($col) {
            return $col->meta_type === 'X' || $col->meta_type === 'C';
        });

        // Skip columns which has 'format' in the name.
        $columns = array_filter($columns, function($col) {
            return strpos($col->name, 'format') === false;
        });

        // Exclude columns that has max length less than the search string.
        if (!empty($searchstring)) {
            // Strip special characters from the search string.
            $searchstring = preg_replace('/[^a-zA-Z0-9]/', '', $searchstring);
            $columns = array_filter($columns, function($col) use ($searchstring) {
                $col->max_length >= strlen($searchstring);
            });
        }

        return $columns;
    }

    /**
     * Build searching list
     *
     * @param string $tables A comma separated list of tables and columns to search.
     * @param string $skiptables A comma separated list of tables to skip.
     * @param string $skipcolumns A comma separated list of columns to skip.
     * @param string $searchstring The string to search for, used to exclude columns having max length less than this.
     * @param array $tablerowcounts Estimated table row counts, used to estimate the total number of data entires.
     *
     * @return array the estimated total number of data entries to search and the actual columns to search.
     */
    public static function build_searching_list(string $tables = '', string $skiptables = '', string $skipcolumns = '',
                                                string $searchstring = '', array $tablerowcounts = []): array {
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
                if (in_array($columnname, $searchlist[$tablename])) {
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

        // Skip tables and columns.
        $skiptables = explode(',', $skiptables);
        $skipcolumns = explode(',', $skipcolumns);

        // Return the list of tables and actual columns to search.
        $count = 0;
        $actualsearchlist = [];
        foreach ($searchlist as $table => $columns) {
            $actualcolumns = self::get_columns($table, $columns, $skiptables, $skipcolumns, $searchstring);
            sort($actualcolumns);
            $count += count($actualcolumns) * ($tablerowcounts[$table] ?? 1);
            if (!empty($actualcolumns)) {
                $actualsearchlist[$table] = $actualcolumns;
            }
        }
        ksort($actualsearchlist);
        return [$count, $actualsearchlist];
    }

    /**
     * Estimate row counts for all tables
     *
     * @return array of table row counts with table name as key.
     */
    public static function estimate_table_rows(): array {
        global $CFG, $DB;

        if ($DB->get_dbfamily() === 'mysql') {
            $sql = "SELECT table_name, table_rows
                      FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                       AND table_type = 'BASE TABLE'
                       AND table_name LIKE :prefix";
        } else if ($DB->get_dbfamily() === 'postgres') {
            $sql = "SELECT relname AS table_name, GREATEST(reltuples::BIGINT, 0) AS table_rows
                      FROM pg_class
                     WHERE relkind = 'r'
                       AND relnamespace IN (SELECT oid FROM pg_namespace WHERE nspname = current_schema())
                       AND relname LIKE :prefix";
        } else {
            // Other databases are not currently supported, so use columns as estimate instead of rows.
            return [];
        }

        $params = ['prefix' => $CFG->prefix . '%'];
        $records = $DB->get_records_sql($sql, $params);

        $tablerows = [];
        foreach ($records as $record) {
            $tablename = str_replace($CFG->prefix, '', $record->table_name);
            $tablerows[$tablename] = $record->table_rows;
        }

        return $tablerows;
    }

    /**
     * Find course field in the table.
     *
     * @param string $table The table to search.
     * @return string The course field name.
     */
    private static function find_course_field(string $table): string {
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
     * @param database_column_info $column The column to search.
     * @param bool $summary Whether to return a summary of the search.
     * @param null $stream The resource to write the results to. If null, the results are returned.
     * @return array The results of the search.
     */
    public static function plain_text_search(string $search, string $table,
                                             database_column_info $column, bool $summary = false,
                                             $stream = null): array {
        global $DB;

        $results = [];

        // Potential course field in the table.
        $coursefield = self::find_course_field($table);

        // Build query.
        $tablealias = 't';
        $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);
        $searchsql = $DB->sql_like("$tablealias." . $columnname, '?', false);
        $searchparam = '%'.$DB->sql_like_escape($search).'%';

        if (!empty($coursefield)) {
            $sql = "SELECT $tablealias.id,
                           $tablealias.$columnname,
                           $tablealias.$coursefield as courseid,
                           c.shortname as courseshortname
                      FROM {".$table."} t
                 LEFT JOIN {course} c ON c.id = t.$coursefield
                     WHERE $searchsql";
        } else {
            $sql = "SELECT id, $columnname FROM {".$table."} $tablealias WHERE $searchsql";
        }

        if ($column->meta_type === 'X' || $column->meta_type === 'C') {
            $limit = $summary ? 1 : 0;
            $records = $DB->get_recordset_sql($sql, [$searchparam], 0, $limit);
            if ($records->valid()) {
                if (!empty($stream)) {
                    if ($summary) {
                        fputcsv($stream, [
                            $table,
                            $column->name,
                        ]);
                        $results['count'] = 1;

                        // Return empty array to skip the rest of the function.
                        return $results;
                    }

                    $count = 0;
                    foreach ($records as $record) {
                        fputcsv($stream, [
                            $table,
                            $column->name,
                            $record->courseid ?? '',
                            $record->courseshortname ?? '',
                            $record->id,
                            $record->$columnname,
                            '',
                        ]);
                        $count++;
                    }
                    $results['count'] = $count;
                } else {
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
     * @param database_column_info $column The column to search.
     * @param bool $summary Whether to return a summary of the search.
     * @param null $stream The resource to write the results to. If null, the results are returned.
     * @return array
     */
    public static function regular_expression_search(string $search, string $table,
                                                     database_column_info $column, bool $summary = false,
                                                     $stream = null): array {
        global $DB;

        // Check if the database supports regular expression searches.
        if (!$DB->sql_regex_supported()) {
            throw new moodle_exception(get_string('errorregexnotsupported', 'tool_advancedreplace'));
        }

        // Find Potential course field in the table.
        $coursefield = self::find_course_field($table);

        // Build query.
        $tablealias = 't';
        $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);
        $select = "$tablealias." . $columnname . ' ' . $DB->sql_regex() . ' :pattern ';
        $params = ['pattern' => $search];

        $results = [];
        if ($column->meta_type === 'X' || $column->meta_type === 'C') {
            if (!empty($coursefield)) {
                $sql = "SELECT $tablealias.id,
                               $tablealias.$columnname,
                               $tablealias.$coursefield as courseid,
                               c.shortname as courseshortname
                          FROM {".$table."} $tablealias
                     LEFT JOIN {course} c ON c.id = $tablealias.$coursefield
                         WHERE $select";
            } else {
                $sql = "SELECT id, $columnname FROM {".$table."} $tablealias WHERE $select";
            }

            $limit = $summary ? 1 : 0;
            $records = $DB->get_recordset_sql($sql, $params, 0, $limit);

            if ($records->valid()) {
                if (!empty($stream)) {
                    if ($summary) {
                        fputcsv($stream, [
                            $table,
                            $column->name,
                        ]);
                        $results['count'] = 1;

                        // Return empty array to skip the rest of the function.
                        return $results;
                    }

                    $count = 0;
                    foreach ($records as $record) {
                        $data = $record->$columnname;
                        // Replace "/" with "\/", as it is used as delimiters.
                        $pattern = str_replace('/', '\\/', $search);

                        // Perform the regular expression search.
                        preg_match_all( "/" . $pattern . "/", $data, $matches);

                        if (!empty($matches[0])) {
                            // Show the result foreach match.
                            foreach ($matches[0] as $match) {
                                fputcsv($stream, [
                                    $table,
                                    $column->name,
                                    $record->courseid ?? '',
                                    $record->courseshortname ?? '',
                                    $record->id,
                                    $match,
                                    '',
                                ]);
                                $count++;
                            }
                        }
                    }
                    $results['count'] = $count;
                } else {
                    $results[$table][$column->name] = $records;
                }
            }
        }
        return $results;
    }

    /**
     * Get column info from column name.
     *
     * @param string $table The table name.
     * @param string $columnname The column name.
     *
     * @return database_column_info|null The column info.
     */
    private static function get_column_info(string $table, string $columnname): ?database_column_info {
        global $DB;

        $columns = $DB->get_columns($table);
        foreach ($columns as $col) {
            if ($col->name == $columnname) {
                return $col;
            }
        }
        return null;
    }

    /**
     * Searches the DB using a persistent record.
     *
     * @param \tool_advancedreplace\search $record
     * @param string $output path
     * @return void
     */
    public static function search_db(\tool_advancedreplace\search $record, string $output = ''): void {
        $id = $record->get('id');
        $search = $record->get('search');
        $regex = $record->get('regex');
        $tables = $record->get('tables');
        $skiptables = $record->get('skiptables');
        $skipcolumns = $record->get('skipcolumns');
        $summary = $record->get('summary');
        $origin = $record->get('origin');

        $filename = \tool_advancedreplace\search::get_filename($record->to_record());
        // Create temp output directory.
        if (!$output) {
            $dir = make_request_directory();
            $output = $dir . '/' . $filename;
        }

        // Start output.
        $fp = fopen($output, 'w');

        // Show header.
        if (!$summary) {
            fputcsv($fp, ['table', 'column', 'courseid', 'shortname', 'id', 'match', 'replace']);
        } else {
            fputcsv($fp, ['table', 'column']);
        }

        // Perform the search.
        $record->set('timestart', time());
        $rowcounts = self::estimate_table_rows();
        [$totalrows, $searchlist] = self::build_searching_list($tables, $skiptables, $skipcolumns, '', $rowcounts);

        // Don't update progress directly for web requests as they are processed as adhoc tasks.
        if ($origin !== 'web') {
            $progress = new \progress_bar();
            $progress->create();
        }

        // Output the result for each table.
        $rowcount = 0;
        $matches = 0;
        $update = new \StdClass();
        $update->time = time();
        $update->percent = 0;
        foreach ($searchlist as $table => $columns) {
            foreach ($columns as $column) {
                // Show the table and column being searched.
                if (isset($progress)) {
                    $colname = $column->name;
                    $progress->update($rowcount, $totalrows, "Searching in $table:$colname");
                }

                // Perform the search.
                if (!empty($regex)) {
                    $results = self::regular_expression_search($search, $table, $column, $summary, $fp);
                } else {
                    $results = self::plain_text_search($search, $table, $column, $summary, $fp);
                }
                $matches += $results['count'] ?? 0;
                $rowcount += $rowcounts[$table] ?? 1;

                // Only update record progress every 10 seconds or 5 percent.
                $time = time();
                $percent = round(100 * $rowcount / $totalrows, 2);
                if ($time > $update->time + 10 || $percent > $update->percent + 5) {
                    $record->set('progress', $percent);
                    $record->set('matches', $matches);
                    $record->save();
                    $update->time = $time;
                    $update->percent = 0;
                }
            }
        }

        // Mark as finished.
        if (isset($progress)) {
            $progress->update_full(100, "Finished searching into $output");
        }

        $record->set('timeend', time());
        $record->set('progress', 100);
        $record->set('matches', $matches);
        $record->save();

        fclose($fp);

        // Save as pluginfile.
        if (!empty($matches)) {
            $fs = get_file_storage();
            $fileinfo = [
                'contextid' => \context_system::instance()->id,
                'component' => 'tool_advancedreplace',
                'filearea'  => 'search',
                'itemid'    => $id,
                'filepath'  => '/',
                'filename'  => $filename,
            ];
            $fs->create_file_from_pathname($fileinfo, $output);
        }
    }

    /**
     * Replace all text in a table and column.
     *
     * @param string $table The table to search.
     * @param string $columnname The column to search.
     * @param string $search The text to search for.
     * @param string $replace The text to replace with.
     * @param int $id The id of the record to restrict the search.
     */
    public static function replace_text_in_a_record(string $table, string $columnname,
                                                    string $search, string $replace, int $id) {

        $column = self::get_column_info($table, $columnname);
        self::replace_all_text($table, $column, $search, $replace, ' AND id = ?', [$id]);
    }

    /**
     * A clone of the core function replace_all_text.
     * We have optional id parameter to restrict the search.
     *
     * @since Moodle 2.6.1
     * @param string $table name of the table
     * @param database_column_info $column
     * @param string $search text to search for
     * @param string $replace text to replace with
     * @param string $wheresql additional where clause
     * @param array $whereparams parameters for the where clause
     */
    private static function replace_all_text($table, database_column_info $column, string $search, string $replace,
                                            string $wheresql = '', array $whereparams = []) {
        global $DB;

        if (!$DB->replace_all_text_supported()) {
            throw new moodle_exception(get_string('errorreplacetextnotsupported', 'tool_advancedreplace'));
        }

        // Enclose the column name by the proper quotes if it's a reserved word.
        $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);

        $searchsql = $DB->sql_like($columnname, '?');
        $searchparam = '%'.$DB->sql_like_escape($search).'%';

        // Additional where clause.
        $searchsql .= $wheresql;
        $params = array_merge([$search, $replace, $searchparam], $whereparams);

        switch ($column->meta_type) {
            case 'C':
                if (core_text::strlen($search) < core_text::strlen($replace)) {
                    $colsize = $column->max_length;
                    $sql = "UPDATE {".$table."}
                               SET $columnname = " . $DB->sql_substr("REPLACE(" . $columnname . ", ?, ?)", 1, $colsize) . "
                             WHERE $searchsql";
                    break;
                }
                // Otherwise, do not break and use the same query as in the 'X' case.
            case 'X':
                $sql = "UPDATE {".$table."}
                           SET $columnname = REPLACE($columnname, ?, ?)
                         WHERE $searchsql";
                break;
            default:
                throw new moodle_exception(get_string('errorcolumntypenotsupported', 'tool_advancedreplace'));
        }
        $DB->execute($sql, $params);
    }
}
