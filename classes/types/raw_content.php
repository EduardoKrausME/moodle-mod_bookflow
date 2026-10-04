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
 * Shared base for raw textarea content.
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
 * Generic raw text content behaviour.
 */
abstract class raw_content extends template_content {
    /**
     * Gets the raw textarea label.
     *
     * @return string
     */
    abstract protected static function get_raw_label(): string;

    /**
     * Gets textarea attributes.
     *
     * @return array
     */
    protected static function get_raw_attributes(): array {
        return ["rows" => 18, "cols" => 90];
    }

    /**
     * Adds raw textarea field.
     */
    public static function add_form_fields(
        content_form $form,
        array $editoroptions,
        array $fileoptions,
        int $repeatcount,
        int $structureddraftid
    ): void {
        $mform = $form->get_mform();
        $mform->addElement(
            "textarea",
            "rawcontent",
            static::get_raw_label(),
            static::get_raw_attributes()
        );
        $mform->setType("rawcontent", PARAM_RAW);
        $mform->addRule("rawcontent", null, "required", null, "client");
    }

    /**
     * Validates raw content.
     */
    public static function validate_form(array $data, array $files): array {
        if (trim((string) ($data["rawcontent"] ?? "")) === "") {
            return ["rawcontent" => get_string("contentrequiredfield", "mod_bookflow")];
        }
        return [];
    }

    /**
     * Prepares raw content.
     */
    public static function prepare_form_data(
        stdClass $record,
        context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        $record->rawcontent = (string) ($record->data1 ?? "");
        return $record;
    }

    /**
     * Converts raw content to storage.
     */
    public static function to_record(stdClass $data): stdClass {
        content_form_mapper::reset_storage_fields($data);
        $data->data1 = (string) ($data->rawcontent ?? "");
        content_form_mapper::unset_fields($data, ["rawcontent"]);
        return $data;
    }
}
