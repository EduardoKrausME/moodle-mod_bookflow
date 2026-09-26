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
 * content_manager.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use invalid_parameter_exception;
use stdClass;

/**
 * Manages FlexBook content block records and ordering.
 */
class content_manager {
    /** @var array Fields whose change can alter derived progress or completion. */
    private const PROGRESS_FIELDS = [
        "chapterid",
        "hidden",
        "trackprogress",
        "required",
        "weight",
        "completiontype",
        "completionvalue",
        "auxint1",
    ];

    /**
     * Creates a content block.
     *
     * @param stdClass $data Record data.
     * @return int
     */
    public static function create(stdClass $data): int {
        global $DB;

        $data->sortorder = $DB->get_field_sql(
            "SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {flexbook_contents} WHERE chapterid = ?",
            [$data->chapterid]
        );
        $data->timecreated = time();
        $data->timemodified = $data->timecreated;
        $id = $DB->insert_record("flexbook_contents", $data);
        if ($data->type == "question") {
            question_manager::sync_from_content($id);
        }
        $flexbookid = self::get_flexbookid($data->chapterid);
        if (empty($data->hidden) && !empty($data->trackprogress)) {
            progress_recalculator::recalculate_all($flexbookid);
        }
        return $id;
    }

    /**
     * Updates a content block.
     *
     * @param stdClass $data Record data.
     * @return bool
     */
    public static function update(stdClass $data): bool {
        global $DB;

        $current = $DB->get_record("flexbook_contents", ["id" => $data->id], "*", MUST_EXIST);
        $data->timemodified = time();
        if ($current->type == "question" && $data->type != "question") {
            question_manager::delete_for_content($data->id);
        }
        $result = $DB->update_record("flexbook_contents", $data);
        if ($data->type == "question") {
            question_manager::sync_from_content($data->id);
        }
        $updated = $DB->get_record("flexbook_contents", ["id" => $data->id], "*", MUST_EXIST);
        $oldflexbookid = self::get_flexbookid($current->chapterid);
        $newflexbookid = self::get_flexbookid($updated->chapterid);
        if (self::progress_structure_changed($current, $updated)) {
            progress_recalculator::recalculate_all($oldflexbookid);
            if ($newflexbookid != $oldflexbookid) {
                progress_recalculator::recalculate_all($newflexbookid);
            }
        }
        if ($current->chapterid != $updated->chapterid) {
            self::normalize_sortorder($current->chapterid);
            self::normalize_sortorder($updated->chapterid);
        }
        return $result;
    }

    /**
     * Duplicates a content block.
     *
     * @param int $contentid Content block ID.
     * @return int
     */
    public static function duplicate(int $contentid): int {
        global $DB;

        $content = $DB->get_record("flexbook_contents", ["id" => $contentid], "*", MUST_EXIST);
        unset($content->id);
        $content->title = get_string("copyof", "mod_flexbook", $content->title);
        return self::create($content);
    }

    /**
     * Deletes a content block and its dependent records.
     *
     * @param int $contentid Content block ID.
     * @param bool $recalculate Whether aggregate progress must be recalculated.
     * @return void
     */
    public static function delete(int $contentid, bool $recalculate = true): void {
        global $DB;

        $content = $DB->get_record("flexbook_contents", ["id" => $contentid], "*", MUST_EXIST);
        $flexbookid = self::get_flexbookid($content->chapterid);
        question_manager::delete_for_content($contentid);
        $DB->delete_records("flexbook_user_progress", ["contentid" => $contentid]);
        $DB->delete_records("flexbook_bookmarks", ["contentid" => $contentid]);
        $DB->delete_records("flexbook_notes", ["contentid" => $contentid]);
        $DB->delete_records("flexbook_highlights", ["contentid" => $contentid]);
        $DB->delete_records("flexbook_contents", ["id" => $contentid]);
        self::normalize_sortorder($content->chapterid);
        if ($recalculate && empty($content->hidden) && !empty($content->trackprogress)) {
            progress_recalculator::recalculate_all($flexbookid);
        }
    }

    /**
     * Moves a content block in the chapter order.
     *
     * @param int $contentid Content block ID.
     * @param int $direction Movement direction.
     * @return void
     */
    public static function move(int $contentid, int $direction): void {
        global $DB;

        $content = $DB->get_record("flexbook_contents", ["id" => $contentid], "*", MUST_EXIST);
        $operator = $direction < 0 ? "<" : ">";
        $order = $direction < 0 ? "DESC" : "ASC";
        $other = $DB->get_record_sql(
            "SELECT *
               FROM {flexbook_contents}
              WHERE chapterid = :chapterid
                AND sortorder {$operator} :sortorder
           ORDER BY sortorder {$order}, id {$order}",
            ["chapterid" => $content->chapterid, "sortorder" => $content->sortorder],
            IGNORE_MULTIPLE
        );
        if (!$other) {
            return;
        }
        $oldorder = $content->sortorder;
        $content->sortorder = $other->sortorder;
        $other->sortorder = $oldorder;
        $DB->update_record("flexbook_contents", $content);
        $DB->update_record("flexbook_contents", $other);
    }

    /**
     * Applies a requested content block order.
     *
     * @param int $chapterid Chapter ID.
     * @param array $contentids Ordered content block IDs.
     * @return void
     */
    public static function reorder(int $chapterid, array $contentids): void {
        global $DB;

        $records = $DB->get_records("flexbook_contents", ["chapterid" => $chapterid]);
        if (count($records) != count($contentids) || array_diff(array_keys($records), $contentids)) {
            throw new invalid_parameter_exception("Invalid content order");
        }
        $transaction = $DB->start_delegated_transaction();
        foreach (array_values($contentids) as $sortorder => $contentid) {
            $DB->set_field("flexbook_contents", "sortorder", $sortorder, [
                "id" => $contentid,
                "chapterid" => $chapterid,
            ]);
        }
        $transaction->allow_commit();
    }

    /**
     * Normalizes content block sort order values.
     *
     * @param int $chapterid Chapter ID.
     * @return void
     */
    public static function normalize_sortorder(int $chapterid): void {
        global $DB;

        $records = $DB->get_records("flexbook_contents", ["chapterid" => $chapterid], "sortorder, id");
        $sortorder = 0;
        foreach ($records as $record) {
            if ($record->sortorder != $sortorder) {
                $record->sortorder = $sortorder;
                $DB->update_record("flexbook_contents", $record);
            }
            $sortorder++;
        }
    }

    /**
     * Gets the FlexBook ID that owns a chapter.
     *
     * @param int $chapterid Chapter ID.
     * @return int
     */
    private static function get_flexbookid(int $chapterid): int {
        global $DB;
        return $DB->get_field("flexbook_chapters", "flexbookid", ["id" => $chapterid], MUST_EXIST);
    }

    /**
     * Checks whether an update changes progress-relevant structure.
     *
     * @param stdClass $before Previous record.
     * @param stdClass $after Updated record.
     * @return bool
     */
    private static function progress_structure_changed(stdClass $before, stdClass $after): bool {
        foreach (self::PROGRESS_FIELDS as $field) {
            if ((string) ($before->{$field} ?? "") !== (string) ($after->{$field} ?? "")) {
                return true;
            }
        }
        return false;
    }

}
