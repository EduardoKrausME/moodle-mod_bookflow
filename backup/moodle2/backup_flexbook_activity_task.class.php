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
 * backup_flexbook_activity_task.class.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . "/mod/flexbook/backup/moodle2/backup_flexbook_stepslib.php");

/**
 * Defines the backup task for a FlexBook activity.
 */
class backup_flexbook_activity_task extends backup_activity_task {
    /**
     * Defines activity-specific backup or restore settings.
     *
     * @return void
     */
    #[Override]
    protected function define_my_settings(): void {
    }

    /**
     * Defines activity-specific backup or restore steps.
     *
     * @return void
     */
    #[Override]
    protected function define_my_steps(): void {
        $this->add_step(new backup_flexbook_activity_structure_step("flexbook_structure", "flexbook.xml"));
    }

    /**
     * Encodes FlexBook links for backup portability.
     *
     * @param mixed $content Exported content.
     * @return string
     */
    #[Override]
    public static function encode_content_links($content): string {
        return preg_replace(
            "!((?:https?://[^/]+)?/mod/flexbook/view.php\?id=)([0-9]+)!",
            "$@FLEXBOOKVIEWBYID*$2@$",
            $content
        );
    }
}
