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
* File Search  test.
*
* Implements some tests to be run on the file_search class.
*
* @package    tool_advancedreplace
* @copyright   2024 Catalyst IT Australia Pty Ltd
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
final class file_search_test extends \advanced_testcase {
    /**
     * Data provider for test_make_where_clause.
     *
     * @return array
     */
    public static function make_where_clause_provider(): array {
        return [
            [
                'no restrictions',
                '', '', '',
                '', '', '', '',
                '',
                [],
            ],

            [
                'one component',
                'mod_h5p', '', '',
                '', '', '', '',
                '( (component=:param1) )',
                ['param1' => 'mod_h5p'],
            ],

            [
                'one component:area',
                'mod_h5p:content', '', '',
                '', '', '', '',
                '( (component=:param1 AND filearea=:param2) )',
                ['param1' => 'mod_h5p', 'param2' => 'content'],
            ],

            [
                'two components',
                'mod_hvp:content,course', '', '',
                '', '', '', '',
                '( (component=:param1 AND filearea=:param2) OR (component=:param3) )',
                ['param1' => 'mod_hvp', 'param2' => 'content', 'param3' => 'course'],
            ],

            [
                'one mimetype',
                '', '', '',
                'application/zip.h5p', '', '', '',
                '( (mimetype=:param1) )',
                ['param1' => 'application/zip.h5p'],
            ],

            [
                'three mimetypes',
                '', '', '',
                'application/zip.h5p,image/jpeg,plain/text', '', '', '',
                '( (mimetype=:param1) OR (mimetype=:param2) OR (mimetype=:param3) )',
                ['param1' => 'application/zip.h5p', 'param2' => 'image/jpeg', 'param3' => 'plain/text'],
            ],

            [
                'one filename',
                '', '', '',
                '', '', 'content.html', '',
                '( (filename=:param1) )',
                ['param1' => 'content.html'],
            ],

            [
                'two filenames',
                '', '', '',
                '', '', 'content.html,example.php', '',
                '( (filename=:param1) OR (filename=:param2) )',
                ['param1' => 'content.html', 'param2' => 'example.php'],
            ],

            [
                'skip one component',
                '', 'mod_assign', '',
                '', '', '', '',
                '(component!=:param1)',
                ['param1' => 'mod_assign'],
            ],

            [
                'skip 2 components',
                '', 'mod_assign,second_one', '',
                '', '', '', '',
                '(component!=:param1) AND (component!=:param2)',
                ['param1' => 'mod_assign', 'param2' => 'second_one'],
            ],

            [
                'skip one area',
                '', '', '',
                'application/zip.h5p', '', '', '',
                '( (mimetype=:param1) )',
                ['param1' => 'application/zip.h5p'],
            ],

            [
                'mixed: all options',
                'goodcomponent:goodarea', 'badcomponent', 'badarea,anotherbadarea',
                'application/zip.h5p', 'image/jpeg,image/png', 'content.html', 'favicon.png',
                '( (component=:param1 AND filearea=:param2) ) AND ( (mimetype=:param3) ) AND ( (filename=:param4) )'.
                ' AND (component!=:param5) AND (mimetype!=:param6) AND (mimetype!=:param7)' .
                ' AND (filename!=:param8) AND (filearea!=:param9) AND (filearea!=:param10)',
                [
                    'param1' => 'goodcomponent',
                    'param2' => 'goodarea',
                    'param3' => 'application/zip.h5p',
                    'param4' => 'content.html',
                    'param5' => 'badcomponent',
                    'param6' => 'image/jpeg',
                    'param7' => 'image/png',
                    'param8' => 'favicon.png',
                    'param9' => 'badarea',
                    'param10' => 'anotherbadarea',
                ],
            ],
        ];
    }

    /**
     * Test make_where_clause.
     *
     * @dataProvider make_where_clause_provider
     * @covers \tool_advancedreplace\file_search::make_where_clause
     *
     * @param string $testcase Text to identify the case.
     * @param string $components Comma-seperated  component:area pairs.
     * @param string $skipcomponents Comma-separated components to be omitted.
     * @param string $skipareas Comma-separated areas to be omitted.
     * @param string $mimetypes Comma-separated mimetypes to be searched.
     * @param string $skipmimetypes Comma-separated mimetypes to be omitted.
     * @param string $filenames Comma-separated filenames to be searched.
     * @param string $skipfilenames Comma-separated filenames to be omitted.
     * @param string $expectedwhereclause The clause that we expect togenerate.
     * @param array $expectedparams The array of parameters that we expect to generate.
     * @return void
     */
    public function test_make_where_clause(string $testcase,
    string $components, string $skipcomponents, string $skipareas,
    string $mimetypes, string $skipmimetypes, string $filenames, string $skipfilenames,
    string $expectedwhereclause, array $expectedparams) {
        global $DB;
        $this->resetAfterTest();
        [$whereclause, $params] = file_search::make_where_clause($components, $skipcomponents, $skipareas,
        $mimetypes, $skipmimetypes, $filenames, $skipfilenames);
        $result = $DB->get_recordset_select('files', $whereclause, $params, 'component, filearea, contextid, itemid' );
        $this->assertNotFalse($result, "SQL should be valid syntax.");
        $this->assertEquals($expectedwhereclause, $whereclause, "The generated where clause should match expected one.");
        $this->assertSame($expectedparams, $params, "The array of parameters for the \$DB call should be as expected.");

    }

}

