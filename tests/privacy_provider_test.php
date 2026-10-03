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
 * privacy_provider_test.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use context_module;
use core_privacy\tests\provider_testcase;
use mod_flexbook\privacy\provider;
use mod_flexbook\progress\progress_manager;

/**
 * Tests Privacy API context discovery and personal data deletion.
 *
 * @covers \mod_flexbook\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * Tests that context discovery and deletion.
     *
     * @return void
     */
    public function test_context_discovery_and_deletion(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, "student");
        $generator = $this->getDataGenerator()->get_plugin_generator("mod_flexbook");
        $flexbook = $generator->create_instance(["course" => $course->id]);
        $chapter = $generator->create_chapter($flexbook);
        $content = $generator->create_content($chapter);
        (new progress_manager())
            ->mark_content_viewed($flexbook->id, $user->id, $content->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist->get_contextids());

        $cm = get_coursemodule_from_instance("flexbook", $flexbook->id, $course->id);
        provider::delete_data_for_all_users_in_context(
            context_module::instance($cm->id)
        );
        $this->assertFalse($DB->record_exists("flexbook_user_progress", ["userid" => $user->id]));
        $this->assertFalse($DB->record_exists("flexbook_user_state", ["userid" => $user->id]));
    }
}
