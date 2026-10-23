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
 * course_module_viewed.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow\event;

use moodle_url;
use Override;

/**
 * Standard activity viewed event consumed by Moodle logs and reports.
 */
class course_module_viewed extends \core\event\course_module_viewed {
    /**
     * Initializes Moodle's standard read event metadata.
     *
     * @return void
     */
    #[Override]
    protected function init(): void {
        $this->data["objecttable"] = "bookflow";
        $this->data["crud"] = "r";
        $this->data["edulevel"] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns the direct activity link for the event.
     *
     * @return moodle_url
     */
    #[Override]
    public function get_url(): moodle_url {
        return new moodle_url("/mod/bookflow/view.php", ["id" => $this->contextinstanceid]);
    }

    /**
     * Declares the object ID mapping for backup and restore.
     *
     * @return array
     */
    #[Override]
    public static function get_objectid_mapping(): array {
        return ["db" => "bookflow", "restore" => "bookflow"];
    }
}
