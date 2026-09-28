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
 * restore_flexbook_stepslib.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores FlexBook activity records and remaps their relationships.
 */
class restore_flexbook_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the backup or restore data structure.
     *
     * @return array
     */
    #[Override]
    protected function define_structure(): array {
        $paths = [
            new restore_path_element("flexbook", "/activity/flexbook"),
            new restore_path_element("flexbook_chapter", "/activity/flexbook/chapters/chapter"),
            new restore_path_element("flexbook_content", "/activity/flexbook/chapters/chapter/contents/content"),
        ];
        $contentpath = $paths[2];
        $this->add_subplugin_structure("flexbookcontent", $contentpath);

        if ($this->get_setting_value("userinfo")) {
            $paths[] = new restore_path_element("flexbook_progress", "/activity/flexbook/progressrecords/progress");
            $paths[] = new restore_path_element(
                "flexbook_chapterprogress",
                "/activity/flexbook/chapterprogressrecords/chapterprogress"
            );
            $paths[] = new restore_path_element("flexbook_state", "/activity/flexbook/states/state");
            $paths[] = new restore_path_element("flexbook_bookmark", "/activity/flexbook/bookmarks/bookmark");
            $paths[] = new restore_path_element("flexbook_note", "/activity/flexbook/notes/note");
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores FlexBook.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_flexbook(array $data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record("flexbook", $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping("flexbook", $oldid, $newid, true);
    }

    /**
     * Restores FlexBook chapter.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_flexbook_chapter(array $data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->flexbookid = $this->get_new_parentid("flexbook");
        $data->parentid = $data->parentid ? $this->get_mappingid("flexbook_chapter", $data->parentid, 0) : 0;
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record("flexbook_chapters", $data);
        $this->set_mapping("flexbook_chapter", $oldid, $newid, true);
    }

    /**
     * Restores FlexBook content.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_flexbook_content(array $data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->chapterid = $this->get_new_parentid("flexbook_chapter");
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newid = $DB->insert_record("flexbook_contents", $data);
        $this->set_mapping("flexbook_content", $oldid, $newid, true);
    }

    /**
     * Restores FlexBook progress.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_flexbook_progress(array $data): void {
        global $DB;

        $data = (object) $data;
        $data->flexbookid = $this->get_new_parentid("flexbook");
        $data->chapterid = $this->get_mappingid("flexbook_chapter", $data->chapterid);
        $data->contentid = $this->get_mappingid("flexbook_content", $data->contentid);
        $data->userid = $this->get_mappingid("user", $data->userid);
        if ($data->userid && $data->chapterid && $data->contentid) {
            $DB->insert_record("flexbook_user_progress", $data);
        }
    }

    /**
     * Restores FlexBook state.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_flexbook_state(array $data): void {
        global $DB;

        $data = (object) $data;
        $data->flexbookid = $this->get_new_parentid("flexbook");
        $data->userid = $this->get_mappingid("user", $data->userid);
        $data->lastchapterid = $this->get_mappingid("flexbook_chapter", $data->lastchapterid, 0);
        $data->lastcontentid = $this->get_mappingid("flexbook_content", $data->lastcontentid, 0);
        if ($data->userid) {
            $DB->insert_record("flexbook_user_state", $data);
        }
    }

    /**
     * Restores FlexBook chapterprogress.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_flexbook_chapterprogress(array $data): void {
        global $DB;

        $record = (object) $data;
        $record->flexbookid = $this->get_new_parentid("flexbook");
        $record->chapterid = $this->get_mappingid("flexbook_chapter", $record->chapterid);
        $record->userid = $this->get_mappingid("user", $record->userid);
        if ($record->userid && $record->chapterid) {
            $DB->insert_record("flexbook_chapter_progress", $record);
        }
    }

    /**
     * Restores FlexBook bookmark.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_flexbook_bookmark(array $data): void {
        $this->insert_user_item("flexbook_bookmarks", $data);
    }

    /**
     * Restores FlexBook note.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_flexbook_note(array $data): void {
        $this->insert_user_item("flexbook_notes", $data);
    }

    /**
     * Restores one user-owned FlexBook record.
     *
     * @param string $table Database table name.
     * @param array $data Record data.
     * @return void
     */
    private function insert_user_item(string $table, array $data): void {
        global $DB;

        $record = (object) $data;
        $record->flexbookid = $this->get_new_parentid("flexbook");
        $record->userid = $this->get_mappingid("user", $record->userid);
        $record->chapterid = $this->get_mappingid("flexbook_chapter", $record->chapterid, 0);
        $record->contentid = $this->get_mappingid("flexbook_content", $record->contentid, 0);
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
        $this->add_related_files("mod_flexbook", "intro", null);
        $this->add_related_files("mod_flexbook", "cover", null);
        foreach (\mod_flexbook\content_type_manager::get_classes() as $classname) {
            foreach ($classname::get_fileareas() as $filearea) {
                $this->add_related_files("mod_flexbook", $filearea, "flexbook_content");
            }
        }
    }
}
