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
 * search.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$query = optional_param("q", "", PARAM_TEXT);

$cm = get_coursemodule_from_id("flexbook", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$flexbook = $DB->get_record("flexbook", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/flexbook:view", $context);

$PAGE->set_url("/mod/flexbook/search.php", ["id" => $cm->id, "q" => $query]);
$PAGE->set_title(get_string("searchinside", "mod_flexbook"));
$PAGE->set_heading(format_string($course->fullname));

$results = [];
if ($flexbook->enablesearch && core_text::strlen(trim($query)) >= 2) {
    $like = "%" . $DB->sql_like_escape($query) . "%";
    $hiddenclause = has_capability("mod/flexbook:managecontent", $context)
        ? ""
        : " AND ch.hidden = 0 AND c.hidden = 0";
    $sql = "SELECT c.id, c.chapterid, c.type, c.data1, ch.title AS chaptertitle
              FROM {flexbook_contents} c
              JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
             WHERE ch.flexbookid = :flexbookid
                   {$hiddenclause}
               AND (" . $DB->sql_like("c.title", ":q1", false) . "
                OR " . $DB->sql_like("c.data1", ":q2", false) . "
                OR " . $DB->sql_like("c.data2", ":q3", false) . "
                OR " . $DB->sql_like("c.data3", ":q4", false) . ")
          ORDER BY ch.sortorder, c.sortorder";
    foreach ($DB->get_records_sql($sql, [
        "flexbookid" => $flexbook->id,
        "q1" => $like,
        "q2" => $like,
        "q3" => $like,
        "q4" => $like,
    ], 0, 100) as $record) {
        $results[] = [
            "chapter" => format_string($record->chaptertitle),
            "excerpt" => shorten_text(html_to_text($record->data1 ?? "", 0, false), 180),
            "type" => $record->type,
            "url" => (new moodle_url("/mod/flexbook/view.php", [
                "id" => $cm->id,
                "chapterid" => $record->chapterid,
            ], "flexbook-content-{$record->id}"))->out(false),
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("searchinside", "mod_flexbook"));
echo html_writer::start_tag("form", ["method" => "get"]);
echo html_writer::empty_tag("input", ["type" => "hidden", "name" => "id", "value" => $cm->id]);
echo html_writer::start_div("input-group mb-4");
echo html_writer::empty_tag("input", [
    "class" => "form-control",
    "type" => "search",
    "name" => "q",
    "value" => $query,
    "minlength" => 2,
    "required" => "required",
]);
echo html_writer::tag("button", get_string("search"), ["class" => "btn btn-primary", "type" => "submit"]);
echo html_writer::end_div();
echo html_writer::end_tag("form");
if ($query) {
    echo $OUTPUT->render_from_template("mod_flexbook/search_results", ["results" => $results]);
}
echo $OUTPUT->footer();
