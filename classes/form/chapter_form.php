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
 * chapter_form.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow\form;

use moodleform;
use Override;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/formslib.php");

/**
 * Defines the chapter form form.
 */
class chapter_form extends moodleform {
    /**
     * Defines the form fields.
     *
     * @return void
     */
    #[Override]
    protected function definition(): void {
        $mform = $this->_form;
        $chapters = $this->_customdata["chapters"] ?? [];
        $id = $this->_customdata["id"] ?? 0;

        $mform->addElement("hidden", "id");
        $mform->setType("id", PARAM_INT);
        $mform->setDefault("id", $id);

        $mform->addElement("hidden", "chapterid");
        $mform->setType("chapterid", PARAM_INT);

        $mform->addElement("text", "title", get_string("chaptertitle", "mod_bookflow"), ["size" => 64]);
        $mform->setType("title", PARAM_TEXT);
        $mform->addRule("title", null, "required", null, "client");

        $mform->addElement("select", "parentid", get_string("parentchapter", "mod_bookflow"), [0 =>
            get_string("none")] + $chapters);
        $mform->setDefault("parentid", 0);

        $mform->addElement("editor", "description_editor", get_string("description"), null, [
            "maxfiles" => 0,
            "noclean" => false,
        ]);
        $mform->setType("description_editor", PARAM_RAW);

        $mform->addElement("advcheckbox", "required", get_string("chapterrequired", "mod_bookflow"));
        $mform->addElement("advcheckbox", "hidden", get_string("hidden", "mod_bookflow"));
        $mform->addElement("duration", "estimatedtime", get_string("estimatedtime", "mod_bookflow"),
            ["optional" => true]);

        $this->add_action_buttons();
    }
}
