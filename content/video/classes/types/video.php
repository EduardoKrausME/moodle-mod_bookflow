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
 * video.php
 *
 * @package   flexbookcontent_video
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace flexbookcontent_video\types;

use mod_flexbook\form\content_form;
use mod_flexbook\form\content_form_mapper;
use mod_flexbook\types\media_content;
use renderer_base;
use stdClass;

/**
 * FlexBook Video content type.
 */
class video extends media_content {
    /** @var string */
    protected static string $type = "video";

    /** @var array */
    protected static array $acceptedtypes = ["video"];

    /**
     * Adds video-specific fields.
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
        $mform->addElement("url", "captionsurl", get_string("captionsurl", "mod_flexbook"), ["size" => 64]);
        $mform->setType("captionsurl", PARAM_URL);
        $mform->addElement(
            "textarea",
            "transcript",
            get_string("transcript", "mod_flexbook"),
            ["rows" => 8, "cols" => 90]
        );
        $mform->setType("transcript", PARAM_RAW);
    }

    /**
     * Gets video completion rules.
     */
    public static function get_completion_options(): array {
        $options = parent::get_completion_options();
        $none = $options["none"];
        unset($options["none"]);
        $options["percent"] = get_string("completeonpercent", "mod_flexbook");
        $options["end"] = get_string("completeonend", "mod_flexbook");
        $options["none"] = $none;
        return $options;
    }

    /**
     * Prepares video-specific form data.
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
        $record->captionsurl = (string) ($record->data2 ?? "");
        $record->transcript = (string) ($record->data3 ?? "");
        return $record;
    }

    /**
     * Stores video-specific fields.
     */
    public static function to_record(stdClass $data): stdClass {
        $captionsurl = trim((string) ($data->captionsurl ?? ""));
        $transcript = trim((string) ($data->transcript ?? ""));
        $data = parent::to_record($data);
        $data->data2 = $captionsurl;
        $data->data3 = $transcript;
        content_form_mapper::unset_fields($data, ["captionsurl", "transcript"]);
        return $data;
    }

    /**
     * Loads video progress tracking from this subplugin.
     */
    public static function require_page_assets(stdClass $flexbook, bool $editing): void {
        global $PAGE;
        if (!$editing && !empty($flexbook->enabletracking)) {
            $PAGE->requires->js_call_amd(
                "flexbookcontent_video/video_progress",
                "init",
                [$flexbook->id]
            );
        }
    }

    /**
     * Renders this content block.
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "flexbookcontent_video/video",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "flexbookcontent_video");
    }

}
