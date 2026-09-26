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
 * Content editing form.
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\form;

use mod_flexbook\content_type_manager;
use moodleform;
use Override;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/formslib.php");

/**
 * Defines the type-specific content form.
 */
class content_form extends moodleform {
    /**
     * Defines the form fields.
     *
     * @return void
     */
    #[Override]
    protected function definition(): void {
        $mform = $this->_form;
        $chapters = $this->_customdata["chapters"] ?? [];
        $type = $this->_customdata["type"] ?? "html";
        $editoroptions = $this->_customdata["editoroptions"] ?? [];
        $fileoptions = $this->_customdata["fileoptions"] ?? [];
        $repeatcount = max(2, (int) ($this->_customdata["repeatcount"] ?? 2));
        $structureddraftid = (int) ($this->_customdata["structureddraftid"] ?? 0);
        $typeoptions = content_type_manager::get_type_options();

        $mform->addElement("hidden", "contentid");
        $mform->setType("contentid", PARAM_INT);

        $mform->addElement("hidden", "type", $type);
        $mform->setType("type", PARAM_ALPHANUMEXT);
        $mform->addElement(
            "static",
            "typelabel",
            get_string("contenttype", "mod_flexbook"),
            $typeoptions[$type] ?? $type
        );

        $mform->addElement("select", "chapterid", get_string("chapter", "mod_flexbook"), $chapters);
        $mform->addRule("chapterid", null, "required", null, "client");

        $mform->addElement("text", "title", get_string("contenttitle", "mod_flexbook"), ["size" => 64]);
        $mform->setType("title", PARAM_TEXT);

        $this->add_type_fields($type, $editoroptions, $fileoptions, $repeatcount, $structureddraftid);

        $mform->addElement("header", "progressheading", get_string("progresssettings", "mod_flexbook"));
        $mform->addElement("advcheckbox", "trackprogress", get_string("trackprogress", "mod_flexbook"));
        $mform->setDefault("trackprogress", 1);

        $mform->addElement("advcheckbox", "required", get_string("contentrequired", "mod_flexbook"));
        $mform->disabledIf("required", "trackprogress", "notchecked");

        $mform->addElement("text", "weight", get_string("weight", "mod_flexbook"), ["size" => 8]);
        $mform->setType("weight", PARAM_FLOAT);
        $mform->setDefault("weight", 1);

        $mform->addElement("select", "completiontype", get_string("completiontype", "mod_flexbook"), [
            "view" => get_string("completeonview", "mod_flexbook"),
            "timed" => get_string("completeontime", "mod_flexbook"),
            "manual" => get_string("completemanually", "mod_flexbook"),
            "percent" => get_string("completeonpercent", "mod_flexbook"),
            "end" => get_string("completeonend", "mod_flexbook"),
            "click" => get_string("completeonclick", "mod_flexbook"),
            "answer" => get_string("completeonanswer", "mod_flexbook"),
            "attempt" => get_string("completeonattempt", "mod_flexbook"),
            "correct" => get_string("completeoncorrect", "mod_flexbook"),
            "allitems" => get_string("completeallitems", "mod_flexbook"),
            "alltabs" => get_string("completealltabs", "mod_flexbook"),
            "allcards" => get_string("completeallcards", "mod_flexbook"),
            "none" => get_string("donottrack", "mod_flexbook"),
        ]);
        $mform->setDefault("completiontype", "view");

        $mform->addElement(
            "text",
            "completionvalue",
            get_string("completionvalue", "mod_flexbook"),
            ["size" => 8]
        );
        $mform->setType("completionvalue", PARAM_FLOAT);
        $mform->setDefault("completionvalue", 0);

        $mform->addElement("hidden", "auxint1", 0);
        $mform->setType("auxint1", PARAM_INT);

        $mform->addElement(
            "duration",
            "estimatedtime",
            get_string("estimatedtime", "mod_flexbook"),
            ["optional" => true]
        );
        $mform->addElement("advcheckbox", "hidden", get_string("hidden", "mod_flexbook"));

        $this->add_action_buttons();
    }

    /**
     * Adds fields specific to the selected content type.
     *
     * @param string $type Content type.
     * @param array $editoroptions Editor options.
     * @param array $fileoptions Filemanager options.
     * @param int $repeatcount Number of initial repeated rows.
     * @param int $structureddraftid Shared draft id for repeated editors.
     * @return void
     */
    private function add_type_fields(
        string $type,
        array $editoroptions,
        array $fileoptions,
        int $repeatcount,
        int $structureddraftid
    ): void {
        $mform = $this->_form;

        switch ($type) {
            case "html":
                $this->add_editor(get_string("contenthtml", "mod_flexbook"), $editoroptions);
                break;

            case "callout":
                $this->add_editor(get_string("calloutcontent", "mod_flexbook"), $editoroptions);
                break;

            case "disclosure":
                $this->add_editor(get_string("disclosurecontent", "mod_flexbook"), $editoroptions);
                break;

            case "markdown":
                $mform->addElement(
                    "textarea",
                    "rawcontent",
                    get_string("markdowncontent", "mod_flexbook"),
                    ["rows" => 18, "cols" => 90]
                );
                $mform->setType("rawcontent", PARAM_RAW);
                $mform->addRule("rawcontent", null, "required", null, "client");
                break;

            case "code":
                $mform->addElement(
                    "textarea",
                    "rawcontent",
                    get_string("codecontent", "mod_flexbook"),
                    ["rows" => 18, "cols" => 90, "class" => "font-monospace"]
                );
                $mform->setType("rawcontent", PARAM_RAW);
                $mform->addRule("rawcontent", null, "required", null, "client");
                break;

            case "image":
                $this->add_source_fields($fileoptions);
                $mform->addElement("text", "imagealt", get_string("imagealt", "mod_flexbook"), ["size" => 64]);
                $mform->setType("imagealt", PARAM_TEXT);
                $mform->addElement("text", "caption", get_string("caption", "mod_flexbook"), ["size" => 64]);
                $mform->setType("caption", PARAM_TEXT);
                break;

            case "video":
                $this->add_source_fields($fileoptions);
                $mform->addElement(
                    "url",
                    "captionsurl",
                    get_string("captionsurl", "mod_flexbook"),
                    ["size" => 64]
                );
                $mform->setType("captionsurl", PARAM_URL);
                $mform->addElement(
                    "textarea",
                    "transcript",
                    get_string("transcript", "mod_flexbook"),
                    ["rows" => 8, "cols" => 90]
                );
                $mform->setType("transcript", PARAM_RAW);
                break;

            case "audio":
                $this->add_source_fields($fileoptions);
                $mform->addElement(
                    "textarea",
                    "transcript",
                    get_string("transcript", "mod_flexbook"),
                    ["rows" => 8, "cols" => 90]
                );
                $mform->setType("transcript", PARAM_RAW);
                break;

            case "download":
                $this->add_source_fields($fileoptions);
                $mform->addElement(
                    "textarea",
                    "filedescription",
                    get_string("filedescription", "mod_flexbook"),
                    ["rows" => 5, "cols" => 90]
                );
                $mform->setType("filedescription", PARAM_TEXT);
                break;

            case "accordion":
            case "tabs":
                $this->add_title_content_repeater($repeatcount, $editoroptions, $structureddraftid);
                break;

            case "flashcards":
                $this->add_flashcard_repeater($repeatcount);
                break;

            case "question":
                $this->add_editor(get_string("questiontext", "mod_flexbook"), $editoroptions);
                $this->add_question_options($repeatcount);
                $mform->addElement(
                    "textarea",
                    "questionfeedback",
                    get_string("questionfeedback", "mod_flexbook"),
                    ["rows" => 5, "cols" => 90]
                );
                $mform->setType("questionfeedback", PARAM_TEXT);
                break;
        }
    }

    /**
     * Adds one Moodle HTML editor backed by the content file area.
     *
     * @param string $label Field label.
     * @param array $editoroptions Editor options.
     * @return void
     */
    private function add_editor(string $label, array $editoroptions): void {
        $mform = $this->_form;
        $mform->addElement("editor", "data1_editor", $label, ["rows" => 20], $editoroptions);
        $mform->setType("data1_editor", PARAM_RAW);
    }

    /**
     * Adds an uploaded source file plus optional external URL.
     *
     * @param array $fileoptions Filemanager options.
     * @return void
     */
    private function add_source_fields(array $fileoptions): void {
        $mform = $this->_form;
        $mform->addElement(
            "filemanager",
            "sourcefile",
            get_string("sourcefile", "mod_flexbook"),
            null,
            $fileoptions
        );
        $mform->addElement(
            "url",
            "sourceurl",
            get_string("sourceurl", "mod_flexbook"),
            ["size" => 64]
        );
        $mform->setType("sourceurl", PARAM_URL);
        $mform->addHelpButton("sourceurl", "sourceurl", "mod_flexbook");
    }

    /**
     * Adds a repeater for accordion and tab items.
     *
     * @param int $repeatcount Initial number of rows.
     * @param array $editoroptions Editor options.
     * @param int $structureddraftid Shared draft id.
     * @return void
     */
    private function add_title_content_repeater(
        int $repeatcount,
        array $editoroptions,
        int $structureddraftid
    ): void {
        $mform = $this->_form;
        $repeat = [
            $mform->createElement("text", "itemtitle", get_string("itemtitle", "mod_flexbook"), ["size" => 56]),
            $mform->createElement(
                "editor",
                "itemcontent",
                get_string("itemcontent", "mod_flexbook"),
                ["rows" => 10],
                $editoroptions
            ),
        ];
        $options = [
            "itemtitle" => ["type" => PARAM_TEXT],
            "itemcontent" => [
                "type" => PARAM_RAW,
                "default" => [
                    "text" => "",
                    "format" => FORMAT_HTML,
                    "itemid" => $structureddraftid,
                ],
            ],
        ];
        $this->repeat_elements(
            $repeat,
            $repeatcount,
            $options,
            "item_repeats",
            "item_add_fields",
            1,
            get_string("additem", "mod_flexbook"),
            true
        );
    }

    /**
     * Adds a repeater for flashcards.
     *
     * @param int $repeatcount Initial number of rows.
     * @return void
     */
    private function add_flashcard_repeater(int $repeatcount): void {
        $mform = $this->_form;
        $repeat = [
            $mform->createElement(
                "textarea",
                "cardfront",
                get_string("cardfront", "mod_flexbook"),
                ["rows" => 4, "cols" => 80]
            ),
            $mform->createElement(
                "textarea",
                "cardback",
                get_string("cardback", "mod_flexbook"),
                ["rows" => 4, "cols" => 80]
            ),
        ];
        $options = [
            "cardfront" => ["type" => PARAM_RAW],
            "cardback" => ["type" => PARAM_RAW],
        ];
        $this->repeat_elements(
            $repeat,
            $repeatcount,
            $options,
            "card_repeats",
            "card_add_fields",
            1,
            get_string("addcard", "mod_flexbook"),
            true
        );
    }

    /**
     * Adds repeated single-choice question options.
     *
     * @param int $repeatcount Initial number of options.
     * @return void
     */
    private function add_question_options(int $repeatcount): void {
        $mform = $this->_form;
        $repeat = [
            $mform->createElement("text", "optiontext", get_string("optiontext", "mod_flexbook"), ["size" => 56]),
            $mform->createElement("hidden", "optionvalue"),
            $mform->createElement("advcheckbox", "optioncorrect", get_string("optioncorrect", "mod_flexbook")),
        ];
        $options = [
            "optiontext" => ["type" => PARAM_TEXT],
            "optionvalue" => ["type" => PARAM_RAW_TRIMMED],
            "optioncorrect" => ["type" => PARAM_BOOL],
        ];
        $this->repeat_elements(
            $repeat,
            $repeatcount,
            $options,
            "option_repeats",
            "option_add_fields",
            1,
            get_string("addoption", "mod_flexbook"),
            true
        );
    }

    /**
     * Validates submitted form data.
     *
     * @param mixed $data Record data.
     * @param mixed $files Submitted files.
     * @return array
     */
    #[Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $type = $this->_customdata["type"] ?? "html";

        if ($data["weight"] < 0) {
            $errors["weight"] = get_string("weightnegative", "mod_flexbook");
        }
        if (in_array($data["completiontype"], ["percent", "end"], true)
                && ($data["completionvalue"] <= 0 || $data["completionvalue"] > 100)) {
            $errors["completionvalue"] = get_string("percentageerror", "mod_flexbook");
        }
        if ($data["completiontype"] === "timed" && $data["completionvalue"] <= 0) {
            $errors["completionvalue"] = get_string("completiontimepositive", "mod_flexbook");
        }
        if (!empty($data["trackprogress"]) && $data["completiontype"] === "none") {
            $errors["completiontype"] = get_string("trackprogressneedscompletion", "mod_flexbook");
        }
        if (!empty($data["required"])
                && (empty($data["trackprogress"]) || $data["completiontype"] === "none")) {
            $errors["required"] = get_string("requiredneedstracking", "mod_flexbook");
        }

        if (in_array($type, ["html", "callout", "disclosure", "question"], true)) {
            $editor = (array) ($data["data1_editor"] ?? []);
            if (trim((string) ($editor["text"] ?? "")) === "") {
                $errors["data1_editor"] = get_string("contentrequiredfield", "mod_flexbook");
            }
        }

        if (in_array($type, ["image", "video", "audio", "download"], true)) {
            $hasurl = !empty(trim((string) ($data["sourceurl"] ?? "")));
            $hasfile = false;
            if (!empty($data["sourcefile"])) {
                $info = file_get_draft_area_info((int) $data["sourcefile"]);
                $hasfile = !empty($info["filecount"]);
            }
            if (!$hasurl && !$hasfile) {
                $errors["sourcefile"] = get_string("sourcerequired", "mod_flexbook");
            }
        }

        if (in_array($type, ["accordion", "tabs"], true)) {
            $count = $this->count_nonempty_pairs(
                (array) ($data["itemtitle"] ?? []),
                (array) ($data["itemcontent"] ?? [])
            );
            if ($count === 0) {
                $errors["itemtitle[0]"] = get_string("interactiveitemrequired", "mod_flexbook");
            }
        }

        if ($type === "flashcards") {
            $count = $this->count_nonempty_pairs(
                (array) ($data["cardfront"] ?? []),
                (array) ($data["cardback"] ?? [])
            );
            if ($count === 0) {
                $errors["cardfront[0]"] = get_string("flashcardrequired", "mod_flexbook");
            }
        }

        if ($type === "question") {
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
        }

        if (in_array($data["completiontype"], ["allitems", "alltabs", "allcards"], true)) {
            $count = match ($type) {
                "accordion", "tabs" => $this->count_nonempty_pairs(
                    (array) ($data["itemtitle"] ?? []),
                    (array) ($data["itemcontent"] ?? [])
                ),
                "flashcards" => $this->count_nonempty_pairs(
                    (array) ($data["cardfront"] ?? []),
                    (array) ($data["cardback"] ?? [])
                ),
                "disclosure" => 1,
                default => 0,
            };
            if ($count <= 0) {
                $errors["completiontype"] = get_string("itemcountpositive", "mod_flexbook");
            }
        }

        return $errors;
    }

    /**
     * Counts non-empty pairs of repeated values.
     *
     * @param array $left Left values.
     * @param array $right Right values.
     * @return int
     */
    private function count_nonempty_pairs(array $left, array $right): int {
        $count = 0;
        $size = max(count($left), count($right));
        for ($i = 0; $i < $size; $i++) {
            if ($this->value_text($left[$i] ?? "") !== ""
                    || $this->value_text($right[$i] ?? "") !== "") {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Extracts comparable text from a normal or editor form value.
     *
     * @param mixed $value Form value.
     * @return string
     */
    private function value_text($value): string {
        if (is_array($value)) {
            return trim((string) ($value["text"] ?? ""));
        }
        return trim((string) $value);
    }
}
