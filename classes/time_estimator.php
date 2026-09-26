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
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

/**
 * Calculates the estimated study time for a FlexBook.
 */
class time_estimator {
    /**
     * Calculates the estimated study time for a FlexBook.
     *
     * @param int $flexbookid FlexBook ID.
     * @return int
     */
    public static function for_flexbook(int $flexbookid): int {
        global $DB;

        $flexbook = $DB->get_record("flexbook", ["id" => $flexbookid], "*", MUST_EXIST);
        if ($flexbook->estimatedtime > 0) {
            return $flexbook->estimatedtime;
        }
        $sql = "SELECT c.*
                  FROM {flexbook_contents} c
                  JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                 WHERE ch.flexbookid = :flexbookid
                   AND ch.hidden = 0
                   AND c.hidden = 0";
        $seconds = 0;
        foreach ($DB->get_records_sql($sql, ["flexbookid" => $flexbookid]) as $content) {
            if ($content->estimatedtime > 0) {
                $seconds += $content->estimatedtime;
                continue;
            }
            if (in_array($content->type, ["video", "audio"]) && $content->auxint2 > 0) {
                $seconds += $content->auxint2;
                continue;
            }
            if ($content->type == "question") {
                $seconds += 60;
                continue;
            }
            $words = count(preg_split("/\s+/u", trim(html_to_text($content->data1 ?? "", 0, false))) ?: []);
            $seconds += ceil($words / 200 * 60);
        }
        return $seconds;
    }
}
