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
 * export.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_flexbook\export_manager;

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$format = optional_param("format", "", PARAM_ALPHA);
$cm = get_coursemodule_from_id("flexbook", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$flexbook = $DB->get_record("flexbook", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/flexbook:export", $context);

if ($format) {
    require_sesskey();
    $basename = clean_filename($flexbook->name);
    if ($format == "markdown") {
        $content = export_manager::to_markdown($flexbook->id, $context);
        send_temp_file(
            export_manager::create_text_file($content, "md"),
            "{$basename}.md"
        );
    } else if ($format == "html") {
        $content = export_manager::to_html($flexbook->id, $context, $OUTPUT);
        send_temp_file(
            export_manager::create_text_file($content, "html"),
            "{$basename}.html"
        );
    } else if ($format == "zip") {
        send_temp_file(
            export_manager::create_zip($flexbook->id, $context, $OUTPUT),
            "{$basename}.zip"
        );
    } else if ($format == "print") {
        redirect(new moodle_url("/mod/flexbook/print.php", ["id" => $cm->id]));
    } else if ($format == "printanswers") {
        require_capability("mod/flexbook:managecontent", $context);
        redirect(new moodle_url("/mod/flexbook/print.php", ["id" => $cm->id, "showanswers" => 1]));
    }
    throw new invalid_parameter_exception("Unsupported export format");
}

$PAGE->set_url("/mod/flexbook/export.php", ["id" => $cm->id]);
$PAGE->set_title(get_string("export", "mod_flexbook"));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("export", "mod_flexbook"));
echo html_writer::start_tag("div", ["class" => "list-group"]);
foreach (["html", "markdown", "zip", "print"] as $exportformat) {
    echo html_writer::link(new moodle_url("/mod/flexbook/export.php", [
        "id" => $cm->id,
        "format" => $exportformat,
        "sesskey" => sesskey(),
    ]), get_string("export{$exportformat}", "mod_flexbook"), ["class" => "list-group-item list-group-item-action"]);
}
if (has_capability("mod/flexbook:managecontent", $context)) {
    echo html_writer::link(new moodle_url("/mod/flexbook/export.php", [
        "id" => $cm->id,
        "format" => "printanswers",
        "sesskey" => sesskey(),
    ]), get_string("exportprintanswers", "mod_flexbook"), [
        "class" => "list-group-item list-group-item-action",
    ]);
}
echo html_writer::end_tag("div");
echo $OUTPUT->footer();
