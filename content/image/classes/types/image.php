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
 * image.php
 *
 * @package   bookflowcontent_image
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookflowcontent_image\types;

use mod_bookflow\form\content_form;
use mod_bookflow\form\content_form_mapper;
use mod_bookflow\types\source_content;
use renderer_base;
use stdClass;

/**
 * BookFlow Image content type.
 */
class image extends source_content {
    /** @var string */
    protected static string $type = "image";

    /** @var array */
    protected static array $acceptedtypes = ["image"];

    /**
     * Adds image-specific fields.
     */
    public static function add_form_fields(
        content_form $form,
        array $editoroptions,
        array $fileoptions,
        int $repeatcount,
        int $structureddraftid
    ): void {
        parent::add_form_fields($form, $editoroptions, $fileoptions, $repeatcount, $structureddraftid);
        $mform = $form->get_mform();
        $mform->addElement("text", "imagealt", get_string("imagealt", "mod_bookflow"), ["size" => 64]);
        $mform->setType("imagealt", PARAM_TEXT);
        $mform->addElement("text", "caption", get_string("caption", "mod_bookflow"), ["size" => 64]);
        $mform->setType("caption", PARAM_TEXT);
    }

    /**
     * Prepares image-specific form data.
     */
    public static function prepare_form_data(
        stdClass $record,
        \context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        $record = parent::prepare_form_data(
            $record,
            $context,
            $editoroptions,
            $fileoptions,
            $structureddraftid
        );
        $record->imagealt = (string) ($record->data2 ?? "");
        $record->caption = (string) ($record->data3 ?? "");
        return $record;
    }

    /**
     * Stores image-specific fields.
     */
    public static function to_record(stdClass $data): stdClass {
        $imagealt = trim((string) ($data->imagealt ?? ""));
        $caption = trim((string) ($data->caption ?? ""));
        $data = parent::to_record($data);
        $data->data2 = $imagealt;
        $data->data3 = $caption;
        content_form_mapper::unset_fields($data, ["imagealt", "caption"]);
        return $data;
    }

    /**
     * Renders this content block.
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "bookflowcontent_image/image",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "bookflowcontent_image");
    }

}
