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

$enrolled = get_enrolled_users($context, "mod/flexbook:view", 0, "u.id, u.firstname, u.lastname, u.email");
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
    foreach ($DB->get_records("flexbook_chapters", ["flexbookid" => $flexbook->id], "sortorder") as $chapter) {
        $stats = $DB->get_record_sql(
            "SELECT COALESCE(SUM(p.viewcount), 0) AS views,
                    COUNT(DISTINCT p.userid) AS uniqueusers,
                    AVG(p.timeviewed) AS averagetime,
                    SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END) AS completions
               FROM {flexbook_chapter_progress} p
              WHERE p.chapterid = :chapterid",
            [
                "chapterid" => $chapter->id,
                "completed" => progress_manager::STATUS_COMPLETED,
            ]
        );
        $rate = $stats->uniqueusers ? round($stats->completions / $stats->uniqueusers * 100, 1) : 0;
        $abandonment = $stats->uniqueusers ? round(100 - $rate, 1) : 0;
        $table->add_data([
            format_string($chapter->title),
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
    $table = new flexible_table("flexbook-user-report-{$flexbook->id}");
    $table->define_columns([
        "fullname", "progress", "chapters", "completedblocks", "pendingrequired",
        "firstaccess", "lastaccess", "position", "questions",
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
        get_string("questionsanswered", "mod_flexbook"),
    ]);
    $table->define_baseurl($PAGE->url);
    $table->setup();
    $table->start_output();
    foreach ($enrolled as $user) {
        $state = $statebyuser[$user->id] ?? null;
        $stats = $DB->get_record_sql(
            "SELECT SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END) AS completedblocks
               FROM {flexbook_user_progress} p
              WHERE p.flexbookid = :flexbookid AND p.userid = :userid",
            [
                "completed" => progress_manager::STATUS_COMPLETED,
                "flexbookid" => $flexbook->id,
                "userid" => $user->id,
            ]
        );
        $accessedchapters = $DB->count_records("flexbook_chapter_progress", [
            "flexbookid" => $flexbook->id,
            "userid" => $user->id,
        ]);
        $pending = (new progress_manager())
            ->get_pending_required_count($flexbook->id, $user->id);
        $position = $state && $state->lastchapterid
            ? $DB->get_field("flexbook_chapters", "title", ["id" => $state->lastchapterid])
            : get_string("notstarted", "mod_flexbook");
        $questions = $DB->count_records_sql(
            "SELECT COUNT(a.id)
               FROM {flexbook_question_attempts} a
               JOIN {flexbook_questions} q ON q.id = a.questionid
               JOIN {flexbook_contents} c ON c.id = q.contentid
               JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
              WHERE ch.flexbookid = :flexbookid AND a.userid = :userid",
            ["flexbookid" => $flexbook->id, "userid" => $user->id]
        );
        $table->add_data([
            fullname($user),
            format_float($state->progress ?? 0, 1) . "%",
            $accessedchapters,
            $stats->completedblocks ?? 0,
            $pending,
            $state ? userdate($state->firstaccess) : "-",
            $state ? userdate($state->lastaccess) : "-",
            format_string($position),
            $questions,
        ]);
    }
    $table->finish_output();
}

echo $OUTPUT->footer();
