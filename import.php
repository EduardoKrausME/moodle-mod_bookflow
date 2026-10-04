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
 * import.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookflow\form\import_form;
use mod_bookflow\import_manager;

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$cm = get_coursemodule_from_id("bookflow", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$bookflow = $DB->get_record("bookflow", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/bookflow:import", $context);

$PAGE->set_url("/mod/bookflow/import.php", ["id" => $cm->id]);
$PAGE->set_title(get_string("import", "mod_bookflow"));
$PAGE->set_heading(format_string($course->fullname));

$form = new import_form();
$form->set_data((object) ["id" => $cm->id]);
if ($form->is_cancelled()) {
    redirect(new moodle_url("/mod/bookflow/view.php", ["id" => $cm->id]));
} else if ($data = $form->get_data()) {
    if ($data->format == "book") {
        $count = import_manager::from_standard_book($data->bookid, $bookflow->id);
    } else {
        $draft = $form->get_new_filename("importfile");
        $content = $form->get_file_content("importfile");
        if ($content === false) {
            throw new moodle_exception("missingimportfile", "mod_bookflow");
        }
        if ($data->format == "markdown") {
            $count = import_manager::from_markdown($content, $bookflow->id, $draft);
        } else if ($data->format == "html") {
            $count = import_manager::from_html($content, $bookflow->id, $draft);
        } else {
            $temporary = make_request_directory() . "/" . clean_param($draft, PARAM_FILE);
            file_put_contents($temporary, $content);
            $count = import_manager::from_markdown_zip($temporary, $bookflow->id);
        }
    }
    redirect(new moodle_url("/mod/bookflow/view.php", ["id" => $cm->id]),
        get_string("importedchapters", "mod_bookflow", $count));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("import", "mod_bookflow"));
$form->display();
echo $OUTPUT->footer();
