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

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Tests for tool_advancedreplace events.
 *
 * @package    tool_advancedreplace
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_test extends \advanced_testcase {
    /**
     * Data provider for search events.
     *
     * @return array
     */
    public static function search_event_provider(): array {
        return [
            'db created' => [db_search::class, 'created', event\db_search_created::class, 'c'],
            'db deleted' => [db_search::class, 'deleted', event\db_search_deleted::class, 'd'],
            'db requeued' => [db_search::class, 'requeued', event\db_search_requeued::class, 'u'],
            'files created' => [files::class, 'created', event\files_search_created::class, 'c'],
            'files deleted' => [files::class, 'deleted', event\files_search_deleted::class, 'd'],
            'files requeued' => [files::class, 'requeued', event\files_search_requeued::class, 'u'],
        ];
    }

    /**
     * Test search events are created with the right data and include search details in descriptions.
     *
     * @dataProvider search_event_provider
     * @covers \tool_advancedreplace\search::create_event
     * @covers \tool_advancedreplace\event\search_base
     * @param string $searchclass
     * @param string $action
     * @param string $eventclass
     * @param string $crud
     */
    public function test_search_events(string $searchclass, string $action, string $eventclass, string $crud): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $secret = 'supersecretvalue';
        $data = (object) [
            'name' => 'My search',
            'userid' => get_admin()->id,
            'origin' => 'web',
        ];
        if ($searchclass === db_search::class) {
            $data->search = $secret;
            $data->tables = 'user,course';
            $scope = 'tables';
            $scopevalue = 'user,course';
        } else {
            $data->pattern = $secret;
            $data->filenames = '*.txt';
            $scope = 'filenames';
            $scopevalue = '*.txt';
        }
        $search = new $searchclass(0, $data);
        $search->create();

        $sink = $this->redirectEvents();
        $search->create_event($action)->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf($eventclass, $event);
        $this->assertEquals(\context_system::instance(), $event->get_context());
        $this->assertEquals($search->get('id'), $event->objectid);
        $this->assertEquals($searchclass::TABLE, $event->objecttable);
        $this->assertEquals($crud, $event->crud);
        $this->assertEquals('My search', $event->other['name']);
        $this->assertStringContainsString('My search', $event->get_description());
        $this->assertStringContainsString($secret, $event->get_description());
        $this->assertStringContainsString($scope . " '$scopevalue'", $event->get_description());
        $this->assertNotEmpty($event->get_description());
        $this->assertNotEmpty($event::get_name());
    }

    /**
     * Test that an invalid action is rejected.
     *
     * @covers \tool_advancedreplace\search::create_event
     */
    public function test_search_event_invalid_action(): void {
        $this->resetAfterTest();
        $search = new db_search(0, (object) ['search' => 'x', 'userid' => 2, 'origin' => 'web']);
        $search->create();

        $this->expectException(\coding_exception::class);
        $search->create_event('updated');
    }

    /**
     * Test that a bulk database replacement event describes replacement details and counts.
     *
     * @covers \tool_advancedreplace\helper::handle_replace_csv
     * @covers \tool_advancedreplace\event\replace_executed
     */
    public function test_replace_executed_event(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', (object) [
            'course' => $course,
            'content' => 'Link to https://example.com.au/1234',
            'contentformat' => FORMAT_HTML,
        ]);

        $csv = "table,column,id,match,replace\n" .
            "page,content,{$page->id},https://example.com.au/1234,https://example.com.au/5678\n" .
            "page,content,{$page->id},https://example.com.au/1234,\n";

        $progress = $this->createMock(\progress_bar::class);

        $sink = $this->redirectEvents();
        helper::handle_replace_csv($csv, $progress, 'db');
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof event\replace_executed);
        $sink->close();

        $this->assertStringContainsString('https://example.com.au/5678', $DB->get_field('page', 'content', ['id' => $page->id]));
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals(\context_system::instance(), $event->get_context());
        $this->assertEquals(event\search_base::TYPE_DB, $event->other['type']);
        $this->assertEquals(2, $event->other['rows']);
        $this->assertEquals(1, $event->other['success']);
        $this->assertEquals(1, $event->other['skipped']);
        $this->assertEquals(0, $event->other['error']);
        $this->assertTrue($event->other['completed']);
        $description = $event->get_description();
        $this->assertStringContainsString("replace 'https://example.com.au/1234' with 'https://example.com.au/5678'", $description);
        $this->assertStringContainsString("table 'page', column 'content'", $description);
        $this->assertNotEmpty($description);
    }
}
