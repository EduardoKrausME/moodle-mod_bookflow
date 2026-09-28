<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Question content backup.
 *
 * @package flexbookcontent_question
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
