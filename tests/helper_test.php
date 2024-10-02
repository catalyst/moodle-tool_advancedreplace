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
            [
                '', '', '', '',
                // Should include these tables/columns.
                [
                    'page' => 'content, intro',
                    'assign' => 'intro, name',
                ],
                // Should not include these tables/columns.
                [
                    'page' => 'id, introformat, timecreated, timemodified, timelimit',
                    'assign' => 'id, introformat, course',
                    'config' => '',
                    'logstore_standard_log' => '',
                ],
            ],
            [
                'page', '', '', '',
                [
                    'page' => 'content, intro',
                ],
                [
                    'assign' => '',
                ],
            ],
            [
                'page:content,assign:intro', '', '', '',
                [
                    'page' => 'content',
                    'assign' => 'intro',
                ],
                [
                    'page' => 'intro',
                    'assign' => 'name',
                ],
            ],
            [
                '', 'assign', '', '',
                [
                    'page' => '',
                ],
                [
                    'assign' => '',
                ],
            ],
            [
                '', 'assign', 'content', '',
                [
                    'page' => '',
                ],
                [
                    'assign' => '',
                    'page:content' => '',
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
     * @param array $expectedlist the tables/columns which should be in the result
     * @param array $unexpectedlist the tables/columns which should not be in the result
     *
     * return void
     */
    public function test_build_searching_list(string $tables, string $skiptables, string $skipcolumns , string $searchstring,
                                              array  $expectedlist, array $unexpectedlist): void {
        $this->resetAfterTest();
        [$count, $searchlist] = helper::build_searching_list($tables, $skiptables, $skipcolumns, $searchstring);

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
     * @covers \tool_advancedreplace\helper::plain_text_search
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

        [$count, $searchlist] = helper::build_searching_list('page,assign');
        $result = [];
        foreach ($searchlist as $table => $columns) {
            foreach ($columns as $column) {
                $result = array_merge($result, helper::plain_text_search($searchstring, $table, $column));
            }
        }
        $this->assertNotNull($result['page']['content']);
        $this->assertNotNull($result['assign']['intro']);
    }

    /**
     * Regular expression search.
     *
     * @covers \tool_advancedreplace\helper::regular_expression_search
     */
    public function test_regular_expression_search(): void {
        $this->resetAfterTest();

        $searchstring = "https://example.com.au/\d+";

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

        [$count, $searchlist] = helper::build_searching_list('page,assign');
        $result = [];
        foreach ($searchlist as $table => $columns) {
            foreach ($columns as $column) {
                $result = array_merge($result, helper::regular_expression_search($searchstring, $table, $column));
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

        // Replace the text in the page content.
        helper::replace_text_in_a_record('page', 'content', 'https://example.com.au/1234',
            'https://example.com.au/5678', $page->id);

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

}
