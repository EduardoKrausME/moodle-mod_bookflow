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
 * lib.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_flexbook\chapter_manager;
use mod_flexbook\content_manager;

defined('MOODLE_INTERNAL') || die;
require_once(__DIR__ . "/../../lib.php");

/**
 * Generates FlexBook fixtures for automated tests.
 */
class mod_flexbook_generator extends testing_module_generator {
    /**
     * Creates a FlexBook activity fixture.
     *
     * @param mixed $record Record data.
     * @param array|null $options Generator options.
     * @return stdClass
     */
    #[Override]
    public function create_instance($record = null, ?array $options = null): stdClass {
        $record = (object) ($record ?? []);
        $record->numbering = $record->numbering ?? FLEXBOOK_NUMBERING_NUMERIC;
        $record->defaultcontenttype = $record->defaultcontenttype ?? "html";
        $record->completionmode = $record->completionmode ?? FLEXBOOK_COMPLETION_PERCENTAGE;
        $record->completionpercentage = $record->completionpercentage ?? 80;
        $record->requiremandatory = $record->requiremandatory ?? 0;
        $record->enablebookmarks = $record->enablebookmarks ?? 1;
        $record->enablenotes = $record->enablenotes ?? 1;
        $record->enablesearch = $record->enablesearch ?? 1;
        $record->enabletracking = $record->enabletracking ?? 1;
        $record->estimatedtime = $record->estimatedtime ?? 0;
        return parent::create_instance($record, $options);
    }

    /**
     * Creates a FlexBook chapter fixture.
     *
     * @param stdClass $flexbook FlexBook record.
     * @param array $data Record data.
     * @return stdClass
     */
    public function create_chapter(stdClass $flexbook, array $data = []): stdClass {
        global $DB;

        $record = (object) array_merge([
            "flexbookid" => $flexbook->id,
            "parentid" => 0,
            "title" => "Chapter",
            "description" => "",
            "descriptionformat" => FORMAT_HTML,
            "hidden" => 0,
            "required" => 0,
            "estimatedtime" => 0,
        ], $data);
        $record->id = chapter_manager::create($record);
        return $DB->get_record("flexbook_chapters", ["id" => $record->id], "*", MUST_EXIST);
    }

    /**
     * Creates a FlexBook content block fixture.
     *
     * @param stdClass $chapter Chapter wrapper.
     * @param array $data Record data.
     * @return stdClass
     */
    public function create_content($instance, $record = []) {
        global $DB;

        $chapter = $instance;
        $data = (array) $record;
        $record = (object) array_merge([
            "chapterid" => $chapter->id,
            "type" => "html",
            "title" => "Block",
            "data1" => "<p>Content</p>",
            "data2" => "",
            "data3" => "",
            "auxint1" => 0,
            "auxint2" => 0,
            "auxint3" => 0,
            "trackprogress" => 1,
            "required" => 0,
            "weight" => 1,
            "completiontype" => "view",
            "completionvalue" => 0,
            "hidden" => 0,
            "estimatedtime" => 0,
        ], $data);
        $record->id = content_manager::create($record);
        return $DB->get_record("flexbook_contents", ["id" => $record->id], "*", MUST_EXIST);
    }
}
