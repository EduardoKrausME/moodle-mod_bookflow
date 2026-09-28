<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Question content restore.
 *
 * @package flexbookcontent_question
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restores question-specific data attached to FlexBook content blocks.
 */
class restore_flexbookcontent_question_subplugin extends restore_subplugin {
    /**
     * Defines restore paths attached to the content connection point.
     *
     * @return array
     */
    protected function define_content_subplugin_structure() {
        $paths = [];
        $paths[] = new restore_path_element(
            $this->get_namefor("question"),
            $this->get_pathfor("/question")
        );
        if ($this->get_setting_value("userinfo")) {
            $paths[] = new restore_path_element(
                $this->get_namefor("attempt"),
                $this->get_pathfor("/question/attempts/attempt")
            );
        }
        return $paths;
    }

    /**
     * Restores one question definition.
     *
     * @param array $data Backup data.
     * @return void
     */
    public function process_flexbookcontent_question_question($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->contentid = $this->get_new_parentid("flexbook_content");
        $newid = $DB->insert_record("flexbook_questions", $data);
        $this->set_mapping("flexbook_question", $oldid, $newid);
    }

    /**
     * Restores one user attempt.
     *
     * @param array $data Backup data.
     * @return void
     */
    public function process_flexbookcontent_question_attempt($data): void {
        global $DB;

        $data = (object) $data;
        $data->questionid = $this->get_mappingid("flexbook_question", $data->questionid);
        $data->userid = $this->get_mappingid("user", $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        if ($data->questionid && $data->userid) {
            $DB->insert_record("flexbook_question_attempts", $data);
        }
    }
}
