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
 * template_content.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\types;

use context_module;
use renderer_base;
use stdClass;

/**
 * Provides template-based rendering for built-in content types.
 */
abstract class template_content extends content {
    /** @var string */
    protected static string $type = "";

    /**
     * Gets the unique content type identifier.
     *
     * @return string
     */
    public static function get_type(): string {
        return static::$type;
    }

    /**
     * Gets the localized content type name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string("contenttype" . static::$type, "mod_flexbook");
    }

    /**
     * Checks whether the content type is safe for standard editing.
     *
     * @return bool
     */
    public static function is_safe(): bool {
        return static::$type != "code";
    }

    /**
     * Checks whether the current user can create the content type.
     *
     * @param chapter|null $chapter Chapter wrapper.
     * @param stdClass $flexbook FlexBook record.
     * @param context_module $context Module context.
     * @return bool
     */
    public static function can_create(
        ?chapter $chapter,
        stdClass $flexbook,
        context_module $context
    ): bool {
        if (!has_capability("mod/flexbook:managecontent", $context)) {
            return false;
        }
        return static::is_safe() || has_capability("mod/flexbook:editunsafecontent", $context);
    }

    /**
     * Renders the content block with its Mustache template.
     *
     * @param renderer_base $output Moodle renderer used to render the Mustache template.
     * @param bool $editing Whether editing controls are enabled.
     * @return string
     */
    public function render(renderer_base $output, bool $editing): string {
        $data = $this->export_data($editing);
        return $output->render_from_template("mod_flexbook/content/" . static::$type, $data);
    }

    /**
     * Builds the data passed to the content Mustache template.
     *
     * @param bool $editing Whether editing controls are enabled.
     * @return array
     */
    protected function export_data(bool $editing): array {
        $format = static::$type == "markdown" ? FORMAT_MARKDOWN : FORMAT_HTML;
        $items = json_decode($this->record->data1 ?? "[]", true);
        if (!is_array($items)) {
            $items = [];
        }
        $normalizeditems = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalizeditems[] = [
                "index" => $index,
                "title" => s($item["title"] ?? ""),
                "content" => format_text($item["content"] ?? "", FORMAT_HTML, [
                    "context" => $this->context,
                ]),
                "front" => s($item["front"] ?? ""),
                "back" => s($item["back"] ?? ""),
                "value" => s($item["value"] ?? $index),
                "first" => $index == 0,
            ];
        }
        return [
            "id" => $this->record->id,
            "title" => format_string($this->record->title ?? ""),
            "content" => format_text($this->record->data1 ?? "", $format, [
                "context" => $this->context,
                "filter" => true,
                "noclean" => !static::is_safe() && has_capability("mod/flexbook:editunsafecontent", $this->context),
            ]),
            "data2" => s($this->record->data2 ?? ""),
            "data3" => s($this->record->data3 ?? ""),
            "source" => clean_param($this->record->data1 ?? "", PARAM_URL),
            "code" => $this->record->data1 ?? "",
            "items" => $normalizeditems,
            "hasitems" => !empty($normalizeditems),
            "editing" => $editing,
            "tracked" => $this->record->trackprogress,
            "required" => $this->record->required,
            "completiontype" => $this->record->completiontype,
            "completionvalue" => $this->record->completionvalue,
            "weight" => $this->record->weight,
            "auxint1" => $this->record->auxint1,
        ];
    }
}
