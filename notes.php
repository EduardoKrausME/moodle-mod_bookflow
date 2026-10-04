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
 * notes.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$delete = optional_param("delete", 0, PARAM_INT);

$cm = get_coursemodule_from_id("bookflow", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$bookflow = $DB->get_record("bookflow", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/bookflow:view", $context);

if (!$bookflow->enablenotes) {
    throw new moodle_exception("notesdisabled", "mod_bookflow");
}
if ($delete) {
    require_sesskey();
    $conditions = ["id" => $delete, "bookflowid" => $bookflow->id];
    if (!has_capability("mod/bookflow:viewallnotes", $context)) {
        $conditions["userid"] = $USER->id;
    }
    $note = $DB->get_record("bookflow_notes", $conditions, "*", MUST_EXIST);
    $DB->delete_records("bookflow_notes", ["id" => $note->id]);
    redirect(new moodle_url("/mod/bookflow/notes.php", ["id" => $cm->id]));
}
$PAGE->set_url("/mod/bookflow/notes.php", ["id" => $cm->id]);
$PAGE->set_title(get_string("mynotes", "mod_bookflow"));
$PAGE->set_heading(format_string($course->fullname));

$sql = "SELECT n.*, ch.title AS chaptertitle, c.title AS contenttitle
          FROM {bookflow_notes} n
     LEFT JOIN {bookflow_chapters} ch ON ch.id = n.chapterid
     LEFT JOIN {bookflow_contents} c ON c.id = n.contentid
         WHERE n.bookflowid = :bookflowid
           AND n.userid = :userid
      ORDER BY n.timemodified DESC";
$records = $DB->get_records_sql($sql, ["bookflowid" => $bookflow->id, "userid" => $USER->id]);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("mynotes", "mod_bookflow"));
if (!$records) {
    echo $OUTPUT->notification(get_string("nonotes", "mod_bookflow"), "info");
}
foreach ($records as $record) {
    $url = new moodle_url("/mod/bookflow/view.php", [
        "id" => $cm->id,
        "chapterid" => $record->chapterid,
    ], $record->contentid ? "bookflow-content-{$record->contentid}" : null);
    echo html_writer::start_div("card mb-3");
    echo html_writer::start_div("card-body");
    echo html_writer::tag("h3", html_writer::link($url,
        format_string($record->contenttitle ?: $record->chaptertitle)), ["class" => "h5"]);
    if ($record->selectiontext) {
        echo html_writer::tag("blockquote", s($record->selectiontext), ["class" => "blockquote small"]);
    }
    echo html_writer::tag("p", nl2br(s($record->note)));
    echo html_writer::tag("small", userdate($record->timemodified), ["class" => "text-muted"]);
    echo html_writer::link(new moodle_url("/mod/bookflow/notes.php", [
        "id" => $cm->id,
        "delete" => $record->id,
        "sesskey" => sesskey(),
    ]), get_string("delete"), ["class" => "btn btn-sm btn-link"]);
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo $OUTPUT->footer();
