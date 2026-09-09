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

use core\exception\moodle_exception;
use database_column_info;

/**
 * Helper test.
 *
 * @package    tool_advancedreplace
 * @copyright   2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class helper_test extends \advanced_testcase {
    /**
     * Data provider for test_build_searching_list.
     *
     * @return array
     */
    public static function build_searching_list_provider(): array {
        return [
            // Check standard inclusions and exclusions.
            [
                '', '', '', '', [],
                // Should include these tables/columns.
                [
                    'page' => 'content, intro, name',
                    'assign' => 'intro, name',
                    'user' => 'country',
                ],
                // Should not include these tables/columns.
                [
                    'page' => 'id, introformat, timecreated, timemodified, timelimit',
                    'assign' => 'id, introformat, course',
                    'config' => '',
                    'logstore_standard_log' => '',
                    'tool_advancedreplace_search' => '',
                    'search_simpledb_index' => '',
                ],
            ],
            // Tables option.
            [
                'page', '', '', '', [],
                [
                    'page' => 'content, intro',
                ],
                [
                    'assign' => '',
                ],
            ],
            // Tables option with columns.
            [
                'page:content,assign:intro', '', '', '', [],
                [
                    'page' => 'content',
                    'assign' => 'intro',
                ],
                [
                    'page' => 'intro',
                    'assign' => 'name',
                ],
            ],
            // Tables option with columns from same table.
            [
                'page:content,page:intro', '', '', '', [],
                [
                    'page' => 'content, intro',
                ],
                [
                    'page' => 'name',
                ],
            ],
            // Skip tables option.
            [
                '', 'assign', '', '', [],
                [
                    'page' => '',
                ],
                [
                    'assign' => '',
                ],
            ],
            // Skip columns option.
            [
                '', 'assign', 'content', '', [],
                [
                    'page' => '',
                ],
                [
                    'assign' => '',
                    'page:content' => '',
                ],
            ],
            // Searches should automatically skip shorter columns.
            [
                '', '', '', 'searchtext', [],
                // Check that both text and longer char feilds still appear.
                [
                    'page' => 'content, intro',
                ],
                [
                    'user' => 'country',
                ],
            ],
            // Exclude tables config setting.
            [
                '', '', '', '',
                [
                    'excludetables' => 'assign',
                ],
                [
                    'page' => '',
                ],
                [
                    'assign' => '',
                ],
            ],
            // Both exclude tables config setting and skiptables.
            [
                '', 'page', '', '',
                [
                    'excludetables' => 'assign',
                ],
                [],
                [
                    'assign' => '',
                    'page' => '',
                ],
            ],
            // Include tables config setting.
            [
                '', '', '', '',
                [
                    'includetables' => 'assign',
                ],
                [
                    'assign' => '',
                ],
                [
                    'page' => '',
                ],
            ],
            // Include tables config setting with columns.
            [
                '', '', '', '',
                [
                    'includetables' => "page:content\nassign:intro",
                ],
                [
                    'page' => 'content',
                    'assign' => 'intro',
                ],
                [
                    'page' => 'intro',
                    'assign' => 'name',
                ],
            ],
            // Include tables config setting with columns from same table.
            [
                '', '', '', '',
                [
                    'includetables' => "page:content\npage:intro",
                ],
                [
                    'page' => 'content, intro',
                ],
                [
                    'page' => 'name',
                ],
            ],
            // Prioritise search options over include config.
            [
                'page', '', '', '',
                [
                    'includetables' => 'assign',
                ],
                [
                    'page' => '',
                ],
                [
                    'assign' => '',
                ],
            ],
        ];
    }

    /**
     * Test build_searching_list.
     *
     * @dataProvider build_searching_list_provider
     * @covers \tool_advancedreplace\helper::build_searching_list
     *
     * @param string $tables the tables to search
     * @param string $skiptables the tables to skip
     * @param string $skipcolumns the columns to skip
     * @param string $searchstring the search string
     * @param array $config config values that should be used for the test
     * @param array $expectedlist the tables/columns which should be in the result
     * @param array $unexpectedlist the tables/columns which should not be in the result
     *
     * return void
     */
    public function test_build_searching_list(
        string $tables,
        string $skiptables,
        string $skipcolumns,
        string $searchstring,
        array $config,
        array $expectedlist,
        array $unexpectedlist
    ): void {
        $this->resetAfterTest();
        foreach ($config as $name => $value) {
            set_config($name, $value, 'tool_advancedreplace');
        }

        // Create a search.
        $search = new db_search(0, (object) [
            'search' => $searchstring,
            'tables' => $tables,
            'skiptables' => $skiptables,
            'skipcolumns' => $skipcolumns,
            'origin' => 'phpunit',
        ]);
        $search->create();

        [$count, $searchlist] = helper::build_searching_list($search);

        // Columns should be in the result.
        foreach ($expectedlist as $table => $columns) {
            // Make sure the table is in the result.
            $this->assertArrayHasKey($table, $searchlist);

            // Get the name of the columns that we are going to search.
            $searchcolumns = array_map(function ($column) {
                return $column->name;
            }, $searchlist[$table]);

            if (empty($columns)) {
                continue;
            }

            // Each column should be in the search list.
            $columns = explode(',', $columns);
            foreach ($columns as $column) {
                // Get all columns in the table.
                $this->assertContains(trim($column), $searchcolumns);
            }
        }

        // Columns should not be in the result.
        foreach ($unexpectedlist as $table => $columns) {
            if (!empty($columns)) {
                // Specific columns of this table should not be in the result.
                $this->assertArrayHasKey($table, $searchlist);
                $columns = explode(',', $columns);
                foreach ($columns as $column) {
                    $this->assertNotContains(trim($column), $searchlist[$table]);
                }
            } else {
                // The table should not be in the result.
                $this->assertArrayNotHasKey($table, $searchlist);
            }
        }
    }

    /**
     * Plain text search.
     *
     * @covers \tool_advancedreplace\helper::search_column
     */
    public function test_plain_text_search(): void {
        $this->resetAfterTest();

        $searchstring = 'https://example.com.au';

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a page content.
        $this->getDataGenerator()->create_module('page', (object) [
            'course' => $course,
            'content' => 'This is a page content with a link to https://example.com.au',
            'contentformat' => FORMAT_HTML,
        ]);

        // Create an assignment.
        $this->getDataGenerator()->create_module('assign', (object)[
            'course' => $course->id,
            'name' => 'Test!',
            'intro' => 'This is an assignment with a link to https://example.com.au/5678',
            'introformat' => FORMAT_HTML,
        ]);

        // Create a search.
        $search = new db_search(0, (object) [
            'search' => $searchstring,
            'tables' => 'page,assign',
            'origin' => 'phpunit',
        ]);
        $search->create();

        [$count, $searchlist] = helper::build_searching_list($search);
        $result = [];
        foreach ($searchlist as $table => $columns) {
            foreach ($columns as $column) {
                $result = array_merge($result, helper::search_column($search, $table, $column));
            }
        }
        $this->assertNotNull($result['page']['content']);
        $this->assertNotNull($result['assign']['intro']);
    }

    /**
     * Tests regular expression search is not allowed when DB does not support it.
     *
     * @covers \tool_advancedreplace\helper::search_column
     */
    public function test_regex_search_not_supported_by_db(): void {
        global $DB;
        $this->resetAfterTest();

        if ($DB->sql_regex_supported()) {
            $this->markTestSkipped('Regex supported by database');
            return;
        }

        $search = new db_search(0, (object) [
            'search' => 'test',
            'regex' => 1,
            'tables' => 'page,assign',
            'origin' => 'phpunit',
        ]);
        $search->create();
        $column = current($DB->get_columns('page'));

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorregexnotsupported', 'tool_advancedreplace'));
        helper::search_column($search, '1234', $column);
    }

    /**
     * Regular expression search.
     *
     * @covers \tool_advancedreplace\helper::search_column
     */
    public function test_regex_search(): void {
        global $DB;

        if (!$DB->sql_regex_supported()) {
            $this->markTestSkipped('Regex not supported by database');
            return;
        }

        $this->resetAfterTest();

        $searchstring = "https://example.com.au/[0-9]+";

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a page content.
        $this->getDataGenerator()->create_module('page', (object)[
            'course' => $course,
            'content' => 'This is a page content with a link to https://example.com.au/1234',
            'contentformat' => FORMAT_HTML,
        ]);
        // Create an assignment.
        $this->getDataGenerator()->create_module('assign', (object)[
            'course' => $course->id,
            'name' => 'Test!',
            'intro' => 'This is an assignment with a link to https://example.com.au/5678',
            'introformat' => FORMAT_HTML,
        ]);

        // Create a search.
        $search = new db_search(0, (object) [
            'search' => $searchstring,
            'regex' => 1,
            'tables' => 'page,assign',
            'origin' => 'phpunit',
        ]);
        $search->create();

        [$count, $searchlist] = helper::build_searching_list($search);
        $result = [];
        foreach ($searchlist as $table => $columns) {
            foreach ($columns as $column) {
                $result = array_merge($result, helper::search_column($search, $table, $column));
            }
        }

        // Replace "/" with "\/", as it is used as delimiters.
        $searchstring = str_replace('/', '\\/', $searchstring);

        // Add delimiters to the search string.
        $searchstring = '/' . $searchstring . '/';

        // Check if page content matches the search string.
        $pagecontent = $result['page']['content'];
        $this->assertMatchesRegularExpression($searchstring, $pagecontent->current()->content);

        // Check if assignment intro matches the search string.
        $assignintro = $result['assign']['intro'];
        $this->assertMatchesRegularExpression($searchstring, $assignintro->current()->intro);
    }

    /**
     * Search results must still be correct and complete when rows span multiple id-range batch
     * windows (helper::$searchbatchsize), including matches in later windows and windows with no
     * matches at all.
     *
     * @covers \tool_advancedreplace\helper::search_column
     */
    public function test_search_column_across_multiple_batches(): void {
        global $DB;
        $this->resetAfterTest();

        // Use a small batch size so a handful of rows already spans several batch windows,
        // without needing to insert thousands of rows to exercise the batching logic.
        $originalbatchsize = helper::$searchbatchsize;
        helper::$searchbatchsize = 3;

        try {
            $searchstring = 'FINDTHISUNIQUETEXT';

            // Insert more rows than the batch size, with matches spread across the id range,
            // including the first and last rows, so matches fall in several different windows.
            $matchingids = [];
            for ($i = 0; $i < 10; $i++) {
                $ismatch = ($i % 3 === 0);
                $description = $ismatch
                    ? "Category $i contains $searchstring in its description"
                    : "Category $i has no interesting content";
                $id = $DB->insert_record('course_categories', (object) [
                    'name' => 'Batch test category ' . $i,
                    'path' => '/',
                    'description' => $description,
                ]);
                if ($ismatch) {
                    $matchingids[] = $id;
                }
            }

            $search = new db_search(0, (object) [
                'search' => $searchstring,
                'tables' => 'course_categories:description',
                'origin' => 'phpunit',
            ]);
            $search->create();

            $columns = $DB->get_columns('course_categories');
            $column = $columns['description'];

            // Use a stream, as only the streaming code path performs batching.
            $tmpfile = tempnam(sys_get_temp_dir(), 'tool_advancedreplace_test_');
            $fp = fopen($tmpfile, 'w');
            $result = helper::search_column($search, 'course_categories', $column, $fp);
            fclose($fp);

            $rows = array_filter(array_map('str_getcsv', file($tmpfile)));
            unlink($tmpfile);

            // All matches should be found exactly once, regardless of which batch window they fall in.
            $this->assertEquals(count($matchingids), $result['count']);
            $this->assertCount(count($matchingids), $rows);

            $foundids = array_map(fn($row) => (int) $row[4], $rows);
            sort($foundids);
            sort($matchingids);
            $this->assertEquals($matchingids, $foundids);
        } finally {
            helper::$searchbatchsize = $originalbatchsize;
        }
    }

    /**
     * Summary search must still find a match even when the only matching row falls in a later
     * id-range batch window, not just the first one searched.
     *
     * @covers \tool_advancedreplace\helper::search_column
     */
    public function test_search_column_summary_across_multiple_batches(): void {
        global $DB;
        $this->resetAfterTest();

        $originalbatchsize = helper::$searchbatchsize;
        helper::$searchbatchsize = 2;

        try {
            $searchstring = 'FINDTHISUNIQUETEXTSUMMARY';

            // Only the last row matches, so earlier (empty) batch windows must not stop the search early.
            for ($i = 0; $i < 6; $i++) {
                $ismatch = ($i === 5);
                $description = $ismatch
                    ? "Category $i contains $searchstring in its description"
                    : "Category $i has no interesting content";
                $DB->insert_record('course_categories', (object) [
                    'name' => 'Summary batch test category ' . $i,
                    'path' => '/',
                    'description' => $description,
                ]);
            }

            $search = new db_search(0, (object) [
                'search' => $searchstring,
                'tables' => 'course_categories:description',
                'summary' => 1,
                'origin' => 'phpunit',
            ]);
            $search->create();

            $columns = $DB->get_columns('course_categories');
            $column = $columns['description'];

            $tmpfile = tempnam(sys_get_temp_dir(), 'tool_advancedreplace_test_');
            $fp = fopen($tmpfile, 'w');
            $result = helper::search_column($search, 'course_categories', $column, $fp);
            fclose($fp);

            $rows = array_filter(array_map('str_getcsv', file($tmpfile)));
            unlink($tmpfile);

            $this->assertEquals(1, $result['count']);
            $this->assertCount(1, $rows);
            $this->assertEquals(['course_categories', 'description'], $rows[0]);
        } finally {
            helper::$searchbatchsize = $originalbatchsize;
        }
    }

    /**
     * Test for replace_text_in_a_record
     *
     * @covers \tool_advancedreplace\helper::replace_text_in_a_record
     */
    public function test_replace_text_in_a_record(): void {
        $this->resetAfterTest();

        global $DB;

        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        // Create a page content.
        $page = $this->getDataGenerator()->create_module('page', (object)[
            'course' => $course,
            'content' => 'This is a page content with a link to https://example.com.au/1234',
            'contentformat' => FORMAT_HTML,
        ]);
        $rowcounts = [
            'success' => 0,
            'skipped' => 0,
            'error' => 0,
            'replacematch' => 0,
        ];
        $errorhandler = new replace_error_handler();

        // Replace the text in the page content.
        helper::replace_text_in_a_record(
            2,
            'page',
            'content',
            'https://example.com.au/1234',
            'https://example.com.au/5678',
            $page->id,
            $rowcounts,
            $errorhandler
        );

        // Get the updated page content.
        $updatedpage = $DB->get_record('page', ['id' => $page->id]);

        // Check if the text is replaced.
        $this->assertStringContainsString('https://example.com.au/5678', $updatedpage->content);
    }

    /**
     * Test for estimate_table_rows
     *
     * @covers \tool_advancedreplace\helper::estimate_table_rows
     */
    public function test_estimate_table_rows(): void {
        global $DB;

        $supporteddb = ['mysql', 'postgres'];

        if (in_array($DB->get_dbfamily(), $supporteddb)) {
            // Confirm the number of estimates match the number of tables.
            $estimates = helper::estimate_table_rows();
            $this->assertEquals(count($DB->get_tables()), count($estimates));
        } else {
            $this->assertEmpty(helper::estimate_table_rows());
        }
    }

    /**
     * Test a module-based link function.
     *
     * @param string $table - name of the table - same as module name.
     * @param int $id = the id of the module table. This will be the instance of coursemodule table.
     * @return void
     */
    public function find_module($table, $id): void {
        global $DB;
        $linkfunction = helper::find_link_function($table, 'dummy');
        $this->assertInstanceOf(\Closure::class, $linkfunction);

        // The URL returned should contain the id from the course_modules table.
        $sql = "SELECT c.id from {course_modules} c JOIN {modules} m ON m.id = c.module
            WHERE c.instance = :instance AND m.name=:table";
        $params = ['instance' => $id, 'table' => $table];
        $coursemodule = current($DB->get_records_sql($sql, $params, 0, 1));
        $record = (object)['id' => $id];
        $linkstring = $linkfunction($record);
        $this->assertEquals("https://www.example.com/moodle/mod/{$table}/view.php?id={$coursemodule->id}", $linkstring);
    }

    /**
     * Test for find_link_function
     *
     *
     * @covers \tool_advancedreplace\helper::find_link_function
     */
    public function test_find_link_function(): void {
        global $DB;
        $this->resetAfterTest();

        // An unrecognised table should return a null.
        $linkfunction = helper::find_link_function('failure', 'dummy');
        $this->assertNull($linkfunction);

        $course = $this->getDataGenerator()->create_course();
        $this->assertInstanceOf(\stdClass::class, $course);

        // A hand-code function for course:shortname.
        $linkfunction = helper::find_link_function('course', 'shortname');
        $this->assertInstanceOf(\Closure::class, $linkfunction);
        $linkstring = $linkfunction((object)['id' => $course->id]);
        $this->assertEquals("https://www.example.com/moodle/course/view.php?id={$course->id}", $linkstring);

        // The page module.
        $page = $this->getDataGenerator()->create_module('page', (object) [
            'course' => $course->id,
            'content' => 'This is a page content with a link to https://example.com.au',
            'contentformat' => FORMAT_HTML,
        ]);
        $this->assertInstanceOf(\stdClass::class, $page);
        $this->find_module('page', $page->id);

        // The assign module.
        $assign = $this->getDataGenerator()->create_module('assign', (object)[
            'course' => $course->id,
            'name' => 'Test!',
            'intro' => 'This is an assignment with a link to https://example.com.au/5678',
            'introformat' => FORMAT_HTML,
        ]);
        $this->assertInstanceOf(\stdClass::class, $assign);
        $this->find_module('assign', $assign->id);
    }
}
