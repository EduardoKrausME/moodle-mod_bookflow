<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * markdown.php
 *
 * @package   flexbookcontent_markdown
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace flexbookcontent_markdown\types;

use mod_flexbook\types\raw_content;
use renderer_base;

/**
 * FlexBook Markdown content type.
 */
class markdown extends raw_content {
    /** @var string */
    protected static string $type = "markdown";

    /** @var int */
    protected static int $contentformat = FORMAT_MARKDOWN;

    /**
     * Gets the raw textarea label.
     */
    protected static function get_raw_label(): string {
        return get_string("markdowncontent", "mod_flexbook");
    }

    /**
     * Renders this content block.
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "flexbookcontent_markdown/markdown",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "flexbookcontent_markdown");
    }

}
