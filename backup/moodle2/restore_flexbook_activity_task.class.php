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
 * restore_flexbook_activity_task.class.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . "/mod/flexbook/backup/moodle2/restore_flexbook_stepslib.php");

/**
 * Defines the restore task for a FlexBook activity.
 */
class restore_flexbook_activity_task extends restore_activity_task {
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
        $this->add_step(new restore_flexbook_activity_structure_step("flexbook_structure", "flexbook.xml"));
    }

    /**
     * Defines content fields that contain encoded links.
     *
     * @return array
     */
    #[Override]
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content("flexbook", ["intro"], "flexbook"),
            new restore_decode_content("flexbook_chapters", ["description"], "flexbook_chapter"),
            new restore_decode_content("flexbook_contents", ["data1", "data2", "data3"], "flexbook_content"),
        ];
    }

    /**
     * Defines URL decoding rules for restored content.
     *
     * @return array
     */
    #[Override]
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule("FLEXBOOKVIEWBYID", "/mod/flexbook/view.php?id=$1", "course_module"),
        ];
    }
}
