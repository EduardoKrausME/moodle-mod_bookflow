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
 * Shared base for content types backed by the Moodle HTML editor.
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
 * Generic HTML-editor content behaviour.
 */
abstract class editor_content extends template_content {
    /**
     * Gets the editor label.
     *
     * @return string
     */
    abstract protected static function get_editor_label(): string;

    /**
     * Adds the primary HTML editor.
     */
    public static function add_form_fields(
        content_form $form,
        array $editoroptions,
        array $fileoptions,
        int $repeatcount,
        int $structureddraftid
    ): void {
        $form->add_editor(static::get_editor_label(), $editoroptions);
    }

    /**
     * Validates the primary editor.
     */
    public static function validate_form(array $data, array $files): array {
        $editor = (array) ($data["data1_editor"] ?? []);
        if (trim((string) ($editor["text"] ?? "")) === "") {
            return ["data1_editor" => get_string("contentrequiredfield", "mod_bookflow")];
        }
        return [];
    }

    /**
     * Prepares the editor draft.
     */
    public static function prepare_form_data(
        stdClass $record,
        context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        return content_form_mapper::prepare_editor_data($record, $context, $editoroptions);
    }

    /**
     * Converts editor fields to generic storage.
     */
    public static function to_record(stdClass $data): stdClass {
        content_form_mapper::reset_storage_fields($data);
        content_form_mapper::unset_fields($data, ["data1_editor"]);
        return $data;
    }

    /**
     * Saves editor draft files and text.
     */
    public static function save_draft_data1(
        stdClass $submitted,
        int $itemid,
        context_module $context,
        array $editoroptions,
        array $fileoptions
    ): ?string {
        return content_form_mapper::save_editor_data1(
            $submitted,
            $itemid,
            $context,
            $editoroptions
        );
    }

    /**
     * Gets file areas owned by editor content.
     */
    public static function get_fileareas(): array {
        return ["content"];
    }
}
