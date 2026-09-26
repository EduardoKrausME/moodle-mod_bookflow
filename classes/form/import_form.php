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
 * import_form.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\form;

use moodleform;
use Override;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/formslib.php");

/**
 * Defines the import form form.
 */
class import_form extends moodleform {
    /**
     * Defines the form fields.
     *
     * @return void
     */
    #[Override]
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement("hidden", "id");
        $mform->setType("id", PARAM_INT);
        $mform->addElement("select", "format", get_string("importformat", "mod_flexbook"), [
            "book" => get_string("importbook", "mod_flexbook"),
            "markdown" => get_string("importmarkdown", "mod_flexbook"),
            "html" => get_string("importhtml", "mod_flexbook"),
            "zip" => get_string("importmarkdownzip", "mod_flexbook"),
        ]);
        $mform->addElement("text", "bookid", get_string("bookid", "mod_flexbook"));
        $mform->setType("bookid", PARAM_INT);
        $mform->hideIf("bookid", "format", "neq", "book");
        $mform->addElement("filepicker", "importfile", get_string("file"), null, [
            "accepted_types" => [".md", ".markdown", ".html", ".htm", ".zip"],
            "maxbytes" => 10 * 1024 * 1024,
        ]);
        $mform->hideIf("importfile", "format", "eq", "book");
        $this->add_action_buttons(true, get_string("import", "mod_flexbook"));
    }
}
