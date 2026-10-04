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
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow\event;

use context_module;
use core\event\base;
use moodle_url;
use Override;

/**
 * Provides shared behavior for BookFlow events.
 */
abstract class base_event extends base {
    /**
     * Creates an event from BookFlow, chapter, and content identifiers.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @param int $userid User ID.
     * @param array $other Additional event data.
     * @return static
     */
    public static function create_from_ids(
        int $bookflowid,
        int $chapterid,
        int $contentid,
        int $userid,
        array $other = []
    ): static {
        global $DB;

        $bookflow = $DB->get_record("bookflow", ["id" => $bookflowid], "id, course", MUST_EXIST);
        $cm = get_coursemodule_from_instance("bookflow", $bookflowid, $bookflow->course, false, MUST_EXIST);
        return static::create([
            "objectid" => $bookflowid,
            "context" => context_module::instance($cm->id),
            "courseid" => $bookflow->course,
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
        return get_string("event" . $shortname, "mod_bookflow");
    }

    /**
     * Gets the human-readable event description.
     *
     * @return string
     */
    #[Override]
    public function get_description(): string {
        return get_string("eventdescription", "mod_bookflow", (object) [
            "event" => static::get_name(),
            "userid" => $this->relateduserid,
            "bookflowid" => $this->objectid,
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
        return new moodle_url("/mod/bookflow/view.php", $params);
    }

    /**
     * Gets the backup mapping for the event object ID.
     *
     * @return array
     */
    #[Override]
    public static function get_objectid_mapping(): array {
        return ["db" => "bookflow", "restore" => "bookflow"];
    }

    /**
     * Gets backup mappings for additional event data.
     *
     * @return array
     */
    #[Override]
    public static function get_other_mapping(): array {
        return [
            "chapterid" => ["db" => "bookflow_chapters", "restore" => "bookflow_chapter"],
            "contentid" => ["db" => "bookflow_contents", "restore" => "bookflow_content"],
        ];
    }
}
