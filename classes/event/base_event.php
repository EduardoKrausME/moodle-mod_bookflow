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
 * base_event.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\event;

use context_module;
use core\event\base;
use moodle_url;
use Override;

/**
 * Provides shared behavior for FlexBook events.
 */
abstract class base_event extends base {
    /**
     * Creates an event from FlexBook, chapter, and content identifiers.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @param int $userid User ID.
     * @param array $other Additional event data.
     * @return static
     */
    public static function create_from_ids(
        int $flexbookid,
        int $chapterid,
        int $contentid,
        int $userid,
        array $other = []
    ): static {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "id, course", MUST_EXIST);
        $cm = get_coursemodule_from_instance("flexbook", $flexbookid, $flexbook->course, false, MUST_EXIST);
        return static::create([
            "objectid" => $flexbookid,
            "context" => context_module::instance($cm->id),
            "courseid" => $flexbook->course,
            "relateduserid" => $userid,
            "other" => array_merge([
                "chapterid" => $chapterid,
                "contentid" => $contentid,
            ], $other),
        ]);
    }

    /**
     * Gets the localized event name.
     *
     * @return string
     */
    #[Override]
    public static function get_name(): string {
        $shortname = substr(strrchr(static::class, "\\"), 1);
        return get_string("event" . $shortname, "mod_flexbook");
    }

    /**
     * Gets the human-readable event description.
     *
     * @return string
     */
    #[Override]
    public function get_description(): string {
        return get_string("eventdescription", "mod_flexbook", (object) [
            "event" => static::get_name(),
            "userid" => $this->relateduserid,
            "flexbookid" => $this->objectid,
        ]);
    }

    /**
     * Gets the URL associated with the event.
     *
     * @return moodle_url
     */
    #[Override]
    public function get_url(): moodle_url {
        $params = ["id" => $this->contextinstanceid];
        if (!empty($this->other["chapterid"])) {
            $params["chapterid"] = $this->other["chapterid"];
        }
        if (!empty($this->other["contentid"])) {
            $params["contentid"] = $this->other["contentid"];
        }
        return new moodle_url("/mod/flexbook/view.php", $params);
    }

    /**
     * Gets the backup mapping for the event object ID.
     *
     * @return array
     */
    #[Override]
    public static function get_objectid_mapping(): array {
        return ["db" => "flexbook", "restore" => "flexbook"];
    }

    /**
     * Gets backup mappings for additional event data.
     *
     * @return array
     */
    #[Override]
    public static function get_other_mapping(): array {
        return [
            "chapterid" => ["db" => "flexbook_chapters", "restore" => "flexbook_chapter"],
            "contentid" => ["db" => "flexbook_contents", "restore" => "flexbook_content"],
        ];
    }
}
