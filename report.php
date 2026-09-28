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
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_flexbook\content_type_manager;
use mod_flexbook\progress\progress_manager;

require_once(__DIR__ . "/../../config.php");
require_once("{$CFG->libdir}/tablelib.php");

$id = required_param("id", PARAM_INT);
$view = optional_param("view", "users", PARAM_ALPHA);
$userid = optional_param("userid", 0, PARAM_INT);

$cm = get_coursemodule_from_id("flexbook", $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$flexbook = $DB->get_record("flexbook", ["id" => $cm->instance], "*", MUST_EXIST);
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability("mod/flexbook:viewreports", $context);

$PAGE->set_url("/mod/flexbook/report.php", compact("id", "view", "userid"));
$PAGE->set_title(get_string("reports", "mod_flexbook"));
$PAGE->set_heading(format_string($course->fullname));

$enrolled = get_enrolled_users($context, "mod/flexbook:view");
$states = $DB->get_records("flexbook_user_state", ["flexbookid" => $flexbook->id]);
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
        "SELECT COALESCE(AVG(timeviewed), 0) FROM {flexbook_user_state} WHERE flexbookid = ?",
        [$flexbook->id]
    )
    : 0;
$contentaccesssql = "SELECT c.id, c.title, COALESCE(SUM(p.viewcount), 0) AS views
                       FROM {flexbook_contents} c
                       JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                  LEFT JOIN {flexbook_user_progress} p ON p.contentid = c.id
                      WHERE ch.flexbookid = :flexbookid
                   GROUP BY c.id, c.title";
$contentaccess = $DB->get_records_sql($contentaccesssql, ["flexbookid" => $flexbook->id]);
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
echo $OUTPUT->heading(get_string("reports", "mod_flexbook"));
echo $OUTPUT->render_from_template("mod_flexbook/report_summary", ["cards" => [
    ["value" => count($enrolled), "label" => get_string("enrolledstudents", "mod_flexbook")],
    ["value" => $notstarted, "label" => get_string("notstarted", "mod_flexbook")],
    ["value" => $inprogress, "label" => get_string("inprogress", "mod_flexbook")],
    ["value" => $completed, "label" => get_string("completed", "mod_flexbook")],
    ["value" => "{$average}%", "label" => get_string("averageprogress", "mod_flexbook")],
    ["value" => format_time($averagetime), "label" => get_string("averagetime", "mod_flexbook")],
    [
        "value" => $mostaccessed ? format_string($mostaccessed->title ?: get_string("untitledcontent", "mod_flexbook")) : "-",
        "label" => get_string("mostaccessedblock", "mod_flexbook"),
    ],
    [
        "value" => $leastaccessed ? format_string($leastaccessed->title ?: get_string("untitledcontent", "mod_flexbook")) : "-",
        "label" => get_string("leastaccessedblock", "mod_flexbook"),
    ],
]]);

$tabs = [
    new tabobject("users", new moodle_url($PAGE->url, ["view" => "users"]), get_string("byuser", "mod_flexbook")),
    new tabobject("chapters", new moodle_url($PAGE->url, ["view" => "chapters"]), get_string("bychapter", "mod_flexbook")),
    new tabobject("contents", new moodle_url($PAGE->url, ["view" => "contents"]), get_string("bycontent", "mod_flexbook")),
];
echo $OUTPUT->tabtree($tabs, $view);

if ($view == "chapters") {
    $table = new flexible_table("flexbook-chapter-report-{$flexbook->id}");
    $table->define_columns(["chapter", "views", "uniqueusers", "completionrate", "averagetime", "abandonment"]);
    $table->define_headers([
        get_string("chapter", "mod_flexbook"),
        get_string("views", "mod_flexbook"),
        get_string("uniqueusers", "mod_flexbook"),
        get_string("completionrate", "mod_flexbook"),
        get_string("averagetime", "mod_flexbook"),
        get_string("abandonment", "mod_flexbook"),
    ]);
    $table->define_baseurl($PAGE->url);
    $table->setup();
    $table->start_output();

    $sql = "SELECT ch.id, ch.title,
                   COALESCE(SUM(p.viewcount), 0) AS views,
                   COUNT(DISTINCT p.userid) AS uniqueusers,
                   COALESCE(AVG(p.timeviewed), 0) AS averagetime,
                   COALESCE(SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END), 0) AS completions
              FROM {flexbook_chapters} ch
         LEFT JOIN {flexbook_chapter_progress} p ON p.chapterid = ch.id
             WHERE ch.flexbookid = :flexbookid
          GROUP BY ch.id, ch.title, ch.sortorder
          ORDER BY ch.sortorder, ch.id";
    $chapterstats = $DB->get_records_sql($sql, [
        "completed" => progress_manager::STATUS_COMPLETED,
        "flexbookid" => $flexbook->id,
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
    $table = new flexible_table("flexbook-content-report-{$flexbook->id}");
    $table->define_columns(["content", "type", "views", "uniqueusers", "completions"]);
    $table->define_headers([
        get_string("content", "mod_flexbook"),
        get_string("contenttype", "mod_flexbook"),
        get_string("views", "mod_flexbook"),
        get_string("uniqueusers", "mod_flexbook"),
        get_string("completions", "mod_flexbook"),
    ]);
    $table->define_baseurl($PAGE->url);
    $table->setup();
    $table->start_output();
    $sql = "SELECT c.id, c.title, c.type,
                   COALESCE(SUM(p.viewcount), 0) AS views,
                   COUNT(DISTINCT p.userid) AS uniqueusers,
                   SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END) AS completions
              FROM {flexbook_contents} c
              JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
         LEFT JOIN {flexbook_user_progress} p ON p.contentid = c.id
             WHERE ch.flexbookid = :flexbookid
          GROUP BY c.id, c.title, c.type, ch.sortorder, c.sortorder
          ORDER BY ch.sortorder, c.sortorder";
    $typeoptions = content_type_manager::get_type_options();
    foreach ($DB->get_records_sql($sql, [
        "flexbookid" => $flexbook->id,
        "completed" => progress_manager::STATUS_COMPLETED,
    ]) as $content) {
        $table->add_data([
            format_string($content->title ?: get_string("untitledcontent", "mod_flexbook")),
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
           FROM {flexbook_user_progress} p
          WHERE p.flexbookid = :flexbookid
       GROUP BY p.userid",
        [
            "completed" => progress_manager::STATUS_COMPLETED,
            "flexbookid" => $flexbook->id,
        ]
    );

    $accessedbyuser = $DB->get_records_sql(
        "SELECT userid, COUNT(id) AS accessedchapters
           FROM {flexbook_chapter_progress}
          WHERE flexbookid = :flexbookid
       GROUP BY userid",
        ["flexbookid" => $flexbook->id]
    );

    $requiredtotal = $DB->count_records_sql(
        "SELECT COUNT(c.id)
           FROM {flexbook_contents} c
           JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
          WHERE ch.flexbookid = :flexbookid
            AND ch.hidden = 0
            AND c.hidden = 0
            AND c.trackprogress = 1
            AND c.completiontype <> 'none'
            AND c.required = 1",
        ["flexbookid" => $flexbook->id]
    );
    $requiredbyuser = $DB->get_records_sql(
        "SELECT p.userid, COUNT(p.id) AS completedrequired
           FROM {flexbook_user_progress} p
           JOIN {flexbook_contents} c ON c.id = p.contentid
           JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
          WHERE ch.flexbookid = :flexbookid
            AND ch.hidden = 0
            AND c.hidden = 0
            AND c.trackprogress = 1
            AND c.completiontype <> 'none'
            AND c.required = 1
            AND p.status = :completed
       GROUP BY p.userid",
        [
            "flexbookid" => $flexbook->id,
            "completed" => progress_manager::STATUS_COMPLETED,
        ]
    );

    $extracolumns = [];
    $extradata = [];
    foreach (content_type_manager::get_classes() as $classname) {
        foreach ($classname::get_user_report_columns() as $key => $label) {
            $extracolumns[$key] = $label;
        }
        foreach ($classname::get_user_report_data($flexbook->id) as $reportuserid => $values) {
            $extradata[$reportuserid] = array_merge(
                $extradata[$reportuserid] ?? [],
                $values
            );
        }
    }

    $chaptertitles = $DB->get_records_menu(
        "flexbook_chapters",
        ["flexbookid" => $flexbook->id],
        "",
        "id, title"
    );

    $table = new flexible_table("flexbook-user-report-{$flexbook->id}");
    $table->define_columns([
        "fullname", "progress", "chapters", "completedblocks", "pendingrequired",
        "firstaccess", "lastaccess", "position",
        ...array_keys($extracolumns),
    ]);
    $table->define_headers([
        get_string("fullname"),
        get_string("progress", "mod_flexbook"),
        get_string("accessedchapters", "mod_flexbook"),
        get_string("completedblocks", "mod_flexbook"),
        get_string("pendingrequired", "mod_flexbook"),
        get_string("firstaccess", "mod_flexbook"),
        get_string("lastaccess", "mod_flexbook"),
        get_string("currentposition", "mod_flexbook"),
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
            ? ($chaptertitles[$state->lastchapterid] ?? get_string("notstarted", "mod_flexbook"))
            : get_string("notstarted", "mod_flexbook");
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
