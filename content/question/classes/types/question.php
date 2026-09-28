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

use context_module;
use mod_flexbook\form\content_form;
use mod_flexbook\form\content_form_mapper;
use flexbookcontent_question\question_manager;
use mod_flexbook\types\editor_content;
use renderer_base;
use stdClass;

/**
 * FlexBook Question content type.
 */
class question extends editor_content {
    /** @var string */
    protected static string $type = "question";

    /**
     * Gets the question editor label.
     */
    protected static function get_editor_label(): string {
        return get_string("questiontext", "mod_flexbook");
    }

    /**
     * Adds question-specific fields.
     */
    public static function add_form_fields(
        content_form $form,
        array $editoroptions,
        array $fileoptions,
        int $repeatcount,
        int $structureddraftid
    ): void {
        parent::add_form_fields($form, $editoroptions, $fileoptions, $repeatcount, $structureddraftid);

        $mform = $form->get_mform();
        $repeat = [
            $mform->createElement(
                "text",
                "optiontext",
                get_string("optiontext", "mod_flexbook"),
                ["size" => 56]
            ),
            $mform->createElement("hidden", "optionvalue"),
            $mform->createElement(
                "advcheckbox",
                "optioncorrect",
                get_string("optioncorrect", "mod_flexbook")
            ),
        ];
        $options = [
            "optiontext" => ["type" => PARAM_TEXT],
            "optionvalue" => ["type" => PARAM_RAW_TRIMMED],
            "optioncorrect" => ["type" => PARAM_BOOL],
        ];
        $form->repeat_content_elements(
            $repeat,
            $repeatcount,
            $options,
            "option_repeats",
            "option_add_fields",
            get_string("addoption", "mod_flexbook")
        );

        $mform->addElement(
            "textarea",
            "questionfeedback",
            get_string("questionfeedback", "mod_flexbook"),
            ["rows" => 5, "cols" => 90]
        );
        $mform->setType("questionfeedback", PARAM_TEXT);
    }

    /**
     * Gets question completion rules.
     */
    public static function get_completion_options(): array {
        $options = parent::get_completion_options();
        $none = $options["none"];
        unset($options["none"]);
        $options["answer"] = get_string("completeonanswer", "mod_flexbook");
        $options["attempt"] = get_string("completeonattempt", "mod_flexbook");
        $options["correct"] = get_string("completeoncorrect", "mod_flexbook");
        $options["none"] = $none;
        return $options;
    }

    /**
     * Validates question options.
     */
    public static function validate_form(array $data, array $files): array {
        $errors = parent::validate_form($data, $files);
        $texts = (array) ($data["optiontext"] ?? []);
        $correct = (array) ($data["optioncorrect"] ?? []);
        $optioncount = 0;
        $correctcount = 0;

        foreach ($texts as $index => $text) {
            if (trim((string) $text) === "") {
                continue;
            }
            $optioncount++;
            if (!empty($correct[$index])) {
                $correctcount++;
            }
        }

        if ($optioncount < 2) {
            $errors["optiontext[0]"] = get_string("questionoptionsrequired", "mod_flexbook");
        } else if ($correctcount !== 1) {
            $errors["optioncorrect[0]"] = get_string("questioncorrectrequired", "mod_flexbook");
        }
        return $errors;
    }

    /**
     * Gets initial option count.
     */
    public static function get_repeat_count(?stdClass $content): int {
        if (!$content) {
            return 2;
        }
        return max(2, count(content_form_mapper::decode_items($content->data2 ?? "[]")));
    }

    /**
     * Prepares question-specific form data.
     */
    public static function prepare_form_data(
        stdClass $record,
        context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        $record = parent::prepare_form_data(
            $record,
            $context,
            $editoroptions,
            $fileoptions,
            $structureddraftid
        );

        $options = content_form_mapper::decode_items($record->data2 ?? "[]");
        $configuration = json_decode($record->data3 ?? "{}", true);
        if (!is_array($configuration)) {
            $configuration = [];
        }
        $answer = $configuration["answer"] ?? null;

        $record->optiontext = [];
        $record->optionvalue = [];
        $record->optioncorrect = [];
        foreach ($options as $index => $option) {
            $value = (string) ($option["value"] ?? $index);
            $record->optiontext[] = (string) ($option["title"] ?? "");
            $record->optionvalue[] = $value;
            $record->optioncorrect[] = $answer !== null && $answer == $value ? 1 : 0;
        }
        $record->questionfeedback = (string) ($configuration["feedback"] ?? "");
        return $record;
    }

    /**
     * Stores question-specific fields.
     */
    public static function to_record(stdClass $data): stdClass {
        $texts = (array) ($data->optiontext ?? []);
        $values = (array) ($data->optionvalue ?? []);
        $correct = (array) ($data->optioncorrect ?? []);
        $feedback = trim((string) ($data->questionfeedback ?? ""));

        $data = parent::to_record($data);

        $options = [];
        $usedvalues = [];
        $answer = null;
        foreach ($texts as $index => $text) {
            $text = trim((string) $text);
            if ($text === "") {
                continue;
            }

            $value = trim((string) ($values[$index] ?? ""));
            if ($value === "" || isset($usedvalues[$value])) {
                $value = "option_" . $index;
            }
            $usedvalues[$value] = true;
            $options[] = ["title" => $text, "value" => $value];

            if (!empty($correct[$index])) {
                $answer = $value;
            }
        }

        $data->data2 = content_form_mapper::encode_items($options);
        $data->data3 = json_encode([
            "answer" => $answer,
            "feedback" => $feedback,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        content_form_mapper::unset_fields($data, [
            "optiontext",
            "optionvalue",
            "optioncorrect",
            "option_repeats",
            "option_add_fields",
            "questionfeedback",
        ]);
        return $data;
    }

    /**
     * Synchronizes the question table after creation.
     */
    public static function after_create(int $contentid): void {
        question_manager::sync_from_content($contentid);
    }

    /**
     * Synchronizes the question table after update.
     */
    public static function after_update(int $contentid): void {
        question_manager::sync_from_content($contentid);
    }

    /**
     * Removes question-specific records before content deletion.
     */
    public static function before_delete(int $contentid): void {
        question_manager::delete_for_content($contentid);
    }

    /**
     * Builds the data passed to the question Mustache template.
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
     * Uses a stable default estimate for one question.
     */
    public function estimate_time(): int {
        if (!empty($this->record->estimatedtime)) {
            return (int) $this->record->estimatedtime;
        }
        return 60;
    }

    /**
     * Renders the answer only when exports explicitly request it.
     */
    public function render_export_extra(bool $showanswers): string {
        global $DB;

        if (!$showanswers) {
            return "";
        }
        $answer = $DB->get_field("flexbook_questions", "answerjson", [
            "contentid" => $this->record->id,
        ]);
        if ($answer === false || $answer === null) {
            return "";
        }

        $decoded = json_decode($answer, true);
        $answertext = is_array($decoded) ? json_encode($decoded) : $decoded;
        return "<div class=\"answer\"><strong>"
            . get_string("answer", "flexbookcontent_question")
            . ":</strong> "
            . s($answertext)
            . "</div>";
    }

    /**
     * Contributes question attempt counts to the user report.
     */
    public static function get_user_report_columns(): array {
        return [
            "questionsanswered" => get_string("questionsanswered", "flexbookcontent_question"),
        ];
    }

    /**
     * Gets question attempt counts keyed by user id.
     */
    public static function get_user_report_data(int $flexbookid): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT a.userid, COUNT(a.id) AS questionsanswered
               FROM {flexbook_question_attempts} a
               JOIN {flexbook_questions} q ON q.id = a.questionid
               JOIN {flexbook_contents} c ON c.id = q.contentid
               JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
              WHERE ch.flexbookid = :flexbookid
           GROUP BY a.userid",
            ["flexbookid" => $flexbookid]
        );

        $result = [];
        foreach ($records as $record) {
            $result[(int) $record->userid] = [
                "questionsanswered" => (int) $record->questionsanswered,
            ];
        }
        return $result;
    }

    /**
     * Loads question submission behaviour from this subplugin.
     */
    public static function require_page_assets(stdClass $flexbook, bool $editing): void {
        global $PAGE;
        if (!$editing) {
            $PAGE->requires->js_call_amd(
                "flexbookcontent_question/question",
                "init",
                [$flexbook->id]
            );
        }
    }

    /**
     * Renders this content block.
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "flexbookcontent_question/question",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "flexbookcontent_question");
    }

}
