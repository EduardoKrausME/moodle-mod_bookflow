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
 * Queues aggregate progress recalculation after structural changes.
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use core\task\manager;
use mod_flexbook\task\recalculate_progress;

/**
 * Schedules progress recalculation outside the teacher request.
 */
class progress_recalculator {
    /**
     * Queues recalculation for all users that already have state in a FlexBook.
     *
     * @param int $flexbookid FlexBook ID.
     * @return void
     */
    public static function recalculate_all(int $flexbookid): void {
        $task = new recalculate_progress();
        $task->set_custom_data([
            "flexbookid" => $flexbookid,
            "afteruserid" => 0,
        ]);
        manager::queue_adhoc_task($task, true);
    }
}
