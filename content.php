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
 * Content block editor.
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookflow\content_manager;
use mod_bookflow\content_type_manager;
use mod_bookflow\form\content_form;
use mod_bookflow\form\content_form_mapper;

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$chapterid = optional_param("chapterid", 0, PARAM_INT);
$contentid = optional_param("contentid", 0, PARAM_INT);
$type = optional_param("type", "", PARAM_ALPHANUMEXT);

$cm = get_coursemodule_from_id("bookflow", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$bookflow = $DB->get_record("bookflow", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability("mod/bookflow:managecontent", $context);

$chapters = $DB->get_records_menu(
    "bookflow_chapters",
    ["bookflowid" => $bookflow->id],
    "sortorder",
    "id, title"
);
if (!$chapters) {
    redirect(
        new moodle_url("/mod/bookflow/chapters.php", ["id" => $cm->id]),
        get_string("createchapterfirst", "mod_bookflow")
    );
}

if (!$chapterid) {
    $chapterid = array_key_first($chapters);
}
$DB->get_record("bookflow_chapters", [
    "id" => $chapterid,
    "bookflowid" => $bookflow->id,
], "id", MUST_EXIST);

$classes = content_type_manager::get_classes();
if (!$classes) {
    throw new moodle_exception("nocontenttypes", "mod_bookflow");
}
$typeoptions = content_type_manager::get_type_options();

$content = null;
if ($contentid) {
    $sql = "SELECT c.*
              FROM {bookflow_contents} c
              JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
             WHERE c.id = :contentid
               AND ch.bookflowid = :bookflowid";
    $content = $DB->get_record_sql($sql, [
        "contentid" => $contentid,
        "bookflowid" => $bookflow->id,
    ], MUST_EXIST);
    $chapterid = (int) $content->chapterid;
    $type = $content->type;
}

$pageparams = [
    "id" => $cm->id,
    "chapterid" => $chapterid,
];
if ($contentid) {
    $pageparams["contentid"] = $contentid;
} else if ($type !== "") {
    $pageparams["type"] = $type;
}

$PAGE->set_url("/mod/bookflow/content.php", $pageparams);
$PAGE->set_title(get_string($contentid ? "editcontent" : "addcontent", "mod_bookflow"));
$PAGE->set_heading(format_string($course->fullname));

if (!$contentid && $type === "") {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string("selectcontenttype", "mod_bookflow"));
    echo html_writer::tag(
        "p",
        get_string("selectcontenttypedescription", "mod_bookflow"),
        ["class" => "text-muted mb-4"]
    );
    echo html_writer::start_div("row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3");
    foreach ($classes as $availabletype => $classname) {
        if (!$classname::can_create(null, $bookflow, $context)) {
            continue;
        }

        $url = new moodle_url("/mod/bookflow/content.php", [
            "id" => $cm->id,
            "chapterid" => $chapterid,
            "type" => $availabletype,
        ]);
        $label = $typeoptions[$availabletype] ?? $classname::get_name();
        $link = html_writer::link(
            $url,
            html_writer::tag("strong", $label),
            ["class" => "stretched-link text-decoration-none"]
        );
        echo html_writer::div(
            html_writer::div(
                html_writer::div($link, "card-body"),
                "card h-100 position-relative"
            ),
            "col"
        );
    }
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

if (!isset($classes[$type])) {
    throw new moodle_exception("unknowncontenttype", "mod_bookflow", "", $type);
}
if (!$classes[$type]::can_create(null, $bookflow, $context)) {
    throw new required_capability_exception(
        $context,
        "mod/bookflow:managecontent",
        "nopermissions",
        ""
    );
}

$editoroptions = [
    "context" => $context,
    "maxfiles" => -1,
    "maxbytes" => get_max_upload_file_size($CFG->maxbytes, $course->maxbytes),
    "subdirs" => true,
    "trusttext" => false,
];
$fileoptions = content_form_mapper::get_file_options($type, $editoroptions);
$repeatcount = content_form_mapper::get_repeat_count($content, $type);
$structureddraftid = content_form_mapper::prepare_structured_draft(
    $type,
    $content,
    $context,
    $editoroptions
);

$formurl = new moodle_url("/mod/bookflow/content.php", $pageparams);
$form = new content_form($formurl->out(false), [
    "chapters" => $chapters,
    "type" => $type,
    "editoroptions" => $editoroptions,
    "fileoptions" => $fileoptions,
    "repeatcount" => $repeatcount,
    "structureddraftid" => $structureddraftid,
]);

if ($content) {
    $content->contentid = $content->id;
    $content = content_form_mapper::prepare_form_data(
        $content,
        $type,
        $context,
        $editoroptions,
        $fileoptions,
        $structureddraftid
    );
    $form->set_data($content);
} else {
    $initialdata = (object) [
        "contentid" => 0,
        "chapterid" => $chapterid,
        "type" => $type,
    ];
    $initialdata = content_form_mapper::prepare_form_data(
        $initialdata,
        $type,
        $context,
        $editoroptions,
        $fileoptions,
        $structureddraftid
    );
    $form->set_data($initialdata);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url("/mod/bookflow/view.php", [
        "id" => $cm->id,
        "chapterid" => $chapterid,
    ]));
} else if ($submitted = $form->get_data()) {
    $recordid = $contentid;
    $formdata = clone $submitted;
    $data = content_form_mapper::to_record(clone $submitted, $type);

    unset($data->contentid);
    $data->type = $type;
    $data->auxint2 = 0;
    $data->auxint3 = 0;

    $DB->get_record("bookflow_chapters", [
        "id" => $data->chapterid,
        "bookflowid" => $bookflow->id,
    ], "*", MUST_EXIST);

    if ($data->completiontype === "none") {
        $data->trackprogress = 0;
    }

    if ($recordid) {
        $draftdata1 = content_form_mapper::save_draft_data1(
            $formdata,
            $type,
            $recordid,
            $context,
            $editoroptions,
            $fileoptions
        );
        if ($draftdata1 !== null) {
            $data->data1 = $draftdata1;
        }

        $data->id = $recordid;
        content_manager::update($data);
    } else {
        $data->id = content_manager::create($data);

        $draftdata1 = content_form_mapper::save_draft_data1(
            $formdata,
            $type,
            $data->id,
            $context,
            $editoroptions,
            $fileoptions
        );
        if ($draftdata1 !== null && $draftdata1 !== $data->data1) {
            $data->data1 = $draftdata1;
            content_manager::update($data);
        }
    }

    redirect(
        new moodle_url(
            "/mod/bookflow/view.php",
            [
                "id" => $cm->id,
                "chapterid" => $data->chapterid,
            ],
            "bookflow-content-{$data->id}"
        ),
        get_string("contentsaved", "mod_bookflow")
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($contentid ? "editcontent" : "addcontent", "mod_bookflow"));
$form->display();
echo $OUTPUT->footer();
