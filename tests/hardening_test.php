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
 * Tests for input hardening: regex building/validation, replace validation and report paging.
 *
 * @package    tool_advancedreplace
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hardening_test extends \advanced_testcase {
    /**
     * Data provider for build_regex.
     *
     * @return array
     */
    public static function build_regex_provider(): array {
        return [
            'no delimiter' => ['abc', 'abc', true],
            'bare delimiter is escaped' => ['50%', '50%', true],
            'escaped delimiter is kept' => ['50\\%', '50%', true],
            'escaped backslash then delimiter' => ['a\\\\%', 'a\\%', true],
            'modifier injection is neutralised' => ['x%e', 'x%e', true],
            'slashes need no escaping' => ['https://example.com', 'https://example.com', true],
            'delimiter does not match other text' => ['50%', '50 percent', false],
        ];
    }

    /**
     * Test that build_regex produces a valid pattern that treats the delimiter literally.
     *
     * @dataProvider build_regex_provider
     * @covers \tool_advancedreplace\helper::build_regex
     * @param string $expression
     * @param string $subject
     * @param bool $matches
     */
    public function test_build_regex(string $expression, string $subject, bool $matches): void {
        $pattern = helper::build_regex($expression, file_search::REGEX_MODIFIERS);
        $this->assertTrue(helper::is_valid_regex($expression, file_search::REGEX_MODIFIERS));
        $this->assertSame($matches ? 1 : 0, preg_match($pattern, $subject));
    }

    /**
     * Test detection of invalid regular expressions.
     *
     * @covers \tool_advancedreplace\helper::is_valid_regex
     */
    public function test_is_valid_regex(): void {
        $this->assertTrue(helper::is_valid_regex('a(b|c)+'));
        $this->assertFalse(helper::is_valid_regex('a(b'));
        $this->assertFalse(helper::is_valid_regex('[abc'));
        // A trailing backslash would escape the closing delimiter.
        $this->assertFalse(helper::is_valid_regex('abc\\'));
    }

    /**
     * Test that invalid regexes are rejected when saving searches.
     *
     * @covers \tool_advancedreplace\files::validate_pattern
     * @covers \tool_advancedreplace\files::validate_zipfilenames
     * @covers \tool_advancedreplace\db_search::validate_search
     */
    public function test_persistent_regex_validation(): void {
        $this->resetAfterTest();
        $base = ['userid' => 2, 'origin' => 'web'];

        $files = new files(0, (object) ($base + ['pattern' => 'a(b']));
        $this->assertArrayHasKey('pattern', $files->get_errors());

        $files = new files(0, (object) ($base + ['pattern' => 'ok', 'zipfilenames' => '[x']));
        $this->assertArrayHasKey('zipfilenames', $files->get_errors());

        $files = new files(0, (object) ($base + ['pattern' => '100%', 'zipfilenames' => '\\.xml$']));
        $this->assertTrue($files->is_valid());

        $search = new db_search(0, (object) ($base + ['search' => 'a(b', 'regex' => 1]));
        $this->assertArrayHasKey('search', $search->get_errors());

        // Plain text searches are not validated as regexes.
        $search = new db_search(0, (object) ($base + ['search' => 'a(b', 'regex' => 0]));
        $this->assertTrue($search->is_valid());
    }

    /**
     * Data provider for replace validation.
     *
     * @return array
     */
    public static function replace_validation_provider(): array {
        return [
            'invalid table' => ['nosuchtable', 'content', null, 'errorreplacinginvalidtable'],
            'invalid column' => ['page', 'nosuchcolumn', null, 'errorreplacinginvalidcolumn'],
            'invalid id' => ['page', 'content', 'abc', 'errorreplacinginvalidid'],
        ];
    }

    /**
     * Test that invalid tables, columns and ids in a replace CSV are reported as errors, not fatal.
     *
     * @dataProvider replace_validation_provider
     * @covers \tool_advancedreplace\helper::handle_replace_csv
     * @covers \tool_advancedreplace\helper::replace_text_in_a_record
     * @param string $table
     * @param string $column
     * @param string|null $id id to use, or null for the real page id
     * @param string $expectederror
     */
    public function test_replace_validation(string $table, string $column, ?string $id, string $expectederror): void {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', (object) [
            'course' => $course,
            'content' => 'Original',
        ]);
        $id = $id ?? $page->id;
        $csv = "table,column,id,match,replace\n{$table},{$column},{$id},Original,Replaced\n";

        $sink = $this->redirectEvents();
        // In CLI mode the error handler prints errors at the end.
        $this->expectOutputRegex('/' . preg_quote(get_string($expectederror, 'tool_advancedreplace'), '/') . '/');
        helper::handle_replace_csv($csv, $this->createMock(\progress_bar::class), event\search_base::TYPE_DB);
        $events = $sink->get_events();
        $sink->close();

        $event = end($events);
        $this->assertInstanceOf(event\replace_executed::class, $event);
        $this->assertEquals(1, $event->other['error']);
        $this->assertEquals(0, $event->other['success']);
        $this->assertTrue($event->other['completed']);
    }

    /**
     * Test report page size clamping.
     *
     * @covers \tool_advancedreplace\search::clamp_report_perpage
     */
    public function test_clamp_report_perpage(): void {
        $this->assertSame(search::REPORT_MIN_PERPAGE, search::clamp_report_perpage(0));
        $this->assertSame(search::REPORT_MIN_PERPAGE, search::clamp_report_perpage(-5));
        $this->assertSame(search::REPORT_DEFAULT_PERPAGE, search::clamp_report_perpage(search::REPORT_DEFAULT_PERPAGE));
        $this->assertSame(search::REPORT_MAX_PERPAGE, search::clamp_report_perpage(PHP_INT_MAX));
    }
}
