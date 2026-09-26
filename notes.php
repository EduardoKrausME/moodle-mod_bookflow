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
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$delete = optional_param("delete", 0, PARAM_INT);
$deletehighlight = optional_param("deletehighlight", 0, PARAM_INT);

$cm = get_coursemodule_from_id("flexbook", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$flexbook = $DB->get_record("flexbook", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/flexbook:view", $context);

if (!$flexbook->enablenotes) {
    throw new moodle_exception("notesdisabled", "mod_flexbook");
}
if ($delete) {
    require_sesskey();
    $conditions = ["id" => $delete, "flexbookid" => $flexbook->id];
    if (!has_capability("mod/flexbook:viewallnotes", $context)) {
        $conditions["userid"] = $USER->id;
    }
    $note = $DB->get_record("flexbook_notes", $conditions, "*", MUST_EXIST);
    $DB->delete_records("flexbook_notes", ["id" => $note->id]);
    redirect(new moodle_url("/mod/flexbook/notes.php", ["id" => $cm->id]));
}
if ($deletehighlight) {
    require_sesskey();
    $highlight = $DB->get_record("flexbook_highlights", [
        "id" => $deletehighlight,
        "flexbookid" => $flexbook->id,
        "userid" => $USER->id,
    ], "*", MUST_EXIST);
    $DB->delete_records("flexbook_highlights", ["id" => $highlight->id]);
    redirect(new moodle_url("/mod/flexbook/notes.php", ["id" => $cm->id]));
}

$PAGE->set_url("/mod/flexbook/notes.php", ["id" => $cm->id]);
$PAGE->set_title(get_string("mynotes", "mod_flexbook"));
$PAGE->set_heading(format_string($course->fullname));

$sql = "SELECT n.*, ch.title AS chaptertitle, c.title AS contenttitle
          FROM {flexbook_notes} n
     LEFT JOIN {flexbook_chapters} ch ON ch.id = n.chapterid
     LEFT JOIN {flexbook_contents} c ON c.id = n.contentid
         WHERE n.flexbookid = :flexbookid
           AND n.userid = :userid
      ORDER BY n.timemodified DESC";
$records = $DB->get_records_sql($sql, ["flexbookid" => $flexbook->id, "userid" => $USER->id]);
$highlights = $DB->get_records_sql(
    "SELECT h.*, ch.title AS chaptertitle, c.title AS contenttitle
       FROM {flexbook_highlights} h
       JOIN {flexbook_chapters} ch ON ch.id = h.chapterid
       JOIN {flexbook_contents} c ON c.id = h.contentid
      WHERE h.flexbookid = :flexbookid AND h.userid = :userid
   ORDER BY h.timecreated DESC",
    ["flexbookid" => $flexbook->id, "userid" => $USER->id]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("mynotes", "mod_flexbook"));
if (!$records) {
    echo $OUTPUT->notification(get_string("nonotes", "mod_flexbook"), "info");
}
foreach ($records as $record) {
    $url = new moodle_url("/mod/flexbook/view.php", [
        "id" => $cm->id,
        "chapterid" => $record->chapterid,
    ], $record->contentid ? "flexbook-content-{$record->contentid}" : null);
    echo html_writer::start_div("card mb-3");
    echo html_writer::start_div("card-body");
    echo html_writer::tag("h3", html_writer::link($url,
        format_string($record->contenttitle ?: $record->chaptertitle)), ["class" => "h5"]);
    if ($record->selectiontext) {
        echo html_writer::tag("blockquote", s($record->selectiontext), ["class" => "blockquote small"]);
    }
    echo html_writer::tag("p", nl2br(s($record->note)));
    echo html_writer::tag("small", userdate($record->timemodified), ["class" => "text-muted"]);
    echo html_writer::link(new moodle_url("/mod/flexbook/notes.php", [
        "id" => $cm->id,
        "delete" => $record->id,
        "sesskey" => sesskey(),
    ]), get_string("delete"), ["class" => "btn btn-sm btn-link"]);
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo $OUTPUT->heading(get_string("myhighlights", "mod_flexbook"), 3);
if (!$highlights) {
    echo $OUTPUT->notification(get_string("nohighlights", "mod_flexbook"), "info");
}
foreach ($highlights as $highlight) {
    $url = new moodle_url("/mod/flexbook/view.php", [
        "id" => $cm->id,
        "chapterid" => $highlight->chapterid,
    ], "flexbook-content-{$highlight->contentid}");
    echo html_writer::start_div("card mb-2");
    echo html_writer::start_div("card-body");
    echo html_writer::tag("h4", html_writer::link(
        $url,
        format_string($highlight->contenttitle ?: $highlight->chaptertitle)
    ), ["class" => "h6"]);
    echo html_writer::tag("mark", s($highlight->selectiontext), [
        "class" => "flexbook-highlight-{$highlight->color}",
    ]);
    echo html_writer::link(new moodle_url("/mod/flexbook/notes.php", [
        "id" => $cm->id,
        "deletehighlight" => $highlight->id,
        "sesskey" => sesskey(),
    ]), get_string("delete"), ["class" => "btn btn-sm btn-link"]);
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo $OUTPUT->footer();
