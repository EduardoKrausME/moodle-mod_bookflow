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
 * progress_manager_test.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use PHPUnit\Framework\Attributes\CoversClass;
use advanced_testcase;
use context_module;
use mod_flexbook\completion\custom_completion;
use mod_flexbook\content_manager;
use mod_flexbook\progress\progress_manager;
use mod_flexbook\task\recalculate_progress;
use mod_flexbook_generator;
use Override;
use ReflectionClass;
use stdClass;

/**
 * Tests weighted progress, completion, permissions, ordering, and reading position.
 */
#[CoversClass(progress_manager::class)]
final class progress_manager_test extends advanced_testcase {
    /** @var stdClass */
    private stdClass $course;
    /** @var stdClass */
    private stdClass $user;
    /** @var stdClass */
    private stdClass $flexbook;
    /** @var stdClass */
    private stdClass $chapter;
    /** @var mod_flexbook_generator */
    private mod_flexbook_generator $generator;

    /**
     * Prepares the test fixture.
     *
     * @return void
     */
    #[Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course(["enablecompletion" => 1]);
        $this->user = $this->getDataGenerator()->create_and_enrol($this->course, "student");
        $this->generator = $this->getDataGenerator()->get_plugin_generator("mod_flexbook");
        $this->flexbook = $this->generator->create_instance([
            "course" => $this->course->id,
            "name" => "Test FlexBook",
            "completionmode" => FLEXBOOK_COMPLETION_PERCENTAGE,
            "completionpercentage" => 80,
            "completion" => COMPLETION_TRACKING_AUTOMATIC,
        ]);
        $this->chapter = $this->generator->create_chapter($this->flexbook, ["title" => "Chapter 1"]);
    }

    /**
     * Tests that activity chapter and content creation.
     *
     * @return void
     */
    public function test_activity_chapter_and_content_creation(): void {
        global $DB;

        $content = $this->generator->create_content($this->chapter);
        $this->assertTrue($DB->record_exists("flexbook", ["id" => $this->flexbook->id]));
        $this->assertTrue($DB->record_exists("flexbook_chapters", ["id" => $this->chapter->id]));
        $this->assertTrue($DB->record_exists("flexbook_contents", ["id" => $content->id]));
    }

    /**
     * Tests that user starts at zero and view increases progress.
     *
     * @return void
     */
    public function test_user_starts_at_zero_and_view_increases_progress(): void {
        $first = $this->generator->create_content($this->chapter);
        $this->generator->create_content($this->chapter);
        $manager = new progress_manager();
        $this->assertEquals(0, $manager->calculate_user_progress($this->flexbook->id, $this->user->id));
        $manager->mark_content_viewed($this->flexbook->id, $this->user->id, $first->id);
        $this->assertEquals(50, $manager->calculate_user_progress($this->flexbook->id, $this->user->id));
        $manager->mark_content_viewed($this->flexbook->id, $this->user->id, $first->id);
        $this->assertEquals(50, $manager->calculate_user_progress($this->flexbook->id, $this->user->id));
    }

    /**
     * Tests that weights and ignored blocks.
     *
     * @return void
     */
    public function test_weights_and_ignored_blocks(): void {
        $light = $this->generator->create_content($this->chapter, ["weight" => 1]);
        $this->generator->create_content($this->chapter, ["weight" => 3]);
        $this->generator->create_content($this->chapter, ["weight" => 100, "trackprogress" => 0]);
        $manager = new progress_manager();
        $manager->mark_content_viewed($this->flexbook->id, $this->user->id, $light->id);
        $this->assertEquals(25, $manager->calculate_user_progress($this->flexbook->id, $this->user->id));
    }

    /**
     * Tests that progress is limited and zero denominator is safe.
     *
     * @return void
     */
    public function test_progress_is_limited_and_zero_denominator_is_safe(): void {
        $this->generator->create_content($this->chapter, ["trackprogress" => 0]);
        $manager = new progress_manager();
        $progress = $manager->calculate_user_progress($this->flexbook->id, $this->user->id);
        $this->assertGreaterThanOrEqual(0, $progress);
        $this->assertLessThanOrEqual(100, $progress);
        $this->assertEquals(0, $progress);
    }

    /**
     * Tests that add delete and weight change recalculate.
     *
     * @return void
     */
    public function test_add_delete_and_weight_change_recalculate(): void {
        global $DB;

        $first = $this->generator->create_content($this->chapter);
        $manager = new progress_manager();
        $manager->mark_content_viewed($this->flexbook->id, $this->user->id, $first->id);
        $this->assertEquals(100, $manager->calculate_user_progress($this->flexbook->id, $this->user->id));

        $second = $this->generator->create_content($this->chapter);
        $this->execute_recalculation_task();
        $this->assertEquals(50, $DB->get_field("flexbook_user_state", "progress", [
            "flexbookid" => $this->flexbook->id,
            "userid" => $this->user->id,
        ]));

        $second->weight = 3;
        content_manager::update($second);
        $this->assertEquals(25, $manager->calculate_user_progress($this->flexbook->id, $this->user->id));

        content_manager::delete($second->id);
        $this->assertEquals(100, $manager->calculate_user_progress($this->flexbook->id, $this->user->id));
    }

    /**
     * Tests that required and combined completion.
     *
     * @return void
     */
    public function test_required_and_combined_completion(): void {
        global $DB;

        $required = $this->generator->create_content($this->chapter, ["required" => 1, "weight" => 1]);
        $optional = $this->generator->create_content($this->chapter, ["weight" => 4]);
        $manager = new progress_manager();

        $this->flexbook->completionmode = FLEXBOOK_COMPLETION_COMBINED;
        $this->flexbook->completionpercentage = 80;
        $DB->update_record("flexbook", $this->flexbook);

        $manager->mark_content_viewed($this->flexbook->id, $this->user->id, $optional->id);
        $this->assertFalse($manager->completion_requirements_met($this->flexbook->id, $this->user->id));
        $manager->mark_content_viewed($this->flexbook->id, $this->user->id, $required->id);
        $this->assertTrue($manager->completion_requirements_met($this->flexbook->id, $this->user->id));

        $this->flexbook->completionmode = FLEXBOOK_COMPLETION_REQUIRED;
        $DB->update_record("flexbook", $this->flexbook);
        $this->assertTrue($manager->completion_requirements_met($this->flexbook->id, $this->user->id));
    }

    /**
     * Tests that required chapter completion.
     *
     * @return void
     */
    public function test_required_chapter_completion(): void {
        global $DB;

        $this->chapter->required = 1;
        $DB->update_record("flexbook_chapters", $this->chapter);
        $content = $this->generator->create_content($this->chapter);
        $this->flexbook->completionmode = FLEXBOOK_COMPLETION_CHAPTERS;
        $DB->update_record("flexbook", $this->flexbook);
        $manager = new progress_manager();
        $this->assertFalse($manager->completion_requirements_met($this->flexbook->id, $this->user->id));
        $manager->mark_content_viewed($this->flexbook->id, $this->user->id, $content->id);
        $this->assertTrue($manager->completion_requirements_met($this->flexbook->id, $this->user->id));
    }

    /**
     * Tests that moodle completion is updated at configured percentage.
     *
     * @return void
     */
    public function test_moodle_completion_is_updated_at_configured_percentage(): void {
        global $DB;

        $contents = [];
        for ($index = 0; $index < 5; $index++) {
            $contents[] = $this->generator->create_content($this->chapter);
        }
        $manager = new progress_manager();
        foreach (array_slice($contents, 0, 4) as $content) {
            $manager->mark_content_viewed($this->flexbook->id, $this->user->id, $content->id);
        }
        $cm = get_coursemodule_from_instance("flexbook", $this->flexbook->id, $this->course->id);
        $completion = $DB->get_record("course_modules_completion", [
            "coursemoduleid" => $cm->id,
            "userid" => $this->user->id,
        ], "*", MUST_EXIST);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->completionstate);
    }

    /**
     * Tests that student can view but cannot manage content.
     *
     * @return void
     */
    public function test_student_can_view_but_cannot_manage_content(): void {
        $cm = get_coursemodule_from_instance("flexbook", $this->flexbook->id, $this->course->id);
        $context = context_module::instance($cm->id);
        $this->setUser($this->user);
        $this->assertTrue(has_capability("mod/flexbook:view", $context));
        $this->assertFalse(has_capability("mod/flexbook:managecontent", $context));
    }

    /**
     * Tests that last position prefers content identifier.
     *
     * @return void
     */
    public function test_last_position_prefers_content_identifier(): void {
        $content = $this->generator->create_content($this->chapter);
        $manager = new progress_manager();
        $manager->save_last_position(
            $this->flexbook->id,
            $this->user->id,
            $this->chapter->id,
            $content->id,
            450
        );
        $position = $manager->get_last_position($this->flexbook->id, $this->user->id);
        $this->assertEquals($content->id, $position->lastcontentid);
        $this->assertEquals(450, $position->scrollposition);
    }

    /**
     * Tests that content ordering.
     *
     * @return void
     */
    public function test_content_ordering(): void {
        global $DB;

        $first = $this->generator->create_content($this->chapter);
        $second = $this->generator->create_content($this->chapter);
        content_manager::reorder($this->chapter->id, [$second->id, $first->id]);
        $ordered = array_values($DB->get_records("flexbook_contents", [
            "chapterid" => $this->chapter->id,
        ], "sortorder"));
        $this->assertEquals($second->id, $ordered[0]->id);
        $this->assertEquals($first->id, $ordered[1]->id);
    }

    /**
     * Tests the custom completion API contract and rule order.
     *
     * @return void
     */
    public function test_custom_completion_contract(): void {
        $reflection = new ReflectionClass(custom_completion::class);
        $completion = $reflection->newInstanceWithoutConstructor();

        $this->assertSame(
            ["completionmode", "completionpercentage"],
            $completion::get_defined_custom_rules()
        );
        $this->assertSame([
            "completionview",
            "completionmode",
            "completionpercentage",
            "completionusegrade",
            "completionpassgrade",
        ], $completion->get_sort_order());
    }

    /**
     * Tests that a forged media jump does not immediately complete the block and
     * metric updates do not inflate the view counter.
     *
     * @return void
     */
    public function test_media_metric_is_bounded_by_server_elapsed_time(): void {
        global $DB;

        $content = $this->generator->create_content($this->chapter, [
            "type" => "video",
            "completiontype" => "percent",
            "completionvalue" => 80,
        ]);
        $manager = new progress_manager();

        $manager->update_content_metric(
            $this->flexbook->id,
            $this->user->id,
            $content->id,
            100,
            ["watchedSeconds" => 100, "duration" => 100]
        );
        $manager->update_content_metric(
            $this->flexbook->id,
            $this->user->id,
            $content->id,
            100,
            ["watchedSeconds" => 100, "duration" => 100]
        );

        $record = $DB->get_record("flexbook_user_progress", [
            "userid" => $this->user->id,
            "contentid" => $content->id,
        ], "*", MUST_EXIST);
        $this->assertEquals(progress_manager::STATUS_VIEWED, $record->status);
        $this->assertLessThan(80, (float) $record->progress);
        $this->assertEquals(1, $record->viewcount);
    }

    /**
     * Tests warnings for completion configurations that cannot be satisfied.
     *
     * @return void
     */
    public function test_completion_configuration_warnings(): void {
        global $DB;

        $manager = new progress_manager();
        $this->assertNotNull($manager->get_completion_configuration_warning($this->flexbook->id));

        $content = $this->generator->create_content($this->chapter);
        $this->assertNull($manager->get_completion_configuration_warning($this->flexbook->id));

        $this->flexbook->completionmode = FLEXBOOK_COMPLETION_REQUIRED;
        $DB->update_record("flexbook", $this->flexbook);
        $this->assertNotNull($manager->get_completion_configuration_warning($this->flexbook->id));

        $content->required = 1;
        content_manager::update($content);
        $this->assertNull($manager->get_completion_configuration_warning($this->flexbook->id));
    }

    /**
     * Executes the first structural progress recalculation batch.
     *
     * @return void
     */
    private function execute_recalculation_task(): void {
        $task = new recalculate_progress();
        $task->set_custom_data([
            "flexbookid" => $this->flexbook->id,
            "afteruserid" => 0,
        ]);
        $task->execute();
    }

}
