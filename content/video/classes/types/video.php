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
     * Builds the video template context.
     *
     * Direct video files keep the native HTML5 player so playback progress can
     * still be tracked. Provider/page URLs are delegated to Moodle's media
     * manager, which supports enabled media players such as YouTube and Vimeo.
     *
     * @param bool $editing Whether editing controls are enabled.
     * @return array
     */
    protected function export_data(bool $editing): array {
        $data = parent::export_data($editing);
        $source = (string) ($data["source"] ?? "");

        $data["nativevideo"] = true;
        $data["embeddedvideo"] = false;
        $data["embedhtml"] = "";
        $data["captionsurl"] = clean_param((string) ($this->record->data2 ?? ""), PARAM_URL);

        if ($source === "" || self::is_direct_video_source($source)) {
            return $data;
        }

        try {
            $url = new \moodle_url($source);
            $manager = \core_media_manager::instance();
            $options = [
                \core_media_manager::OPTION_BLOCK => true,
            ];

            if ($manager->can_embed_url($url, $options)) {
                $embedhtml = $manager->embed_url(
                    $url,
                    format_string($this->record->title ?? ""),
                    1280,
                    720,
                    $options
                );
                if (trim($embedhtml) !== "") {
                    $data["nativevideo"] = false;
                    $data["embeddedvideo"] = true;
                    $data["embedhtml"] = $embedhtml;
                }
            }
        } catch (\Throwable $exception) {
            // Keep the native player as a safe fallback for unusual external URLs.
        }

        return $data;
    }

    /**
     * Checks whether the URL points directly to a browser-playable video file.
     *
     * @param string $source Media URL.
     * @return bool
     */
    private static function is_direct_video_source(string $source): bool {
        $path = (string) parse_url(html_entity_decode($source), PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, [
            "mp4",
            "m4v",
            "webm",
            "ogv",
            "ogg",
            "mov",
        ], true);
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
