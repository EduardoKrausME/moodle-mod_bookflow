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
 * Shared base for content types backed by one uploaded file or external URL.
 *
 * @package mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow\types;

use context_module;
use mod_bookflow\form\content_form;
use mod_bookflow\form\content_form_mapper;
use stdClass;

/**
 * Generic source-backed content behaviour.
 */
abstract class source_content extends template_content {
    /** @var array Accepted Moodle file types. */
    protected static array $acceptedtypes = ["*"];

    /**
     * Adds source file and URL fields.
     */
    public static function add_form_fields(
        content_form $form,
        array $editoroptions,
        array $fileoptions,
        int $repeatcount,
        int $structureddraftid
    ): void {
        $form->add_source_fields($fileoptions);
    }

    /**
     * Gets file manager options.
     */
    public static function get_file_options(array $baseoptions): array {
        return content_form_mapper::build_file_options($baseoptions, static::$acceptedtypes);
    }

    /**
     * Validates that either a file or URL was provided.
     */
    public static function validate_form(array $data, array $files): array {
        if (!content_form_mapper::has_source($data)) {
            return ["sourcefile" => get_string("sourcerequired", "mod_bookflow")];
        }
        return [];
    }

    /**
     * Prepares source fields.
     */
    public static function prepare_form_data(
        stdClass $record,
        context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        return content_form_mapper::prepare_source_data(
            $record,
            $context,
            $fileoptions,
            static::$filearea
        );
    }

    /**
     * Converts source fields to generic storage.
     */
    public static function to_record(stdClass $data): stdClass {
        content_form_mapper::reset_storage_fields($data);
        $data->data1 = trim((string) ($data->sourceurl ?? ""));
        content_form_mapper::unset_fields($data, ["sourcefile", "sourceurl"]);
        return $data;
    }

    /**
     * Saves an uploaded source file.
     */
    public static function save_draft_data1(
        stdClass $submitted,
        int $itemid,
        context_module $context,
        array $editoroptions,
        array $fileoptions
    ): ?string {
        return content_form_mapper::save_source_data1(
            $submitted,
            $itemid,
            $context,
            $fileoptions,
            static::$filearea
        );
    }

    /**
     * Exports source-backed content as a Markdown link.
     */
    public function export_markdown(): string {
        $source = clean_param($this->get_rewritten_primary_data(), PARAM_URL);
        $label = static::get_name();
        $markdown = "[{$label}]({$source})\n\n";
        if (!empty($this->record->data2)) {
            $markdown .= trim((string) $this->record->data2) . "\n\n";
        }
        return $markdown;
    }

    /**
     * Adds the normalized source URL to the template context.
     */
    protected function export_data(bool $editing): array {
        $data = parent::export_data($editing);
        $data["source"] = clean_param($this->get_rewritten_primary_data(), PARAM_URL);
        return $data;
    }

    /**
     * Gets file areas owned by source content.
     */
    public static function get_fileareas(): array {
        return [static::$filearea];
    }
}
