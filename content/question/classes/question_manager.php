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
 * question_manager.php
 *
 * @package   bookflowcontent_question
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookflowcontent_question;

use bookflowcontent_question\event\question_answered;
use mod_bookflow\progress\progress_manager;
use moodle_exception;

/**
 * Synchronizes question records with question content blocks.
 */
class question_manager {
    /**
     * Synchronizes a question record from its content block.
     *
     * @param int $contentid Content block ID.
     * @return void
     */
    public static function sync_from_content(int $contentid): void {
        global $DB;

        $content = $DB->get_record("bookflow_contents", ["id" => $contentid, "type" => "question"], "*",
            MUST_EXIST);
        $options = json_decode($content->data2 ?? "[]", true);
        $configuration = json_decode($content->data3 ?? "{}", true);
        if (!is_array($options) || !is_array($configuration)) {
            throw new moodle_exception("invalidquestionconfiguration", "mod_bookflow");
        }

        $record = $DB->get_record("bookflow_questions", ["contentid" => $contentid]);
        $now = time();
        if (!$record) {
            $record = (object) [
                "contentid" => $contentid,
                "timecreated" => $now,
            ];
        }
        $record->questiontext = $content->data1 ?? "";
        $record->optionsjson = json_encode($options);
        $record->answerjson = array_key_exists("answer", $configuration)
            ? json_encode($configuration["answer"])
            : null;
        $record->feedback = clean_param($configuration["feedback"] ?? "", PARAM_TEXT);
        $record->timemodified = $now;

        if (!empty($record->id)) {
            $DB->update_record("bookflow_questions", $record);
        } else {
            $DB->insert_record("bookflow_questions", $record);
        }
    }

    /**
     * Deletes the question and attempts associated with a content block.
     *
     * @param int $contentid Content block ID.
     * @return void
     */
    public static function delete_for_content(int $contentid): void {
        global $DB;

        $questionids = $DB->get_fieldset_select("bookflow_questions", "id", "contentid = ?", [$contentid]);
        if ($questionids) {
            [$insql, $params] = $DB->get_in_or_equal($questionids);
            $DB->delete_records_select("bookflow_question_attempts", "questionid {$insql}", $params);
        }
        $DB->delete_records("bookflow_questions", ["contentid" => $contentid]);
    }
    /**
     * Submits and evaluates one answer.
     *
     * @param int $bookflowid BookFlow id.
     * @param int $contentid Content id.
     * @param int $userid User id.
     * @param string $answer JSON-encoded answer.
     * @return array
     */
    public static function submit_answer(
        int $bookflowid,
        int $contentid,
        int $userid,
        string $answer
    ): array {
        global $DB;

        $content = $DB->get_record_sql(
            "SELECT c.*
               FROM {bookflow_contents} c
               JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
              WHERE c.id = :contentid
                AND c.type = :type
                AND ch.bookflowid = :bookflowid",
            [
                "contentid" => $contentid,
                "type" => "question",
                "bookflowid" => $bookflowid,
            ],
            MUST_EXIST
        );
        $question = $DB->get_record(
            "bookflow_questions",
            ["contentid" => $contentid],
            "*",
            MUST_EXIST
        );

        $decoded = json_decode($answer, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \invalid_parameter_exception("Invalid answer JSON");
        }

        $expected = json_decode($question->answerjson ?? "null", true);
        $iscorrect = $expected !== null && $decoded == $expected;
        $attemptnumber = $DB->count_records("bookflow_question_attempts", [
            "questionid" => $question->id,
            "userid" => $userid,
        ]) + 1;

        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record("bookflow_question_attempts", (object) [
            "questionid" => $question->id,
            "userid" => $userid,
            "answerjson" => json_encode($decoded),
            "iscorrect" => $iscorrect,
            "attemptnumber" => $attemptnumber,
            "timecreated" => time(),
        ]);

        $progress = new progress_manager();
        $progress->mark_content_viewed($bookflowid, $userid, $contentid);
        $shouldcomplete = in_array($content->completiontype, ["answer", "attempt"], true)
            || ($content->completiontype === "correct" && $iscorrect);
        if ($shouldcomplete) {
            $progress->mark_content_completed($bookflowid, $userid, $contentid);
        }
        $transaction->allow_commit();

        question_answered::create_from_ids(
            $bookflowid,
            $content->chapterid,
            $content->id,
            $userid
        )->trigger();

        return [
            "iscorrect" => $iscorrect,
            "attemptnumber" => $attemptnumber,
            "feedback" => $question->feedback ?? "",
        ];
    }

}
