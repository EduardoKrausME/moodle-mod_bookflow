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
 * Provides shared template rendering without knowing concrete content types.
 */
abstract class template_content extends content {
    /** @var string Unique content type identifier. */
    protected static string $type = "";

    /** @var string Text format used for the primary stored content. */
    protected static string $contentformat = FORMAT_HTML;

    /** @var string File area used when rewriting primary content URLs. */
    protected static string $filearea = "content";

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
        return get_string("pluginname", "flexbookcontent_" . static::$type);
    }

    /**
     * Checks whether the content type is safe for standard editing.
     *
     * @return bool
     */
    public static function is_safe(): bool {
        return true;
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
     * Renders the content block with the template owned by the subplugin.
     *
     * @param renderer_base $output Moodle renderer.
     * @param bool $editing Whether editing controls are enabled.
     * @return string
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "flexbookcontent_" . static::$type . "/" . static::$type,
            $this->export_data($editing)
        );
    }

    /**
     * Builds data passed to the subplugin Mustache template.
     *
     * @param bool $editing Whether editing controls are enabled.
     * @return array
     */
    protected function export_data(bool $editing): array {
        $rewrittenprimarydata = $this->get_rewritten_primary_data();

        return [
            "id" => $this->record->id,
            "title" => format_string($this->record->title ?? ""),
            "content" => format_text($rewrittenprimarydata, static::$contentformat, [
                "context" => $this->context,
                "filter" => true,
                "noclean" => !static::is_safe() && has_capability(
                    "mod/flexbook:editunsafecontent",
                    $this->context
                ),
            ]),
            "data2" => s($this->record->data2 ?? ""),
            "data3" => s($this->record->data3 ?? ""),
            "editing" => $editing,
            "tracked" => $this->record->trackprogress,
            "required" => $this->record->required,
            "completiontype" => $this->record->completiontype,
            "completionvalue" => $this->record->completionvalue,
            "weight" => $this->record->weight,
            "auxint1" => $this->record->auxint1,
        ];
    }

    /**
     * Rewrites @@PLUGINFILE@@ URLs from the primary storage field.
     *
     * @return string
     */
    protected function get_rewritten_primary_data(): string {
        return file_rewrite_pluginfile_urls(
            $this->record->data1 ?? "",
            "pluginfile.php",
            $this->context->id,
            "mod_flexbook",
            static::$filearea,
            $this->record->id
        );
    }
}
