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
 * Question content backup.
 *
 * @package flexbookcontent_question
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds question-specific data below each FlexBook content block.
 */
class backup_flexbookcontent_question_subplugin extends backup_subplugin {
    /**
     * Defines data attached to the content connection point.
     *
     * @return backup_subplugin_element
     */
    protected function define_content_subplugin_structure() {
        $subplugin = $this->get_subplugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $question = new backup_nested_element("question", ["id"], [
            "questiontext",
            "optionsjson",
            "answerjson",
            "feedback",
            "timecreated",
            "timemodified",
        ]);
        $attempts = new backup_nested_element("attempts");
        $attempt = new backup_nested_element("attempt", ["id"], [
            "userid",
            "answerjson",
            "iscorrect",
            "attemptnumber",
            "timecreated",
        ]);

        $subplugin->add_child($wrapper);
        $wrapper->add_child($question);
        $question->add_child($attempts);
        $attempts->add_child($attempt);

        $question->set_source_table("flexbook_questions", [
            "contentid" => backup::VAR_PARENTID,
        ]);
        if ($this->get_setting_value("userinfo")) {
            $attempt->set_source_table("flexbook_question_attempts", [
                "questionid" => backup::VAR_PARENTID,
            ]);
            $attempt->annotate_ids("user", "userid");
        }

        return $subplugin;
    }
}
