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
 * callout.php
 *
 * @package   bookflowcontent_callout
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookflowcontent_callout\types;

use mod_bookflow\types\editor_content;
use renderer_base;

/**
 * BookFlow Callout content type.
 */
class callout extends editor_content {
    /** @var string */
    protected static string $type = "callout";

    /**
     * Gets the editor label.
     */
    protected static function get_editor_label(): string {
        return get_string("calloutcontent", "mod_bookflow");
    }

    /**
     * Renders this content block.
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "bookflowcontent_callout/callout",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "bookflowcontent_callout");
    }

}
