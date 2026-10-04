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
 * time_estimator.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow;

/**
 * Calculates the estimated study time for a BookFlow.
 */
class time_estimator {
    /**
     * Calculates the estimated study time for a BookFlow.
     *
     * @param int $bookflowid BookFlow ID.
     * @return int
     */
    public static function for_bookflow(int $bookflowid): int {
        global $DB;

        $bookflow = $DB->get_record("bookflow", ["id" => $bookflowid], "*", MUST_EXIST);
        if ($bookflow->estimatedtime > 0) {
            return (int) $bookflow->estimatedtime;
        }

        $cm = get_coursemodule_from_instance("bookflow", $bookflowid, $bookflow->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $sql = "SELECT c.*
                  FROM {bookflow_contents} c
                  JOIN {bookflow_chapters} ch ON ch.id = c.chapterid
                 WHERE ch.bookflowid = :bookflowid
                   AND ch.hidden = 0
                   AND c.hidden = 0";

        $seconds = 0;
        foreach ($DB->get_records_sql($sql, ["bookflowid" => $bookflowid]) as $record) {
            $content = content_type_manager::create_content($record, $bookflow, $context);
            $seconds += $content->estimate_time();
        }
        return $seconds;
    }
}
