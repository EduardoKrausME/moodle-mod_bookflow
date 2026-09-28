<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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
