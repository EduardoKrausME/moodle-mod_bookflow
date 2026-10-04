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
 * api.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow\external;

use context_module;
use core_text;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_bookflow\event\bookmark_created;
use mod_bookflow\event\bookmark_deleted;
use mod_bookflow\event\note_created;
use mod_bookflow\event\note_deleted;
use mod_bookflow\event\note_updated;
use mod_bookflow\content_manager;
use mod_bookflow\progress\progress_manager;
use moodle_exception;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/externallib.php");

/**
 * Exposes the BookFlow AJAX web service functions.
 */
class api extends external_api {
    /**
     * Defines parameters for the mark contents viewed external function.
     *
     * @return external_function_parameters
     */
    public static function mark_contents_viewed_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "contentids" => new external_multiple_structure(
                new external_value(PARAM_INT, "Content id")
            ),
        ]);
    }

    /**
     * Marks contents viewed.
     *
     * @param int $bookflowid BookFlow ID.
     * @param array $contentids Ordered content block IDs.
     * @return array
     */
    public static function mark_contents_viewed(int $bookflowid, array $contentids): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::mark_contents_viewed_parameters(), [
            "bookflowid" => $bookflowid,
            "contentids" => $contentids,
        ]);
        $context = self::require_instance($params["bookflowid"])[2];
        self::validate_context($context);
        $manager = new progress_manager();
        $transaction = $DB->start_delegated_transaction();
        foreach (array_unique($params["contentids"]) as $contentid) {
            $manager->mark_content_viewed($params["bookflowid"], $USER->id, $contentid);
        }
        $transaction->allow_commit();
        return self::progress_result($params["bookflowid"], $USER->id);
    }

    /**
     * Defines the return structure for the mark contents viewed external function.
     *
     * @return external_single_structure
     */
    public static function mark_contents_viewed_returns(): external_single_structure {
        return self::progress_structure();
    }

    /**
     * Defines parameters for the mark content completed external function.
     *
     * @return external_function_parameters
     */
    public static function mark_content_completed_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "contentid" => new external_value(PARAM_INT, "Content id"),
            "metric" => new external_value(PARAM_FLOAT, "Validated progress evidence", VALUE_DEFAULT, 100),
            "details" => new external_value(PARAM_RAW, "JSON evidence", VALUE_DEFAULT, "{}"),
        ]);
    }

    /**
     * Marks content completed.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $contentid Content block ID.
     * @param float $metric Completion metric reported by the client.
     * @param string $details Supporting completion evidence.
     * @return array
     */
    public static function mark_content_completed(
        int $bookflowid,
        int $contentid,
        float $metric = 100,
        string $details = "{}"
    ): array {
        global $USER;

        $params = self::validate_parameters(self::mark_content_completed_parameters(), compact(
            "bookflowid",
            "contentid",
            "metric",
            "details"
        ));
        $context = self::require_instance($params["bookflowid"])[2];
        self::validate_context($context);
        $decoded = json_decode($params["details"], true);
        if (!is_array($decoded)) {
            throw new invalid_parameter_exception("Invalid evidence JSON");
        }
        $manager = new progress_manager();
        $manager->update_content_metric(
            $params["bookflowid"],
            $USER->id,
            $params["contentid"],
            $params["metric"],
            $decoded
        );
        return self::progress_result($params["bookflowid"], $USER->id);
    }

    /**
     * Defines the return structure for the mark content completed external function.
     *
     * @return external_single_structure
     */
    public static function mark_content_completed_returns(): external_single_structure {
        return self::progress_structure();
    }

    /**
     * Defines parameters for the save user position external function.
     *
     * @return external_function_parameters
     */
    public static function save_user_position_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id"),
            "contentid" => new external_value(PARAM_INT, "Content id", VALUE_DEFAULT, 0),
            "scrollposition" => new external_value(PARAM_INT, "Approximate scroll position", VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Saves user position.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @param int $scrollposition Approximate vertical reading position.
     * @return array
     */
    public static function save_user_position(
        int $bookflowid,
        int $chapterid,
        int $contentid = 0,
        int $scrollposition = 0
    ): array {
        global $USER;

        $params = self::validate_parameters(self::save_user_position_parameters(), compact(
            "bookflowid",
            "chapterid",
            "contentid",
            "scrollposition"
        ));
        $context = self::require_instance($params["bookflowid"])[2];
        self::validate_context($context);
        $manager = new progress_manager();
        $manager->save_last_position(
            $params["bookflowid"],
            $USER->id,
            $params["chapterid"],
            $params["contentid"] ?: null,
            $params["scrollposition"]
        );
        return ["saved" => true];
    }

    /**
     * Defines the return structure for the save user position external function.
     *
     * @return external_single_structure
     */
    public static function save_user_position_returns(): external_single_structure {
        return new external_single_structure([
            "saved" => new external_value(PARAM_BOOL, "Saved"),
        ]);
    }

    /**
     * Defines parameters for the get user progress external function.
     *
     * @return external_function_parameters
     */
    public static function get_user_progress_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
        ]);
    }

    /**
     * Gets user progress.
     *
     * @param int $bookflowid BookFlow ID.
     * @return array
     */
    public static function get_user_progress(int $bookflowid): array {
        global $USER;

        $params = self::validate_parameters(self::get_user_progress_parameters(), compact("bookflowid"));
        $context = self::require_instance($params["bookflowid"])[2];
        self::validate_context($context);
        return self::progress_result($params["bookflowid"], $USER->id);
    }

    /**
     * Defines the return structure for the get user progress external function.
     *
     * @return external_single_structure
     */
    public static function get_user_progress_returns(): external_single_structure {
        return self::progress_structure();
    }

    /**
     * Defines parameters for the get chapter progress external function.
     *
     * @return external_function_parameters
     */
    public static function get_chapter_progress_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id"),
        ]);
    }

    /**
     * Gets chapter progress.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $chapterid Chapter ID.
     * @return array
     */
    public static function get_chapter_progress(int $bookflowid, int $chapterid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_chapter_progress_parameters(), compact(
            "bookflowid",
            "chapterid"
        ));
        $context = self::require_instance($params["bookflowid"])[2];
        self::validate_context($context);
        $DB->get_record("bookflow_chapters", [
            "id" => $params["chapterid"],
            "bookflowid" => $params["bookflowid"],
        ], "*", MUST_EXIST);

        $sql = "SELECT c.id, c.weight, p.status
                  FROM {bookflow_contents} c
             LEFT JOIN {bookflow_user_progress} p
                    ON p.contentid = c.id AND p.userid = :userid
                 WHERE c.chapterid = :chapterid
                   AND c.hidden = 0
                   AND c.trackprogress = 1";
        $records = $DB->get_records_sql($sql, [
            "userid" => $USER->id,
            "chapterid" => $params["chapterid"],
        ]);
        $total = 0.0;
        $completed = 0.0;
        foreach ($records as $record) {
            $weight = max(0, $record->weight);
            $total += $weight;
            if ($record->status == progress_manager::STATUS_COMPLETED) {
                $completed += $weight;
            }
        }
        return [
            "percentage" => $total > 0 ? round(min(100, $completed / $total * 100), 2) : 0,
            "completed" => $completed,
            "total" => $total,
        ];
    }

    /**
     * Defines the return structure for the get chapter progress external function.
     *
     * @return external_single_structure
     */
    public static function get_chapter_progress_returns(): external_single_structure {
        return new external_single_structure([
            "percentage" => new external_value(PARAM_FLOAT, "Percentage"),
            "completed" => new external_value(PARAM_FLOAT, "Completed weight"),
            "total" => new external_value(PARAM_FLOAT, "Total weight"),
        ]);
    }

    /**
     * Defines parameters for the create bookmark external function.
     *
     * @return external_function_parameters
     */
    public static function create_bookmark_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "itemtype" => new external_value(PARAM_ALPHA, "book, chapter or content"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id", VALUE_DEFAULT, 0),
            "contentid" => new external_value(PARAM_INT, "Content id", VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Creates a private bookmark for the current user.
     *
     * @param int $bookflowid BookFlow ID.
     * @param string $itemtype Bookmark target type.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @return array
     */
    public static function create_bookmark(
        int $bookflowid,
        string $itemtype,
        int $chapterid = 0,
        int $contentid = 0
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::create_bookmark_parameters(), compact(
            "bookflowid",
            "itemtype",
            "chapterid",
            "contentid"
        ));
        [$bookflow, , $context] = self::require_instance($params["bookflowid"]);
        self::validate_context($context);
        if (!$bookflow->enablebookmarks || !in_array($params["itemtype"], ["book", "chapter", "content"])) {
            throw new invalid_parameter_exception("Bookmarks are unavailable");
        }
        $validtarget = ($params["itemtype"] == "book" && !$params["chapterid"] && !$params["contentid"])
            || ($params["itemtype"] == "chapter" && $params["chapterid"] && !$params["contentid"])
            || ($params["itemtype"] == "content" && $params["contentid"]);
        if (!$validtarget) {
            throw new invalid_parameter_exception("Invalid bookmark target");
        }
        self::validate_item($params["bookflowid"], $params["chapterid"], $params["contentid"]);
        $conditions = [
            "bookflowid" => $params["bookflowid"],
            "userid" => $USER->id,
            "itemtype" => $params["itemtype"],
            "chapterid" => $params["chapterid"],
            "contentid" => $params["contentid"],
        ];
        $id = $DB->get_field("bookflow_bookmarks", "id", $conditions);
        if (!$id) {
            $id = $DB->insert_record("bookflow_bookmarks", (object) ($conditions + ["timecreated" => time()]));
            bookmark_created::create_from_ids(
                $params["bookflowid"],
                $params["chapterid"],
                $params["contentid"],
                $USER->id
            )->trigger();
        }
        return ["id" => $id, "created" => true];
    }

    /**
     * Defines the return structure for the create bookmark external function.
     *
     * @return external_single_structure
     */
    public static function create_bookmark_returns(): external_single_structure {
        return new external_single_structure([
            "id" => new external_value(PARAM_INT, "Bookmark id"),
            "created" => new external_value(PARAM_BOOL, "Created"),
        ]);
    }

    /**
     * Defines parameters for the delete bookmark external function.
     *
     * @return external_function_parameters
     */
    public static function delete_bookmark_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "bookmarkid" => new external_value(PARAM_INT, "Bookmark id"),
        ]);
    }

    /**
     * Deletes a private bookmark owned by the current user.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $bookmarkid Bookmark ID.
     * @return array
     */
    public static function delete_bookmark(int $bookflowid, int $bookmarkid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::delete_bookmark_parameters(), compact(
            "bookflowid",
            "bookmarkid"
        ));
        $context = self::require_instance($params["bookflowid"])[2];
        self::validate_context($context);
        $bookmark = $DB->get_record("bookflow_bookmarks", [
            "id" => $params["bookmarkid"],
            "bookflowid" => $params["bookflowid"],
            "userid" => $USER->id,
        ], "*", MUST_EXIST);
        $DB->delete_records("bookflow_bookmarks", ["id" => $bookmark->id]);
        bookmark_deleted::create_from_ids(
            $params["bookflowid"],
            $bookmark->chapterid,
            $bookmark->contentid,
            $USER->id
        )->trigger();
        return ["deleted" => true];
    }

    /**
     * Defines the return structure for the delete bookmark external function.
     *
     * @return external_single_structure
     */
    public static function delete_bookmark_returns(): external_single_structure {
        return new external_single_structure(["deleted" => new external_value(PARAM_BOOL, "Deleted")]);
    }

    /**
     * Defines parameters for the create note external function.
     *
     * @return external_function_parameters
     */
    public static function create_note_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id", VALUE_DEFAULT, 0),
            "contentid" => new external_value(PARAM_INT, "Content id", VALUE_DEFAULT, 0),
            "note" => new external_value(PARAM_TEXT, "Private note"),
            "selectiontext" => new external_value(PARAM_TEXT, "Selected text", VALUE_DEFAULT, ""),
        ]);
    }

    /**
     * Creates a private note for the current user.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @param string $note Private note text.
     * @param string $selectiontext Selected source text.
     * @return array
     */
    public static function create_note(
        int $bookflowid,
        int $chapterid,
        int $contentid,
        string $note,
        string $selectiontext = ""
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::create_note_parameters(), compact(
            "bookflowid",
            "chapterid",
            "contentid",
            "note",
            "selectiontext"
        ));
        [$bookflow, , $context] = self::require_instance($params["bookflowid"]);
        self::validate_context($context);
        if (!$bookflow->enablenotes) {
            throw new moodle_exception("notesdisabled", "mod_bookflow");
        }
        if (!$params["chapterid"] && !$params["contentid"]) {
            throw new invalid_parameter_exception("A note must belong to a chapter or content block");
        }
        self::validate_item($params["bookflowid"], $params["chapterid"], $params["contentid"]);
        $now = time();
        $id = $DB->insert_record("bookflow_notes", (object) [
            "bookflowid" => $params["bookflowid"],
            "userid" => $USER->id,
            "chapterid" => $params["chapterid"],
            "contentid" => $params["contentid"],
            "note" => $params["note"],
            "selectiontext" => $params["selectiontext"],
            "timecreated" => $now,
            "timemodified" => $now,
        ]);
        note_created::create_from_ids(
            $params["bookflowid"],
            $params["chapterid"],
            $params["contentid"],
            $USER->id
        )->trigger();
        return ["id" => $id, "note" => $params["note"], "timemodified" => $now];
    }

    /**
     * Defines the return structure for the create note external function.
     *
     * @return external_single_structure
     */
    public static function create_note_returns(): external_single_structure {
        return self::note_structure();
    }

    /**
     * Defines parameters for the update note external function.
     *
     * @return external_function_parameters
     */
    public static function update_note_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "noteid" => new external_value(PARAM_INT, "Note id"),
            "note" => new external_value(PARAM_TEXT, "Private note"),
        ]);
    }

    /**
     * Updates a private note owned by the current user.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $noteid Note ID.
     * @param string $note Private note text.
     * @return array
     */
    public static function update_note(int $bookflowid, int $noteid, string $note): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::update_note_parameters(), compact(
            "bookflowid",
            "noteid",
            "note"
        ));
        $context = self::require_instance($params["bookflowid"])[2];
        self::validate_context($context);
        $record = $DB->get_record("bookflow_notes", [
            "id" => $params["noteid"],
            "bookflowid" => $params["bookflowid"],
            "userid" => $USER->id,
        ], "*", MUST_EXIST);
        $record->note = $params["note"];
        $record->timemodified = time();
        $DB->update_record("bookflow_notes", $record);
        note_updated::create_from_ids(
            $params["bookflowid"],
            $record->chapterid,
            $record->contentid,
            $USER->id
        )->trigger();
        return ["id" => $record->id, "note" => $record->note, "timemodified" => $record->timemodified];
    }

    /**
     * Defines the return structure for the update note external function.
     *
     * @return external_single_structure
     */
    public static function update_note_returns(): external_single_structure {
        return self::note_structure();
    }

    /**
     * Defines parameters for the delete note external function.
     *
     * @return external_function_parameters
     */
    public static function delete_note_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "noteid" => new external_value(PARAM_INT, "Note id"),
        ]);
    }

    /**
     * Deletes a private note owned by the current user.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $noteid Note ID.
     * @return array
     */
    public static function delete_note(int $bookflowid, int $noteid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::delete_note_parameters(), compact("bookflowid", "noteid"));
        $context = self::require_instance($params["bookflowid"])[2];
        self::validate_context($context);
        $record = $DB->get_record("bookflow_notes", [
            "id" => $params["noteid"],
            "bookflowid" => $params["bookflowid"],
            "userid" => $USER->id,
        ], "*", MUST_EXIST);
        $DB->delete_records("bookflow_notes", ["id" => $record->id]);
        note_deleted::create_from_ids(
            $params["bookflowid"],
            $record->chapterid,
            $record->contentid,
            $USER->id
        )->trigger();
        return ["deleted" => true];
    }

    /**
     * Defines the return structure for the delete note external function.
     *
     * @return external_single_structure
     */
    public static function delete_note_returns(): external_single_structure {
        return new external_single_structure(["deleted" => new external_value(PARAM_BOOL, "Deleted")]);
    }

    /**
     * Defines parameters for the search contents external function.
     *
     * @return external_function_parameters
     */
    public static function search_contents_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "query" => new external_value(PARAM_TEXT, "Search query"),
        ]);
    }

    /**
     * Searches accessible published BookFlow content.
     *
     * @param int $bookflowid BookFlow ID.
     * @param string $query Search query.
     * @return array
     */
    public static function search_contents(int $bookflowid, string $query): array {
        global $DB;

        $params = self::validate_parameters(self::search_contents_parameters(), compact("bookflowid", "query"));
        [$bookflow, $cm, $context] = self::require_instance($params["bookflowid"]);
        self::validate_context($context);
        if (!$bookflow->enablesearch || core_text::strlen(trim($params["query"])) < 2) {
            return [];
        }
        $like = "%" . $DB->sql_like_escape($params["query"]) . "%";
        $hiddenclause = has_capability("mod/bookflow:managecontent", $context)
            ? ""
            : " AND ch.hidden = 0 AND c.hidden = 0";
        $sql = "SELECT c.id, c.chapterid, c.type, c.title, c.data1, c.data2, c.data3,
                       ch.title AS chaptertitle
                  FROM {bookflow_contents} c
                  JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
                 WHERE ch.bookflowid = :bookflowid
                   {$hiddenclause}
                   AND (" . $DB->sql_like("c.title", ":q1", false) . "
                    OR " . $DB->sql_like("c.data1", ":q2", false) . "
                    OR " . $DB->sql_like("c.data2", ":q3", false) . "
                    OR " . $DB->sql_like("c.data3", ":q4", false) . ")
              ORDER BY ch.sortorder, c.sortorder";
        $records = $DB->get_records_sql($sql, [
            "bookflowid" => $params["bookflowid"],
            "q1" => $like,
            "q2" => $like,
            "q3" => $like,
            "q4" => $like,
        ], 0, 100);
        $results = [];
        foreach ($records as $record) {
            $plain = trim(html_to_text($record->data1 ?? "", 0, false));
            $results[] = [
                "chapter" => format_string($record->chaptertitle),
                "excerpt" => shorten_text($plain, 180),
                "type" => $record->type,
                "url" => (new moodle_url("/mod/bookflow/view.php", [
                    "id" => $cm->id,
                    "chapterid" => $record->chapterid,
                ], "bookflow-content-{$record->id}"))->out(false),
            ];
        }
        return $results;
    }

    /**
     * Defines the return structure for the search contents external function.
     *
     * @return external_multiple_structure
     */
    public static function search_contents_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            "chapter" => new external_value(PARAM_TEXT, "Chapter"),
            "excerpt" => new external_value(PARAM_TEXT, "Excerpt"),
            "type" => new external_value(PARAM_ALPHANUMEXT, "Content type"),
            "url" => new external_value(PARAM_URL, "Direct URL"),
        ]));
    }

    /**
     * Defines parameters for the reorder contents external function.
     *
     * @return external_function_parameters
     */
    public static function reorder_contents_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id"),
            "contentids" => new external_multiple_structure(new external_value(PARAM_INT, "Content id")),
        ]);
    }

    /**
     * Validates and applies a chapter content order.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $chapterid Chapter ID.
     * @param array $contentids Ordered content block IDs.
     * @return array
     */
    public static function reorder_contents(int $bookflowid, int $chapterid, array $contentids): array {
        $params = self::validate_parameters(self::reorder_contents_parameters(), compact(
            "bookflowid",
            "chapterid",
            "contentids"
        ));
        [$bookflow, $cm, $context] = self::require_instance($params["bookflowid"], "mod/bookflow:managecontent");
        self::validate_context($context);
        self::validate_item($params["bookflowid"], $params["chapterid"], 0);
        content_manager::reorder($params["chapterid"], $params["contentids"]);
        return ["saved" => true];
    }

    /**
     * Defines the return structure for the reorder contents external function.
     *
     * @return external_single_structure
     */
    public static function reorder_contents_returns(): external_single_structure {
        return new external_single_structure(["saved" => new external_value(PARAM_BOOL, "Saved")]);
    }

    /**
     * Validates access to a BookFlow activity instance.
     *
     * @param int $bookflowid BookFlow ID.
     * @param string $capability Required module capability.
     * @return array
     */
    private static function require_instance(
        int $bookflowid,
        string $capability = "mod/bookflow:view"
    ): array {
        global $DB;

        require_sesskey();
        $bookflow = $DB->get_record("bookflow", ["id" => $bookflowid], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance("bookflow", $bookflowid, $bookflow->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_login($bookflow->course, false, $cm);
        require_capability($capability, $context);
        return [$bookflow, $cm, $context];
    }

    /**
     * Validates that a chapter and content block belong to the BookFlow.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @return void
     */
    private static function validate_item(int $bookflowid, int $chapterid, int $contentid): void {
        global $DB;

        if ($chapterid) {
            $DB->get_record("bookflow_chapters", [
                "id" => $chapterid,
                "bookflowid" => $bookflowid,
            ], "*", MUST_EXIST);
        }
        if ($contentid) {
            $sql = "SELECT c.id, c.chapterid
                      FROM {bookflow_contents} c
                      JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
                     WHERE c.id = :contentid AND ch.bookflowid = :bookflowid";
            $record = $DB->get_record_sql($sql, [
                "contentid" => $contentid,
                "bookflowid" => $bookflowid,
            ], MUST_EXIST);
            if ($chapterid && $record->chapterid != $chapterid) {
                throw new invalid_parameter_exception("Content does not belong to chapter");
            }
        }
    }

    /**
     * Builds the external function progress result.
     *
     * @param int $bookflowid BookFlow ID.
     * @param int $userid User ID.
     * @return array
     */
    private static function progress_result(int $bookflowid, int $userid): array {
        global $DB;

        $manager = new progress_manager();
        $progress = $manager->calculate_user_progress($bookflowid, $userid);
        $stats = $DB->get_record_sql(
            "SELECT COUNT(c.id) AS total,
                    COALESCE(SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END), 0) AS completed
               FROM {bookflow_contents} c
               JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
          LEFT JOIN {bookflow_user_progress} p
                 ON p.contentid = c.id
                AND p.userid = :userid
              WHERE ch.bookflowid = :bookflowid
                AND ch.hidden = 0
                AND c.hidden = 0
                AND c.trackprogress = 1
                AND c.completiontype <> 'none'",
            [
                "completed" => progress_manager::STATUS_COMPLETED,
                "userid" => $userid,
                "bookflowid" => $bookflowid,
            ]
        );
        $completed = (int) ($stats->completed ?? 0);
        $total = (int) ($stats->total ?? 0);
        return [
            "percentage" => $progress,
            "completed" => $completed,
            "total" => $total,
            "pendingrequired" => $manager->get_pending_required_count($bookflowid, $userid),
            "activitycompleted" => $manager->completion_requirements_met($bookflowid, $userid, $progress),
        ];
    }

    /**
     * Defines the external progress result structure.
     *
     * @return external_single_structure
     */
    private static function progress_structure(): external_single_structure {
        return new external_single_structure([
            "percentage" => new external_value(PARAM_FLOAT, "Percentage"),
            "completed" => new external_value(PARAM_INT, "Completed block count"),
            "total" => new external_value(PARAM_INT, "Tracked block count"),
            "pendingrequired" => new external_value(PARAM_INT, "Pending required block count"),
            "activitycompleted" => new external_value(PARAM_BOOL, "Moodle completion requirements met"),
        ]);
    }

    /**
     * Defines the external note result structure.
     *
     * @return external_single_structure
     */
    private static function note_structure(): external_single_structure {
        return new external_single_structure([
            "id" => new external_value(PARAM_INT, "Note id"),
            "note" => new external_value(PARAM_TEXT, "Note"),
            "timemodified" => new external_value(PARAM_INT, "Modification time"),
        ]);
    }
}
