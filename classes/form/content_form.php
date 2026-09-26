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
 * content_form.php
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
 * Defines the content form.
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

        if ($type === "html") {
            $mform->addElement(
                "editor",
                "data1_editor",
                get_string("contenthtml", "mod_flexbook"),
                ["rows" => 20],
                $editoroptions
            );
            $mform->setType("data1_editor", PARAM_RAW);
        } else {
            $mform->addElement("textarea", "data1", get_string("contentdata1", "mod_flexbook"), [
                "rows" => 14,
                "cols" => 80,
            ]);
            $mform->setType("data1", PARAM_RAW);
            $mform->addHelpButton("data1", "contentdata1", "mod_flexbook");

            $mform->addElement("textarea", "data2", get_string("contentdata2", "mod_flexbook"), [
                "rows" => 4,
                "cols" => 80,
            ]);
            $mform->setType("data2", PARAM_RAW);
            $mform->addElement("textarea", "data3", get_string("contentdata3", "mod_flexbook"), [
                "rows" => 4,
                "cols" => 80,
            ]);
            $mform->setType("data3", PARAM_RAW);
        }

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

        $mform->addElement("text", "completionvalue", get_string("completionvalue", "mod_flexbook"), ["size" => 8]);
        $mform->setType("completionvalue", PARAM_FLOAT);
        $mform->setDefault("completionvalue", 0);

        $mform->addElement("text", "auxint1", get_string("itemcount", "mod_flexbook"), ["size" => 8]);
        $mform->setType("auxint1", PARAM_INT);
        $mform->setDefault("auxint1", 0);

        $mform->addElement("duration", "estimatedtime", get_string("estimatedtime", "mod_flexbook"),
            ["optional" => true]);
        $mform->addElement("advcheckbox", "hidden", get_string("hidden", "mod_flexbook"));

        $this->add_action_buttons();
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
        if ($data["weight"] < 0) {
            $errors["weight"] = get_string("weightnegative", "mod_flexbook");
        }
        if (in_array($data["completiontype"], ["percent", "end"])
                && ($data["completionvalue"] <= 0 || $data["completionvalue"] > 100)) {
            $errors["completionvalue"] = get_string("percentageerror", "mod_flexbook");
        }
        if ($data["completiontype"] == "timed" && $data["completionvalue"] <= 0) {
            $errors["completionvalue"] = get_string("completiontimepositive", "mod_flexbook");
        }
        if (in_array($data["completiontype"], ["allitems", "alltabs", "allcards"])
                && $data["auxint1"] <= 0) {
            $errors["auxint1"] = get_string("itemcountpositive", "mod_flexbook");
        }
        if (!empty($data["trackprogress"]) && $data["completiontype"] == "none") {
            $errors["completiontype"] = get_string("trackprogressneedscompletion", "mod_flexbook");
        }
        if (!empty($data["required"])
                && (empty($data["trackprogress"]) || $data["completiontype"] == "none")) {
            $errors["required"] = get_string("requiredneedstracking", "mod_flexbook");
        }
        return $errors;
    }
}
