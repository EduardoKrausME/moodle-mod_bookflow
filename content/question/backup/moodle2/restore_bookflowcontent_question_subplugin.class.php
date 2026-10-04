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
 * Question content restore.
 *
 * @package bookflowcontent_question
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores question-specific data attached to BookFlow content blocks.
 */
class restore_bookflowcontent_question_subplugin extends restore_subplugin {
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
    public function process_bookflowcontent_question_question($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->contentid = $this->get_new_parentid("bookflow_content");
        $newid = $DB->insert_record("bookflow_questions", $data);
        $this->set_mapping("bookflow_question", $oldid, $newid);
    }

    /**
     * Restores one user attempt.
     *
     * @param array $data Backup data.
     * @return void
     */
    public function process_bookflowcontent_question_attempt($data): void {
        global $DB;

        $data = (object) $data;
        $data->questionid = $this->get_mappingid("bookflow_question", $data->questionid);
        $data->userid = $this->get_mappingid("user", $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        if ($data->questionid && $data->userid) {
            $DB->insert_record("bookflow_question_attempts", $data);
        }
    }
}
