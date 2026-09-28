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

use context_module;
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
        $classname = self::get_content_class($data->type);
        $classname::after_create($id);
        $flexbookid = self::get_flexbookid($data->chapterid);
        if (empty($data->hidden) && !empty($data->trackprogress) && $data->completiontype !== "none") {
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
        $currentclass = self::get_content_class($current->type);
        $newclass = self::get_content_class($data->type);
        $data->timemodified = time();

        if ($current->type !== $data->type) {
            $currentclass::before_delete($data->id);
        }
        $newclass::before_update($current, $data);
        $result = $DB->update_record("flexbook_contents", $data);
        $newclass::after_update($data->id);
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
        $sourcecontentid = $content->id;
        $classname = self::get_content_class($content->type);
        $fileareas = $classname::get_fileareas();
        unset($content->id);
        $content->title = get_string("copyof", "mod_flexbook", $content->title);
        $newcontentid = self::create($content);
        self::copy_content_files($sourcecontentid, $newcontentid, $content->chapterid, $fileareas);
        return $newcontentid;
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
        $classname = self::get_content_class($content->type);
        $classname::before_delete($contentid);
        self::delete_content_files($contentid, $content->chapterid, $classname::get_fileareas());
        $DB->delete_records("flexbook_user_progress", ["contentid" => $contentid]);
        $DB->delete_records("flexbook_bookmarks", ["contentid" => $contentid]);
        $DB->delete_records("flexbook_notes", ["contentid" => $contentid]);
        $DB->delete_records("flexbook_contents", ["id" => $contentid]);
        self::normalize_sortorder($content->chapterid);
        if ($recalculate && empty($content->hidden) && !empty($content->trackprogress)
                && $content->completiontype !== "none") {
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
     * Copies stored files owned by a content block.
     *
     * @param int $sourcecontentid Source content block ID.
     * @param int $targetcontentid Target content block ID.
     * @param int $chapterid Chapter ID.
     * @return void
     */
    private static function copy_content_files(
        int $sourcecontentid,
        int $targetcontentid,
        int $chapterid,
        array $fileareas
    ): void {
        $context = self::get_context($chapterid);
        $fs = get_file_storage();
        foreach ($fileareas as $filearea) {
            $files = $fs->get_area_files(
                $context->id,
                "mod_flexbook",
                $filearea,
                $sourcecontentid,
                "id",
                false
            );
            foreach ($files as $file) {
                $filerecord = [
                    "contextid" => $context->id,
                    "component" => "mod_flexbook",
                    "filearea" => $filearea,
                    "itemid" => $targetcontentid,
                    "filepath" => $file->get_filepath(),
                    "filename" => $file->get_filename(),
                ];
                $fs->create_file_from_storedfile($filerecord, $file);
            }
        }
    }

    /**
     * Deletes stored files owned by a content block.
     *
     * @param int $contentid Content block ID.
     * @param int $chapterid Chapter ID.
     * @return void
     */
    private static function delete_content_files(int $contentid, int $chapterid, array $fileareas): void {
        $context = self::get_context($chapterid);
        $fs = get_file_storage();
        foreach ($fileareas as $filearea) {
            $fs->delete_area_files($context->id, "mod_flexbook", $filearea, $contentid);
        }
    }

    /**
     * Resolves a registered content class.
     *
     * @param string $type Content type.
     * @return string
     */
    private static function get_content_class(string $type): string {
        $classes = content_type_manager::get_classes();
        if (!isset($classes[$type])) {
            throw new invalid_parameter_exception("Unknown FlexBook content type: {$type}");
        }
        return $classes[$type];
    }

    /**
     * Gets the activity context that owns a chapter.
     *
     * @param int $chapterid Chapter ID.
     * @return context_module
     */
    private static function get_context(int $chapterid): context_module {
        $flexbookid = self::get_flexbookid($chapterid);
        $cm = get_coursemodule_from_instance("flexbook", $flexbookid, 0, false, MUST_EXIST);
        return context_module::instance($cm->id);
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
