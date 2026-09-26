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

/**
 * backup_restore_test.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests backup support and complete activity data deletion.
 */
#[CoversNothing]
final class backup_restore_test extends advanced_testcase {
    /**
     * Tests that backup support and instance deletion cover all data.
     *
     * @return void
     */
    public function test_backup_support_and_instance_deletion_cover_all_data(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator("mod_flexbook");
        $flexbook = $generator->create_instance(["course" => $course->id]);
        $chapter = $generator->create_chapter($flexbook);
        $content = $generator->create_content($chapter);

        $this->assertTrue(flexbook_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(flexbook_delete_instance($flexbook->id));
        $this->assertFalse($DB->record_exists("flexbook", ["id" => $flexbook->id]));
        $this->assertFalse($DB->record_exists("flexbook_chapters", ["id" => $chapter->id]));
        $this->assertFalse($DB->record_exists("flexbook_contents", ["id" => $content->id]));
    }
}
