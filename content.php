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
 * content.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_flexbook\form\content_form;
use mod_flexbook\content_manager;
use mod_flexbook\content_type_manager;

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$chapterid = optional_param("chapterid", 0, PARAM_INT);
$contentid = optional_param("contentid", 0, PARAM_INT);

$cm = get_coursemodule_from_id("flexbook", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$flexbook = $DB->get_record("flexbook", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/flexbook:managecontent", $context);

$PAGE->set_url("/mod/flexbook/content.php", compact("id", "chapterid", "contentid"));
$PAGE->set_title(get_string("editcontent", "mod_flexbook"));
$PAGE->set_heading(format_string($course->fullname));

$chapters = $DB->get_records_menu("flexbook_chapters", ["flexbookid" => $flexbook->id], "sortorder", "id, title");
if (!$chapters) {
    redirect(new moodle_url("/mod/flexbook/chapters.php", ["id" => $cm->id]),
        get_string("createchapterfirst", "mod_flexbook"));
}
$formurl = new moodle_url("/mod/flexbook/content.php", ["id" => $cm->id]);
$form = new content_form($formurl->out(false), ["chapters" => $chapters]);

if ($form->is_cancelled()) {
    redirect(new moodle_url("/mod/flexbook/view.php", [
        "id" => $cm->id,
        "chapterid" => $chapterid,
    ]));
} else if ($data = $form->get_data()) {
    $recordid = $data->contentid;
    unset($data->contentid);
    $DB->get_record("flexbook_chapters", [
        "id" => $data->chapterid,
        "flexbookid" => $flexbook->id,
    ], "*", MUST_EXIST);
    $classes = content_type_manager::get_classes();
    if (!isset($classes[$data->type])
            || !$classes[$data->type]::can_create(null, $flexbook, $context)) {
        throw new required_capability_exception($context, "mod/flexbook:managecontent", "nopermissions", "");
    }
    $data->auxint2 = 0;
    $data->auxint3 = 0;
    if ($data->completiontype == "none") {
        $data->trackprogress = 0;
    }
    if (in_array($data->type, ["accordion", "tabs", "flashcards"]) && !$data->auxint1) {
        $items = json_decode($data->data1 ?? "[]", true);
        if (is_array($items)) {
            $data->auxint1 = count($items);
        }
    } else if ($data->type == "disclosure" && !$data->auxint1) {
        $data->auxint1 = 1;
    }
    if ($recordid) {
        $data->id = $recordid;
        $DB->get_record_sql(
            "SELECT c.id
               FROM {flexbook_contents} c
               JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
              WHERE c.id = :id AND ch.flexbookid = :flexbookid",
            ["id" => $data->id, "flexbookid" => $flexbook->id],
            MUST_EXIST
        );
        content_manager::update($data);
    } else {
        $data->id = content_manager::create($data);
    }
    redirect(new moodle_url("/mod/flexbook/view.php", [
        "id" => $cm->id,
        "chapterid" => $data->chapterid,
    ], "flexbook-content-{$data->id}"), get_string("contentsaved", "mod_flexbook"));
}

if ($contentid) {
    $sql = "SELECT c.*
              FROM {flexbook_contents} c
              JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
             WHERE c.id = :contentid AND ch.flexbookid = :flexbookid";
    $content = $DB->get_record_sql($sql, [
        "contentid" => $contentid,
        "flexbookid" => $flexbook->id,
    ], MUST_EXIST);
    $content->contentid = $content->id;
    $form->set_data($content);
} else {
    $form->set_data((object) [
        "contentid" => 0,
        "chapterid" => $chapterid ?: array_key_first($chapters),
        "type" => $flexbook->defaultcontenttype,
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($contentid ? "editcontent" : "addcontent", "mod_flexbook"));
$form->display();
echo $OUTPUT->footer();
