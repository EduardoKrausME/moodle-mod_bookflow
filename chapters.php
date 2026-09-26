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
 * chapters.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_flexbook\form\chapter_form;
use mod_flexbook\chapter_manager;

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$chapterid = optional_param("chapterid", 0, PARAM_INT);
$delete = optional_param("delete", 0, PARAM_BOOL);
$confirm = optional_param("confirm", 0, PARAM_BOOL);

$cm = get_coursemodule_from_id("flexbook", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$flexbook = $DB->get_record("flexbook", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/flexbook:managechapters", $context);

$PAGE->set_url("/mod/flexbook/chapters.php", ["id" => $cm->id, "chapterid" => $chapterid]);
$PAGE->set_title(get_string("managechapters", "mod_flexbook"));
$PAGE->set_heading(format_string($course->fullname));

$chapters = $DB->get_records("flexbook_chapters", ["flexbookid" => $flexbook->id], "sortorder, id");
$options = [];
foreach ($chapters as $chapter) {
    if ($chapter->id != $chapterid) {
        $options[$chapter->id] = format_string($chapter->title);
    }
}
$formurl = new moodle_url("/mod/flexbook/chapters.php", ["id" => $cm->id]);
$form = new chapter_form($formurl->out(false), ["id" => $cm->id, "chapters" => $options]);

if ($delete && $chapterid) {
    require_sesskey();
    $chapter = $DB->get_record("flexbook_chapters", [
        "id" => $chapterid,
        "flexbookid" => $flexbook->id,
    ], "*", MUST_EXIST);
    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string("confirmdeletechapter", "mod_flexbook", format_string($chapter->title)),
            new moodle_url("/mod/flexbook/chapters.php", [
                "id" => $cm->id,
                "chapterid" => $chapterid,
                "delete" => 1,
                "confirm" => 1,
                "sesskey" => sesskey(),
            ]),
            new moodle_url("/mod/flexbook/chapters.php", ["id" => $cm->id])
        );
        echo $OUTPUT->footer();
        exit;
    }
    chapter_manager::delete($chapterid);
    redirect(new moodle_url("/mod/flexbook/chapters.php", ["id" => $cm->id]),
        get_string("chapterdeleted", "mod_flexbook"));
}

if ($form->is_cancelled()) {
    redirect(new moodle_url("/mod/flexbook/view.php", ["id" => $cm->id]));
} else if ($data = $form->get_data()) {
    $recordid = $data->chapterid;
    unset($data->chapterid);
    $data->flexbookid = $flexbook->id;
    $data->description = $data->description_editor["text"];
    $data->descriptionformat = $data->description_editor["format"];
    unset($data->description_editor);
    if ($recordid) {
        $data->id = $recordid;
        $existing = $DB->get_record("flexbook_chapters", [
            "id" => $data->id,
            "flexbookid" => $flexbook->id,
        ], "*", MUST_EXIST);
        chapter_manager::update($data);
    } else {
        $data->id = chapter_manager::create($data);
    }
    redirect(new moodle_url("/mod/flexbook/view.php", [
        "id" => $cm->id,
        "chapterid" => $data->id,
    ]), get_string("chaptersaved", "mod_flexbook"));
}

if ($chapterid) {
    $chapter = $DB->get_record("flexbook_chapters", [
        "id" => $chapterid,
        "flexbookid" => $flexbook->id,
    ], "*", MUST_EXIST);
    $chapter->chapterid = $chapter->id;
    $chapter->description_editor = [
        "text" => $chapter->description,
        "format" => $chapter->descriptionformat,
    ];
    $form->set_data($chapter);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($chapterid ? "editchapter" : "addchapter", "mod_flexbook"));
$form->display();

if ($chapters) {
    $rows = [];
    foreach ($chapters as $chapter) {
        $rows[] = [
            "title" => format_string($chapter->title),
            "required" => $chapter->required ? get_string("yes") : get_string("no"),
            "hidden" => $chapter->hidden ? get_string("yes") : get_string("no"),
            "viewurl" => (new moodle_url("/mod/flexbook/view.php", [
                "id" => $cm->id,
                "chapterid" => $chapter->id,
            ]))->out(false),
            "editurl" => (new moodle_url("/mod/flexbook/chapters.php", [
                "id" => $cm->id,
                "chapterid" => $chapter->id,
            ]))->out(false),
            "deleteurl" => (new moodle_url("/mod/flexbook/chapters.php", [
                "id" => $cm->id,
                "chapterid" => $chapter->id,
                "delete" => 1,
                "sesskey" => sesskey(),
            ]))->out(false),
        ];
    }
    echo $OUTPUT->render_from_template("mod_flexbook/manage_chapters", ["chapters" => $rows]);
}
echo $OUTPUT->footer();
