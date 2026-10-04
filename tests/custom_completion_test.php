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
 * custom_completion_test.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow;

use advanced_testcase;
use mod_bookflow\completion\custom_completion;

/**
 * Tests the BookFlow custom completion integration with Moodle core.
 *
 * @covers \mod_bookflow\completion\custom_completion
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * Percentage mode must remain enabled even though its configured value is zero.
     *
     * @return void
     */
    public function test_percentage_mode_exposes_only_the_active_completion_rule(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, "student");
        $generator = $this->getDataGenerator()->get_plugin_generator("mod_bookflow");
        $bookflow = $generator->create_instance([
            "course" => $course->id,
            "name" => "Completion test",
            "completion" => COMPLETION_TRACKING_AUTOMATIC,
            "completionmode" => BOOKFLOW_COMPLETION_PERCENTAGE,
            "completionpercentage" => 80,
        ]);
        $chapter = $generator->create_chapter($bookflow);
        $generator->create_content($chapter);

        rebuild_course_cache($course->id, true);
        $cmrecord = get_coursemodule_from_instance("bookflow", $bookflow->id, $course->id, false, MUST_EXIST);
        $cm = get_fast_modinfo($course)->get_cm($cmrecord->id);
        $customdata = (array) $cm->customdata;

        $this->assertSame(["completionmode" => 1], $customdata["customcompletionrules"]);
        $this->assertSame(BOOKFLOW_COMPLETION_PERCENTAGE, $customdata["completionmode"]);
        $this->assertSame(80, $customdata["completionpercentage"]);

        $completion = new custom_completion($cm, $user->id);
        $this->assertTrue($completion->is_defined("completionmode"));
        $this->assertTrue($completion->is_defined("completionpercentage"));
        $this->assertSame(["completionmode"], $completion->get_available_custom_rules());
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state("completionmode"));
    }

    /**
     * The obsolete completionpercentage rule remains recognised for stale modinfo caches.
     *
     * @return void
     */
    public function test_legacy_completion_rule_is_kept_in_sort_order(): void {
        $this->assertContains("completionpercentage", custom_completion::get_defined_custom_rules());

        $reflection = new \ReflectionClass(custom_completion::class);
        $completion = $reflection->newInstanceWithoutConstructor();
        $this->assertSame([
            "completionview",
            "completionmode",
            "completionpercentage",
            "completionusegrade",
            "completionpassgrade",
        ], $completion->get_sort_order());
    }
}
