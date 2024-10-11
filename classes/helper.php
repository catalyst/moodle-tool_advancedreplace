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
use tool_advancedreplace\search;

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

    /** @var array SKIP_TABLES Additional tables that should always be skipped. Most are already handled by core. **/
    const SKIP_TABLES = [
        search::TABLE,
        'search_simpledb_index',
    ];

    /**
     * Get columns to search for in a table.
     *
     * @param search $search persistent record
     * @param string $table The table to search.
     * @param array $searchingcolumns The columns to search.
     * @return array The columns to search.
     */
    private static function get_columns(search $search, string $table, array $searchingcolumns = []): array {
        global $DB;

        // Skip tables that are in the skip list.
        $skiptables = $search->get_all_skiptables();
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
        $skipcolumns = $search->get_all_skipcolumns();
        $columns = array_filter($columns, function($col) use ($skipcolumns) {
            return !in_array($col->name, $skipcolumns);
        });

        // Only search the specified columns.
        if (!in_array(self::ALL_COLUMNS, $searchingcolumns)) {
            $columns = array_filter($columns, function($col) use ($searchingcolumns) {
                return in_array($col->name, $searchingcolumns);
            });
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
        $minlenth = $search->get_min_search_length();
        if (!empty($minlenth)) {
            $columns = array_filter($columns, function($col) use ($minlenth) {
                return $col->max_length < 0 || $col->max_length >= $minlenth;
            });
        }

        return $columns;
    }

    /**
     * Build searching list
     *
     * @param search $search persistent record
     * @param array $tablerowcounts Estimated table row counts, used to estimate the total number of data entires.
     *
     * @return array the estimated total number of data entries to search and the actual columns to search.
     */
    public static function build_searching_list(search $search, array $tablerowcounts = []): array {
        global $DB;

        // Build a list of tables and columns to search.
        $searchlist = [];
        $tables = $search->get_all_searchtables();
        foreach ($tables as $table) {
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
        // Return the list of tables and actual columns to search.
        $count = 0;
        $actualsearchlist = [];
        foreach ($searchlist as $table => $columns) {
            $actualcolumns = self::get_columns($search, $table, $columns);
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

        if ($table == 'course') {
            return 'id';
        }

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
     * @param search $search persistent record.
     * @param string $table The table to search.
     * @param database_column_info $column The column to search.
     * @param null $stream The resource to write the results to. If null, the results are returned.
     * @return array The results of the search.
     */
    public static function plain_text_search(search $search, string $table, database_column_info $column, $stream = null): array {
        global $DB;

        $results = [];
        $linkstring = '';
        $linkfunction = self::find_link_function($table, $column->name);
        $summary = $search->get('summary');

        // Potential course field in the table.
        $coursefield = self::find_course_field($table);

        // Build query.
        $tablealias = 't';
        $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);
        $searchsql = $DB->sql_like("$tablealias." . $columnname, '?', false);
        $searchparam = '%'.$DB->sql_like_escape($search->get('search')).'%';

        if (!empty($coursefield)) {
            $sql = "SELECT $tablealias.id,
                           $tablealias.$columnname,
                           $tablealias.$coursefield as courseid,
                           c.shortname as courseshortname
                      FROM {".$table."} $tablealias
                 LEFT JOIN {course} c ON c.id = $tablealias.$coursefield
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
                        if ( ! empty($linkfunction)) {
                            $linkstring = $linkfunction($record);
                        }
                        fputcsv($stream, [
                            $table,
                            $column->name,
                            $record->courseid ?? '',
                            $record->courseshortname ?? '',
                            $record->id,
                            $record->$columnname,
                            '',
                            $linkstring,
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
     * @param search $search persistent record.
     * @param string $table The table to search.
     * @param database_column_info $column The column to search.
     * @param null $stream The resource to write the results to. If null, the results are returned.
     * @return array
     */
    public static function regex_search(search $search, string $table, database_column_info $column, $stream = null): array {
        global $DB;

        // Check if the database supports regular expression searches.
        if (!$DB->sql_regex_supported()) {
            throw new moodle_exception(get_string('errorregexnotsupported', 'tool_advancedreplace'));
        }

        $summary = $search->get('summary');

        // Find Potential course field in the table.
        $coursefield = self::find_course_field($table);

        // Build query.
        $tablealias = 't';
        $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);
        $wheresql = [];
        $params = [];
        if ($prematch = $search->get('prematch')) {
            $wheresql[] = $DB->sql_like("$tablealias." . $columnname, ':prematch', false);
            $params['prematch'] = '%'.$DB->sql_like_escape($prematch).'%';
        }
        $wheresql[] = "$tablealias." . $columnname . ' ' . $DB->sql_regex() . ' :pattern ';
        $params['pattern'] = $search->get('search');
        $searchsql = implode(' AND ', $wheresql);

        $linkstring = '';
        $linkfunction = self::find_link_function($table, $column->name);
        $results = [];
        if ($column->meta_type === 'X' || $column->meta_type === 'C') {
            if (!empty($coursefield)) {
                $sql = "SELECT $tablealias.id,
                               $tablealias.$columnname,
                               $tablealias.$coursefield as courseid,
                               c.shortname as courseshortname
                          FROM {".$table."} $tablealias
                     LEFT JOIN {course} c ON c.id = $tablealias.$coursefield
                         WHERE $searchsql";
            } else {
                $sql = "SELECT id, $columnname FROM {".$table."} $tablealias WHERE $searchsql";
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
                        if ( ! empty($linkfunction)) {
                            $linkstring = $linkfunction($record);
                        }
                        // Replace "/" with "\/", as it is used as delimiters.
                        $pattern = str_replace('/', '\\/', $search->get('search'));

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
                                    $linkstring,
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
     * @param search $search persistent record
     * @param string $output path
     * @return void
     */
    public static function search_db(search $search, string $output = ''): void {
        // Create temp output directory.
        $filename = search::get_filename($search->to_record());
        if (!$output) {
            $dir = make_request_directory();
            $output = $dir . '/' . $filename;
        }

        // Grab log settings, 0 is a valid setting so set false to a sensible default..
        $logduration = get_config('tool_advancedreplace', 'logduration');
        $logduration = $logduration === false ? 30 : $logduration;
        $logoutput = [];

        // Start output.
        $fp = fopen($output, 'w');

        // Show header.
        if (!$search->get('summary')) {
            fputcsv($fp, ['table', 'column', 'courseid', 'shortname', 'id', 'match', 'replace', 'link']);
        } else {
            fputcsv($fp, ['table', 'column']);
        }

        // Perform the search.
        $search->set('timestart', time());
        $rowcounts = self::estimate_table_rows();
        [$totalrows, $searchlist] = self::build_searching_list($search, $rowcounts);

        // Don't update progress directly for web requests as they are processed as adhoc tasks.
        if ($search->get('origin') === 'cli') {
            $progress = new \progress_bar();
            $progress->create();
        }

        // Output the result for each table.
        $rowcount = 0;
        $matches = 0;
        $update = new \stdClass();
        $update->time = time();
        $update->percent = 0;
        foreach ($searchlist as $table => $columns) {
            foreach ($columns as $column) {
                $colname = $column->name;
                $colstart = time();

                // Show the table and column being searched.
                if (isset($progress)) {
                    $progress->update($rowcount, $totalrows, "Searching in $table:$colname");
                }

                // Perform the search.
                if (!empty($search->get('regex'))) {
                    $results = self::regex_search($search, $table, $column, $fp);
                } else {
                    $results = self::plain_text_search($search, $table, $column, $fp);
                }

                $colend = time();
                $colduration = $colend - $colstart;
                $colmatches = $results['count'] ?? 0;
                $matches += $colmatches;
                $rowcount += $rowcounts[$table] ?? 1;

                // Add logging info.
                if (!empty($colmatches) || $colduration >= $logduration) {
                    $logoutput[] = (object) [
                        'table' => $table,
                        'column' => $colname,
                        'rows' => $rowcounts[$table],
                        'matches' => $colmatches,
                        'time' => $colduration,
                    ];
                }

                // Only update search progress every 10 seconds or 5 percent.
                $percent = round(100 * $rowcount / $totalrows, 2);
                if ($colend > $update->time + 10 || $percent > $update->percent + 5) {
                    $search->set('progress', $percent);
                    $search->set('matches', $matches);
                    $search->save();
                    $update->time = $colend;
                    $update->percent = 0;
                }
            }
        }

        // Mark as finished.
        if (isset($progress)) {
            $progress->update_full(100, "Finished searching into $output");
        }

        $search->set('timeend', time());
        $search->set('progress', 100);
        $search->set('matches', $matches);
        $search->save();

        fclose($fp);

        // Display log output.
        if (!empty($logoutput)) {
            $format = "%-32s %-32s %10s %10s %10s";
            mtrace(sprintf($format, "table", "column", "records", "matches", "time"));
            foreach ($logoutput as $log) {
                mtrace(sprintf($format, $log->table, $log->column, $log->rows, $log->matches, $log->time));
            }
        }

        // Save as pluginfile.
        if (!empty($matches)) {
            $fs = get_file_storage();
            $fileinfo = [
                'contextid' => \context_system::instance()->id,
                'component' => 'tool_advancedreplace',
                'filearea'  => 'search',
                'itemid'    => $search->get('id'),
                'filepath'  => '/',
                'filename'  => $filename,
            ];
            $fs->create_file_from_pathname($fileinfo, $output);
        }
    }

    /**
     * Return a closure that can be used to create the link from the record.
     *
     * @param string $table   The name of the table being searched.
     * @param string $column  The name of the column being searched.
     * @return \Closure    $urlstring = closure($record).
     */
    public static function find_link_function($table, $column) {
        global $DB;

        $linktypes = [
            'course' => function($record) {
                $url = new \moodle_url('/course/view.php', ['id' => $record->id]);
                return $url->out();
            },
        ];

        static $linkmappings = [
            'course:fullname' => 'course',
            'course:shortname' => 'course',
            'course:summary' => 'course',
        ];

        static $modulefunctions = null;
        if ($modulefunctions === null) {
            // First time: establish an index of module_name => module_id.
            $modules = $DB->get_records('modules');
            $modulefunctions = [];
            foreach ($modules as $module) {
                $modulefunctions[$module->name] = function($record) use ($module) {
                    global $DB;
                    $coursemodule = $DB->get_record('course_modules', ['module' => $module->id, 'instance' => $record->id], 'id');
                    if (empty($coursemodule)) {
                        return null;
                    } else {
                        $url = new \moodle_url("/mod/{$module->name}/view.php", ['id' => $coursemodule->id]);
                        return $url->out();
                    }
                };
            }
        }

        // Consider links from hand-coded table:column combinations.
        if (! empty($linkmappings["{$table}:{$column}"])) {
            $type = $linkmappings["{$table}:{$column}"];
            if (! empty($linktypes[$type]) ) {
                $linkfunction = $linktypes[$type];
                return $linkfunction;
            }
        }

        // Consider links based on the table name being a module.
        if (isset($modulefunctions[$table])) {
            return $modulefunctions[$table];
        }

        return null;
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
