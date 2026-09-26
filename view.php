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
 * view.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_flexbook\event\flexbook_viewed;
use mod_flexbook\content_type_manager;
use mod_flexbook\time_estimator;
use mod_flexbook\progress\progress_manager;

require_once(__DIR__ . "/../../config.php");

$id = required_param("id", PARAM_INT);
$chapterid = optional_param("chapterid", 0, PARAM_INT);
$contentid = optional_param("contentid", 0, PARAM_INT);

$cm = get_coursemodule_from_id("flexbook", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$flexbook = $DB->get_record("flexbook", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability("mod/flexbook:view", $context);

$PAGE->set_url("/mod/flexbook/view.php", ["id" => $cm->id, "chapterid" => $chapterid]);
$PAGE->set_title(format_string($flexbook->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class("mod-flexbook");
$PAGE->requires->css("/mod/flexbook/styles.css");
$PAGE->requires->strings_for_js([
    "activitycompletedmessage",
], "mod_flexbook");

$editing = has_capability("mod/flexbook:managecontent", $context) && $PAGE->user_is_editing();
$progressmanager = new progress_manager();
$progress = $progressmanager->calculate_user_progress($flexbook->id, $USER->id);
$lastposition = $progressmanager->get_last_position($flexbook->id, $USER->id);
$chapters = $DB->get_records("flexbook_chapters", ["flexbookid" => $flexbook->id], "sortorder, id");
$haschapters = !empty($chapters);
$canmanagechapters = has_capability("mod/flexbook:managechapters", $context);
if (!$editing) {
    $chapters = array_filter($chapters, fn($chapter) => !$chapter->hidden);
}

flexbook_viewed::create_from_ids($flexbook->id, 0, 0, $USER->id)->trigger();

$toc = [];
$chapterindex = 0;
foreach ($chapters as $chapter) {
    $chapterindex++;
    $toccontents = [];
    foreach ($DB->get_records("flexbook_contents", ["chapterid" => $chapter->id], "sortorder, id") as $toccontent) {
        if ($toccontent->hidden && !$editing) {
            continue;
        }
        $toccontents[] = [
            "title" => format_string($toccontent->title ?: get_string("untitledcontent", "mod_flexbook")),
            "type" => $toccontent->type,
            "url" => (new moodle_url("/mod/flexbook/view.php", [
                "id" => $cm->id,
                "chapterid" => $chapter->id,
            ], "flexbook-content-{$toccontent->id}"))->out(false),
        ];
    }
    $completedcount = $DB->count_records_sql(
        "SELECT COUNT(c.id)
           FROM {flexbook_contents} c
          WHERE c.chapterid = :chapterid
            AND c.trackprogress = 1
            AND c.hidden = 0
            AND EXISTS (
                SELECT 1 FROM {flexbook_user_progress} p
                 WHERE p.contentid = c.id
                   AND p.userid = :userid
                   AND p.status = :completed
            )",
        [
            "chapterid" => $chapter->id,
            "userid" => $USER->id,
            "completed" => progress_manager::STATUS_COMPLETED,
        ]
    );
    $totalcount = $DB->count_records("flexbook_contents", [
        "chapterid" => $chapter->id,
        "trackprogress" => 1,
        "hidden" => 0,
    ]);
    $toc[] = [
        "id" => $chapter->id,
        "number" => $flexbook->numbering ? $chapterindex : "",
        "title" => format_string($chapter->title),
        "url" => (new moodle_url("/mod/flexbook/view.php", [
            "id" => $cm->id,
            "chapterid" => $chapter->id,
        ]))->out(false),
        "current" => $chapter->id == $chapterid,
        "completed" => $totalcount > 0 && $completedcount == $totalcount,
        "hidden" => $chapter->hidden,
        "required" => $chapter->required,
        "subchapter" => $chapter->parentid > 0,
        "contents" => $toccontents,
        "hascontents" => !empty($toccontents),
    ];
}

$PAGE->navbar->add(format_string($flexbook->name), new moodle_url("/mod/flexbook/view.php", ["id" => $cm->id]));

echo $OUTPUT->header();

$emptycontent = "";
if (!$haschapters) {
    $emptymessage = $canmanagechapters
        ? get_string("emptyflexbookteacher", "mod_flexbook")
        : get_string("emptyflexbookstudent", "mod_flexbook");
    if ($canmanagechapters) {
        $createchapterurl = new moodle_url("/mod/flexbook/chapters.php", ["id" => $cm->id]);
        $emptymessage .= html_writer::div(
            html_writer::link(
                $createchapterurl,
                get_string("createfirstchapter", "mod_flexbook"),
                ["class" => "btn btn-primary"]
            ),
            "mt-3"
        );
    }
    $emptycontent = $OUTPUT->notification($emptymessage, \core\output\notification::NOTIFY_INFO);
}

if (!$chapterid) {
    $accessedchapters = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT p.chapterid)
           FROM {flexbook_chapter_progress} p
          WHERE p.flexbookid = :flexbookid
            AND p.userid = :userid",
        ["flexbookid" => $flexbook->id, "userid" => $USER->id]
    );
    $estimated = time_estimator::for_flexbook($flexbook->id);
    $coverfiles = get_file_storage()->get_area_files(
        $context->id,
        "mod_flexbook",
        "cover",
        0,
        "itemid, filepath, filename",
        false
    );
    $cover = $coverfiles ? reset($coverfiles) : null;
    $data = [
        "cmid" => $cm->id,
        "flexbookid" => $flexbook->id,
        "name" => format_string($flexbook->name),
        "hascover" => !empty($cover),
        "coverurl" => $cover
            ? moodle_url::make_pluginfile_url(
                $context->id,
                "mod_flexbook",
                "cover",
                0,
                $cover->get_filepath(),
                $cover->get_filename()
            )->out(false)
            : "",
        "intro" => format_module_intro("flexbook", $flexbook, $cm->id),
        "chaptercount" => count($chapters),
        "chaptercountlabel" => get_string("chaptercount", "mod_flexbook", count($chapters)),
        "estimatedtime" => format_time($estimated),
        "progress" => $progress,
        "progressrounded" => round($progress),
        "accessedchapters" => $accessedchapters,
        "chapteraccesslabel" => get_string("chapteraccesscount", "mod_flexbook", (object) [
            "accessed" => $accessedchapters,
            "total" => count($chapters),
        ]),
        "toc" => $toc,
        "emptycontent" => $emptycontent,
        "hascontinue" => $lastposition && $lastposition->lastchapterid,
        "continueurl" => $lastposition && $lastposition->lastchapterid
            ? (new moodle_url("/mod/flexbook/view.php", [
                "id" => $cm->id,
                "chapterid" => $lastposition->lastchapterid,
            ], $lastposition->lastcontentid ? "flexbook-content-{$lastposition->lastcontentid}" : null))->out(false)
            : "",
        "continuechapter" => $lastposition->chaptertitle ?? "",
        "manageurl" => (new moodle_url("/mod/flexbook/chapters.php", ["id" => $cm->id]))->out(false),
        "canmanage" => $canmanagechapters,
        "bookmarksurl" => (new moodle_url("/mod/flexbook/bookmarks.php", ["id" => $cm->id]))->out(false),
        "notesurl" => (new moodle_url("/mod/flexbook/notes.php", ["id" => $cm->id]))->out(false),
        "reporturl" => (new moodle_url("/mod/flexbook/report.php", ["id" => $cm->id]))->out(false),
        "canreport" => has_capability("mod/flexbook:viewreports", $context),
        "enablebookmarks" => $flexbook->enablebookmarks,
        "enablenotes" => $flexbook->enablenotes,
        "enablesearch" => $flexbook->enablesearch,
        "booktools" => $OUTPUT->render_from_template("mod_flexbook/content_tools", [
            "enablebookmarks" => $flexbook->enablebookmarks,
            "enablenotes" => false,
            "itemtype" => "book",
            "chapterid" => 0,
            "contentid" => 0,
            "bookmarkid" => $DB->get_field("flexbook_bookmarks", "id", [
                "flexbookid" => $flexbook->id,
                "userid" => $USER->id,
                "itemtype" => "book",
            ]),
            "bookmarked" => $DB->record_exists("flexbook_bookmarks", [
                "flexbookid" => $flexbook->id,
                "userid" => $USER->id,
                "itemtype" => "book",
            ]),
        ]),
    ];
    echo $OUTPUT->render_from_template("mod_flexbook/activity_overview", $data);
    if ($flexbook->enablesearch) {
        $PAGE->requires->js_call_amd("mod_flexbook/search", "init", [$flexbook->id]);
    }
    if ($flexbook->enablebookmarks) {
        $PAGE->requires->js_call_amd("mod_flexbook/bookmark", "init", [$flexbook->id]);
    }
} else {
    $chapter = $DB->get_record("flexbook_chapters", [
        "id" => $chapterid,
        "flexbookid" => $flexbook->id,
    ], "*", MUST_EXIST);
    if ($chapter->hidden && !$editing) {
        throw new moodle_exception("chapterhidden", "mod_flexbook");
    }
    $PAGE->navbar->add(format_string($chapter->title));
    $contents = $DB->get_records("flexbook_contents", ["chapterid" => $chapter->id], "sortorder, id");
    if (!$editing) {
        $contents = array_filter($contents, fn($content) => !$content->hidden);
    }
    $renderedcontents = [];
    foreach ($contents as $content) {
        $type = content_type_manager::create_content($content, $flexbook, $context);
        $highlights = [];
        foreach ($DB->get_records("flexbook_highlights", [
            "flexbookid" => $flexbook->id,
            "userid" => $USER->id,
            "contentid" => $content->id,
        ]) as $highlight) {
            $highlights[] = [
                "id" => $highlight->id,
                "quote" => $highlight->selectiontext,
                "color" => $highlight->color,
            ];
        }
        $toolshtml = $OUTPUT->render_from_template("mod_flexbook/content_tools", [
            "enablebookmarks" => $flexbook->enablebookmarks,
            "enablenotes" => $flexbook->enablenotes,
            "itemtype" => "content",
            "chapterid" => $chapter->id,
            "contentid" => $content->id,
            "bookmarkid" => $DB->get_field("flexbook_bookmarks", "id", [
                "flexbookid" => $flexbook->id,
                "userid" => $USER->id,
                "itemtype" => "content",
                "contentid" => $content->id,
            ]),
            "bookmarked" => $DB->record_exists("flexbook_bookmarks", [
                "flexbookid" => $flexbook->id,
                "userid" => $USER->id,
                "itemtype" => "content",
                "contentid" => $content->id,
            ]),
            "manual" => $content->completiontype == "manual",
            "highlights" => $highlights,
            "hashighlights" => !empty($highlights),
        ]);
        $renderedcontents[] = [
            "id" => $content->id,
            "type" => $content->type,
            "html" => $type->render($OUTPUT, $editing),
            "tracked" => $content->trackprogress,
            "required" => $content->required,
            "completiontype" => $content->completiontype,
            "completionvalue" => $content->completionvalue,
            "hidden" => $content->hidden,
            "toolshtml" => $toolshtml,
            "editurl" => (new moodle_url("/mod/flexbook/content.php", [
                "id" => $cm->id,
                "contentid" => $content->id,
            ]))->out(false),
            "duplicateurl" => (new moodle_url("/mod/flexbook/action.php", [
                "id" => $cm->id,
                "contentid" => $content->id,
                "action" => "duplicate",
                "sesskey" => sesskey(),
            ]))->out(false),
            "upurl" => (new moodle_url("/mod/flexbook/action.php", [
                "id" => $cm->id,
                "contentid" => $content->id,
                "action" => "up",
                "sesskey" => sesskey(),
            ]))->out(false),
            "downurl" => (new moodle_url("/mod/flexbook/action.php", [
                "id" => $cm->id,
                "contentid" => $content->id,
                "action" => "down",
                "sesskey" => sesskey(),
            ]))->out(false),
            "hideurl" => (new moodle_url("/mod/flexbook/action.php", [
                "id" => $cm->id,
                "contentid" => $content->id,
                "action" => $content->hidden ? "show" : "hide",
                "sesskey" => sesskey(),
            ]))->out(false),
            "deleteurl" => (new moodle_url("/mod/flexbook/action.php", [
                "id" => $cm->id,
                "contentid" => $content->id,
                "action" => "delete",
                "sesskey" => sesskey(),
            ]))->out(false),
        ];
    }

    $chapterids = array_keys($chapters);
    $position = array_search($chapter->id, $chapterids);
    $previousid = $position !== false && $position > 0 ? $chapterids[$position - 1] : 0;
    $nextid = $position !== false && isset($chapterids[$position + 1]) ? $chapterids[$position + 1] : 0;
    $progressmanager->save_last_position($flexbook->id, $USER->id, $chapter->id, $contentid ?: null);
    if (!$chapter->hidden) {
        $progressmanager->mark_chapter_viewed($flexbook->id, $USER->id, $chapter->id);
    }

    $addcontenttypes = [];
    if ($editing) {
        $typeoptions = content_type_manager::get_type_options();
        foreach (content_type_manager::get_classes() as $addtype => $classname) {
            if (!$classname::can_create(null, $flexbook, $context)) {
                continue;
            }
            $addcontenttypes[] = [
                "name" => $typeoptions[$addtype] ?? $classname::get_name(),
                "url" => (new moodle_url("/mod/flexbook/content.php", [
                    "id" => $cm->id,
                    "chapterid" => $chapter->id,
                    "type" => $addtype,
                ]))->out(false),
            ];
        }
    }

    echo $OUTPUT->render_from_template("mod_flexbook/chapter", [
        "cmid" => $cm->id,
        "flexbookid" => $flexbook->id,
        "chapterid" => $chapter->id,
        "title" => format_string($chapter->title),
        "description" => format_text($chapter->description, $chapter->descriptionformat, ["context" => $context]),
        "contents" => $renderedcontents,
        "hascontents" => !empty($renderedcontents),
        "editing" => $editing,
        "addcontenttypes" => $addcontenttypes,
        "hasaddcontenttypes" => !empty($addcontenttypes),
        "toc" => $toc,
        "progress" => $progress,
        "progressrounded" => round($progress),
        "chaptertools" => $OUTPUT->render_from_template("mod_flexbook/content_tools", [
            "enablebookmarks" => $flexbook->enablebookmarks,
            "enablenotes" => $flexbook->enablenotes,
            "itemtype" => "chapter",
            "chapterid" => $chapter->id,
            "contentid" => 0,
            "bookmarkid" => $DB->get_field("flexbook_bookmarks", "id", [
                "flexbookid" => $flexbook->id,
                "userid" => $USER->id,
                "itemtype" => "chapter",
                "chapterid" => $chapter->id,
            ]),
            "bookmarked" => $DB->record_exists("flexbook_bookmarks", [
                "flexbookid" => $flexbook->id,
                "userid" => $USER->id,
                "itemtype" => "chapter",
                "chapterid" => $chapter->id,
            ]),
        ]),
        "overviewurl" => (new moodle_url("/mod/flexbook/view.php", ["id" => $cm->id]))->out(false),
        "addcontenturl" => (new moodle_url("/mod/flexbook/content.php", [
            "id" => $cm->id,
            "chapterid" => $chapter->id,
        ]))->out(false),
        "hasprevious" => $previousid > 0,
        "previousurl" => $previousid
            ? (new moodle_url("/mod/flexbook/view.php", ["id" => $cm->id, "chapterid" => $previousid]))->out(false)
            : "",
        "hasnext" => $nextid > 0,
        "nexturl" => $nextid
            ? (new moodle_url("/mod/flexbook/view.php", ["id" => $cm->id, "chapterid" => $nextid]))->out(false)
            : "",
    ]);

    if ($flexbook->enabletracking && !$editing) {
        $PAGE->requires->js_call_amd("mod_flexbook/progress_tracker", "init", [
            $flexbook->id,
            $chapter->id,
        ]);
        $PAGE->requires->js_call_amd("mod_flexbook/video_progress", "init", [$flexbook->id]);
        $PAGE->requires->js_call_amd("mod_flexbook/audio_progress", "init", [$flexbook->id]);
        $PAGE->requires->js_call_amd("mod_flexbook/accordion", "init", [$flexbook->id]);
        $PAGE->requires->js_call_amd("mod_flexbook/tabs", "init", [$flexbook->id]);
        $PAGE->requires->js_call_amd("mod_flexbook/disclosure", "init", [$flexbook->id]);
        $PAGE->requires->js_call_amd("mod_flexbook/flashcards", "init", [$flexbook->id]);
    }
    if ($editing) {
        $PAGE->requires->js_call_amd("mod_flexbook/content_sorting", "init", [
            $flexbook->id,
            $chapter->id,
        ]);
    }
    $PAGE->requires->js_call_amd("mod_flexbook/chapter_navigation", "init", []);
    if ($flexbook->enablebookmarks) {
        $PAGE->requires->js_call_amd("mod_flexbook/bookmark", "init", [$flexbook->id]);
    }
    if ($flexbook->enablenotes) {
        $PAGE->requires->js_call_amd("mod_flexbook/notes", "init", [$flexbook->id]);
        $PAGE->requires->js_call_amd("mod_flexbook/highlights", "init", [$flexbook->id]);
    }
}

echo $OUTPUT->footer();
