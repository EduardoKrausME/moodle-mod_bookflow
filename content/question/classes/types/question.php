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
 * question.php
 *
 * @package   flexbookcontent_question
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace flexbookcontent_question\types;

use mod_flexbook\types\template_content;
use renderer_base;
use stdClass;

/**
 * FlexBook Question content type.
 */
class question extends template_content {

    /** @var string */
    protected static string $type = "question";

    /**
     * Builds the data passed to the content Mustache template.
     *
     * @param bool $editing Whether editing controls are enabled.
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function export_data(bool $editing): array {
        global $DB;

        $data = parent::export_data($editing);
        $question = $DB->get_record("flexbook_questions", ["contentid" => $this->record->id]);
        if (!$question) {
            $data["items"] = [];
            $data["hasitems"] = false;
            return $data;
        }
        $options = json_decode($question->optionsjson, true) ?: [];
        $items = [];
        foreach ($options as $index => $option) {
            if (is_array($option)) {
                $items[] = [
                    "title" => s($option["title"] ?? $option["label"] ?? ""),
                    "value" => s($option["value"] ?? $index),
                ];
            } else {
                $items[] = ["title" => s($option), "value" => s($index)];
            }
        }
        $questiontext = file_rewrite_pluginfile_urls(
            $question->questiontext,
            "pluginfile.php",
            $this->context->id,
            "mod_flexbook",
            "content",
            $this->record->id
        );
        $data["content"] = format_text($questiontext, FORMAT_HTML, [
            "context" => $this->context,
            "filter" => true,
        ]);
        $data["items"] = $items;
        $data["hasitems"] = !empty($items);
        return $data;
    }


    /**
     * Validates question completion from persisted attempts.
     *
     * @param stdClass $progress User progress record.
     * @return bool
     */
    public function completion_evidence_is_valid(stdClass $progress): bool {
        global $DB;

        if (!in_array($this->record->completiontype, ["answer", "attempt", "correct"])) {
            return parent::completion_evidence_is_valid($progress);
        }

        $sql = "SELECT COUNT(a.id)
                  FROM {flexbook_question_attempts} a
                  JOIN {flexbook_questions} q ON q.id = a.questionid
                 WHERE q.contentid = :contentid
                   AND a.userid = :userid";
        $params = [
            "contentid" => $this->record->id,
            "userid" => $progress->userid,
        ];
        if ($this->record->completiontype == "correct") {
            $sql .= " AND a.iscorrect = 1";
        }
        return $DB->count_records_sql($sql, $params) > 0;
    }

    /**
     * Renders the content block using the template owned by this subplugin.
     *
     * @param renderer_base $output Moodle renderer used to render the Mustache template.
     * @param bool $editing Whether editing controls are enabled.
     * @return string
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "flexbookcontent_question/question",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string("pluginname", "flexbookcontent_question");
    }
}
