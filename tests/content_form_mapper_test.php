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
 * Tests type-specific content form mapping.
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use advanced_testcase;
use mod_flexbook\form\content_form_mapper;

/**
 * Tests conversion between friendly forms and generic content storage.
 *
 * @covers \mod_flexbook\form\content_form_mapper
 */
final class content_form_mapper_test extends advanced_testcase {
    /**
     * Tests accordion rows are stored as JSON without blank rows.
     *
     * @return void
     */
    public function test_accordion_is_mapped_to_structured_json(): void {
        $data = (object) [
            "itemtitle" => ["First", "", "Second"],
            "itemcontent" => ["Content 1", "", "Content 2"],
        ];

        $result = content_form_mapper::to_record($data, "accordion");
        $items = json_decode($result->data1, true);

        $this->assertSame([
            ["title" => "First", "content" => "Content 1"],
            ["title" => "Second", "content" => "Content 2"],
        ], $items);
        $this->assertSame(2, $result->auxint1);
        $this->assertObjectNotHasProperty("itemtitle", $result);
        $this->assertObjectNotHasProperty("itemcontent", $result);
    }

    /**
     * Tests rich-text structured items read the editor text value.
     *
     * @return void
     */
    public function test_accordion_accepts_editor_values(): void {
        $data = (object) [
            "itemtitle" => ["Rich item"],
            "itemcontent" => [[
                "text" => "<p>Rich <strong>content</strong></p>",
                "format" => FORMAT_HTML,
                "itemid" => 123,
            ]],
        ];

        $result = content_form_mapper::to_record($data, "accordion");
        $items = json_decode($result->data1, true);

        $this->assertSame("<p>Rich <strong>content</strong></p>", $items[0]["content"]);
        $this->assertSame(1, $result->auxint1);
    }

    /**
     * Tests flashcard rows are stored in the format consumed by the renderer.
     *
     * @return void
     */
    public function test_flashcards_are_mapped_to_front_and_back_pairs(): void {
        $data = (object) [
            "cardfront" => ["Front 1", "Front 2"],
            "cardback" => ["Back 1", "Back 2"],
        ];

        $result = content_form_mapper::to_record($data, "flashcards");

        $this->assertSame([
            ["front" => "Front 1", "back" => "Back 1"],
            ["front" => "Front 2", "back" => "Back 2"],
        ], json_decode($result->data1, true));
        $this->assertSame(2, $result->auxint1);
    }

    /**
     * Tests question options and the correct answer are stored in their expected fields.
     *
     * @return void
     */
    public function test_question_uses_data2_for_options_and_data3_for_configuration(): void {
        $data = (object) [
            "optiontext" => ["Option A", "Option B"],
            "optionvalue" => ["a", "b"],
            "optioncorrect" => [0, 1],
            "questionfeedback" => "Correct explanation",
        ];

        $result = content_form_mapper::to_record($data, "question");

        $this->assertSame([
            ["title" => "Option A", "value" => "a"],
            ["title" => "Option B", "value" => "b"],
        ], json_decode($result->data2, true));
        $this->assertSame([
            "answer" => "b",
            "feedback" => "Correct explanation",
        ], json_decode($result->data3, true));
    }

    /**
     * Tests existing option values remain stable when a question is edited.
     *
     * @return void
     */
    public function test_question_preserves_existing_option_values(): void {
        $data = (object) [
            "optiontext" => ["Changed first", "Changed second"],
            "optionvalue" => ["original_a", "original_b"],
            "optioncorrect" => [1, 0],
            "questionfeedback" => "",
        ];

        $result = content_form_mapper::to_record($data, "question");
        $options = json_decode($result->data2, true);

        $this->assertSame("original_a", $options[0]["value"]);
        $this->assertSame("original_b", $options[1]["value"]);
        $this->assertSame("original_a", json_decode($result->data3, true)["answer"]);
    }

    /**
     * Tests media metadata maps to the legacy storage fields.
     *
     * @return void
     */
    public function test_video_metadata_keeps_legacy_storage_compatibility(): void {
        $data = (object) [
            "sourceurl" => "https://example.com/video.mp4",
            "captionsurl" => "https://example.com/captions.vtt",
            "transcript" => "Transcript",
        ];

        $result = content_form_mapper::to_record($data, "video");

        $this->assertSame("https://example.com/video.mp4", $result->data1);
        $this->assertSame("https://example.com/captions.vtt", $result->data2);
        $this->assertSame("Transcript", $result->data3);
    }
}
