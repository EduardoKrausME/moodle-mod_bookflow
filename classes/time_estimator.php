<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * time_estimator.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus
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
            return (int) $flexbook->estimatedtime;
        }

        $cm = get_coursemodule_from_instance("flexbook", $flexbookid, $flexbook->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $sql = "SELECT c.*
                  FROM {flexbook_contents} c
                  JOIN {flexbook_chapters} ch ON ch.id = c.chapterid
                 WHERE ch.flexbookid = :flexbookid
                   AND ch.hidden = 0
                   AND c.hidden = 0";

        $seconds = 0;
        foreach ($DB->get_records_sql($sql, ["flexbookid" => $flexbookid]) as $record) {
            $content = content_type_manager::create_content($record, $flexbook, $context);
            $seconds += $content->estimate_time();
        }
        return $seconds;
    }
}
