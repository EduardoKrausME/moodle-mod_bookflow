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
 * security_regression_test.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow;

use advanced_testcase;
use context_module;
use mod_bookflow\event\course_module_viewed;
use mod_bookflow\external\api;
use moodle_exception;

/**
 * Regression tests for logging, private user state and visible exports.
 */
final class security_regression_test extends advanced_testcase {
    /**
     * Activity view events must implement Moodle's standard module contract.
     *
     * @covers \mod_bookflow\event\course_module_viewed
     */
    public function test_standard_course_module_viewed_event(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $bookflow = $this->getDataGenerator()->get_plugin_generator("mod_bookflow")->create_instance([
            "course" => $course->id,
            "name" => "BookFlow log test",
        ]);
        $cm = get_coursemodule_from_instance("bookflow", $bookflow->id, $course->id);
        $event = course_module_viewed::create([
            "objectid" => $bookflow->id,
            "context" => context_module::instance($cm->id),
        ]);
        $this->assertInstanceOf(\core\event\course_module_viewed::class, $event);
        $this->assertEquals($bookflow->id, $event->objectid);
        $event->trigger();
    }

    /**
     * Search must never inspect option metadata or private answer keys.
     *
     * @covers \mod_bookflow\external\api::search_contents
     */
    public function test_search_excludes_stored_answer_metadata(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, "student");
        $generator = $this->getDataGenerator()->get_plugin_generator("mod_bookflow");
        $bookflow = $generator->create_instance([
            "course" => $course->id,
            "name" => "BookFlow search test",
            "enablesearch" => 1,
        ]);
        $chapter = $generator->create_chapter($bookflow);
        $generator->create_content($chapter, [
            "title" => "Public topic",
            "data1" => "<p>Visible explanation</p>",
            "data2" => "private_option_token",
            "data3" => '{"answer":"private_answer_token"}',
        ]);
        $this->setUser($student);
        $this->assertCount(0, api::search_contents($bookflow->id, "private_option_token"));
        $this->assertCount(0, api::search_contents($bookflow->id, "private_answer_token"));
        $this->assertCount(1, api::search_contents($bookflow->id, "Visible explanation"));
    }

    /**
     * The shared guest user must not read or persist learner-specific data.
     *
     * @covers \mod_bookflow\external\api::create_note
     * @covers \mod_bookflow\external\api::create_bookmark
     * @covers \mod_bookflow\external\api::save_user_position
     * @covers \mod_bookflow\external\api::mark_contents_viewed
     * @covers \mod_bookflow\external\api::get_user_progress
     */
    public function test_guest_cannot_store_or_read_shared_state(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator("mod_bookflow");
        $bookflow = $generator->create_instance([
            "course" => $course->id,
            "name" => "BookFlow guest test",
        ]);
        $chapter = $generator->create_chapter($bookflow);
        $content = $generator->create_content($chapter);
        $this->setUser(get_guest_user());
        $this->assertTrue(isguestuser());

        $operations = [
            fn() => api::create_note($bookflow->id, $chapter->id, 0, "Note"),
            fn() => api::create_bookmark($bookflow->id, "chapter", $chapter->id),
            fn() => api::save_user_position($bookflow->id, $chapter->id),
            fn() => api::mark_contents_viewed($bookflow->id, [$content->id]),
            fn() => api::get_user_progress($bookflow->id),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail("The shared guest operation was accepted");
            } catch (moodle_exception $exception) {
                $this->assertSame("guestsnotallowed", $exception->errorcode);
            }
        }
        $this->assertEquals(0, $DB->count_records("bookflow_notes", ["bookflowid" => $bookflow->id]));
        $this->assertEquals(0, $DB->count_records("bookflow_bookmarks", ["bookflowid" => $bookflow->id]));
        $this->assertEquals(0, $DB->count_records("bookflow_user_progress", ["bookflowid" => $bookflow->id]));
    }

    /**
     * Markdown used in standalone and ZIP exports must omit hidden data.
     *
     * @covers \mod_bookflow\export_manager::to_markdown
     */
    public function test_markdown_excludes_hidden_chapters_and_blocks(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator("mod_bookflow");
        $bookflow = $generator->create_instance([
            "course" => $course->id,
            "name" => "BookFlow export test",
        ]);
        $visible = $generator->create_chapter($bookflow, ["title" => "PUBLIC_CHAPTER"]);
        $hidden = $generator->create_chapter($bookflow, ["title" => "PRIVATE_CHAPTER", "hidden" => 1]);
        $generator->create_content($visible, ["title" => "PUBLIC_BLOCK", "data1" => "public body"]);
        $generator->create_content($visible, ["title" => "PRIVATE_BLOCK", "hidden" => 1]);
        $generator->create_content($hidden, ["title" => "PRIVATE_CHILD_BLOCK"]);
        $cm = get_coursemodule_from_instance("bookflow", $bookflow->id, $course->id);
        $markdown = export_manager::to_markdown($bookflow->id, context_module::instance($cm->id));
        $this->assertStringContainsString("PUBLIC_CHAPTER", $markdown);
        $this->assertStringContainsString("PUBLIC_BLOCK", $markdown);
        $this->assertStringNotContainsString("PRIVATE_CHAPTER", $markdown);
        $this->assertStringNotContainsString("PRIVATE_BLOCK", $markdown);
        $this->assertStringNotContainsString("PRIVATE_CHILD_BLOCK", $markdown);
    }
}
