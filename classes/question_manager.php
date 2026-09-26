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
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

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

        $content = $DB->get_record("flexbook_contents", ["id" => $contentid, "type" => "question"], "*",
            MUST_EXIST);
        $options = json_decode($content->data2 ?? "[]", true);
        $configuration = json_decode($content->data3 ?? "{}", true);
        if (!is_array($options) || !is_array($configuration)) {
            throw new moodle_exception("invalidquestionconfiguration", "mod_flexbook");
        }

        $record = $DB->get_record("flexbook_questions", ["contentid" => $contentid]);
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
            $DB->update_record("flexbook_questions", $record);
        } else {
            $DB->insert_record("flexbook_questions", $record);
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

        $questionids = $DB->get_fieldset_select("flexbook_questions", "id", "contentid = ?", [$contentid]);
        if ($questionids) {
            [$insql, $params] = $DB->get_in_or_equal($questionids);
            $DB->delete_records_select("flexbook_question_attempts", "questionid {$insql}", $params);
        }
        $DB->delete_records("flexbook_questions", ["contentid" => $contentid]);
    }
}
