<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * html.php
 *
 * @package   flexbookcontent_html
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace flexbookcontent_html\types;

use mod_flexbook\types\editor_content;
use renderer_base;

/**
 * FlexBook Html content type.
 */
class html extends editor_content {
    /** @var string */
    protected static string $type = "html";

    /**
     * Gets the editor label.
     */
    protected static function get_editor_label(): string {
        return get_string("contenthtml", "mod_flexbook");
    }

    /**
     * Renders this content block.
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "flexbookcontent_html/html",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "flexbookcontent_html");
    }

}
