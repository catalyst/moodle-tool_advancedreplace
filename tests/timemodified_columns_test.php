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
 * Time modified columns test.
 *
 * Note: This class deliberately contains both old annotations and new attributes. PHPUnit will use one and ignore the
 * other.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversMethod(timemodified_columns::class, 'generate_map')]
#[\PHPUnit\Framework\Attributes\CoversMethod(timemodified_columns::class, 'get_map')]
#[\PHPUnit\Framework\Attributes\CoversMethod(timemodified_columns::class, 'get_column')]
final class timemodified_columns_test extends \advanced_testcase {
    /** @var array|null The generated map, shared by all tests as it is the same every time. */
    private static ?array $map = null;

    /**
     * Generate the shared map.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$map = timemodified_columns::generate_map();
    }

    /**
     * Release the shared map.
     */
    public static function tearDownAfterClass(): void {
        self::$map = null;
        parent::tearDownAfterClass();
    }

    /**
     * Data provider for test_generate_map.
     *
     * @return array
     */
    public static function generate_map_provider(): array {
        return [
            // Standard column name.
            ['course', 'timemodified'],
            // Preferred over timemodifiedoffline.
            ['quiz_attempts', 'timemodified'],
            // Non-standard column names.
            ['forum_posts', 'modified'],
            ['post', 'lastmodified'],
            ['enrol_paypal', 'timeupdated'],
            ['enrol_lti_lti2_consumer', 'updated'],
            ['event_subscriptions', 'lastupdated'],
            ['lti_submission', 'dateupdated'],
            // No time modified column at all.
            ['config', null],
        ];
    }

    /**
     * Test the map is generated from the table definitions.
     *
     * @dataProvider generate_map_provider
     * @covers \tool_advancedreplace\timemodified_columns::generate_map
     * @param string $table The table to check.
     * @param string|null $expected The expected column, or null if the table should not be in the map.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('generate_map_provider')]
    public function test_generate_map(string $table, ?string $expected): void {
        $this->assertSame($expected, self::$map[$table] ?? null);
    }

    /**
     * Test the map is cached, and generated when the cache is empty.
     *
     * @covers \tool_advancedreplace\timemodified_columns::get_map
     * @covers \tool_advancedreplace\timemodified_columns::get_column
     */
    public function test_get_map_uses_cache(): void {
        $this->resetAfterTest();

        $cache = \cache::make('tool_advancedreplace', 'timemodifiedcolumns');
        $cache->purge();

        // An empty cache is populated with the generated map.
        $this->assertSame('modified', timemodified_columns::get_column('forum_posts'));
        $this->assertSame(self::$map, $cache->get(timemodified_columns::CACHE_KEY));

        // A populated cache is used as is.
        $cache->set(timemodified_columns::CACHE_KEY, ['forum_posts' => 'cachedcolumn']);
        $this->assertSame('cachedcolumn', timemodified_columns::get_column('forum_posts'));
        $this->assertNull(timemodified_columns::get_column('course'));
    }
}
