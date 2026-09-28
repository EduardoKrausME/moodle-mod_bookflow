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

use coding_exception;
use mod_flexbook\content_type_manager;
use moodleform;
use Override;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/formslib.php");

/**
 * Defines generic content fields and delegates type-specific behaviour to subplugins.
 */
class content_form extends moodleform {
    /**
     * Defines the form.
     *
     * @return void
     */
    #[Override]
    protected function definition(): void {
        $mform = $this->_form;
        $chapters = $this->_customdata["chapters"] ?? [];
        $type = (string) ($this->_customdata["type"] ?? "");
        $editoroptions = $this->_customdata["editoroptions"] ?? [];
        $fileoptions = $this->_customdata["fileoptions"] ?? [];
        $repeatcount = max(2, (int) ($this->_customdata["repeatcount"] ?? 0));
        $structureddraftid = (int) ($this->_customdata["structureddraftid"] ?? 0);
        $classname = $this->get_content_class($type);

        $mform->addElement("hidden", "contentid");
        $mform->setType("contentid", PARAM_INT);

        $mform->addElement("hidden", "type", $type);
        $mform->setType("type", PARAM_ALPHANUMEXT);
        $mform->addElement(
            "static",
            "typelabel",
            get_string("contenttype", "mod_flexbook"),
            $classname::get_name()
        );

        $mform->addElement("select", "chapterid", get_string("chapter", "mod_flexbook"), $chapters);
        $mform->addRule("chapterid", null, "required", null, "client");

        $mform->addElement("text", "title", get_string("contenttitle", "mod_flexbook"), ["size" => 64]);
        $mform->setType("title", PARAM_TEXT);

        $classname::add_form_fields(
            $this,
            $editoroptions,
            $fileoptions,
            $repeatcount,
            $structureddraftid
        );

        $mform->addElement("header", "progressheading", get_string("progresssettings", "mod_flexbook"));
        $mform->addElement("advcheckbox", "trackprogress", get_string("trackprogress", "mod_flexbook"));
        $mform->setDefault("trackprogress", 1);

        $mform->addElement("advcheckbox", "required", get_string("contentrequired", "mod_flexbook"));
        $mform->disabledIf("required", "trackprogress", "notchecked");

        $mform->addElement("text", "weight", get_string("weight", "mod_flexbook"), ["size" => 8]);
        $mform->setType("weight", PARAM_FLOAT);
        $mform->setDefault("weight", 1);

        $mform->addElement(
            "select",
            "completiontype",
            get_string("completiontype", "mod_flexbook"),
            $classname::get_completion_options()
        );
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
     * Exposes the underlying MoodleQuickForm to subplugins.
     *
     * @return \MoodleQuickForm
     */
    public function get_mform(): \MoodleQuickForm {
        return $this->_form;
    }

    /**
     * Adds a standard HTML editor field.
     *
     * @param string $label Field label.
     * @param array $editoroptions Editor options.
     * @return void
     */
    public function add_editor(string $label, array $editoroptions): void {
        $this->_form->addElement("editor", "data1_editor", $label, ["rows" => 20], $editoroptions);
        $this->_form->setType("data1_editor", PARAM_RAW);
    }

    /**
     * Adds the standard source file and external URL fields.
     *
     * @param array $fileoptions File manager options.
     * @return void
     */
    public function add_source_fields(array $fileoptions): void {
        $this->_form->addElement(
            "filemanager",
            "sourcefile",
            get_string("sourcefile", "mod_flexbook"),
            null,
            $fileoptions
        );
        $this->_form->addElement(
            "url",
            "sourceurl",
            get_string("sourceurl", "mod_flexbook"),
            ["size" => 64]
        );
        $this->_form->setType("sourceurl", PARAM_URL);
        $this->_form->addHelpButton("sourceurl", "sourceurl", "mod_flexbook");
    }

    /**
     * Public wrapper around moodleform::repeat_elements for subplugins.
     *
     * @param array $elements Repeated elements.
     * @param int $repeatcount Initial row count.
     * @param array $options Repeated element options.
     * @param string $repeathiddenname Hidden repeat count field.
     * @param string $addfieldsname Add button field.
     * @param string $addbuttonlabel Add button label.
     * @return void
     */
    public function repeat_content_elements(
        array $elements,
        int $repeatcount,
        array $options,
        string $repeathiddenname,
        string $addfieldsname,
        string $addbuttonlabel
    ): void {
        $this->repeat_elements(
            $elements,
            $repeatcount,
            $options,
            $repeathiddenname,
            $addfieldsname,
            1,
            $addbuttonlabel,
            true
        );
    }

    /**
     * Validates generic fields and delegates type-specific validation.
     *
     * @param mixed $data Submitted data.
     * @param mixed $files Submitted files.
     * @return array
     */
    #[Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $type = (string) ($this->_customdata["type"] ?? "");
        $classname = $this->get_content_class($type);
        $completionoptions = $classname::get_completion_options();

        if (!array_key_exists($data["completiontype"], $completionoptions)) {
            $errors["completiontype"] = get_string("invalidcompletiontypefortype", "mod_flexbook");
        }
        if ($data["weight"] < 0) {
            $errors["weight"] = get_string("weightnegative", "mod_flexbook");
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

        return array_merge($errors, $classname::validate_form((array) $data, (array) $files));
    }

    /**
     * Resolves the selected content class.
     *
     * @param string $type Content type.
     * @return string
     */
    private function get_content_class(string $type): string {
        $classes = content_type_manager::get_classes();
        if ($type === "" || !isset($classes[$type])) {
            throw new coding_exception("Unknown FlexBook content type: {$type}");
        }
        return $classes[$type];
    }
}
