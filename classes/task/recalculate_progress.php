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
 * Recalculates BookFlow progress in bounded batches.
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow\task;

use core\task\adhoc_task;
use core\task\manager;
use mod_bookflow\progress\progress_manager;

/**
 * Recalculates derived progress after content structure changes.
 */
class recalculate_progress extends adhoc_task {
    /** @var int Number of users processed by one task execution. */
    private const BATCH_SIZE = 200;

    /**
     * Executes one bounded recalculation batch.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $bookflowid = (int) ($data->bookflowid ?? 0);
        $afteruserid = (int) ($data->afteruserid ?? 0);

        if (!$bookflowid || !$DB->record_exists("bookflow", ["id" => $bookflowid])) {
            return;
        }

        $userids = $DB->get_fieldset_sql(
            "SELECT userid
               FROM {bookflow_user_state}
              WHERE bookflowid = :bookflowid
                AND userid > :afteruserid
           ORDER BY userid",
            [
                "bookflowid" => $bookflowid,
                "afteruserid" => $afteruserid,
            ],
            0,
            self::BATCH_SIZE
        );

        $progressmanager = new progress_manager();
        foreach ($userids as $userid) {
            $progressmanager->recalculate_user($bookflowid, (int) $userid);
        }

        if (count($userids) === self::BATCH_SIZE) {
            $next = new self();
            $next->set_custom_data([
                "bookflowid" => $bookflowid,
                "afteruserid" => (int) end($userids),
            ]);
            manager::queue_adhoc_task($next, true);
        }
    }
}
