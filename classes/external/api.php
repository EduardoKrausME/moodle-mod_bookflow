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
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\external;

use context_module;
use core_text;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_flexbook\event\bookmark_created;
use mod_flexbook\event\bookmark_deleted;
use mod_flexbook\event\note_created;
use mod_flexbook\event\note_deleted;
use mod_flexbook\event\note_updated;
use mod_flexbook\event\question_answered;
use mod_flexbook\content_manager;
use mod_flexbook\progress\progress_manager;
use moodle_exception;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/externallib.php");

/**
 * Exposes the FlexBook AJAX web service functions.
 */
class api extends external_api {
    /**
     * Defines parameters for the mark contents viewed external function.
     *
     * @return external_function_parameters
     */
    public static function mark_contents_viewed_parameters(): external_function_parameters {
        return new external_function_parameters([
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "contentids" => new external_multiple_structure(
                new external_value(PARAM_INT, "Content id")
            ),
        ]);
    }

    /**
     * Marks contents viewed.
     *
     * @param int $flexbookid FlexBook ID.
     * @param array $contentids Ordered content block IDs.
     * @return array
     */
    public static function mark_contents_viewed(int $flexbookid, array $contentids): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::mark_contents_viewed_parameters(), [
            "flexbookid" => $flexbookid,
            "contentids" => $contentids,
        ]);
        self::require_instance($params["flexbookid"]);
        $manager = new progress_manager();
        $transaction = $DB->start_delegated_transaction();
        foreach (array_unique($params["contentids"]) as $contentid) {
            $manager->mark_content_viewed($params["flexbookid"], $USER->id, $contentid);
        }
        $transaction->allow_commit();
        return self::progress_result($params["flexbookid"], $USER->id);
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "contentid" => new external_value(PARAM_INT, "Content id"),
            "metric" => new external_value(PARAM_FLOAT, "Validated progress evidence", VALUE_DEFAULT, 100),
            "details" => new external_value(PARAM_RAW, "JSON evidence", VALUE_DEFAULT, "{}"),
        ]);
    }

    /**
     * Marks content completed.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $contentid Content block ID.
     * @param float $metric Completion metric reported by the client.
     * @param string $details Supporting completion evidence.
     * @return array
     */
    public static function mark_content_completed(
        int $flexbookid,
        int $contentid,
        float $metric = 100,
        string $details = "{}"
    ): array {
        global $USER;

        $params = self::validate_parameters(self::mark_content_completed_parameters(), compact(
            "flexbookid",
            "contentid",
            "metric",
            "details"
        ));
        self::require_instance($params["flexbookid"]);
        $decoded = json_decode($params["details"], true);
        if (!is_array($decoded)) {
            throw new invalid_parameter_exception("Invalid evidence JSON");
        }
        $manager = new progress_manager();
        $manager->update_content_metric(
            $params["flexbookid"],
            $USER->id,
            $params["contentid"],
            $params["metric"],
            $decoded
        );
        return self::progress_result($params["flexbookid"], $USER->id);
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id"),
            "contentid" => new external_value(PARAM_INT, "Content id", VALUE_DEFAULT, 0),
            "scrollposition" => new external_value(PARAM_INT, "Approximate scroll position", VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Saves user position.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @param int $scrollposition Approximate vertical reading position.
     * @return array
     */
    public static function save_user_position(
        int $flexbookid,
        int $chapterid,
        int $contentid = 0,
        int $scrollposition = 0
    ): array {
        global $USER;

        $params = self::validate_parameters(self::save_user_position_parameters(), compact(
            "flexbookid",
            "chapterid",
            "contentid",
            "scrollposition"
        ));
        self::require_instance($params["flexbookid"]);
        $manager = new progress_manager();
        $manager->save_last_position(
            $params["flexbookid"],
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
        ]);
    }

    /**
     * Gets user progress.
     *
     * @param int $flexbookid FlexBook ID.
     * @return array
     */
    public static function get_user_progress(int $flexbookid): array {
        global $USER;

        $params = self::validate_parameters(self::get_user_progress_parameters(), compact("flexbookid"));
        self::require_instance($params["flexbookid"]);
        return self::progress_result($params["flexbookid"], $USER->id);
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id"),
        ]);
    }

    /**
     * Gets chapter progress.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $chapterid Chapter ID.
     * @return array
     */
    public static function get_chapter_progress(int $flexbookid, int $chapterid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_chapter_progress_parameters(), compact(
            "flexbookid",
            "chapterid"
        ));
        self::require_instance($params["flexbookid"]);
        $DB->get_record("flexbook_chapters", [
            "id" => $params["chapterid"],
            "flexbookid" => $params["flexbookid"],
        ], "*", MUST_EXIST);

        $sql = "SELECT c.id, c.weight, p.status
                  FROM {flexbook_contents} c
             LEFT JOIN {flexbook_user_progress} p
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "itemtype" => new external_value(PARAM_ALPHA, "book, chapter or content"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id", VALUE_DEFAULT, 0),
            "contentid" => new external_value(PARAM_INT, "Content id", VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Creates a private bookmark for the current user.
     *
     * @param int $flexbookid FlexBook ID.
     * @param string $itemtype Bookmark target type.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @return array
     */
    public static function create_bookmark(
        int $flexbookid,
        string $itemtype,
        int $chapterid = 0,
        int $contentid = 0
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::create_bookmark_parameters(), compact(
            "flexbookid",
            "itemtype",
            "chapterid",
            "contentid"
        ));
        [$flexbook] = self::require_instance($params["flexbookid"]);
        if (!$flexbook->enablebookmarks || !in_array($params["itemtype"], ["book", "chapter", "content"])) {
            throw new invalid_parameter_exception("Bookmarks are unavailable");
        }
        $validtarget = ($params["itemtype"] == "book" && !$params["chapterid"] && !$params["contentid"])
            || ($params["itemtype"] == "chapter" && $params["chapterid"] && !$params["contentid"])
            || ($params["itemtype"] == "content" && $params["contentid"]);
        if (!$validtarget) {
            throw new invalid_parameter_exception("Invalid bookmark target");
        }
        self::validate_item($params["flexbookid"], $params["chapterid"], $params["contentid"]);
        $conditions = [
            "flexbookid" => $params["flexbookid"],
            "userid" => $USER->id,
            "itemtype" => $params["itemtype"],
            "chapterid" => $params["chapterid"],
            "contentid" => $params["contentid"],
        ];
        $id = $DB->get_field("flexbook_bookmarks", "id", $conditions);
        if (!$id) {
            $id = $DB->insert_record("flexbook_bookmarks", (object) ($conditions + ["timecreated" => time()]));
            bookmark_created::create_from_ids(
                $params["flexbookid"],
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "bookmarkid" => new external_value(PARAM_INT, "Bookmark id"),
        ]);
    }

    /**
     * Deletes a private bookmark owned by the current user.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $bookmarkid Bookmark ID.
     * @return array
     */
    public static function delete_bookmark(int $flexbookid, int $bookmarkid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::delete_bookmark_parameters(), compact(
            "flexbookid",
            "bookmarkid"
        ));
        self::require_instance($params["flexbookid"]);
        $bookmark = $DB->get_record("flexbook_bookmarks", [
            "id" => $params["bookmarkid"],
            "flexbookid" => $params["flexbookid"],
            "userid" => $USER->id,
        ], "*", MUST_EXIST);
        $DB->delete_records("flexbook_bookmarks", ["id" => $bookmark->id]);
        bookmark_deleted::create_from_ids(
            $params["flexbookid"],
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id", VALUE_DEFAULT, 0),
            "contentid" => new external_value(PARAM_INT, "Content id", VALUE_DEFAULT, 0),
            "note" => new external_value(PARAM_TEXT, "Private note"),
            "selectiontext" => new external_value(PARAM_TEXT, "Selected text", VALUE_DEFAULT, ""),
        ]);
    }

    /**
     * Creates a private note for the current user.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @param string $note Private note text.
     * @param string $selectiontext Selected source text.
     * @return array
     */
    public static function create_note(
        int $flexbookid,
        int $chapterid,
        int $contentid,
        string $note,
        string $selectiontext = ""
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::create_note_parameters(), compact(
            "flexbookid",
            "chapterid",
            "contentid",
            "note",
            "selectiontext"
        ));
        [$flexbook] = self::require_instance($params["flexbookid"]);
        if (!$flexbook->enablenotes) {
            throw new moodle_exception("notesdisabled", "mod_flexbook");
        }
        if (!$params["chapterid"] && !$params["contentid"]) {
            throw new invalid_parameter_exception("A note must belong to a chapter or content block");
        }
        self::validate_item($params["flexbookid"], $params["chapterid"], $params["contentid"]);
        $now = time();
        $id = $DB->insert_record("flexbook_notes", (object) [
            "flexbookid" => $params["flexbookid"],
            "userid" => $USER->id,
            "chapterid" => $params["chapterid"],
            "contentid" => $params["contentid"],
            "note" => $params["note"],
            "selectiontext" => $params["selectiontext"],
            "timecreated" => $now,
            "timemodified" => $now,
        ]);
        note_created::create_from_ids(
            $params["flexbookid"],
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "noteid" => new external_value(PARAM_INT, "Note id"),
            "note" => new external_value(PARAM_TEXT, "Private note"),
        ]);
    }

    /**
     * Updates a private note owned by the current user.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $noteid Note ID.
     * @param string $note Private note text.
     * @return array
     */
    public static function update_note(int $flexbookid, int $noteid, string $note): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::update_note_parameters(), compact(
            "flexbookid",
            "noteid",
            "note"
        ));
        self::require_instance($params["flexbookid"]);
        $record = $DB->get_record("flexbook_notes", [
            "id" => $params["noteid"],
            "flexbookid" => $params["flexbookid"],
            "userid" => $USER->id,
        ], "*", MUST_EXIST);
        $record->note = $params["note"];
        $record->timemodified = time();
        $DB->update_record("flexbook_notes", $record);
        note_updated::create_from_ids(
            $params["flexbookid"],
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "noteid" => new external_value(PARAM_INT, "Note id"),
        ]);
    }

    /**
     * Deletes a private note owned by the current user.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $noteid Note ID.
     * @return array
     */
    public static function delete_note(int $flexbookid, int $noteid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::delete_note_parameters(), compact("flexbookid", "noteid"));
        self::require_instance($params["flexbookid"]);
        $record = $DB->get_record("flexbook_notes", [
            "id" => $params["noteid"],
            "flexbookid" => $params["flexbookid"],
            "userid" => $USER->id,
        ], "*", MUST_EXIST);
        $DB->delete_records("flexbook_notes", ["id" => $record->id]);
        note_deleted::create_from_ids(
            $params["flexbookid"],
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
     * Defines parameters for the save highlight external function.
     *
     * @return external_function_parameters
     */
    public static function save_highlight_parameters(): external_function_parameters {
        return new external_function_parameters([
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id"),
            "contentid" => new external_value(PARAM_INT, "Content id"),
            "selectiontext" => new external_value(PARAM_TEXT, "Selected text"),
            "selector" => new external_value(PARAM_RAW, "Serialized text selector"),
            "color" => new external_value(PARAM_ALPHA, "Highlight color"),
        ]);
    }

    /**
     * Saves a private text highlight for the current user.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @param string $selectiontext Selected source text.
     * @param string $selector Serialized highlight selector.
     * @param string $color Highlight color.
     * @return array
     */
    public static function save_highlight(
        int $flexbookid,
        int $chapterid,
        int $contentid,
        string $selectiontext,
        string $selector,
        string $color
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::save_highlight_parameters(), compact(
            "flexbookid",
            "chapterid",
            "contentid",
            "selectiontext",
            "selector",
            "color"
        ));
        self::require_instance($params["flexbookid"]);
        if (!in_array($params["color"], ["yellow", "green", "blue", "pink"])) {
            throw new invalid_parameter_exception("Invalid highlight color");
        }
        self::validate_item($params["flexbookid"], $params["chapterid"], $params["contentid"]);
        $id = $DB->insert_record("flexbook_highlights", (object) [
            "flexbookid" => $params["flexbookid"],
            "userid" => $USER->id,
            "chapterid" => $params["chapterid"],
            "contentid" => $params["contentid"],
            "selectiontext" => $params["selectiontext"],
            "selector" => $params["selector"],
            "color" => $params["color"],
            "timecreated" => time(),
        ]);
        return ["id" => $id, "saved" => true];
    }

    /**
     * Defines the return structure for the save highlight external function.
     *
     * @return external_single_structure
     */
    public static function save_highlight_returns(): external_single_structure {
        return new external_single_structure([
            "id" => new external_value(PARAM_INT, "Highlight id"),
            "saved" => new external_value(PARAM_BOOL, "Saved"),
        ]);
    }

    /**
     * Defines parameters for the delete highlight external function.
     *
     * @return external_function_parameters
     */
    public static function delete_highlight_parameters(): external_function_parameters {
        return new external_function_parameters([
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "highlightid" => new external_value(PARAM_INT, "Highlight id"),
        ]);
    }

    /**
     * Deletes a private text highlight owned by the current user.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $highlightid Highlight ID.
     * @return array
     */
    public static function delete_highlight(int $flexbookid, int $highlightid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::delete_highlight_parameters(), compact(
            "flexbookid",
            "highlightid"
        ));
        self::require_instance($params["flexbookid"]);
        $record = $DB->get_record("flexbook_highlights", [
            "id" => $params["highlightid"],
            "flexbookid" => $params["flexbookid"],
            "userid" => $USER->id,
        ], "*", MUST_EXIST);
        $DB->delete_records("flexbook_highlights", ["id" => $record->id]);
        return ["deleted" => true];
    }

    /**
     * Defines the return structure for the delete highlight external function.
     *
     * @return external_single_structure
     */
    public static function delete_highlight_returns(): external_single_structure {
        return new external_single_structure(["deleted" => new external_value(PARAM_BOOL, "Deleted")]);
    }

    /**
     * Defines parameters for the submit question answer external function.
     *
     * @return external_function_parameters
     */
    public static function submit_question_answer_parameters(): external_function_parameters {
        return new external_function_parameters([
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "contentid" => new external_value(PARAM_INT, "Content id"),
            "answer" => new external_value(PARAM_RAW, "JSON answer"),
        ]);
    }

    /**
     * Submits and evaluates an answer to a question content block.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $contentid Content block ID.
     * @param string $answer Submitted answer.
     * @return array
     */
    public static function submit_question_answer(int $flexbookid, int $contentid, string $answer): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::submit_question_answer_parameters(), compact(
            "flexbookid",
            "contentid",
            "answer"
        ));
        self::require_instance($params["flexbookid"]);
        self::validate_item($params["flexbookid"], 0, $params["contentid"]);
        $question = $DB->get_record("flexbook_questions", ["contentid" => $params["contentid"]], "*", MUST_EXIST);
        $decoded = json_decode($params["answer"], true);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new invalid_parameter_exception("Invalid answer JSON");
        }
        $expected = json_decode($question->answerjson ?? "null", true);
        $iscorrect = $expected !== null && $decoded == $expected;
        $attemptnumber = $DB->count_records("flexbook_question_attempts", [
            "questionid" => $question->id,
            "userid" => $USER->id,
        ]) + 1;
        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record("flexbook_question_attempts", (object) [
            "questionid" => $question->id,
            "userid" => $USER->id,
            "answerjson" => json_encode($decoded),
            "iscorrect" => $iscorrect,
            "attemptnumber" => $attemptnumber,
            "timecreated" => time(),
        ]);

        $content = $DB->get_record("flexbook_contents", ["id" => $params["contentid"]], "*", MUST_EXIST);
        $shouldcomplete = in_array($content->completiontype, ["answer", "attempt"])
            || ($content->completiontype == "correct" && $iscorrect);
        $manager = new progress_manager();
        $manager->mark_content_viewed($params["flexbookid"], $USER->id, $params["contentid"]);
        if ($shouldcomplete) {
            $manager->mark_content_completed($params["flexbookid"], $USER->id, $params["contentid"]);
        }
        $transaction->allow_commit();
        question_answered::create_from_ids(
            $params["flexbookid"],
            $content->chapterid,
            $content->id,
            $USER->id
        )->trigger();
        return [
            "iscorrect" => $iscorrect,
            "attemptnumber" => $attemptnumber,
            "feedback" => $question->feedback ?? "",
        ];
    }

    /**
     * Defines the return structure for the submit question answer external function.
     *
     * @return external_single_structure
     */
    public static function submit_question_answer_returns(): external_single_structure {
        return new external_single_structure([
            "iscorrect" => new external_value(PARAM_BOOL, "Answer correctness"),
            "attemptnumber" => new external_value(PARAM_INT, "Attempt number"),
            "feedback" => new external_value(PARAM_RAW, "Feedback"),
        ]);
    }

    /**
     * Defines parameters for the search contents external function.
     *
     * @return external_function_parameters
     */
    public static function search_contents_parameters(): external_function_parameters {
        return new external_function_parameters([
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "query" => new external_value(PARAM_TEXT, "Search query"),
        ]);
    }

    /**
     * Searches accessible published FlexBook content.
     *
     * @param int $flexbookid FlexBook ID.
     * @param string $query Search query.
     * @return array
     */
    public static function search_contents(int $flexbookid, string $query): array {
        global $DB;

        $params = self::validate_parameters(self::search_contents_parameters(), compact("flexbookid", "query"));
        [$flexbook, $cm, $context] = self::require_instance($params["flexbookid"]);
        if (!$flexbook->enablesearch || core_text::strlen(trim($params["query"])) < 2) {
            return [];
        }
        $like = "%" . $DB->sql_like_escape($params["query"]) . "%";
        $hiddenclause = has_capability("mod/flexbook:managecontent", $context)
            ? ""
            : " AND ch.hidden = 0 AND c.hidden = 0";
        $sql = "SELECT c.id, c.chapterid, c.type, c.title, c.data1, c.data2, c.data3,
                       ch.title AS chaptertitle
                  FROM {flexbook_contents} c
                  JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                 WHERE ch.flexbookid = :flexbookid
                   {$hiddenclause}
                   AND (" . $DB->sql_like("c.title", ":q1", false) . "
                    OR " . $DB->sql_like("c.data1", ":q2", false) . "
                    OR " . $DB->sql_like("c.data2", ":q3", false) . "
                    OR " . $DB->sql_like("c.data3", ":q4", false) . ")
              ORDER BY ch.sortorder, c.sortorder";
        $records = $DB->get_records_sql($sql, [
            "flexbookid" => $params["flexbookid"],
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
                "url" => (new moodle_url("/mod/flexbook/view.php", [
                    "id" => $cm->id,
                    "chapterid" => $record->chapterid,
                ], "flexbook-content-{$record->id}"))->out(false),
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
            "flexbookid" => new external_value(PARAM_INT, "FlexBook id"),
            "chapterid" => new external_value(PARAM_INT, "Chapter id"),
            "contentids" => new external_multiple_structure(new external_value(PARAM_INT, "Content id")),
        ]);
    }

    /**
     * Validates and applies a chapter content order.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $chapterid Chapter ID.
     * @param array $contentids Ordered content block IDs.
     * @return array
     */
    public static function reorder_contents(int $flexbookid, int $chapterid, array $contentids): array {
        $params = self::validate_parameters(self::reorder_contents_parameters(), compact(
            "flexbookid",
            "chapterid",
            "contentids"
        ));
        [$flexbook, $cm, $context] = self::require_instance($params["flexbookid"], "mod/flexbook:managecontent");
        self::validate_item($params["flexbookid"], $params["chapterid"], 0);
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
     * Validates access to a FlexBook activity instance.
     *
     * @param int $flexbookid FlexBook ID.
     * @param string $capability Required module capability.
     * @return array
     */
    private static function require_instance(
        int $flexbookid,
        string $capability = "mod/flexbook:view"
    ): array {
        global $DB;

        require_sesskey();
        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance("flexbook", $flexbookid, $flexbook->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_login($flexbook->course, false, $cm);
        require_capability($capability, $context);
        return [$flexbook, $cm, $context];
    }

    /**
     * Validates that a chapter and content block belong to the FlexBook.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $chapterid Chapter ID.
     * @param int $contentid Content block ID.
     * @return void
     */
    private static function validate_item(int $flexbookid, int $chapterid, int $contentid): void {
        global $DB;

        if ($chapterid) {
            $DB->get_record("flexbook_chapters", [
                "id" => $chapterid,
                "flexbookid" => $flexbookid,
            ], "*", MUST_EXIST);
        }
        if ($contentid) {
            $sql = "SELECT c.id, c.chapterid
                      FROM {flexbook_contents} c
                      JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                     WHERE c.id = :contentid AND ch.flexbookid = :flexbookid";
            $record = $DB->get_record_sql($sql, [
                "contentid" => $contentid,
                "flexbookid" => $flexbookid,
            ], MUST_EXIST);
            if ($chapterid && $record->chapterid != $chapterid) {
                throw new invalid_parameter_exception("Content does not belong to chapter");
            }
        }
    }

    /**
     * Builds the external function progress result.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return array
     */
    private static function progress_result(int $flexbookid, int $userid): array {
        global $DB;

        $manager = new progress_manager();
        $progress = $manager->calculate_user_progress($flexbookid, $userid);
        $completed = $DB->count_records("flexbook_user_progress", [
            "flexbookid" => $flexbookid,
            "userid" => $userid,
            "status" => progress_manager::STATUS_COMPLETED,
        ]);
        $total = $DB->count_records_sql(
            "SELECT COUNT(c.id)
               FROM {flexbook_contents} c
               JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
              WHERE ch.flexbookid = :flexbookid
                AND ch.hidden = 0
                AND c.hidden = 0
                AND c.trackprogress = 1",
            ["flexbookid" => $flexbookid]
        );
        return [
            "percentage" => $progress,
            "completed" => $completed,
            "total" => $total,
            "pendingrequired" => $manager->get_pending_required_count($flexbookid, $userid),
            "activitycompleted" => $manager->completion_requirements_met($flexbookid, $userid, $progress),
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
