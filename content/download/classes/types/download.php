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
 * download.php
 *
 * @package   flexbookcontent_download
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace flexbookcontent_download\types;

use mod_flexbook\form\content_form;
use mod_flexbook\form\content_form_mapper;
use mod_flexbook\types\source_content;
use renderer_base;
use stdClass;

/**
 * FlexBook Download content type.
 */
class download extends source_content {
    /** @var string */
    protected static string $type = "download";

    /** @var string */
    protected static string $filearea = "download";

    /**
     * Adds download-specific fields.
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
        $mform->addElement(
            "textarea",
            "filedescription",
            get_string("filedescription", "mod_flexbook"),
            ["rows" => 5, "cols" => 90]
        );
        $mform->setType("filedescription", PARAM_TEXT);
    }

    /**
     * Gets download completion rules.
     */
    public static function get_completion_options(): array {
        $options = parent::get_completion_options();
        $none = $options["none"];
        unset($options["none"]);
        $options["click"] = get_string("completeonclick", "mod_flexbook");
        $options["none"] = $none;
        return $options;
    }

    /**
     * Stores download click completion evidence.
     */
    public function update_completion_evidence(stdClass $progress, float $metric, array $details): stdClass {
        if ($this->record->completiontype === "click") {
            return $this->update_click_completion_evidence($progress, $details);
        }
        return parent::update_completion_evidence($progress, $metric, $details);
    }

    /**
     * Validates download click completion evidence.
     */
    public function completion_evidence_is_valid(stdClass $progress): bool {
        if ($this->record->completiontype === "click") {
            return $this->click_completion_evidence_is_valid($progress);
        }
        return parent::completion_evidence_is_valid($progress);
    }

    /**
     * Prepares download-specific form data.
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
        $record->filedescription = (string) ($record->data2 ?? "");
        return $record;
    }

    /**
     * Stores download-specific fields.
     */
    public static function to_record(stdClass $data): stdClass {
        $description = trim((string) ($data->filedescription ?? ""));
        $data = parent::to_record($data);
        $data->data2 = $description;
        content_form_mapper::unset_fields($data, ["filedescription"]);
        return $data;
    }

    /**
     * Forces the download file area to be served as an attachment.
     */
    public static function get_forcedownload_fileareas(): array {
        return [static::$filearea];
    }

    /**
     * Loads download tracking from this subplugin.
     */
    public static function require_page_assets(stdClass $flexbook, bool $editing): void {
        global $PAGE;
        if (!$editing && !empty($flexbook->enabletracking)) {
            $PAGE->requires->js_call_amd(
                "flexbookcontent_download/download",
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
            "flexbookcontent_download/download",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "flexbookcontent_download");
    }

}
