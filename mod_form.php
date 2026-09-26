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
 * mod_form.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_flexbook\content_type_manager;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . "/course/moodleform_mod.php");

/**
 * Defines the FlexBook activity settings form.
 */
class mod_flexbook_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     *
     * @return void
     */
    #[Override]
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement("text", "name", get_string("flexbookname", "mod_flexbook"), ["size" => 64]);
        $mform->setType("name", PARAM_TEXT);
        $mform->addRule("name", null, "required", null, "client");

        $this->standard_intro_elements();

        $mform->addElement("filemanager", "cover", get_string("cover", "mod_flexbook"), null, [
            "accepted_types" => ["image"],
            "maxfiles" => 1,
            "subdirs" => 0,
        ]);

        $mform->addElement("header", "appearance", get_string("appearance"));
        $mform->addElement("select", "numbering", get_string("numbering", "mod_flexbook"), [
            FLEXBOOK_NUMBERING_NONE => get_string("numberingnone", "mod_flexbook"),
            FLEXBOOK_NUMBERING_NUMERIC => get_string("numberingnumeric", "mod_flexbook"),
        ]);
        $mform->setDefault("numbering", FLEXBOOK_NUMBERING_NUMERIC);

        $mform->addElement("select", "defaultcontenttype", get_string("defaultcontenttype", "mod_flexbook"),
            content_type_manager::get_type_options());
        $mform->setDefault("defaultcontenttype", "html");

        $mform->addElement("duration", "estimatedtime", get_string("manualestimatedtime", "mod_flexbook"),
            ["optional" => true]);

        $mform->addElement("header", "features", get_string("features", "mod_flexbook"));
        foreach (["enablebookmarks", "enablenotes", "enablesearch", "enabletracking"] as $field) {
            $mform->addElement("advcheckbox", $field, get_string($field, "mod_flexbook"));
            $mform->setDefault($field, 1);
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds the custom completion fields to the activity form.
     *
     * @return array
     */
    #[Override]
    public function add_completion_rules(): array {
        $mform = $this->_form;

        $mform->addElement("select", "completionmode", get_string("completionmode", "mod_flexbook"), [
            FLEXBOOK_COMPLETION_PERCENTAGE => get_string("completionpercentage", "mod_flexbook"),
            FLEXBOOK_COMPLETION_REQUIRED => get_string("completionrequired", "mod_flexbook"),
            FLEXBOOK_COMPLETION_COMBINED => get_string("completioncombined", "mod_flexbook"),
            FLEXBOOK_COMPLETION_CHAPTERS => get_string("completionchapters", "mod_flexbook"),
        ]);
        $mform->setDefault("completionmode", FLEXBOOK_COMPLETION_PERCENTAGE);

        $mform->addElement("text", "completionpercentage", get_string("completionpercentagelabel", "mod_flexbook"),
            ["size" => 4]);
        $mform->setType("completionpercentage", PARAM_INT);
        $mform->setDefault("completionpercentage", 80);
        $mform->addRule("completionpercentage", get_string("completionpercentageerror", "mod_flexbook"),
            "numeric", null, "client");
        $mform->disabledIf("completionpercentage", "completionmode", "eq", FLEXBOOK_COMPLETION_REQUIRED);
        $mform->disabledIf("completionpercentage", "completionmode", "eq", FLEXBOOK_COMPLETION_CHAPTERS);

        $mform->addElement("advcheckbox", "requiremandatory", get_string("requiremandatory", "mod_flexbook"));
        $mform->disabledIf("requiremandatory", "completionmode", "eq", FLEXBOOK_COMPLETION_PERCENTAGE);
        $mform->setDefault("requiremandatory", 1);

        return ["completionmode", "completionpercentage", "requiremandatory"];
    }

    /**
     * Checks whether a custom completion rule is enabled.
     *
     * @param mixed $data Record data.
     * @return bool
     */
    #[Override]
    public function completion_rule_enabled($data): bool {
        return isset($data["completionmode"]);
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
        if (isset($data["completionpercentage"])
                && ($data["completionpercentage"] < 1 || $data["completionpercentage"] > 100)) {
            $errors["completionpercentage"] = get_string("completionpercentageerror", "mod_flexbook");
        }
        return $errors;
    }

    /**
     * Prepares existing activity data for the form.
     *
     * @param mixed $defaultvalues Default form values.
     * @return void
     */
    #[Override]
    public function data_preprocessing(&$defaultvalues): void {
        parent::data_preprocessing($defaultvalues);
        if (empty($this->current->instance)) {
            return;
        }
        $draftitemid = file_get_submitted_draft_itemid("cover");
        file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            "mod_flexbook",
            "cover",
            0,
            ["accepted_types" => ["image"], "maxfiles" => 1, "subdirs" => 0]
        );
        $defaultvalues["cover"] = $draftitemid;
    }
}
