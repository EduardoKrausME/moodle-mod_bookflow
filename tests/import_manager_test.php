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
 * import_manager_test.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use advanced_testcase;
use moodle_exception;

/**
 * Tests import resource limits before database changes are made.
 */
class import_manager_test extends advanced_testcase {
    /**
     * Oversized direct sources are rejected before parsing.
     *
     * @return void
     */
    public function test_oversized_markdown_source_is_rejected(): void {
        $this->resetAfterTest();

        try {
            import_manager::from_markdown(str_repeat("x", (10 * 1024 * 1024) + 1), 0);
            $this->fail("Oversized Markdown source was accepted");
        } catch (moodle_exception $exception) {
            $this->assertSame("importsourceoversize", $exception->errorcode);
        }
    }

    /**
     * A small source cannot manufacture an unbounded number of chapters.
     *
     * @return void
     */
    public function test_markdown_chapter_limit_is_enforced(): void {
        $this->resetAfterTest();

        $markdown = "";
        for ($index = 0; $index < 501; $index++) {
            $markdown .= "# Chapter {$index}\n\nContent\n\n";
        }

        try {
            import_manager::from_markdown($markdown, 0);
            $this->fail("Markdown chapter limit was not enforced");
        } catch (moodle_exception $exception) {
            $this->assertSame("importchapterlimit", $exception->errorcode);
        }
    }
}
