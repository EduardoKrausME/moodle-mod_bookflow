<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Privacy provider for question attempts.
 *
 * @package flexbookcontent_question
 */

namespace flexbookcontent_question\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use Override;

/**
 * Owns privacy operations for user question attempts.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {

    #[Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table("flexbook_question_attempts", [
            "userid" => "privacy:metadata:userid",
            "answerjson" => "privacy:metadata:answer",
            "iscorrect" => "privacy:metadata:iscorrect",
            "attemptnumber" => "privacy:metadata:attemptnumber",
            "timecreated" => "privacy:metadata:timecreated",
        ], "privacy:metadata:questionattempts");
        return $collection;
    }

    #[Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {flexbook} f ON f.id = cm.instance
                 WHERE ctx.contextlevel = :contextlevel
                   AND EXISTS (
                        SELECT 1
                          FROM {flexbook_question_attempts} a
                          JOIN {flexbook_questions} q ON q.id = a.questionid
                          JOIN {flexbook_contents} c ON c.id = q.contentid
                          JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                         WHERE ch.flexbookid = f.id
                           AND a.userid = :userid
                   )";
        $contextlist->add_from_sql($sql, [
            "modname" => "flexbook",
            "contextlevel" => CONTEXT_MODULE,
            "userid" => $userid,
        ]);
        return $contextlist;
    }

    #[Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $sql = "SELECT a.userid
                  FROM {flexbook_question_attempts} a
                  JOIN {flexbook_questions} q ON q.id = a.questionid
                  JOIN {flexbook_contents} c ON c.id = q.contentid
                  JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                  JOIN {flexbook} f ON f.id = ch.flexbookid
                  JOIN {course_modules} cm ON cm.instance = f.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql("userid", $sql, [
            "cmid" => $context->instanceid,
            "modname" => "flexbook",
        ]);
    }

    #[Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id(
                "flexbook",
                $context->instanceid,
                0,
                false,
                IGNORE_MISSING
            );
            if (!$cm) {
                continue;
            }

            $sql = "SELECT a.*
                      FROM {flexbook_question_attempts} a
                      JOIN {flexbook_questions} q ON q.id = a.questionid
                      JOIN {flexbook_contents} c ON c.id = q.contentid
                      JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                     WHERE ch.flexbookid = :flexbookid
                       AND a.userid = :userid";
            $records = $DB->get_records_sql($sql, [
                "flexbookid" => $cm->instance,
                "userid" => $userid,
            ]);

            $export = [];
            foreach ($records as $record) {
                $copy = clone $record;
                unset($copy->userid);
                if (!empty($copy->timecreated)) {
                    $copy->timecreated = transform::datetime($copy->timecreated);
                }
                $export[] = $copy;
            }

            writer::with_context($context)->export_data(
                [get_string("privacy:question_attempts", "flexbookcontent_question")],
                $export
            );
        }
    }

    #[Override]
    public static function delete_data_for_all_users_in_context(context $context): void {
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id(
            "flexbook",
            $context->instanceid,
            0,
            false,
            IGNORE_MISSING
        );
        if ($cm) {
            self::delete_attempts($cm->instance, []);
        }
    }

    #[Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id(
                "flexbook",
                $context->instanceid,
                0,
                false,
                IGNORE_MISSING
            );
            if ($cm) {
                self::delete_attempts($cm->instance, [$userid]);
            }
        }
    }

    #[Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id(
            "flexbook",
            $context->instanceid,
            0,
            false,
            IGNORE_MISSING
        );
        if ($cm) {
            self::delete_attempts($cm->instance, $userlist->get_userids());
        }
    }

    /**
     * Deletes attempts for one FlexBook, optionally restricted to user ids.
     *
     * @param int $flexbookid FlexBook id.
     * @param array $userids User ids, or empty for all.
     * @return void
     */
    private static function delete_attempts(int $flexbookid, array $userids): void {
        global $DB;

        $params = ["flexbookid" => $flexbookid];
        $userclause = "";
        if ($userids) {
            [$usersql, $userparams] = $DB->get_in_or_equal(
                $userids,
                SQL_PARAMS_NAMED,
                "privacyuser"
            );
            $userclause = " AND userid {$usersql}";
            $params += $userparams;
        }

        $sql = "questionid IN (
                    SELECT q.id
                      FROM {flexbook_questions} q
                      JOIN {flexbook_contents} c ON c.id = q.contentid
                      JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                     WHERE ch.flexbookid = :flexbookid
                ){$userclause}";
        $DB->delete_records_select("flexbook_question_attempts", $sql, $params);
    }
}
