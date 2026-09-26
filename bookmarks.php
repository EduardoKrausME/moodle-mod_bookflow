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
 * bookmarks.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$delete = optional_param("delete", 0, PARAM_INT);

$cm = get_coursemodule_from_id("flexbook", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$flexbook = $DB->get_record("flexbook", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/flexbook:view", $context);

if (!$flexbook->enablebookmarks) {
    throw new moodle_exception("bookmarksdisabled", "mod_flexbook");
}
if ($delete) {
    require_sesskey();
    $bookmark = $DB->get_record("flexbook_bookmarks", [
        "id" => $delete,
        "userid" => $USER->id,
        "flexbookid" => $flexbook->id,
    ], "*", MUST_EXIST);
    $DB->delete_records("flexbook_bookmarks", ["id" => $bookmark->id]);
    redirect(new moodle_url("/mod/flexbook/bookmarks.php", ["id" => $cm->id]));
}

$PAGE->set_url("/mod/flexbook/bookmarks.php", ["id" => $cm->id]);
$PAGE->set_title(get_string("mybookmarks", "mod_flexbook"));
$PAGE->set_heading(format_string($course->fullname));

$sql = "SELECT b.*, ch.title AS chaptertitle, c.title AS contenttitle, c.type AS contenttype
          FROM {flexbook_bookmarks} b
     LEFT JOIN {flexbook_chapters} ch ON ch.id = b.chapterid
     LEFT JOIN {flexbook_contents} c ON c.id = b.contentid
         WHERE b.flexbookid = :flexbookid
           AND b.userid = :userid
      ORDER BY b.timecreated DESC";
$records = $DB->get_records_sql($sql, ["flexbookid" => $flexbook->id, "userid" => $USER->id]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("mybookmarks", "mod_flexbook"));
if (!$records) {
    echo $OUTPUT->notification(get_string("nobookmarks", "mod_flexbook"), "info");
} else {
    echo html_writer::start_tag("ul", ["class" => "list-group"]);
    foreach ($records as $record) {
        $title = $record->itemtype == "book"
            ? $flexbook->name
            : ($record->contenttitle ?: $record->chaptertitle);
        $url = new moodle_url("/mod/flexbook/view.php", [
            "id" => $cm->id,
            "chapterid" => $record->chapterid,
        ], $record->contentid ? "flexbook-content-{$record->contentid}" : null);
        $deleteurl = new moodle_url("/mod/flexbook/bookmarks.php", [
            "id" => $cm->id,
            "delete" => $record->id,
            "sesskey" => sesskey(),
        ]);
        echo html_writer::tag("li",
            html_writer::link($url, format_string($title))
            . html_writer::link($deleteurl, get_string("delete"), ["class" => "btn btn-sm btn-link"]),
            ["class" => "list-group-item d-flex justify-content-between align-items-center"]
        );
    }
    echo html_writer::end_tag("ul");
}
echo $OUTPUT->footer();
