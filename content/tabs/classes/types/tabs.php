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
 * tabs.php
 *
 * @package   flexbookcontent_tabs
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace flexbookcontent_tabs\types;

use mod_flexbook\types\structured_content;
use renderer_base;
use stdClass;

/**
 * FlexBook Tabs content type.
 */
class tabs extends structured_content {
    /** @var string */
    protected static string $type = "tabs";

    /** @var string */
    protected static string $collectioncompletiontype = "alltabs";

    /**
     * Gets tabs completion rules.
     */
    public static function get_completion_options(): array {
        $options = parent::get_completion_options();
        $none = $options["none"];
        unset($options["none"]);
        $options["alltabs"] = get_string("completealltabs", "mod_flexbook");
        $options["none"] = $none;
        return $options;
    }

    /**
     * Loads tabs behaviour from this subplugin.
     */
    public static function require_page_assets(stdClass $flexbook, bool $editing): void {
        global $PAGE;
        if (!$editing) {
            $PAGE->requires->js_call_amd(
                "flexbookcontent_tabs/tabs",
                "init",
                [$flexbook->id, !empty($flexbook->enabletracking)]
            );
        }
    }

    /**
     * Renders this content block.
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "flexbookcontent_tabs/tabs",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "flexbookcontent_tabs");
    }

}
