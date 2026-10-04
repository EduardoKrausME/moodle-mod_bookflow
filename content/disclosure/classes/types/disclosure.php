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
 * disclosure.php
 *
 * @package   bookflowcontent_disclosure
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookflowcontent_disclosure\types;

use mod_bookflow\types\editor_content;
use renderer_base;
use stdClass;

/**
 * BookFlow Disclosure content type.
 */
class disclosure extends editor_content {
    /** @var string */
    protected static string $type = "disclosure";

    /**
     * Gets the editor label.
     */
    protected static function get_editor_label(): string {
        return get_string("disclosurecontent", "mod_bookflow");
    }

    /**
     * Gets completion rules supported by disclosure content.
     */
    public static function get_completion_options(): array {
        $options = parent::get_completion_options();
        $none = $options["none"];
        unset($options["none"]);
        $options["click"] = get_string("completeonclick", "mod_bookflow");
        $options["none"] = $none;
        return $options;
    }

    /**
     * Stores disclosure click completion evidence.
     */
    public function update_completion_evidence(stdClass $progress, float $metric, array $details): stdClass {
        if ($this->record->completiontype === "click") {
            return $this->update_click_completion_evidence($progress, $details);
        }
        return parent::update_completion_evidence($progress, $metric, $details);
    }

    /**
     * Validates disclosure click completion evidence.
     */
    public function completion_evidence_is_valid(stdClass $progress): bool {
        if ($this->record->completiontype === "click") {
            return $this->click_completion_evidence_is_valid($progress);
        }
        return parent::completion_evidence_is_valid($progress);
    }

    /**
     * Stores disclosure-specific derived fields.
     */
    public static function to_record(stdClass $data): stdClass {
        $data = parent::to_record($data);
        $data->auxint1 = 1;
        return $data;
    }

    /**
     * Loads disclosure interaction owned by this subplugin.
     */
    public static function require_page_assets(stdClass $bookflow, bool $editing): void {
        global $PAGE;
        if (!$editing) {
            $PAGE->requires->js_call_amd(
                "bookflowcontent_disclosure/disclosure",
                "init",
                [$bookflow->id, !empty($bookflow->enabletracking)]
            );
        }
    }

    /**
     * Renders this content block.
     */
    public function render(renderer_base $output, bool $editing): string {
        return $output->render_from_template(
            "bookflowcontent_disclosure/disclosure",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "bookflowcontent_disclosure");
    }

}
