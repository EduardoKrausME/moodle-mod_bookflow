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
 * progress_manager.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\progress;

use completion_info;
use context_module;
use dml_write_exception;
use invalid_parameter_exception;
use mod_flexbook\content_type_manager;
use mod_flexbook\event\chapter_completed;
use mod_flexbook\event\chapter_viewed;
use mod_flexbook\event\content_completed;
use mod_flexbook\event\content_viewed;
use mod_flexbook\event\flexbook_completed;
use mod_flexbook\event\progress_updated;
use moodle_exception;
use stdClass;

/**
 * Tracks reading evidence and calculates user progress.
 */
class progress_manager {
    /**
     * None
     */
    public const STATUS_NONE = 0;
    /**
     * Viewed
     */
    public const STATUS_VIEWED = 1;
    /**
     * Completed
     */
    public const STATUS_COMPLETED = 2;


    /**
     * Marks content viewed.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param int $contentid Content block ID.
     * @return void
     */
    public function mark_content_viewed(int $flexbookid, int $userid, int $contentid): void {
        global $DB;

        [$content, $chapter] = $this->validate_content($flexbookid, $contentid);
        if ($content->hidden || $chapter->hidden) {
            throw new moodle_exception("contentnotavailable", "mod_flexbook");
        }

        $record = $this->get_or_create_content_progress($flexbookid, $userid, $content, $chapter);
        $now = time();
        $DB->execute(
            "UPDATE {flexbook_user_progress}
                SET viewcount = viewcount + 1,
                    lastaccess = :lastaccess,
                    timemodified = :timemodified,
                    status = CASE WHEN status < :viewed THEN :viewedstatus ELSE status END
              WHERE id = :id",
            [
                "lastaccess" => $now,
                "timemodified" => $now,
                "viewed" => self::STATUS_VIEWED,
                "viewedstatus" => self::STATUS_VIEWED,
                "id" => $record->id,
            ]
        );

        if (in_array($content->completiontype, ["open", "view"])) {
            $this->mark_content_completed($flexbookid, $userid, $contentid);
        } else {
            $this->calculate_user_progress($flexbookid, $userid);
        }

        content_viewed::create_from_ids(
            $flexbookid,
            $chapter->id,
            $contentid,
            $userid
        )->trigger();
    }


    /**
     * Marks content completed.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param int $contentid Content block ID.
     * @return void
     */
    public function mark_content_completed(int $flexbookid, int $userid, int $contentid): void {
        global $DB;

        [$content, $chapter] = $this->validate_content($flexbookid, $contentid);
        if (!$content->trackprogress || $content->completiontype == "none") {
            return;
        }

        $record = $DB->get_record("flexbook_user_progress", [
            "userid" => $userid,
            "contentid" => $contentid,
        ]);
        if (!$record) {
            $this->mark_content_viewed($flexbookid, $userid, $contentid);
            $record = $DB->get_record("flexbook_user_progress", [
                "userid" => $userid,
                "contentid" => $contentid,
            ], "*", MUST_EXIST);
        }
        if ($record->status == self::STATUS_COMPLETED) {
            return;
        }
        if (!$this->can_complete($flexbookid, $content, $record)) {
            throw new moodle_exception("completionevidencemissing", "mod_flexbook");
        }

        $record->status = self::STATUS_COMPLETED;
        $record->progress = 100;
        $record->timecompleted = time();
        $record->timemodified = time();
        $DB->update_record("flexbook_user_progress", $record);

        $progress = $this->calculate_user_progress($flexbookid, $userid);
        $this->update_chapter_completion($flexbookid, $userid, $chapter->id);
        $this->update_completion($flexbookid, $userid, $progress);

        content_completed::create_from_ids(
            $flexbookid,
            $chapter->id,
            $contentid,
            $userid
        )->trigger();
        progress_updated::create_from_ids(
            $flexbookid,
            $chapter->id,
            $contentid,
            $userid,
            ["progress" => $progress]
        )->trigger();
    }


    /**
     * Updates content metric.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param int $contentid Content block ID.
     * @param float $progress Progress evidence reported by the client.
     * @param array $details Supporting completion evidence.
     * @return void
     */
    public function update_content_metric(
        int $flexbookid,
        int $userid,
        int $contentid,
        float $progress,
        array $details = []
    ): void {
        global $DB;

        [$content, $chapter] = $this->validate_content($flexbookid, $contentid);
        if ($content->hidden || $chapter->hidden) {
            throw new moodle_exception("contentnotavailable", "mod_flexbook");
        }

        $record = $DB->get_record("flexbook_user_progress", [
            "userid" => $userid,
            "contentid" => $contentid,
        ]);
        if (!$record) {
            $this->mark_content_viewed($flexbookid, $userid, $contentid);
            $record = $DB->get_record("flexbook_user_progress", [
                "userid" => $userid,
                "contentid" => $contentid,
            ], "*", MUST_EXIST);
        }

        $handler = $this->get_content_handler($flexbookid, $content);
        $record = $handler->update_completion_evidence($record, $progress, $details);
        $record->timemodified = time();
        $DB->update_record("flexbook_user_progress", $record);

        if ($record->status != self::STATUS_COMPLETED
                && $handler->completion_evidence_is_valid($record)) {
            $this->mark_content_completed($flexbookid, $userid, $contentid);
        }
    }


    /**
     * Calculates user progress.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return float
     */
    public function calculate_user_progress(int $flexbookid, int $userid): float {
        global $DB;

        $sql = "SELECT
                       COALESCE(SUM(CASE WHEN c.weight > 0 THEN c.weight ELSE 0 END), 0) AS totalweight,
                       COALESCE(SUM(CASE
                           WHEN p.status = :completed
                           THEN CASE WHEN c.weight > 0 THEN c.weight ELSE 0 END
                           ELSE 0
                       END), 0) AS completedweight,
                       COALESCE(SUM(p.timeviewed), 0) AS timeviewed
                  FROM {flexbook_contents} c
                  JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
             LEFT JOIN {flexbook_user_progress} p
                    ON p.contentid = c.id AND p.userid = :userid
                 WHERE ch.flexbookid = :flexbookid
                   AND ch.hidden = 0
                   AND c.hidden = 0
                   AND c.trackprogress = 1
                   AND c.completiontype <> 'none'";
        $stats = $DB->get_record_sql($sql, [
            "completed" => self::STATUS_COMPLETED,
            "userid" => $userid,
            "flexbookid" => $flexbookid,
        ]);

        $totalweight = (float) ($stats->totalweight ?? 0);
        $completedweight = (float) ($stats->completedweight ?? 0);
        $progress = $totalweight > 0 ? ($completedweight / $totalweight) * 100 : 0;
        $progress = round(min(100, max(0, $progress)), 2);
        $this->save_aggregate_progress(
            $flexbookid,
            $userid,
            $progress,
            (int) round($stats->timeviewed ?? 0)
        );
        return $progress;
    }

    /**
     * Gets last position.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return stdClass|null
     */
    public function get_last_position(int $flexbookid, int $userid): ?stdClass {
        global $DB;

        $sql = "SELECT s.*, ch.title AS chaptertitle
                  FROM {flexbook_user_state} s
             LEFT JOIN {flexbook_chapters} ch ON ch.id = s.lastchapterid
                 WHERE s.flexbookid = :flexbookid
                   AND s.userid = :userid";
        return $DB->get_record_sql($sql, ["flexbookid" => $flexbookid, "userid" => $userid]) ?: null;
    }


    /**
     * Marks chapter viewed.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param int $chapterid Chapter ID.
     * @return void
     */
    public function mark_chapter_viewed(int $flexbookid, int $userid, int $chapterid): void {
        global $DB;

        $chapter = $DB->get_record("flexbook_chapters", [
            "id" => $chapterid,
            "flexbookid" => $flexbookid,
        ], "*", MUST_EXIST);
        if ($chapter->hidden) {
            throw new moodle_exception("chapterhidden", "mod_flexbook");
        }

        $record = $this->get_or_create_chapter_progress($flexbookid, $userid, $chapterid);
        $now = time();
        $DB->execute(
            "UPDATE {flexbook_chapter_progress}
                SET viewcount = viewcount + 1,
                    lastaccess = :lastaccess,
                    timemodified = :timemodified,
                    status = CASE WHEN status < :viewed THEN :viewedstatus ELSE status END
              WHERE id = :id",
            [
                "lastaccess" => $now,
                "timemodified" => $now,
                "viewed" => self::STATUS_VIEWED,
                "viewedstatus" => self::STATUS_VIEWED,
                "id" => $record->id,
            ]
        );
        chapter_viewed::create_from_ids(
            $flexbookid,
            $chapterid,
            0,
            $userid
        )->trigger();
    }

    /**
     * Saves last position.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param int $chapterid Chapter ID.
     * @param int|null $contentid Content block ID.
     * @param int $scrollposition Approximate vertical reading position.
     * @return void
     */
    public function save_last_position(
        int $flexbookid,
        int $userid,
        int $chapterid,
        ?int $contentid,
        int $scrollposition = 0
    ): void {
        global $DB;

        $chapter = $DB->get_record("flexbook_chapters", [
            "id" => $chapterid,
            "flexbookid" => $flexbookid,
        ], "*", MUST_EXIST);
        if ($contentid && !$DB->record_exists("flexbook_contents", [
                "id" => $contentid,
                "chapterid" => $chapter->id,
            ])) {
            throw new invalid_parameter_exception("Content does not belong to chapter");
        }

        $state = $this->get_or_create_state($flexbookid, $userid);
        $state->lastchapterid = $chapterid;
        $state->lastcontentid = $contentid ?? 0;
        $state->scrollposition = max(0, $scrollposition);
        $state->lastaccess = time();
        $state->timemodified = time();
        $DB->update_record("flexbook_user_state", $state);
    }


    /**
     * Updates Moodle activity completion from the current FlexBook progress.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param float|null $progress Already calculated aggregate progress.
     * @return void
     */
    public function update_completion(int $flexbookid, int $userid, ?float $progress = null): void {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance("flexbook", $flexbookid, $flexbook->course, false, MUST_EXIST);
        $completion = new completion_info(get_course($flexbook->course));
        if (!$completion->is_enabled($cm)) {
            return;
        }

        $complete = $this->completion_requirements_met($flexbookid, $userid, $progress);
        $completion->update_state($cm, $complete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE, $userid);

        $state = $this->get_or_create_state($flexbookid, $userid);
        if ($complete && !$state->timecompleted) {
            $state->timecompleted = time();
            $DB->update_record("flexbook_user_state", $state);
            flexbook_completed::create_from_ids(
                $flexbookid,
                0,
                0,
                $userid
            )->trigger();
        } else if (!$complete && $state->timecompleted) {
            $state->timecompleted = 0;
            $DB->update_record("flexbook_user_state", $state);
        }
    }


    /**
     * Checks whether the user satisfies the configured completion requirements.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param float|null $progress Already calculated aggregate progress.
     * @return bool
     */
    public function completion_requirements_met(
        int $flexbookid,
        int $userid,
        ?float $progress = null
    ): bool {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        $progress ??= $this->calculate_user_progress($flexbookid, $userid);
        $percentagemet = $progress >= $flexbook->completionpercentage;
        $requiredcontentcount = $this->get_required_content_count($flexbookid);
        $requiredchaptercount = $this->get_required_chapter_count($flexbookid);
        $mandatorymet = $requiredcontentcount > 0
            && !$this->has_pending_required_contents($flexbookid, $userid);
        $chaptersmet = $requiredchaptercount > 0
            && !$this->has_pending_required_chapters($flexbookid, $userid);

        return match ($flexbook->completionmode) {
            FLEXBOOK_COMPLETION_REQUIRED => $mandatorymet,
            FLEXBOOK_COMPLETION_COMBINED => $percentagemet && $mandatorymet,
            FLEXBOOK_COMPLETION_CHAPTERS => $chaptersmet,
            default => $percentagemet,
        };
    }

    /**
     * Recalculates all derived progress for one user.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return void
     */
    public function recalculate_user(int $flexbookid, int $userid): void {
        $progress = $this->calculate_user_progress($flexbookid, $userid);
        $this->recalculate_chapters($flexbookid, $userid);
        $this->update_completion($flexbookid, $userid, $progress);
    }

    /**
     * Returns a warning for completion rules that cannot currently be satisfied.
     *
     * @param int $flexbookid FlexBook ID.
     * @return string|null
     */
    public function get_completion_configuration_warning(int $flexbookid): ?string {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        $trackedcount = $DB->count_records_sql(
            "SELECT COUNT(c.id)
               FROM {flexbook_contents} c
               JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
              WHERE ch.flexbookid = :flexbookid
                AND ch.hidden = 0
                AND c.hidden = 0
                AND c.trackprogress = 1
                   AND c.completiontype <> 'none'",
            ["flexbookid" => $flexbookid]
        );

        if (in_array($flexbook->completionmode, [FLEXBOOK_COMPLETION_PERCENTAGE, FLEXBOOK_COMPLETION_COMBINED])
                && !$trackedcount) {
            return get_string("completionconfignotracked", "mod_flexbook");
        }
        if (in_array($flexbook->completionmode, [FLEXBOOK_COMPLETION_REQUIRED, FLEXBOOK_COMPLETION_COMBINED])
                && !$this->get_required_content_count($flexbookid)) {
            return get_string("completionconfignorequired", "mod_flexbook");
        }
        if ($flexbook->completionmode == FLEXBOOK_COMPLETION_CHAPTERS) {
            if (!$this->get_required_chapter_count($flexbookid)) {
                return get_string("completionconfignorequiredchapters", "mod_flexbook");
            }
            if ($this->get_invalid_required_chapter_count($flexbookid)) {
                return get_string("completionconfigemptyrequiredchapter", "mod_flexbook");
            }
        }
        return null;
    }


    /**
     * Recalculates chapter progress in one aggregate query.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return void
     */
    public function recalculate_chapters(int $flexbookid, int $userid): void {
        global $DB;

        $sql = "SELECT ch.id,
                       COUNT(c.id) AS total,
                       COALESCE(SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END), 0) AS completed,
                       COALESCE(SUM(p.timeviewed), 0) AS timeviewed,
                       MIN(p.firstaccess) AS firstaccess,
                       MAX(p.lastaccess) AS lastaccess,
                       COUNT(p.id) AS progressrecords
                  FROM {flexbook_chapters} ch
             LEFT JOIN {flexbook_contents} c
                    ON c.chapterid = ch.id
                   AND c.hidden = 0
                   AND c.trackprogress = 1
                   AND c.completiontype <> 'none'
             LEFT JOIN {flexbook_user_progress} p
                    ON p.contentid = c.id
                   AND p.userid = :userid
                 WHERE ch.flexbookid = :flexbookid
              GROUP BY ch.id";
        $stats = $DB->get_records_sql($sql, [
            "completed" => self::STATUS_COMPLETED,
            "userid" => $userid,
            "flexbookid" => $flexbookid,
        ]);
        $records = $DB->get_records("flexbook_chapter_progress", [
            "flexbookid" => $flexbookid,
            "userid" => $userid,
        ]);
        $bychapter = [];
        foreach ($records as $record) {
            $bychapter[$record->chapterid] = $record;
        }

        $now = time();
        foreach ($stats as $chapterid => $chapterstats) {
            $record = $bychapter[$chapterid] ?? null;
            if (!$record && !$chapterstats->progressrecords) {
                continue;
            }
            if (!$record) {
                $record = $this->get_or_create_chapter_progress($flexbookid, $userid, (int) $chapterid);
                $record->firstaccess = (int) ($chapterstats->firstaccess ?: $now);
            }

            $wascompleted = $record->status == self::STATUS_COMPLETED;
            $iscompleted = $chapterstats->total > 0
                && $chapterstats->completed >= $chapterstats->total;
            $record->status = $iscompleted ? self::STATUS_COMPLETED : self::STATUS_VIEWED;
            $record->timeviewed = (int) round($chapterstats->timeviewed ?? 0);
            $record->lastaccess = (int) ($chapterstats->lastaccess ?: $record->lastaccess ?: $now);
            $record->timecompleted = $iscompleted
                ? ($record->timecompleted ?: $now)
                : 0;
            $record->timemodified = $now;
            $DB->update_record("flexbook_chapter_progress", $record);

            if ($iscompleted && !$wascompleted) {
                chapter_completed::create_from_ids(
                    $flexbookid,
                    (int) $chapterid,
                    0,
                    $userid
                )->trigger();
            }
        }
    }


    /**
     * Gets pending required count.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return int
     */
    public function get_pending_required_count(int $flexbookid, int $userid): int {
        global $DB;

        $sql = "SELECT COUNT(c.id)
                  FROM {flexbook_contents} c
                  JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
             LEFT JOIN {flexbook_user_progress} p
                    ON p.contentid = c.id AND p.userid = :userid
                 WHERE ch.flexbookid = :flexbookid
                   AND ch.hidden = 0
                   AND c.hidden = 0
                   AND c.trackprogress = 1
                   AND c.completiontype <> 'none'
                   AND c.required = 1
                   AND (p.status IS NULL OR p.status < :completed)";
        return $DB->count_records_sql($sql, [
            "flexbookid" => $flexbookid,
            "userid" => $userid,
            "completed" => self::STATUS_COMPLETED,
        ]);
    }

    /**
     * Checks whether required content blocks remain incomplete.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return bool
     */
    private function has_pending_required_contents(int $flexbookid, int $userid): bool {
        return $this->get_pending_required_count($flexbookid, $userid) > 0;
    }


    /**
     * Gets required content count.
     *
     * @param int $flexbookid FlexBook ID.
     * @return int
     */
    private function get_required_content_count(int $flexbookid): int {
        global $DB;

        return $DB->count_records_sql(
            "SELECT COUNT(c.id)
               FROM {flexbook_contents} c
               JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
              WHERE ch.flexbookid = :flexbookid
                AND ch.hidden = 0
                AND c.hidden = 0
                AND c.trackprogress = 1
                   AND c.completiontype <> 'none'
                AND c.required = 1",
            ["flexbookid" => $flexbookid]
        );
    }

    /**
     * Gets required chapter count.
     *
     * @param int $flexbookid FlexBook ID.
     * @return int
     */
    private function get_required_chapter_count(int $flexbookid): int {
        global $DB;

        return $DB->count_records("flexbook_chapters", [
            "flexbookid" => $flexbookid,
            "required" => 1,
            "hidden" => 0,
        ]);
    }



    /**
     * Counts required chapters that do not contain any completable tracked block.
     *
     * @param int $flexbookid FlexBook ID.
     * @return int
     */
    private function get_invalid_required_chapter_count(int $flexbookid): int {
        global $DB;

        $sql = "SELECT COUNT(ch.id)
                  FROM {flexbook_chapters} ch
                 WHERE ch.flexbookid = :flexbookid
                   AND ch.required = 1
                   AND ch.hidden = 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {flexbook_contents} c
                        WHERE c.chapterid = ch.id
                          AND c.hidden = 0
                          AND c.trackprogress = 1
                          AND c.completiontype <> 'none'
                   )";
        return $DB->count_records_sql($sql, ["flexbookid" => $flexbookid]);
    }

    /**
     * Checks whether required chapters remain incomplete.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return bool
     */
    private function has_pending_required_chapters(int $flexbookid, int $userid): bool {
        global $DB;

        $sql = "SELECT COUNT(ch.id)
                  FROM {flexbook_chapters} ch
                 WHERE ch.flexbookid = :flexbookid
                   AND ch.required = 1
                   AND ch.hidden = 0
                   AND (
                       NOT EXISTS (
                           SELECT 1
                             FROM {flexbook_contents} eligible
                            WHERE eligible.chapterid = ch.id
                              AND eligible.hidden = 0
                              AND eligible.trackprogress = 1
                              AND eligible.completiontype <> 'none'
                       )
                       OR EXISTS (
                           SELECT 1
                             FROM {flexbook_contents} c
                        LEFT JOIN {flexbook_user_progress} p
                               ON p.contentid = c.id
                              AND p.userid = :userid
                            WHERE c.chapterid = ch.id
                              AND c.hidden = 0
                              AND c.trackprogress = 1
                              AND c.completiontype <> 'none'
                              AND (p.status IS NULL OR p.status < :completed)
                       )
                   )";
        return $DB->count_records_sql($sql, [
            "flexbookid" => $flexbookid,
            "userid" => $userid,
            "completed" => self::STATUS_COMPLETED,
        ]) > 0;
    }


    /**
     * Updates chapter completion.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param int $chapterid Chapter ID.
     * @return void
     */
    private function update_chapter_completion(int $flexbookid, int $userid, int $chapterid): void {
        global $DB;

        $sql = "SELECT COUNT(c.id) AS total,
                       COALESCE(SUM(CASE WHEN p.status = :completed THEN 1 ELSE 0 END), 0) AS completed,
                       COALESCE(SUM(p.timeviewed), 0) AS timeviewed
                  FROM {flexbook_contents} c
             LEFT JOIN {flexbook_user_progress} p
                    ON p.contentid = c.id AND p.userid = :userid
                 WHERE c.chapterid = :chapterid
                   AND c.hidden = 0
                   AND c.trackprogress = 1
                   AND c.completiontype <> 'none'";
        $stats = $DB->get_record_sql($sql, [
            "completed" => self::STATUS_COMPLETED,
            "userid" => $userid,
            "chapterid" => $chapterid,
        ]);
        $record = $DB->get_record("flexbook_chapter_progress", [
            "userid" => $userid,
            "chapterid" => $chapterid,
        ]);

        $iscompleted = ($stats->total ?? 0) > 0
            && ($stats->completed ?? 0) >= $stats->total;
        if (!$iscompleted) {
            if ($record && $record->status == self::STATUS_COMPLETED) {
                $record->status = self::STATUS_VIEWED;
                $record->timecompleted = 0;
                $record->timeviewed = (int) round($stats->timeviewed ?? 0);
                $record->timemodified = time();
                $DB->update_record("flexbook_chapter_progress", $record);
            }
            return;
        }

        $record ??= $this->get_or_create_chapter_progress($flexbookid, $userid, $chapterid);
        if ($record->status == self::STATUS_COMPLETED) {
            return;
        }
        $record->status = self::STATUS_COMPLETED;
        $record->timecompleted = time();
        $record->timeviewed = (int) round($stats->timeviewed ?? 0);
        $record->timemodified = time();
        $DB->update_record("flexbook_chapter_progress", $record);
        chapter_completed::create_from_ids(
            $flexbookid,
            $chapterid,
            0,
            $userid
        )->trigger();
    }


    /**
     * Checks whether the available evidence satisfies a content completion rule.
     *
     * @param int $flexbookid FlexBook ID.
     * @param stdClass $content Content record.
     * @param stdClass $progress User progress record.
     * @return bool
     */
    private function can_complete(int $flexbookid, stdClass $content, stdClass $progress): bool {
        return $this->get_content_handler($flexbookid, $content)
            ->completion_evidence_is_valid($progress);
    }

    /**
     * Creates the registered content handler used to validate completion evidence.
     *
     * @param int $flexbookid FlexBook ID.
     * @param stdClass $content Content record.
     * @return \mod_flexbook\types\content
     */
    private function get_content_handler(int $flexbookid, stdClass $content): \mod_flexbook\types\content {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance("flexbook", $flexbookid, $flexbook->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        return content_type_manager::create_content($content, $flexbook, $context);
    }

    /**
     * Validates a content block and returns it with its chapter.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $contentid Content block ID.
     * @return array
     */
    private function validate_content(int $flexbookid, int $contentid): array {
        global $DB;

        $sql = "SELECT c.*, ch.flexbookid, ch.hidden AS chapterhidden
                  FROM {flexbook_contents} c
                  JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                 WHERE c.id = :contentid AND ch.flexbookid = :flexbookid";
        $content = $DB->get_record_sql($sql, [
            "contentid" => $contentid,
            "flexbookid" => $flexbookid,
        ], MUST_EXIST);
        $chapter = (object) [
            "id" => $content->chapterid,
            "hidden" => $content->chapterhidden,
            "flexbookid" => $content->flexbookid,
        ];
        return [$content, $chapter];
    }


    /**
     * Stores the aggregate progress in the user state.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param float $progress Calculated progress percentage.
     * @param int|null $timeviewed Already aggregated viewing time.
     * @return void
     */
    private function save_aggregate_progress(
        int $flexbookid,
        int $userid,
        float $progress,
        ?int $timeviewed = null
    ): void {
        global $DB;

        $state = $this->get_or_create_state($flexbookid, $userid);
        $state->progress = $progress;
        $state->timeviewed = $timeviewed ?? (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(timeviewed), 0)
               FROM {flexbook_user_progress}
              WHERE flexbookid = ? AND userid = ?",
            [$flexbookid, $userid]
        );
        $state->lastaccess = time();
        $state->timemodified = time();
        $DB->update_record("flexbook_user_state", $state);
    }


    /**
     * Gets or creates the aggregate user state.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return stdClass
     */
    private function get_or_create_state(int $flexbookid, int $userid): stdClass {
        global $DB;

        $state = $DB->get_record("flexbook_user_state", [
            "flexbookid" => $flexbookid,
            "userid" => $userid,
        ]);
        if ($state) {
            return $state;
        }

        $now = time();
        $state = (object) [
            "flexbookid" => $flexbookid,
            "userid" => $userid,
            "lastchapterid" => 0,
            "lastcontentid" => 0,
            "scrollposition" => 0,
            "progress" => 0,
            "timeviewed" => 0,
            "firstaccess" => $now,
            "lastaccess" => $now,
            "timecompleted" => 0,
            "timemodified" => $now,
        ];
        try {
            $state->id = $DB->insert_record("flexbook_user_state", $state);
            return $state;
        } catch (dml_write_exception $exception) {
            $existing = $DB->get_record("flexbook_user_state", [
                "flexbookid" => $flexbookid,
                "userid" => $userid,
            ]);
            if (!$existing) {
                throw $exception;
            }
            return $existing;
        }
    }

    /**
     * Gets or creates per-content progress safely under concurrent requests.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param stdClass $content Content record.
     * @param stdClass $chapter Chapter record.
     * @return stdClass
     */
    private function get_or_create_content_progress(
        int $flexbookid,
        int $userid,
        stdClass $content,
        stdClass $chapter
    ): stdClass {
        global $DB;

        $record = $DB->get_record("flexbook_user_progress", [
            "userid" => $userid,
            "contentid" => $content->id,
        ]);
        if ($record) {
            return $record;
        }

        $now = time();
        $record = (object) [
            "flexbookid" => $flexbookid,
            "chapterid" => $chapter->id,
            "contentid" => $content->id,
            "userid" => $userid,
            "status" => self::STATUS_NONE,
            "progress" => 0,
            "details" => null,
            "viewcount" => 0,
            "timeviewed" => 0,
            "firstaccess" => $now,
            "lastaccess" => $now,
            "timecompleted" => 0,
            "timemodified" => $now,
        ];
        try {
            $record->id = $DB->insert_record("flexbook_user_progress", $record);
            return $record;
        } catch (dml_write_exception $exception) {
            $existing = $DB->get_record("flexbook_user_progress", [
                "userid" => $userid,
                "contentid" => $content->id,
            ]);
            if (!$existing) {
                throw $exception;
            }
            return $existing;
        }
    }

    /**
     * Gets or creates chapter progress safely under concurrent requests.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @param int $chapterid Chapter ID.
     * @return stdClass
     */
    private function get_or_create_chapter_progress(int $flexbookid, int $userid, int $chapterid): stdClass {
        global $DB;

        $record = $DB->get_record("flexbook_chapter_progress", [
            "userid" => $userid,
            "chapterid" => $chapterid,
        ]);
        if ($record) {
            return $record;
        }

        $now = time();
        $record = (object) [
            "flexbookid" => $flexbookid,
            "chapterid" => $chapterid,
            "userid" => $userid,
            "status" => self::STATUS_NONE,
            "viewcount" => 0,
            "timeviewed" => 0,
            "firstaccess" => $now,
            "lastaccess" => $now,
            "timecompleted" => 0,
            "timemodified" => $now,
        ];
        try {
            $record->id = $DB->insert_record("flexbook_chapter_progress", $record);
            return $record;
        } catch (dml_write_exception $exception) {
            $existing = $DB->get_record("flexbook_chapter_progress", [
                "userid" => $userid,
                "chapterid" => $chapterid,
            ]);
            if (!$existing) {
                throw $exception;
            }
            return $existing;
        }
    }


}
