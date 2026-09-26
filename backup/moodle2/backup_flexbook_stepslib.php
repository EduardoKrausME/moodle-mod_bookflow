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
 * backup_flexbook_stepslib.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Builds the nested data structure used to back up a FlexBook activity.
 */
class backup_flexbook_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup or restore data structure.
     *
     * @return backup_nested_element
     */
    #[Override]
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value("userinfo");

        $flexbook = new backup_nested_element("flexbook", ["id"], [
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
        $questions = new backup_nested_element("questions");
        $question = new backup_nested_element("question", ["id"], [
            "questiontext", "optionsjson", "answerjson", "feedback", "timecreated", "timemodified",
        ]);
        $attempts = new backup_nested_element("attempts");
        $attempt = new backup_nested_element("attempt", ["id"], [
            "userid", "answerjson", "iscorrect", "attemptnumber", "timecreated",
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
        $highlights = new backup_nested_element("highlights");
        $highlight = new backup_nested_element("highlight", ["id"], [
            "userid", "chapterid", "contentid", "selectiontext", "selector", "color", "timecreated",
        ]);

        $flexbook->add_child($chapters);
        $chapters->add_child($chapter);
        $chapter->add_child($contents);
        $contents->add_child($content);
        $content->add_child($questions);
        $questions->add_child($question);
        $question->add_child($attempts);
        $attempts->add_child($attempt);
        $flexbook->add_child($progressrecords);
        $progressrecords->add_child($progress);
        $flexbook->add_child($states);
        $states->add_child($state);
        $flexbook->add_child($chapterprogressrecords);
        $chapterprogressrecords->add_child($chapterprogress);
        $flexbook->add_child($bookmarks);
        $bookmarks->add_child($bookmark);
        $flexbook->add_child($notes);
        $notes->add_child($note);
        $flexbook->add_child($highlights);
        $highlights->add_child($highlight);

        $flexbook->set_source_table("flexbook", ["id" => backup::VAR_ACTIVITYID]);
        $chapter->set_source_table("flexbook_chapters", ["flexbookid" => backup::VAR_PARENTID], "sortorder, id");
        $content->set_source_table("flexbook_contents", ["chapterid" => backup::VAR_PARENTID], "sortorder, id");
        $question->set_source_table("flexbook_questions", ["contentid" => backup::VAR_PARENTID]);
        if ($userinfo) {
            $attempt->set_source_table("flexbook_question_attempts", ["questionid" => backup::VAR_PARENTID]);
            $progress->set_source_table("flexbook_user_progress", ["flexbookid" => backup::VAR_PARENTID]);
            $chapterprogress->set_source_table(
                "flexbook_chapter_progress",
                ["flexbookid" => backup::VAR_PARENTID]
            );
            $state->set_source_table("flexbook_user_state", ["flexbookid" => backup::VAR_PARENTID]);
            $bookmark->set_source_table("flexbook_bookmarks", ["flexbookid" => backup::VAR_PARENTID]);
            $note->set_source_table("flexbook_notes", ["flexbookid" => backup::VAR_PARENTID]);
            $highlight->set_source_table("flexbook_highlights", ["flexbookid" => backup::VAR_PARENTID]);
        }

        foreach ([$attempt, $progress, $chapterprogress, $state, $bookmark, $note, $highlight] as $userelement) {
            $userelement->annotate_ids("user", "userid");
        }
        $flexbook->annotate_files("mod_flexbook", "intro", null);
        $flexbook->annotate_files("mod_flexbook", "cover", null);
        $content->annotate_files("mod_flexbook", "content", "id");
        $content->annotate_files("mod_flexbook", "download", "id");

        return $this->prepare_activity_structure($flexbook);
    }
}
