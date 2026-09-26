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
 * instance_manager.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use context_module;
use stdClass;

/**
 * Manages FlexBook activity instances and their files.
 */
class instance_manager {
    /**
     * Creates a FlexBook activity instance.
     *
     * @param stdClass $data Record data.
     * @return int
     */
    public static function create(stdClass $data): int {
        global $DB;

        $now = time();
        $data->timecreated = $now;
        $data->timemodified = $now;
        $id = $DB->insert_record("flexbook", $data);
        self::save_cover($data);
        return $id;
    }

    /**
     * Updates a FlexBook activity instance.
     *
     * @param stdClass $data Record data.
     * @return bool
     */
    public static function update(stdClass $data): bool {
        global $DB;

        $data->id = $data->instance;
        $data->timemodified = time();
        $result = $DB->update_record("flexbook", $data);
        self::save_cover($data);
        return $result;
    }

    /**
     * Deletes a FlexBook activity and all dependent records.
     *
     * @param int $id FlexBook ID.
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;

        if (!$DB->record_exists("flexbook", ["id" => $id])) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();
        $chapterids = $DB->get_fieldset_select("flexbook_chapters", "id", "flexbookid = ?", [$id]);
        $contentids = [];
        if ($chapterids) {
            [$insql, $params] = $DB->get_in_or_equal($chapterids);
            $contentids = $DB->get_fieldset_select("flexbook_contents", "id", "chapterid {$insql}", $params);
        }

        if ($contentids) {
            [$insql, $params] = $DB->get_in_or_equal($contentids);
            $questionids = $DB->get_fieldset_select("flexbook_questions", "id", "contentid {$insql}", $params);
            if ($questionids) {
                [$qsql, $qparams] = $DB->get_in_or_equal($questionids);
                $DB->delete_records_select("flexbook_question_attempts", "questionid {$qsql}", $qparams);
            }
            $DB->delete_records_select("flexbook_questions", "contentid {$insql}", $params);
        }

        foreach ([
            "flexbook_user_progress",
            "flexbook_chapter_progress",
            "flexbook_user_state",
            "flexbook_bookmarks",
            "flexbook_notes",
            "flexbook_highlights",
        ] as $table) {
            $DB->delete_records($table, ["flexbookid" => $id]);
        }
        if ($contentids) {
            [$insql, $params] = $DB->get_in_or_equal($contentids);
            $DB->delete_records_select("flexbook_contents", "id {$insql}", $params);
        }
        $DB->delete_records("flexbook_chapters", ["flexbookid" => $id]);
        $DB->delete_records("flexbook", ["id" => $id]);
        $transaction->allow_commit();
        return true;
    }

    /**
     * Stores the activity cover file.
     *
     * @param stdClass $data Record data.
     * @return void
     */
    private static function save_cover(stdClass $data): void {
        if (empty($data->cover) || empty($data->coursemodule)) {
            return;
        }
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files(
            $data->cover,
            $context->id,
            "mod_flexbook",
            "cover",
            0,
            ["accepted_types" => ["image"], "maxfiles" => 1, "subdirs" => 0]
        );
    }
}
