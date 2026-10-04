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
 * restore_bookflow_stepslib.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores BookFlow activity records and remaps their relationships.
 */
class restore_bookflow_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the backup or restore data structure.
     *
     * @return array
     */
    #[Override]
    protected function define_structure(): array {
        $paths = [
            new restore_path_element("bookflow", "/activity/bookflow"),
            new restore_path_element("bookflow_chapter", "/activity/bookflow/chapters/chapter"),
            new restore_path_element("bookflow_content", "/activity/bookflow/chapters/chapter/contents/content"),
        ];
        $contentpath = $paths[2];
        $this->add_subplugin_structure("bookflowcontent", $contentpath);

        if ($this->get_setting_value("userinfo")) {
            $paths[] = new restore_path_element("bookflow_progress", "/activity/bookflow/progressrecords/progress");
            $paths[] = new restore_path_element(
                "bookflow_chapterprogress",
                "/activity/bookflow/chapterprogressrecords/chapterprogress"
            );
            $paths[] = new restore_path_element("bookflow_state", "/activity/bookflow/states/state");
            $paths[] = new restore_path_element("bookflow_bookmark", "/activity/bookflow/bookmarks/bookmark");
            $paths[] = new restore_path_element("bookflow_note", "/activity/bookflow/notes/note");
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores BookFlow.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_bookflow(array $data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record("bookflow", $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping("bookflow", $oldid, $newid, true);
    }

    /**
     * Restores BookFlow chapter.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_bookflow_chapter(array $data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->bookflowid = $this->get_new_parentid("bookflow");
        $data->parentid = $data->parentid ? $this->get_mappingid("bookflow_chapter", $data->parentid, 0) : 0;
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record("bookflow_chapters", $data);
        $this->set_mapping("bookflow_chapter", $oldid, $newid, true);
    }

    /**
     * Restores BookFlow content.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_bookflow_content(array $data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->chapterid = $this->get_new_parentid("bookflow_chapter");
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record("bookflow_contents", $data);
        $this->set_mapping("bookflow_content", $oldid, $newid, true);
    }

    /**
     * Restores BookFlow progress.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_bookflow_progress(array $data): void {
        global $DB;

        $data = (object) $data;
        $data->bookflowid = $this->get_new_parentid("bookflow");
        $data->chapterid = $this->get_mappingid("bookflow_chapter", $data->chapterid);
        $data->contentid = $this->get_mappingid("bookflow_content", $data->contentid);
        $data->userid = $this->get_mappingid("user", $data->userid);
        if ($data->userid && $data->chapterid && $data->contentid) {
            $DB->insert_record("bookflow_user_progress", $data);
        }
    }

    /**
     * Restores BookFlow state.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_bookflow_state(array $data): void {
        global $DB;

        $data = (object) $data;
        $data->bookflowid = $this->get_new_parentid("bookflow");
        $data->userid = $this->get_mappingid("user", $data->userid);
        $data->lastchapterid = $this->get_mappingid("bookflow_chapter", $data->lastchapterid, 0);
        $data->lastcontentid = $this->get_mappingid("bookflow_content", $data->lastcontentid, 0);
        if ($data->userid) {
            $DB->insert_record("bookflow_user_state", $data);
        }
    }

    /**
     * Restores BookFlow chapterprogress.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_bookflow_chapterprogress(array $data): void {
        global $DB;

        $record = (object) $data;
        $record->bookflowid = $this->get_new_parentid("bookflow");
        $record->chapterid = $this->get_mappingid("bookflow_chapter", $record->chapterid);
        $record->userid = $this->get_mappingid("user", $record->userid);
        if ($record->userid && $record->chapterid) {
            $DB->insert_record("bookflow_chapter_progress", $record);
        }
    }

    /**
     * Restores BookFlow bookmark.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_bookflow_bookmark(array $data): void {
        $this->insert_user_item("bookflow_bookmarks", $data);
    }

    /**
     * Restores BookFlow note.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_bookflow_note(array $data): void {
        $this->insert_user_item("bookflow_notes", $data);
    }

    /**
     * Restores one user-owned BookFlow record.
     *
     * @param string $table Database table name.
     * @param array $data Record data.
     * @return void
     */
    private function insert_user_item(string $table, array $data): void {
        global $DB;

        $record = (object) $data;
        $record->bookflowid = $this->get_new_parentid("bookflow");
        $record->userid = $this->get_mappingid("user", $record->userid);
        $record->chapterid = $this->get_mappingid("bookflow_chapter", $record->chapterid, 0);
        $record->contentid = $this->get_mappingid("bookflow_content", $record->contentid, 0);
        if ($record->userid) {
            $DB->insert_record($table, $record);
        }
    }

    /**
     * Restores files after the activity data has been processed.
     *
     * @return void
     */
    #[Override]
    protected function after_execute(): void {
        $this->add_related_files("mod_bookflow", "intro", null);
        $this->add_related_files("mod_bookflow", "cover", null);
        foreach (\mod_bookflow\content_type_manager::get_classes() as $classname) {
            foreach ($classname::get_fileareas() as $filearea) {
                $this->add_related_files("mod_bookflow", $filearea, "bookflow_content");
            }
        }
    }
}
