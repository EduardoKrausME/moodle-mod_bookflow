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
use invalid_parameter_exception;
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

        $now = time();
        $record = $DB->get_record("flexbook_user_progress", [
            "userid" => $userid,
            "contentid" => $contentid,
        ]);
        if (!$record) {
            $record = (object) [
                "flexbookid" => $flexbookid,
                "chapterid" => $chapter->id,
                "contentid" => $contentid,
                "userid" => $userid,
                "status" => self::STATUS_VIEWED,
                "progress" => 0,
                "details" => null,
                "viewcount" => 1,
                "timeviewed" => 0,
                "firstaccess" => $now,
                "lastaccess" => $now,
                "timecompleted" => 0,
                "timemodified" => $now,
            ];
            $record->id = $DB->insert_record("flexbook_user_progress", $record);
        } else {
            $record->viewcount++;
            $record->lastaccess = $now;
            $record->timemodified = $now;
            if ($record->status < self::STATUS_VIEWED) {
                $record->status = self::STATUS_VIEWED;
            }
            $DB->update_record("flexbook_user_progress", $record);
        }

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
        if (!$this->can_complete($content, $record)) {
            throw new moodle_exception("completionevidencemissing", "mod_flexbook");
        }

        $record->status = self::STATUS_COMPLETED;
        $record->progress = 100;
        $record->timecompleted = time();
        $record->timemodified = time();
        $DB->update_record("flexbook_user_progress", $record);

        $progress = $this->calculate_user_progress($flexbookid, $userid);
        $this->update_chapter_completion($flexbookid, $userid, $chapter->id);
        $this->update_completion($flexbookid, $userid);

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
     * @param float $progress Calculated progress percentage.
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

        $this->mark_content_viewed($flexbookid, $userid, $contentid);
        $record = $DB->get_record("flexbook_user_progress", [
            "userid" => $userid,
            "contentid" => $contentid,
        ], "*", MUST_EXIST);
        $record->progress = max($record->progress, min(100, max(0, $progress)));
        $existing = json_decode($record->details ?? "[]", true) ?: [];
        $record->details = json_encode(array_merge($existing, $details));
        $reportedseconds = max(
            $details["visibleSeconds"] ?? 0,
            $details["watchedSeconds"] ?? 0,
            $details["playedSeconds"] ?? 0
        );
        $record->timeviewed = max($record->timeviewed, min(86400, max(0, $reportedseconds)));
        $record->timemodified = time();
        $DB->update_record("flexbook_user_progress", $record);

        $content = $DB->get_record("flexbook_contents", ["id" => $contentid], "*", MUST_EXIST);
        if ($this->can_complete($content, $record)) {
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

        $sql = "SELECT c.id, c.weight, p.status
                  FROM {flexbook_contents} c
                  JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
             LEFT JOIN {flexbook_user_progress} p
                    ON p.contentid = c.id AND p.userid = :userid
                 WHERE ch.flexbookid = :flexbookid
                   AND ch.hidden = 0
                   AND c.hidden = 0
                   AND c.trackprogress = 1";
        $contents = $DB->get_records_sql($sql, ["flexbookid" => $flexbookid, "userid" => $userid]);

        $totalweight = 0.0;
        $completedweight = 0.0;
        foreach ($contents as $content) {
            $weight = max(0, $content->weight);
            $totalweight += $weight;
            if ($content->status == self::STATUS_COMPLETED) {
                $completedweight += $weight;
            }
        }

        $progress = $totalweight > 0 ? ($completedweight / $totalweight) * 100 : 0;
        $progress = round(min(100, max(0, $progress)), 2);
        $this->save_aggregate_progress($flexbookid, $userid, $progress);
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
        $record = $DB->get_record("flexbook_chapter_progress", [
            "userid" => $userid,
            "chapterid" => $chapterid,
        ]);
        $now = time();
        if ($record) {
            $record->viewcount++;
            $record->lastaccess = $now;
            $record->timemodified = $now;
            $DB->update_record("flexbook_chapter_progress", $record);
        } else {
            $DB->insert_record("flexbook_chapter_progress", (object) [
                "flexbookid" => $flexbookid,
                "chapterid" => $chapterid,
                "userid" => $userid,
                "status" => self::STATUS_VIEWED,
                "viewcount" => 1,
                "timeviewed" => 0,
                "firstaccess" => $now,
                "lastaccess" => $now,
                "timecompleted" => 0,
                "timemodified" => $now,
            ]);
        }
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
     * @return void
     */
    public function update_completion(int $flexbookid, int $userid): void {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance("flexbook", $flexbookid, $flexbook->course, false, MUST_EXIST);
        $completion = new completion_info(get_course($flexbook->course));
        if (!$completion->is_enabled($cm)) {
            return;
        }

        $complete = $this->completion_requirements_met($flexbookid, $userid);
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
     * @return bool
     */
    public function completion_requirements_met(int $flexbookid, int $userid): bool {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        $progress = $this->calculate_user_progress($flexbookid, $userid);
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
     * Recalculates chapters.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return void
     */
    public function recalculate_chapters(int $flexbookid, int $userid): void {
        global $DB;

        $chapterids = $DB->get_fieldset_select(
            "flexbook_chapters",
            "id",
            "flexbookid = ?",
            [$flexbookid]
        );
        foreach ($chapterids as $chapterid) {
            $this->update_chapter_completion($flexbookid, $userid, $chapterid);
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
     * Checks whether required chapters remain incomplete.
     *
     * @param int $flexbookid FlexBook ID.
     * @param int $userid User ID.
     * @return bool
     */
    private function has_pending_required_chapters(int $flexbookid, int $userid): bool {
        global $DB;

        $requiredchapters = $DB->get_records("flexbook_chapters", [
            "flexbookid" => $flexbookid,
            "required" => 1,
            "hidden" => 0,
        ]);
        foreach ($requiredchapters as $chapter) {
            $sql = "SELECT COUNT(c.id)
                      FROM {flexbook_contents} c
                 LEFT JOIN {flexbook_user_progress} p
                        ON p.contentid = c.id AND p.userid = :userid
                     WHERE c.chapterid = :chapterid
                       AND c.hidden = 0
                       AND c.trackprogress = 1
                       AND (p.status IS NULL OR p.status < :completed)";
            if ($DB->count_records_sql($sql, [
                    "userid" => $userid,
                    "chapterid" => $chapter->id,
                    "completed" => self::STATUS_COMPLETED,
                ]) > 0) {
                return true;
            }
        }
        return false;
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

        $total = $DB->count_records("flexbook_contents", [
            "chapterid" => $chapterid,
            "hidden" => 0,
            "trackprogress" => 1,
        ]);
        $record = $DB->get_record("flexbook_chapter_progress", [
            "userid" => $userid,
            "chapterid" => $chapterid,
        ]);
        if (!$total) {
            if ($record && $record->status == self::STATUS_COMPLETED) {
                $record->status = self::STATUS_VIEWED;
                $record->timecompleted = 0;
                $record->timemodified = time();
                $DB->update_record("flexbook_chapter_progress", $record);
            }
            return;
        }
        $completed = $DB->count_records("flexbook_user_progress", [
            "chapterid" => $chapterid,
            "userid" => $userid,
            "status" => self::STATUS_COMPLETED,
        ]);
        if ($completed < $total) {
            if ($record && $record->status == self::STATUS_COMPLETED) {
                $record->status = self::STATUS_VIEWED;
                $record->timecompleted = 0;
                $record->timemodified = time();
                $DB->update_record("flexbook_chapter_progress", $record);
            }
            return;
        }
        if (!$record) {
            $this->mark_chapter_viewed($flexbookid, $userid, $chapterid);
            $record = $DB->get_record("flexbook_chapter_progress", [
                "userid" => $userid,
                "chapterid" => $chapterid,
            ], "*", MUST_EXIST);
        }
        if ($record->status == self::STATUS_COMPLETED) {
            return;
        }
        $record->status = self::STATUS_COMPLETED;
        $record->timecompleted = time();
        $record->timeviewed = $DB->get_field_sql(
            "SELECT COALESCE(SUM(timeviewed), 0)
               FROM {flexbook_user_progress}
              WHERE chapterid = ? AND userid = ?",
            [$chapterid, $userid]
        );
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
     * @param stdClass $content Exported content.
     * @param stdClass $progress Calculated progress percentage.
     * @return bool
     */
    private function can_complete(stdClass $content, stdClass $progress): bool {
        $completiontype = $content->completiontype;
        if (in_array($completiontype, ["view", "open", "manual", "click"])) {
            return true;
        }
        if (in_array($completiontype, ["answer", "attempt", "correct"])) {
            return $this->has_question_evidence(
                $content->id,
                $progress->userid,
                $completiontype == "correct"
            );
        }
        if ($completiontype == "timed") {
            return time() - $progress->firstaccess >= $content->completionvalue;
        }
        if (in_array($completiontype, ["percent", "end"])) {
            $required = $completiontype == "end" ? 100 : $content->completionvalue;
            return $progress->progress >= $required;
        }
        if (in_array($completiontype, ["allitems", "alltabs", "allcards"])) {
            $details = json_decode($progress->details ?? "[]", true) ?: [];
            return count(array_unique($details["visited"] ?? [])) >= $content->auxint1;
        }
        return false;
    }

    /**
     * Checks whether a question attempt provides valid completion evidence.
     *
     * @param int $contentid Content block ID.
     * @param int $userid User ID.
     * @param bool $mustbecorrect Whether the recorded answer must be correct.
     * @return bool
     */
    private function has_question_evidence(int $contentid, int $userid, bool $mustbecorrect): bool {
        global $DB;

        $sql = "SELECT COUNT(a.id)
                  FROM {flexbook_question_attempts} a
                  JOIN {flexbook_questions} q ON q.id = a.questionid
                 WHERE q.contentid = :contentid
                   AND a.userid = :userid";
        $params = ["contentid" => $contentid, "userid" => $userid];
        if ($mustbecorrect) {
            $sql .= " AND a.iscorrect = 1";
        }
        return $DB->count_records_sql($sql, $params) > 0;
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
     * @return void
     */
    private function save_aggregate_progress(int $flexbookid, int $userid, float $progress): void {
        global $DB;

        $state = $this->get_or_create_state($flexbookid, $userid);
        $state->progress = $progress;
        $state->timeviewed = $DB->get_field_sql(
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
        $state->id = $DB->insert_record("flexbook_user_state", $state);
        return $state;
    }
}
