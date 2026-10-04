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
 * provider.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use Override;

/**
 * Implements the Moodle Privacy API for BookFlow user data.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {

    /**
     * Describes the personal data stored by BookFlow.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    #[Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table("bookflow_user_progress", [
            "userid" => "privacy:metadata:userid",
            "status" => "privacy:metadata:status",
            "progress" => "privacy:metadata:progress",
            "details" => "privacy:metadata:details",
            "viewcount" => "privacy:metadata:viewcount",
            "timeviewed" => "privacy:metadata:timeviewed",
            "firstaccess" => "privacy:metadata:firstaccess",
            "lastaccess" => "privacy:metadata:lastaccess",
            "timecompleted" => "privacy:metadata:timecompleted",
        ], "privacy:metadata:userprogress");
        $collection->add_database_table("bookflow_user_state", [
            "userid" => "privacy:metadata:userid",
            "lastchapterid" => "privacy:metadata:lastchapter",
            "lastcontentid" => "privacy:metadata:lastcontent",
            "scrollposition" => "privacy:metadata:scrollposition",
            "progress" => "privacy:metadata:progress",
            "firstaccess" => "privacy:metadata:firstaccess",
            "lastaccess" => "privacy:metadata:lastaccess",
        ], "privacy:metadata:userstate");
        $collection->add_database_table("bookflow_chapter_progress", [
            "userid" => "privacy:metadata:userid",
            "status" => "privacy:metadata:status",
            "viewcount" => "privacy:metadata:viewcount",
            "timeviewed" => "privacy:metadata:timeviewed",
            "firstaccess" => "privacy:metadata:firstaccess",
            "lastaccess" => "privacy:metadata:lastaccess",
            "timecompleted" => "privacy:metadata:timecompleted",
        ], "privacy:metadata:chapterprogress");
        $collection->add_database_table("bookflow_bookmarks", [
            "userid" => "privacy:metadata:userid",
            "itemtype" => "privacy:metadata:itemtype",
            "chapterid" => "privacy:metadata:chapterid",
            "contentid" => "privacy:metadata:contentid",
            "timecreated" => "privacy:metadata:timecreated",
        ], "privacy:metadata:bookmarks");
        $collection->add_database_table("bookflow_notes", [
            "userid" => "privacy:metadata:userid",
            "note" => "privacy:metadata:note",
            "selectiontext" => "privacy:metadata:selectiontext",
            "timecreated" => "privacy:metadata:timecreated",
            "timemodified" => "privacy:metadata:timemodified",
        ], "privacy:metadata:notes");
        return $collection;
    }

    /**
     * Gets contexts containing BookFlow data for a user.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    #[Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {bookflow} f ON f.id = cm.instance
                 WHERE ctx.contextlevel = :contextlevel
                   AND (
                        EXISTS (SELECT 1 FROM {bookflow_user_progress} p
                                 WHERE p.bookflowid = f.id AND p.userid = :u1)
                     OR EXISTS (SELECT 1 FROM {bookflow_user_state} s
                                 WHERE s.bookflowid = f.id AND s.userid = :u2)
                     OR EXISTS (SELECT 1 FROM {bookflow_chapter_progress} cp
                                 WHERE cp.bookflowid = f.id AND cp.userid = :u7)
                     OR EXISTS (SELECT 1 FROM {bookflow_bookmarks} b
                                 WHERE b.bookflowid = f.id AND b.userid = :u3)
                     OR EXISTS (SELECT 1 FROM {bookflow_notes} n
                                 WHERE n.bookflowid = f.id AND n.userid = :u4)
                   )";
        $contextlist->add_from_sql($sql, [
            "modname" => "bookflow",
            "contextlevel" => CONTEXT_MODULE,
            "u1" => $userid,
            "u2" => $userid,
            "u3" => $userid,
            "u4" => $userid,
            "u7" => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Adds users with personal data in the supplied context.
     *
     * @param userlist $userlist Approved user list.
     * @return void
     */
    #[Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $params = ["cmid" => $context->instanceid, "modname" => "bookflow"];
        foreach ([
            "bookflow_user_progress",
            "bookflow_user_state",
            "bookflow_chapter_progress",
            "bookflow_bookmarks",
            "bookflow_notes",
        ] as $table) {
            $sql = "SELECT d.userid
                      FROM {{$table}} d
                      JOIN {bookflow} f ON f.id = d.bookflowid
                      JOIN {course_modules} cm ON cm.instance = f.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                     WHERE cm.id = :cmid";
            $userlist->add_from_sql("userid", $sql, $params);
        }
    }

    /**
     * Exports approved BookFlow user data.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    #[Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id("bookflow", $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $bookflow = $DB->get_record("bookflow", ["id" => $cm->instance], "*", MUST_EXIST);
            $contextdata = helper::get_context_data($context, $contextlist->get_user());
            writer::with_context($context)->export_data([], $contextdata);

            $datasets = [
                "progress" => $DB->get_records("bookflow_user_progress", [
                    "bookflowid" => $bookflow->id,
                    "userid" => $userid,
                ]),
                "state" => $DB->get_records("bookflow_user_state", [
                    "bookflowid" => $bookflow->id,
                    "userid" => $userid,
                ]),
                "chapter_progress" => $DB->get_records("bookflow_chapter_progress", [
                    "bookflowid" => $bookflow->id,
                    "userid" => $userid,
                ]),
                "bookmarks" => $DB->get_records("bookflow_bookmarks", [
                    "bookflowid" => $bookflow->id,
                    "userid" => $userid,
                ]),
                "notes" => $DB->get_records("bookflow_notes", [
                    "bookflowid" => $bookflow->id,
                    "userid" => $userid,
                ]),
            ];
            foreach ($datasets as $name => $records) {
                $export = [];
                foreach ($records as $record) {
                    $copy = clone $record;
                    unset($copy->userid);
                    foreach (["timecreated", "timemodified", "firstaccess", "lastaccess", "timecompleted"] as $field) {
                        if (!empty($copy->{$field})) {
                            $copy->{$field} = transform::datetime($copy->{$field});
                        }
                    }
                    $export[] = $copy;
                }
                writer::with_context($context)->export_data([get_string("privacy:{$name}", "mod_bookflow")], $export);
            }
        }
    }

    /**
     * Deletes all BookFlow user data in a context.
     *
     * @param context $context Module context.
     * @return void
     */
    #[Override]
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id("bookflow", $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        self::delete_for_bookflow($cm->instance);
    }

    /**
     * Deletes BookFlow data for one approved user.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    #[Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id("bookflow", $context->instanceid, 0, false, IGNORE_MISSING);
            if ($cm) {
                self::delete_for_user($cm->instance, [$userid]);
            }
        }
    }

    /**
     * Deletes BookFlow data for approved users.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    #[Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id("bookflow", $context->instanceid, 0, false, IGNORE_MISSING);
        if ($cm) {
            self::delete_for_user($cm->instance, $userlist->get_userids());
        }
    }

    /**
     * Deletes all user-owned data for a BookFlow.
     *
     * @param int $bookflowid BookFlow ID.
     * @return void
     */
    private static function delete_for_bookflow(int $bookflowid): void {
        global $DB;

        foreach ([
            "bookflow_user_progress",
            "bookflow_user_state",
            "bookflow_chapter_progress",
            "bookflow_bookmarks",
            "bookflow_notes",
        ] as $table) {
            $DB->delete_records($table, ["bookflowid" => $bookflowid]);
        }
    }

    /**
     * Deletes selected users' data from a BookFlow.
     *
     * @param int $bookflowid BookFlow ID.
     * @param array $userids User IDs.
     * @return void
     */
    private static function delete_for_user(int $bookflowid, array $userids): void {
        global $DB;

        if (!$userids) {
            return;
        }
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        foreach ([
            "bookflow_user_progress",
            "bookflow_user_state",
            "bookflow_chapter_progress",
            "bookflow_bookmarks",
            "bookflow_notes",
        ] as $table) {
            $DB->delete_records_select($table, "bookflowid = :bookflowid AND userid {$usersql}",
                ["bookflowid" => $bookflowid] + $userparams);
        }
    }
}
