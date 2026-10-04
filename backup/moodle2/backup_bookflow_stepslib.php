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
 * backup_bookflow_stepslib.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Builds the nested data structure used to back up a BookFlow activity.
 */
class backup_bookflow_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup or restore data structure.
     *
     * @return backup_nested_element
     */
    #[Override]
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value("userinfo");

        $bookflow = new backup_nested_element("bookflow", ["id"], [
            "name", "intro", "introformat", "numbering", "defaultcontenttype",
            "completionmode", "completionpercentage", "requiremandatory",
            "enablebookmarks", "enablenotes", "enablesearch", "enabletracking",
            "estimatedtime", "timecreated", "timemodified",
        ]);
        $chapters = new backup_nested_element("chapters");
        $chapter = new backup_nested_element("chapter", ["id"], [
            "parentid", "title", "description", "descriptionformat", "sortorder",
            "hidden", "required", "estimatedtime", "timecreated", "timemodified",
        ]);
        $contents = new backup_nested_element("contents");
        $content = new backup_nested_element("content", ["id"], [
            "type", "title", "sortorder", "data1", "data2", "data3",
            "auxint1", "auxint2", "auxint3", "trackprogress", "required", "weight",
            "completiontype", "completionvalue", "hidden", "estimatedtime",
            "timecreated", "timemodified",
        ]);
        $progressrecords = new backup_nested_element("progressrecords");
        $progress = new backup_nested_element("progress", ["id"], [
            "chapterid", "contentid", "userid", "status", "progress", "details",
            "viewcount", "timeviewed", "firstaccess", "lastaccess", "timecompleted", "timemodified",
        ]);
        $chapterprogressrecords = new backup_nested_element("chapterprogressrecords");
        $chapterprogress = new backup_nested_element("chapterprogress", ["id"], [
            "chapterid", "userid", "status", "viewcount", "timeviewed",
            "firstaccess", "lastaccess", "timecompleted", "timemodified",
        ]);
        $states = new backup_nested_element("states");
        $state = new backup_nested_element("state", ["id"], [
            "userid", "lastchapterid", "lastcontentid", "scrollposition", "progress",
            "timeviewed", "firstaccess", "lastaccess", "timecompleted", "timemodified",
        ]);
        $bookmarks = new backup_nested_element("bookmarks");
        $bookmark = new backup_nested_element("bookmark", ["id"], [
            "userid", "itemtype", "chapterid", "contentid", "timecreated",
        ]);
        $notes = new backup_nested_element("notes");
        $note = new backup_nested_element("note", ["id"], [
            "userid", "chapterid", "contentid", "note", "selectiontext", "timecreated", "timemodified",
        ]);
        $bookflow->add_child($chapters);
        $chapters->add_child($chapter);
        $chapter->add_child($contents);
        $contents->add_child($content);
        $this->add_subplugin_structure("bookflowcontent", $content, true);
        $bookflow->add_child($progressrecords);
        $progressrecords->add_child($progress);
        $bookflow->add_child($states);
        $states->add_child($state);
        $bookflow->add_child($chapterprogressrecords);
        $chapterprogressrecords->add_child($chapterprogress);
        $bookflow->add_child($bookmarks);
        $bookmarks->add_child($bookmark);
        $bookflow->add_child($notes);
        $notes->add_child($note);

        $bookflow->set_source_table("bookflow", ["id" => backup::VAR_ACTIVITYID]);
        $chapter->set_source_table("bookflow_chapters", ["bookflowid" => backup::VAR_PARENTID], "sortorder, id");
        $content->set_source_table("bookflow_contents", ["chapterid" => backup::VAR_PARENTID], "sortorder, id");
        if ($userinfo) {
            $progress->set_source_table("bookflow_user_progress", ["bookflowid" => backup::VAR_PARENTID]);
            $chapterprogress->set_source_table(
                "bookflow_chapter_progress",
                ["bookflowid" => backup::VAR_PARENTID]
            );
            $state->set_source_table("bookflow_user_state", ["bookflowid" => backup::VAR_PARENTID]);
            $bookmark->set_source_table("bookflow_bookmarks", ["bookflowid" => backup::VAR_PARENTID]);
            $note->set_source_table("bookflow_notes", ["bookflowid" => backup::VAR_PARENTID]);
        }

        foreach ([$progress, $chapterprogress, $state, $bookmark, $note] as $userelement) {
            $userelement->annotate_ids("user", "userid");
        }
        $bookflow->annotate_files("mod_bookflow", "intro", null);
        $bookflow->annotate_files("mod_bookflow", "cover", null);
        foreach (\mod_bookflow\content_type_manager::get_classes() as $classname) {
            foreach ($classname::get_fileareas() as $filearea) {
                $content->annotate_files("mod_bookflow", $filearea, "id");
            }
        }

        return $this->prepare_activity_structure($bookflow);
    }
}
