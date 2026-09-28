<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * code.php
 *
 * @package   flexbookcontent_code
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace flexbookcontent_code\types;

use mod_flexbook\types\raw_content;
use renderer_base;

/**
 * FlexBook Code content type.
 */
class code extends raw_content {
    /** @var string */
    protected static string $type = "code";

    /**
     * Gets the raw textarea label.
     */
    protected static function get_raw_label(): string {
        return get_string("codecontent", "mod_flexbook");
    }

    /**
     * Gets raw textarea attributes.
     */
    protected static function get_raw_attributes(): array {
        return ["rows" => 18, "cols" => 90, "class" => "font-monospace"];
    }

    /**
     * Adds raw code to the template context without HTML formatting.
     */
    protected function export_data(bool $editing): array {
        $data = parent::export_data($editing);
        $data["code"] = (string) ($this->record->data1 ?? "");
        return $data;
    }

    /**
     * Code content can contain unsafe markup and requires the dedicated capability.
     */
    public static function is_safe(): bool {
        return false;
    }

    /**
     * Renders this content block.
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "flexbookcontent_code/code",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "flexbookcontent_code");
    }

}
