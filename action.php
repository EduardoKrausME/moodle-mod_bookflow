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
 * action.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_flexbook\content_manager;

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$contentid = required_param("contentid", PARAM_INT);
$action = required_param("action", PARAM_ALPHA);
$confirm = optional_param("confirm", 0, PARAM_BOOL);

$cm = get_coursemodule_from_id("flexbook", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$flexbook = $DB->get_record("flexbook", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/flexbook:managecontent", $context);
require_sesskey();

$content = $DB->get_record_sql(
    "SELECT c.*
       FROM {flexbook_contents} c
       JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
      WHERE c.id = :contentid AND ch.flexbookid = :flexbookid",
    ["contentid" => $contentid, "flexbookid" => $flexbook->id],
    MUST_EXIST
);

if ($action == "delete" && !$confirm) {
    $PAGE->set_url("/mod/flexbook/action.php", [
        "id" => $cm->id,
        "contentid" => $contentid,
        "action" => "delete",
    ]);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string("delete"));
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string("confirmdeletecontent", "mod_flexbook", format_string($content->title)),
        new moodle_url("/mod/flexbook/action.php", [
            "id" => $cm->id,
            "contentid" => $contentid,
            "action" => "delete",
            "confirm" => 1,
            "sesskey" => sesskey(),
        ]),
        new moodle_url("/mod/flexbook/view.php", [
            "id" => $cm->id,
            "chapterid" => $content->chapterid,
        ])
    );
    echo $OUTPUT->footer();
    exit;
}

switch ($action) {
    case "up":
        content_manager::move($contentid, -1);
        break;
    case "down":
        content_manager::move($contentid, 1);
        break;
    case "duplicate":
        content_manager::duplicate($contentid);
        break;
    case "hide":
    case "show":
        $content->hidden = $action == "hide" ? 1 : 0;
        content_manager::update($content);
        break;
    case "delete":
        content_manager::delete($contentid);
        break;
    default:
        throw new invalid_parameter_exception("Unknown action");
}

redirect(new moodle_url("/mod/flexbook/view.php", [
    "id" => $cm->id,
    "chapterid" => $content->chapterid,
]));
