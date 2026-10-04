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
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow;

use context_module;
use stdClass;

/**
 * Manages BookFlow activity instances and their files.
 */
class instance_manager {
    /**
     * Creates a BookFlow activity instance.
     *
     * @param stdClass $data Record data.
     * @return int
     */
    public static function create(stdClass $data): int {
        global $DB;

        $now = time();
        $data->timecreated = $now;
        $data->timemodified = $now;
        $id = $DB->insert_record("bookflow", $data);
        self::save_cover($data);
        return $id;
    }

    /**
     * Updates a BookFlow activity instance.
     *
     * @param stdClass $data Record data.
     * @return bool
     */
    public static function update(stdClass $data): bool {
        global $DB;

        $current = $DB->get_record("bookflow", ["id" => $data->instance], "*", MUST_EXIST);
        $data->id = $data->instance;
        $data->timemodified = time();
        $result = $DB->update_record("bookflow", $data);
        self::save_cover($data);

        $updated = $DB->get_record("bookflow", ["id" => $data->id], "*", MUST_EXIST);
        if ((string) $current->completionmode !== (string) $updated->completionmode
                || (string) $current->completionpercentage !== (string) $updated->completionpercentage) {
            progress_recalculator::recalculate_all($data->id);
        }
        return $result;
    }

    /**
     * Deletes a BookFlow activity and all dependent records.
     *
     * @param int $id BookFlow ID.
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;

        if (!$DB->record_exists("bookflow", ["id" => $id])) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();
        $chapterids = $DB->get_fieldset_select("bookflow_chapters", "id", "bookflowid = ?", [$id]);
        $contentids = [];
        if ($chapterids) {
            [$insql, $params] = $DB->get_in_or_equal($chapterids);
            $contentids = $DB->get_fieldset_select("bookflow_contents", "id", "chapterid {$insql}", $params);
        }

        foreach ($contentids as $contentid) {
            content_manager::delete((int) $contentid, false);
        }

        foreach ([
            "bookflow_user_progress",
            "bookflow_chapter_progress",
            "bookflow_user_state",
            "bookflow_bookmarks",
            "bookflow_notes",
        ] as $table) {
            $DB->delete_records($table, ["bookflowid" => $id]);
        }
        $DB->delete_records("bookflow_chapters", ["bookflowid" => $id]);
        $DB->delete_records("bookflow", ["id" => $id]);
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
            "mod_bookflow",
            "cover",
            0,
            ["accepted_types" => ["image"], "maxfiles" => 1, "subdirs" => 0]
        );
    }
}
