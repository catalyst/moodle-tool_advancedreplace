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
require_once($CFG->dirroot . '/question/engine/bank.php');


use core\exception\moodle_exception;
use core_text;
use csv_import_reader;
use database_column_info;
use progress_bar;
use tool_advancedreplace\db_search;

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
        db_search::TABLE,
        'search_simpledb_index',
    ];

    /**
     * Get columns to search for in a table.
     *
     * @param db_search $search persistent record
     * @param string $table The table to search.
     * @param array $searchingcolumns The columns to search.
     * @return array The columns to search.
     */
    private static function get_columns(db_search $search, string $table, array $searchingcolumns = []): array {
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
        $columns = array_filter($columns, function ($col) use ($skipcolumns) {
            return !in_array($col->name, $skipcolumns);
        });

        // Only search the specified columns.
        if (!in_array(self::ALL_COLUMNS, $searchingcolumns)) {
            $columns = array_filter($columns, function ($col) use ($searchingcolumns) {
                return in_array($col->name, $searchingcolumns);
            });
        }

        // Check if we need to skip some columns.
        $columns = array_filter($columns, function ($col) use ($table) {
            return db_should_replace($table, $col->name);
        });

        // Only search columns that are of type text or char.
        $columns = array_filter($columns, function ($col) {
            return $col->meta_type === 'X' || $col->meta_type === 'C';
        });

        // Skip columns which has 'format' in the name.
        $columns = array_filter($columns, function ($col) {
            return strpos($col->name, 'format') === false;
        });

        // Exclude columns that has max length less than the search string.
        $minlenth = $search->get_min_search_length();
        if (!empty($minlenth)) {
            $columns = array_filter($columns, function ($col) use ($minlenth) {
                return $col->max_length < 0 || $col->max_length >= $minlenth;
            });
        }

        return $columns;
    }

    /**
     * Build searching list
     *
     * @param db_search $search persistent record
     * @param array $tablerowcounts Estimated table row counts, used to estimate the total number of data entires.
     *
     * @return array the estimated total number of data entries to search and the actual columns to search.
     */
    public static function build_searching_list(db_search $search, array $tablerowcounts = []): array {
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
            $count += count($actualcolumns) * (($tablerowcounts[$table] ?? 1) ?: 1);
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
     * Builds sql for a search on a table and column.
     *
     * @param db_search $search persistent record.
     * @param string $table The table to search.
     * @param database_column_info $column The column to search.
     * @return array [$sql, $params]
     */
    private static function build_search_query(db_search $search, string $table, database_column_info $column): array {
        global $DB;

        // Check if column meta type can be searched.
        if ($column->meta_type !== 'X' && $column->meta_type !== 'C') {
            return ['', []];
        }

        $tablealias = 't';
        $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);
        $coursefield = self::find_course_field($table);
        $wheresql = [];
        $params = [];

        static $supportedtablemappings = [
            'course_sections' => ['t.id as id, section', ''],
            'book_chapters' => ['t.bookid as moduleid, t.id as id,', 'LEFT JOIN {book} t2 ON t.bookid = t2.id'],
            'forum_posts' => ['t.id as id,', 'LEFT JOIN {forum_discussions} t2 ON t.discussion = t2.id
                 LEFT JOIN {forum} f ON t2.forum = f.id'],
            'lesson_pages' => ['t.lessonid as moduleid, t.id as id,', 'LEFT JOIN {lesson} t2 ON t.lessonid = t2.id'],
        ];

        $regex = $search->get('regex');
        $prematch = $search->get('prematch');

        // Add base search using like.
        if (!$regex || !empty($prematch)) {
            $searchtext = !$regex ? $search->get('search') : $prematch;
            $wheresql[] = $DB->sql_like("$tablealias." . $columnname, ':search', false);
            $params['search'] = '%' . $DB->sql_like_escape($searchtext) . '%';
        }

        // Add regex search.
        if ($regex) {
            $wheresql[] = "$tablealias." . $columnname . ' ' . $DB->sql_regex() . ' :pattern ';
            $params['pattern'] = $search->get('search');
        }

        // Build query.
        $wheresql = implode(' AND ', $wheresql);
        if (!empty($coursefield)) {
            $sql = "SELECT $tablealias.id,
                           $tablealias.$columnname,
                           $tablealias.$coursefield as courseid,
                           c.shortname as courseshortname
                      FROM {" . $table . "} $tablealias
                 LEFT JOIN {course} c ON c.id = $tablealias.$coursefield
                     WHERE $wheresql";
        } else if (isset($supportedtablemappings[$table])) {
            $sql = "SELECT {$supportedtablemappings[$table][0]}
                           $tablealias.$columnname,
                           c.id as courseid,
                           c.shortname as courseshortname
                      FROM {" . $table . "} $tablealias
                 {$supportedtablemappings[$table][1]}
                 LEFT JOIN {course} c ON c.id = t2.course
                     WHERE $wheresql";
        } else {
            $sql = "SELECT id, $columnname FROM {" . $table . "} $tablealias WHERE $wheresql";
        }

        return [$sql, $params];
    }

    /**
     * Perform a search on a table and column.
     *
     * @param db_search $search persistent record.
     * @param string $table The table to search.
     * @param database_column_info $column The column to search.
     * @param resource|null $stream The resource to write the results to. If null, the results are returned.
     * @return array
     */
    public static function search_column(db_search $search, string $table, database_column_info $column, $stream = null): array {
        global $DB;

        $results = [];
        $regex = $search->get('regex');
        $summary = $search->get('summary');

        // If using a regex search make sure the database supports them.
        if ($regex && !$DB->sql_regex_supported()) {
            throw new \moodle_exception(get_string('errorregexnotsupported', 'tool_advancedreplace'));
        }

        // Build search.
        [$sql, $params] = self::build_search_query($search, $table, $column);
        if (empty($sql)) {
            return $results;
        }

        // Get records.
        $limit = $summary ? 1 : 0;
        $records = $DB->get_recordset_sql($sql, $params, 0, $limit);
        if (!$records->valid()) {
            return $results;
        }

        // If no stream, return the records.
        if (empty($stream)) {
            $results[$table][$column->name] = $records;
            return $results;
        }

        // Output summary search.
        if ($summary) {
            fputcsv($stream, [
                $table,
                $column->name,
            ]);
            $results['count'] = 1;
            return $results;
        }

        // Output full search.
        $count = 0;
        $linkstring = '';
        $linkfunction = self::find_link_function($table, $column->name);
        foreach ($records as $record) {
            if (!empty($linkfunction)) {
                if ($table == 'question') {
                    // If the question belongs to a course, set the fields to generate a proper link.
                    $question = \question_bank::load_question($record->id);
                    if (
                        ($category = $DB->get_record('question_categories', ['id' => $question->category])) &&
                        ($context = \context::instance_by_id($category->contextid, IGNORE_MISSING)) &&
                        $context->contextlevel === CONTEXT_COURSE
                    ) {
                        $record->courseid = $context->instanceid;
                        $record->courseshortname = $DB->get_field('course', 'shortname', ['id' => $context->instanceid]) ?: '';
                    }
                }
                $linkstring = $linkfunction($record);
            }

            if (!$regex) {
                fputcsv($stream, [
                    $table,
                    $column->name,
                    $record->courseid ?? '',
                    $record->courseshortname ?? '',
                    $record->id,
                    $record->{$column->name},
                    '',
                    $linkstring,
                ]);
                $count++;
            } else {
                // Process records to show result for each match.
                $data = $record->{$column->name};

                // Replace "/" with "\/", as it is used as delimiters.
                $pattern = str_replace('/', '\\/', $search->get('search'));

                // Perform the regular expression search.
                preg_match_all("/" . $pattern . "/", $data, $matches);

                if (!empty($matches[0])) {
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
        }
        $results['count'] = $count;
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
     * @param db_search $search persistent record
     * @param string $output path
     * @return void
     */
    public static function search_db(db_search $search, string $output = ''): void {
        $processing = true;

        // Create a shared temp output directory.
        if (!$output) {
            $tempfile = true;
            $dir = make_temp_directory('tool_advancedreplace');
            $output = $dir . '/' . $search->get_temp_filename();
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
        $rowcounts = self::estimate_table_rows();
        [$totalrows, $searchlist] = self::build_searching_list($search, $rowcounts);
        $search->mark_started($totalrows);

        // Output the result for each table.
        $rowcount = 0;
        $matches = 0;
        $update = new \stdClass();
        $update->time = time();
        $update->percent = 0;

        $tablec = 0;
        $columnc = 0;

        foreach ($searchlist as $table => $columns) {
            $tablec++;
            foreach ($columns as $column) {
                $colname = $column->name;
                $colstart = time();

                mtrace("Searching in $table:$colname");
                $columnc++;

                // Show the table and column being searched.
                $search->update_progress_bar("Searching in $table:$colname");

                // Perform the search.
                $results = self::search_column($search, $table, $column, $fp);

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

                // Update status. If this returns false, the record is gone so stop searching.
                if (!$processing = $search->update_status($rowcount, $matches)) {
                    break 2;
                }
            }
        }

        mtrace("Searched in $columnc columns across $tablec tables");
        fclose($fp);

        // Display log output.
        if (!empty($logoutput)) {
            $format = "%-32s %-32s %10s %10s %10s";
            mtrace(sprintf($format, "table", "column", "records", "matches", "time"));
            foreach ($logoutput as $log) {
                mtrace(sprintf($format, $log->table, $log->column, $log->rows, $log->matches, $log->time));
            }
        }

        if ($processing) {
            $search->mark_finished($matches, $output);
            $search->save_pluginfile($output);
        }
        // Remove temp file.
        if (isset($tempfile) && file_exists($output)) {
            @unlink($output);
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
            'course' => function ($record) {
                $url = new \moodle_url('/course/view.php', ['id' => $record->id]);
                return $url->out();
            },
            'course_section' => function ($record) {
                global $DB;
                $coursesections = $DB->get_record('course_sections', ['id' => $record->id], 'section');
                $url = new \moodle_url('/course/view.php#section-' . $coursesections->section, ['id' => $record->courseid]);
                return $url->out();
            },
            'question' => function ($record) {
                $url = new \moodle_url(
                    '/question/bank/previewquestion/preview.php',
                    ['id' => $record->id, 'courseid' => $record->courseid ?? SITEID]
                );
                return $url->out(false);
            },
            'forum_post' => function ($record) {
                $url = new \moodle_url('/mod/forum/discuss.php', ['d' => $record->id]);
                return $url->out(false);
            },
        ];

        static $linkmappings = [
            'course:fullname' => 'course',
            'course:shortname' => 'course',
            'course:summary' => 'course',
            'course_sections:name' => 'course_section',
            'course_sections:summary' => 'course_section',
            'question:name' => 'question',
            'question:questiontext' => 'question',
            'forum_posts:subject' => 'forum_post',
            'forum_posts:message' => 'forum_post',
        ];

        static $modulefunctions = null;
        if ($modulefunctions === null) {
            // First time: establish an index of module_name => module_id.
            $modules = $DB->get_records('modules');
            $modulefunctions = [];
            foreach ($modules as $module) {
                $modulefunctions[$module->name] = function ($record) use ($module) {
                    global $DB;
                    $coursemodule = $DB->get_record(
                        'course_modules',
                        ['module' => $module->id, 'instance' => ($record->moduleid ?? $record->id)],
                        'id'
                    );
                    if (empty($coursemodule)) {
                        return null;
                    } else if ($module->name == 'book' && isset($record->moduleid)) {
                            $url = new \moodle_url(
                                "/mod/{$module->name}/view.php",
                                ['id' => $coursemodule->id, 'chapterid' => $record->id]
                            );
                            return $url->out(false);
                    } else if ($module->name == 'lesson'  && isset($record->moduleid)) {
                        $url = new \moodle_url(
                            "/mod/{$module->name}/view.php",
                            ['id' => $coursemodule->id, 'pageid' => $record->id]
                        );
                        return $url->out(false);
                    } else {
                        $url = new \moodle_url("/mod/{$module->name}/view.php", ['id' => $coursemodule->id]);
                        return $url->out();
                    }
                };
            }
        }

        // Consider links from hand-coded table:column combinations.
        if (!empty($linkmappings["{$table}:{$column}"])) {
            $type = $linkmappings["{$table}:{$column}"];
            if (!empty($linktypes[$type])) {
                $linkfunction = $linktypes[$type];
                return $linkfunction;
            }
        }

        static $moduelmappings = [
            'book_chapters' => 'book',
            'lesson_pages' => 'lesson',
        ];

        // Consider links based on the table name being a module.
        if (isset($modulefunctions[$table])) {
            return $modulefunctions[$table];
        } else if (isset($moduelmappings[$table]) && isset($modulefunctions[$moduelmappings[$table]])) {
            return $modulefunctions[$moduelmappings[$table]];
        }

        return null;
    }

    /**
     * Replace all text in a table and column.
     *
     * @param int $rownum Row number in CSV.
     * @param string $table The table to search.
     * @param string $columnname The column to search.
     * @param string $search The text to search for.
     * @param string $replace The text to replace with.
     * @param int $id The id of the record to restrict the search.
     * @param array $rowcounts The count/outcome for each row.
     * @param replace_error_handler $errorhandler
     */
    public static function replace_text_in_a_record(
        int $rownum,
        string $table,
        string $columnname,
        string $search,
        string $replace,
        int $id,
        &$rowcounts,
        replace_error_handler $errorhandler
    ) {
        global $DB;

        $column = self::get_column_info($table, $columnname);

        // Enclose the column name by the proper quotes if it's a reserved word.
        $columnname = $DB->get_manager()->generator->getEncQuoted($column->name);

        $record = $DB->get_record($table, ['id' => $id], $columnname);

        if (!$record) {
            $rowcounts['error']++;
            $errorhandler->add([$rownum, $table, $columnname, $id,
                get_string('errorreplacingstringnorecord', 'tool_advancedreplace')]);
            return;
        }

        $escapedsearchstring = str_replace("\n", "\r\n", $search);

        if (str_contains($record->$columnname, $search)) {
            $newstring = str_replace($search, $replace, $record->$columnname);
            $DB->set_field($table, $columnname, $newstring, ['id' => $id]) ? $rowcounts['success']++ : $rowcounts['error']++;
        } else if (str_contains($record->$columnname, $escapedsearchstring)) {
            $newstring = str_replace($escapedsearchstring, $replace, $record->$columnname);
            $DB->set_field($table, $columnname, $newstring, ['id' => $id]) ? $rowcounts['success']++ : $rowcounts['error']++;
        } else if (str_contains($record->$columnname, $replace)) {
            $rowcounts['replacematch']++;
        } else {
            $rowcounts['error']++;
            $errorhandler->add([$rownum, $table, $columnname, $id,
                get_string('errorreplacingstring', 'tool_advancedreplace')]);
        }
    }

    /**
     * Read the last line of a file.
     * @param string $filename Name of file to be read.
     * @return string $lastline The last line of the file.
     * @return int $linecount The number of lines in the file.
     */
    public static function read_last_line(string $filename) {
        $lastline = '';
        $linecount = 0;
        if (file_exists($filename)) {
            $file = fopen($filename, 'r');
            $linecount = 0;

            while (false != ($buffer = fgets($file))) {
                $linecount++;
                $lastline = $buffer;
            }
            fclose($file);
        }
        return [$lastline, $linecount];
    }

    /**
     * Takes csv data and replaces all matching strings within the DB
     * @param string $data CSV data to be read.
     * @param progress_bar $progress a progress bar.
     * @param string $type type of replace db || files.
     */
    public static function handle_replace_csv(string $data, progress_bar $progress, string $type = 'db') {
        // Load the CSV content.
        $iid = csv_import_reader::get_new_iid('tool_advancedreplace');
        $csvimport = new csv_import_reader($iid, 'tool_advancedreplace');
        $contentcount = $csvimport->load_csv_content($data, 'utf-8', 'comma');

        if ($contentcount === false) {
            if (CLI_SCRIPT) {
                cli_error(get_string('errorinvalidfile', 'tool_advancedreplace'));
            } else {
                throw new \moodle_exception(get_string('errorinvalidfile', 'tool_advancedreplace'));
            }
        }

        // Read the header.
        $header = $csvimport->get_columns();
        if (empty($header)) {
            if (CLI_SCRIPT) {
                cli_error(get_string('errorinvalidfile', 'tool_advancedreplace'));
            } else {
                throw new \moodle_exception(get_string('errorinvalidfile', 'tool_advancedreplace'));
            }
        }

        // Check if all required columns are present, and show which ones are missing.
        if ($type == 'db') {
            $requiredcolumns = ['table', 'column', 'id', 'match', 'replace'];
            // Column indexes.
            $tableindex = array_search('table', $header);
            $columnindex = array_search('column', $header);
            $idindex = array_search('id', $header);
            $matchindex = array_search('match', $header);
            $replaceindex = array_search('replace', $header);
        } else if ($type == 'files') {
            $requiredcolumns = ['contextid', 'component', 'filearea', 'itemid', 'filepath',
                'filename', 'replace', 'match', 'mimetype', 'internal'];
            // Column indexes.
            $contextidindex = array_search('contextid', $header);
            $componentindex = array_search('component', $header);
            $fileareaindex = array_search('filearea', $header);
            $itemidindex = array_search('itemid', $header);
            $filepathindex = array_search('filepath', $header);
            $filenameindex = array_search('filename', $header);
            $matchindex = array_search('match', $header);
            $replaceindex = array_search('replace', $header);
            $mimeindex = array_search('mimetype', $header);
            $internalindex = array_search('internal', $header);
        }

        $missingcolumns = array_diff($requiredcolumns, $header);

        if (!empty($missingcolumns)) {
            if (CLI_SCRIPT) {
                cli_error(get_string('errormissingfields', 'tool_advancedreplace', implode(', ', $missingcolumns)));
            } else {
                throw new \moodle_exception(get_string(
                    'errormissingfields',
                    'tool_advancedreplace',
                    implode(', ', $missingcolumns)
                ));
            }
        }

        // Error handler, which will output a table of errors.
        $errorhandler = new replace_error_handler();
        $errorhandler->set_type($type);

        // Read the data and replace the strings.
        $csvimport->init();
        $rowcounts = [
            'success' => 0,
            'skipped' => 0,
            'error' => 0,
            'replacematch' => 0,
        ];

        // Start at 1 since we've already read the header.
        $rownum = 1;
        while ($record = $csvimport->next()) {
            $rownum++;
            if (empty($record[$replaceindex])) {
                // Skip if 'replace' is empty.
                $rowcounts['skipped']++;
            } else if ($type == 'db') {
                // Replace the string.
                self::replace_text_in_a_record(
                    $rownum,
                    $record[$tableindex],
                    $record[$columnindex],
                    $record[$matchindex],
                    $record[$replaceindex],
                    $record[$idindex],
                    $rowcounts,
                    $errorhandler
                );
            } else if ($type == 'files') {
                $filerecord = [
                    'contextid' => $record[$contextidindex],
                    'component' => $record[$componentindex],
                    'filearea' => $record[$fileareaindex],
                    'itemid' => $record[$itemidindex],
                    'filepath' => $record[$filepathindex],
                    'filename' => $record[$filenameindex],
                    'mimetype' => $record[$mimeindex],
                ];

                self::replace_text_in_file(
                    $rownum,
                    $filerecord,
                    $record[$matchindex],
                    $record[$replaceindex],
                    $record[$internalindex],
                    $rowcounts,
                    $errorhandler
                );
            }
            // Update the progress bar.
            $progress->update_full(
                100 * $rownum / $contentcount,
                $rowcounts['success'] . " Replaced, " . $rowcounts['skipped'] . " Skipped, "
                    . $rowcounts['replacematch'] . " Already replaced, " . $rowcounts['error'] . " Errors."
            );
        }
        $csvimport->cleanup();
        $csvimport->close();
        $errorhandler->finish();
    }

    /**
     * Returns the file content of the replace csv. Required for confirmation.
     * This should mimic handling in moodleform get_file_content()
     * @param int $draftid
     * @return string
     */
    public static function get_replace_csv_content(int $draftid): string {
        global $USER;

        $fs = get_file_storage();
        $context = \context_user::instance($USER->id);
        if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false)) {
            return '';
        }
        $file = reset($files);
        return $file->get_content();
    }

    /**
     * Replace a string in a file stored in Moodle's file storage. Supports both normal files and files inside zip archives.
     *
     * @param int $rownum Row number in CSV.
     * @param array $filerecord File record
     * @param string $match The string to search for in the file's contents.
     * @param string $replace The string to replace the matched string with.
     * @param string $internal The name of the internal file to modify (only used for zip files).
     * @param array $rowcounts The count/outcome for each row.
     * @param replace_error_handler $errorhandler
     */
    public static function replace_text_in_file(
        int $rownum,
        array $filerecord,
        string $match,
        string $replace,
        string $internal,
        array &$rowcounts,
        replace_error_handler $errorhandler
    ) {
        $fs = get_file_storage();
        $file = $fs->get_file(
            $filerecord['contextid'],
            $filerecord['component'],
            $filerecord['filearea'],
            $filerecord['itemid'],
            $filerecord['filepath'],
            $filerecord['filename']
        );

        if (!$file) {
            $rowcounts['error']++;
            $errorhandler->add([$rownum, $filerecord['component'], $filerecord['filearea'], $filerecord['filename'],
                get_string('errorreplacingfilenotfound', 'tool_advancedreplace')]);
            return;
        }

        // Specify tmp filename to avoid unique constraint conflict.
        $filerecord['filename'] = time();

        if ($filerecord['mimetype'] == 'application/zip' || $filerecord['mimetype'] == 'application/zip.h5p') {
            if ($newzip = self::replace_text_in_zip($rownum, $file, $match, $replace, $internal, $rowcounts, $errorhandler)) {
                $newfile = $fs->create_file_from_pathname($filerecord, $newzip);
            } else {
                return;
            }

            unlink($newzip);
        } else {
            $content = $file->get_content();
            $newcontent = str_replace($match, $replace, $content);

            // New and old content are the same, we assume this replace has already been run.
            if ($newcontent == $content) {
                $rowcounts['replacematch']++;
                return;
            }
            $newfile = $fs->create_file_from_string($filerecord, $newcontent);
        }
        if ($newfile) {
            $file->replace_file_with($newfile);
            $newfile->delete();
            $rowcounts['success']++;
        } else {
            $rowcounts['error']++;
            $errorhandler->add([$rownum, $filerecord['component'], $filerecord['filearea'], $file->get_filename(),
                get_string('errorreplacingfile', 'tool_advancedreplace')]);
        }
    }

    /**
     * Extracts a file by name from a zip archive, replaces a string, and updates the zip file.
     *
     * @param int $rownum Row number in CSV.
     * @param \stored_file $zipfile    Name of the file to extract and modify inside the zip.
     * @param string $searchstring  The string to search for in the file's contents.
     * @param string $replacestring The string to replace the search string with.
     * @param string $internalfilename The file name of the internal file to be modified.
     * @param array $rowcounts The count/outcome for each row.
     * @param replace_error_handler $errorhandler
     */
    public static function replace_text_in_zip(
        int $rownum,
        \stored_file $zipfile,
        string $searchstring,
        string $replacestring,
        string $internalfilename,
        array &$rowcounts,
        replace_error_handler $errorhandler
    ) {

        // Create a temporary file path for working with the ZIP file.
        $tempzip = make_request_directory() . '/' . $zipfile->get_filename();
        $zipfile->copy_content_to($tempzip);

        // Open and modify the ZIP file.
        $zip = new \ZipArchive();
        if ($zip->open($tempzip) !== true) {
            $rowcounts['error']++;
            $errorhandler->add([$rownum, $zipfile->get_component(), $zipfile->get_filearea(), $internalfilename,
                get_string('errorreplacingopenzip', 'tool_advancedreplace')]);
            return false;
        }

        // Check if the target file exists in the zip.
        $fileindex = $zip->locateName($internalfilename);
        if ($fileindex === false) {
            $zip->close();
            $rowcounts['error']++;
            $errorhandler->add([$rownum, $zipfile->get_component(), $zipfile->get_filearea(), $internalfilename,
                get_string('errorreplacingfilenotfoundzip', 'tool_advancedreplace')]);
            return false;
        }

        // Extract the target file's content.
        $filecontent = $zip->getFromIndex($fileindex);
        if ($filecontent === false) {
            $zip->close();
            $rowcounts['error']++;
            $errorhandler->add([$rownum, $zipfile->get_component(), $zipfile->get_filearea(), $internalfilename,
                get_string('errorreplacingcontentzip', 'tool_advancedreplace')]);
            return false;
        }

        // Replace the string in the file's contents.
        $modifiedcontents = str_replace($searchstring, $replacestring, $filecontent);

        if ($modifiedcontents == $filecontent) {
            $zip->close();
            $rowcounts['replacematch']++;
            return false;
        }

        // Delete the old file and add the modified file back to the ZIP.
        $zip->deleteName($internalfilename);
        $zip->addFromString($internalfilename, $modifiedcontents);
        $zip->close();

        return $tempzip;
    }
}
