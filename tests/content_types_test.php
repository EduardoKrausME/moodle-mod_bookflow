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
 * content_types_test.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use PHPUnit\Framework\Attributes\CoversClass;
use advanced_testcase;
use coding_exception;
use mod_flexbook\hook\content_types;
use mod_flexbook\content_type_manager;
use mod_flexbook\types\content;
use flexbookcontent_html\types\html;

/**
 * Tests core content type registration and hook validation.
 */
#[CoversClass(content_type_manager::class)]
final class content_types_test extends advanced_testcase {
    /**
     * Tests that core types are registered.
     *
     * @return void
     */
    public function test_core_types_are_registered(): void {
        $this->resetAfterTest();
        $classes = content_type_manager::get_classes();

        $types = [
            "accordion",
            "audio",
            "callout",
            "code",
            "disclosure",
            "download",
            "flashcards",
            "html",
            "image",
            "markdown",
            "question",
            "tabs",
            "video",
        ];
        foreach ($types as $type) {
            $this->assertArrayHasKey($type, $classes);
            $this->assertTrue(is_subclass_of($classes[$type], content::class));
        }
    }

    /**
     * Tests that hook rejects invalid and duplicate types.
     *
     * @return void
     */
    public function test_hook_rejects_invalid_and_duplicate_types(): void {
        $hook = new content_types();
        $hook->register("html", html::class);
        $this->assertEquals(["html" => html::class], $hook->get_classes());
        $this->expectException(coding_exception::class);
        $hook->register("Invalid-Type", html::class);
    }
}
