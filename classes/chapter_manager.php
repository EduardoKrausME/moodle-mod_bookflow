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
 * chapter_manager.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow;

use stdClass;

/**
 * Manages BookFlow chapter records and ordering.
 */
class chapter_manager {
    /**
     * Creates a chapter.
     *
     * @param stdClass $data Record data.
     * @return int
     */
    public static function create(stdClass $data): int {
        global $DB;

        $data->sortorder = $DB->get_field_sql(
            "SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {bookflow_chapters} WHERE bookflowid = ?",
            [$data->bookflowid]
        );
        $data->timecreated = time();
        $data->timemodified = $data->timecreated;
        return $DB->insert_record("bookflow_chapters", $data);
    }

    /**
     * Updates a chapter.
     *
     * @param stdClass $data Record data.
     * @return bool
     */
    public static function update(stdClass $data): bool {
        global $DB;

        $current = $DB->get_record("bookflow_chapters", ["id" => $data->id], "*", MUST_EXIST);
        $data->timemodified = time();
        $result = $DB->update_record("bookflow_chapters", $data);
        $updated = $DB->get_record("bookflow_chapters", ["id" => $data->id], "*", MUST_EXIST);
        if ((string) $current->hidden !== (string) $updated->hidden
                || (string) $current->required !== (string) $updated->required) {
            progress_recalculator::recalculate_all($updated->bookflowid);
        }
        return $result;
    }

    /**
     * Deletes a chapter and its dependent records.
     *
     * @param int $chapterid Chapter ID.
     * @return void
     */
    public static function delete(int $chapterid): void {
        global $DB;

        $chapter = $DB->get_record("bookflow_chapters", ["id" => $chapterid], "*", MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        $contentids = $DB->get_fieldset_select("bookflow_contents", "id", "chapterid = ?", [$chapterid]);
        foreach ($contentids as $contentid) {
            content_manager::delete($contentid, false);
        }
        $DB->delete_records("bookflow_chapter_progress", ["chapterid" => $chapterid]);
        $DB->delete_records("bookflow_chapters", ["id" => $chapterid]);
        self::normalize_sortorder($chapter->bookflowid);
        progress_recalculator::recalculate_all($chapter->bookflowid);
        $transaction->allow_commit();
    }

    /**
     * Normalizes chapter sort order values.
     *
     * @param int $bookflowid BookFlow ID.
     * @return void
     */
    public static function normalize_sortorder(int $bookflowid): void {
        global $DB;

        $records = $DB->get_records("bookflow_chapters", ["bookflowid" => $bookflowid], "sortorder, id");
        $sortorder = 0;
        foreach ($records as $record) {
            if ($record->sortorder != $sortorder) {
                $record->sortorder = $sortorder;
                $DB->update_record("bookflow_chapters", $record);
            }
            $sortorder++;
        }
    }
}
