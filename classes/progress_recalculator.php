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
 * progress_recalculator.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use mod_flexbook\progress\progress_manager;

/**
 * Recalculates progress after structural content changes.
 */
class progress_recalculator {
    /**
     * Recalculates aggregate progress for all users in a FlexBook.
     *
     * @param int $flexbookid FlexBook ID.
     * @return void
     */
    public static function recalculate_all(int $flexbookid): void {
        global $DB;

        $userids = $DB->get_fieldset_select("flexbook_user_state", "userid", "flexbookid = ?", [$flexbookid]);
        $manager = new progress_manager();
        foreach ($userids as $userid) {
            $manager->calculate_user_progress($flexbookid, $userid);
            $manager->recalculate_chapters($flexbookid, $userid);
            $manager->update_completion($flexbookid, $userid);
        }
    }
}
