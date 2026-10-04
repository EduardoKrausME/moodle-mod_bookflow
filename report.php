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
 * report.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookflow\content_type_manager;
use mod_bookflow\progress\progress_manager;

require_once(__DIR__ . "/../../config.php");
require_once("{$CFG->libdir}/tablelib.php");

$id = required_param("id", PARAM_INT);
$view = optional_param("view", "users", PARAM_ALPHA);
$userid = optional_param("userid", 0, PARAM_INT);

$cm = get_coursemodule_from_id("bookflow", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$bookflow = $DB->get_record("bookflow", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/bookflow:viewreports", $context);

$PAGE->set_url("/mod/bookflow/report.php", compact("id", "view", "userid"));
$PAGE->set_title(get_string("reports", "mod_bookflow"));
$PAGE->set_heading(format_string($course->fullname));

$enrolled = get_enrolled_users($context, "mod/bookflow:view");
$states = $DB->get_records("bookflow_user_state", ["bookflowid" => $bookflow->id]);
$statebyuser = [];
foreach ($states as $state) {
    $statebyuser[$state->userid] = $state;
}
$notstarted = 0;
$inprogress = 0;
$completed = 0;
$progresssum = 0;
foreach ($enrolled as $user) {
    $state = $statebyuser[$user->id] ?? null;
    if (!$state) {
        $notstarted++;
    } else if ($state->timecompleted) {
        $completed++;
        $progresssum += $state->progress;
    } else {
        $inprogress++;
        $progresssum += $state->progress;
    }
}
$average = count($enrolled) ? round($progresssum / count($enrolled), 1) : 0;
$averagetime = $states
    ? $DB->get_field_sql(
        "SELECT COALESCE(AVG(timeviewed), 0) FROM {bookflow_user_state} WHERE bookflowid = ?",
        [$bookflow->id]
    )
    : 0;
$contentaccesssql = "SELECT c.id, c.title, COALESCE(SUM(p.viewcount), 0) AS views
                       FROM {bookflow_contents} c
                       JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
                  LEFT JOIN {bookflow_user_progress} p ON p.contentid = c.id
                      WHERE ch.bookflowid = :bookflowid
                   GROUP BY c.id, c.title";
$contentaccess = $DB->get_records_sql($contentaccesssql, ["bookflowid" => $bookflow->id]);
$mostaccessed = null;
$leastaccessed = null;
foreach ($contentaccess as $access) {
    if (!$mostaccessed || $access->views > $mostaccessed->views) {
        $mostaccessed = $access;
    }
    if (!$leastaccessed || $access->views < $leastaccessed->views) {
        $leastaccessed = $access;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("reports", "mod_bookflow"));
echo $OUTPUT->render_from_template("mod_bookflow/report_summary", ["cards" => [
    ["value" => count($enrolled), "label" => get_string("enrolledstudents", "mod_bookflow")],
    ["value" => $notstarted, "label" => get_string("notstarted", "mod_bookflow")],
    ["value" => $inprogress, "label" => get_string("inprogress", "mod_bookflow")],
    ["value" => $completed, "label" => get_string("completed", "mod_bookflow")],
    ["value" => "{$average}%", "label" => get_string("averageprogress", "mod_bookflow")],
    ["value" => format_time($averagetime), "label" => get_string("averagetime", "mod_bookflow")],
    [
        "value" => $mostaccessed ? format_string($mostaccessed->title ?: get_string("untitledcontent", "mod_bookflow")) : "-",
        "label" => get_string("mostaccessedblock", "mod_bookflow"),
    ],
    [
        "value" => $leastaccessed ? format_string($leastaccessed->title ?: get_string("untitledcontent", "mod_bookflow")) : "-",
        "label" => get_string("leastaccessedblock", "mod_bookflow"),
    ],
]]);

$tabs = [
    new tabobject("users", new moodle_url($PAGE->url, ["view" => "users"]), get_string("byuser", "mod_bookflow")),
    new tabobject("chapters", new moodle_url($PAGE->url, ["view" => "chapters"]), get_string("bychapter", "mod_bookflow")),
    new tabobject("contents", new moodle_url($PAGE->url, ["view" => "contents"]), get_string("bycontent", "mod_bookflow")),
];
echo $OUTPUT->tabtree($tabs, $view);

if ($view == "chapters") {
    $table = new flexible_table("bookflow-chapter-report-{$bookflow->id}");
    $table->define_columns(["chapter", "views", "uniqueusers", "completionrate", "averagetime", "abandonment"]);
    $table->define_headers([
        get_string("chapter", "mod_bookflow"),
        get_string("views", "mod_bookflow"),
        get_string("uniqueusers", "mod_bookflow"),
        get_string("completionrate", "mod_bookflow"),
        get_string("averagetime", "mod_bookflow"),
        get_string("abandonment", "mod_bookflow"),
    ]);
    $table->define_baseurl($PAGE->url);
    $table->setup();
    $table->start_output();

    $sql = "SELECT ch.id, ch.title,
                   COALESCE(SUM(p.viewcount), 0) AS views,
                   COUNT(DISTINCT p.userid) AS uniqueusers,
                   COALESCE(AVG(p.timeviewed), 0) AS averagetime,
                   COALESCE(SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END), 0) AS completions
              FROM {bookflow_chapters} ch
         LEFT JOIN {bookflow_chapter_progress} p ON p.chapterid = ch.id
             WHERE ch.bookflowid = :bookflowid
          GROUP BY ch.id, ch.title, ch.sortorder
          ORDER BY ch.sortorder, ch.id";
    $chapterstats = $DB->get_records_sql($sql, [
        "completed" => progress_manager::STATUS_COMPLETED,
        "bookflowid" => $bookflow->id,
    ]);
    foreach ($chapterstats as $stats) {
        $rate = $stats->uniqueusers
            ? round($stats->completions / $stats->uniqueusers * 100, 1)
            : 0;
        $abandonment = $stats->uniqueusers ? round(100 - $rate, 1) : 0;
        $table->add_data([
            format_string($stats->title),
            $stats->views,
            $stats->uniqueusers,
            format_float($rate, 1) . "%",
            format_time($stats->averagetime ?? 0),
            format_float(max(0, $abandonment), 1) . "%",
        ]);
    }
    $table->finish_output();
} else if ($view == "contents") {
    $table = new flexible_table("bookflow-content-report-{$bookflow->id}");
    $table->define_columns(["content", "type", "views", "uniqueusers", "completions"]);
    $table->define_headers([
        get_string("content", "mod_bookflow"),
        get_string("contenttype", "mod_bookflow"),
        get_string("views", "mod_bookflow"),
        get_string("uniqueusers", "mod_bookflow"),
        get_string("completions", "mod_bookflow"),
    ]);
    $table->define_baseurl($PAGE->url);
    $table->setup();
    $table->start_output();
    $sql = "SELECT c.id, c.title, c.type,
                   COALESCE(SUM(p.viewcount), 0) AS views,
                   COUNT(DISTINCT p.userid) AS uniqueusers,
                   SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END) AS completions
              FROM {bookflow_contents} c
              JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
         LEFT JOIN {bookflow_user_progress} p ON p.contentid = c.id
             WHERE ch.bookflowid = :bookflowid
          GROUP BY c.id, c.title, c.type, ch.sortorder, c.sortorder
          ORDER BY ch.sortorder, c.sortorder";
    $typeoptions = content_type_manager::get_type_options();
    foreach ($DB->get_records_sql($sql, [
        "bookflowid" => $bookflow->id,
        "completed" => progress_manager::STATUS_COMPLETED,
    ]) as $content) {
        $table->add_data([
            format_string($content->title ?: get_string("untitledcontent", "mod_bookflow")),
            $typeoptions[$content->type] ?? s($content->type),
            $content->views,
            $content->uniqueusers,
            $content->completions,
        ]);
    }
    $table->finish_output();
} else {
    $completedbyuser = $DB->get_records_sql(
        "SELECT p.userid,
                COALESCE(SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END), 0) AS completedblocks
           FROM {bookflow_user_progress} p
          WHERE p.bookflowid = :bookflowid
       GROUP BY p.userid",
        [
            "completed" => progress_manager::STATUS_COMPLETED,
            "bookflowid" => $bookflow->id,
        ]
    );

    $accessedbyuser = $DB->get_records_sql(
        "SELECT userid, COUNT(id) AS accessedchapters
           FROM {bookflow_chapter_progress}
          WHERE bookflowid = :bookflowid
       GROUP BY userid",
        ["bookflowid" => $bookflow->id]
    );

    $requiredtotal = $DB->count_records_sql(
        "SELECT COUNT(c.id)
           FROM {bookflow_contents} c
           JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
          WHERE ch.bookflowid = :bookflowid
            AND ch.hidden = 0
            AND c.hidden = 0
            AND c.trackprogress = 1
            AND c.completiontype <> 'none'
            AND c.required = 1",
        ["bookflowid" => $bookflow->id]
    );
    $requiredbyuser = $DB->get_records_sql(
        "SELECT p.userid, COUNT(p.id) AS completedrequired
           FROM {bookflow_user_progress} p
           JOIN {bookflow_contents} c ON c.id = p.contentid
           JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
          WHERE ch.bookflowid = :bookflowid
            AND ch.hidden = 0
            AND c.hidden = 0
            AND c.trackprogress = 1
            AND c.completiontype <> 'none'
            AND c.required = 1
            AND p.status = :completed
       GROUP BY p.userid",
        [
            "bookflowid" => $bookflow->id,
            "completed" => progress_manager::STATUS_COMPLETED,
        ]
    );

    $extracolumns = [];
    $extradata = [];
    foreach (content_type_manager::get_classes() as $classname) {
        foreach ($classname::get_user_report_columns() as $key => $label) {
            $extracolumns[$key] = $label;
        }
        foreach ($classname::get_user_report_data($bookflow->id) as $reportuserid => $values) {
            $extradata[$reportuserid] = array_merge(
                $extradata[$reportuserid] ?? [],
                $values
            );
        }
    }

    $chaptertitles = $DB->get_records_menu(
        "bookflow_chapters",
        ["bookflowid" => $bookflow->id],
        "",
        "id, title"
    );

    $table = new flexible_table("bookflow-user-report-{$bookflow->id}");
    $table->define_columns([
        "fullname", "progress", "chapters", "completedblocks", "pendingrequired",
        "firstaccess", "lastaccess", "position",
        ...array_keys($extracolumns),
    ]);
    $table->define_headers([
        get_string("fullname"),
        get_string("progress", "mod_bookflow"),
        get_string("accessedchapters", "mod_bookflow"),
        get_string("completedblocks", "mod_bookflow"),
        get_string("pendingrequired", "mod_bookflow"),
        get_string("firstaccess", "mod_bookflow"),
        get_string("lastaccess", "mod_bookflow"),
        get_string("currentposition", "mod_bookflow"),
        ...array_values($extracolumns),
    ]);
    $table->define_baseurl($PAGE->url);
    $table->setup();
    $table->start_output();
    foreach ($enrolled as $user) {
        $state = $statebyuser[$user->id] ?? null;
        $completedblocks = (int) ($completedbyuser[$user->id]->completedblocks ?? 0);
        $accessedchapters = (int) ($accessedbyuser[$user->id]->accessedchapters ?? 0);
        $completedrequired = (int) ($requiredbyuser[$user->id]->completedrequired ?? 0);
        $pending = max(0, $requiredtotal - $completedrequired);
        $position = $state && $state->lastchapterid
            ? ($chaptertitles[$state->lastchapterid] ?? get_string("notstarted", "mod_bookflow"))
            : get_string("notstarted", "mod_bookflow");
        $row = [
            fullname($user),
            format_float($state->progress ?? 0, 1) . "%",
            $accessedchapters,
            $completedblocks,
            $pending,
            $state ? userdate($state->firstaccess) : "-",
            $state ? userdate($state->lastaccess) : "-",
            format_string($position),
        ];
        foreach (array_keys($extracolumns) as $key) {
            $row[] = $extradata[$user->id][$key] ?? 0;
        }
        $table->add_data($row);
    }
    $table->finish_output();
}

echo $OUTPUT->footer();
