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
 * flashcards.php
 *
 * @package   bookflowcontent_flashcards
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookflowcontent_flashcards\types;

use context_module;
use mod_bookflow\form\content_form;
use mod_bookflow\form\content_form_mapper;
use mod_bookflow\types\template_content;
use renderer_base;
use stdClass;

/**
 * BookFlow Flashcards content type.
 */
class flashcards extends template_content {
    /** @var string */
    protected static string $type = "flashcards";

    /** @var string */
    protected static string $collectioncompletiontype = "allcards";

    /**
     * Adds flashcard fields.
     */
    public static function add_form_fields(
        content_form $form,
        array $editoroptions,
        array $fileoptions,
        int $repeatcount,
        int $structureddraftid
    ): void {
        $mform = $form->get_mform();
        $repeat = [
            $mform->createElement(
                "textarea",
                "cardfront",
                get_string("cardfront", "mod_bookflow"),
                ["rows" => 4, "cols" => 80]
            ),
            $mform->createElement(
                "textarea",
                "cardback",
                get_string("cardback", "mod_bookflow"),
                ["rows" => 4, "cols" => 80]
            ),
        ];
        $options = [
            "cardfront" => ["type" => PARAM_RAW],
            "cardback" => ["type" => PARAM_RAW],
        ];
        $form->repeat_content_elements(
            $repeat,
            $repeatcount,
            $options,
            "card_repeats",
            "card_add_fields",
            get_string("addcard", "mod_bookflow")
        );
    }

    /**
     * Gets flashcard completion rules.
     */
    public static function get_completion_options(): array {
        $options = parent::get_completion_options();
        $none = $options["none"];
        unset($options["none"]);
        $options["allcards"] = get_string("completeallcards", "mod_bookflow");
        $options["none"] = $none;
        return $options;
    }

    /**
     * Validates flashcard fields.
     */
    public static function validate_form(array $data, array $files): array {
        $count = content_form_mapper::count_nonempty_pairs(
            (array) ($data["cardfront"] ?? []),
            (array) ($data["cardback"] ?? [])
        );
        if ($count === 0) {
            return ["cardfront[0]" => get_string("flashcardrequired", "mod_bookflow")];
        }
        return [];
    }

    /**
     * Gets initial card count.
     */
    public static function get_repeat_count(?stdClass $content): int {
        if (!$content) {
            return 2;
        }
        return max(2, count(content_form_mapper::decode_items($content->data1 ?? "[]")));
    }

    /**
     * Prepares flashcard form data.
     */
    public static function prepare_form_data(
        stdClass $record,
        context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        $items = content_form_mapper::decode_items($record->data1 ?? "[]");
        $record->cardfront = array_column($items, "front");
        $record->cardback = array_column($items, "back");
        return $record;
    }

    /**
     * Stores flashcard data.
     */
    public static function to_record(stdClass $data): stdClass {
        content_form_mapper::reset_storage_fields($data);

        $items = [];
        $fronts = (array) ($data->cardfront ?? []);
        $backs = (array) ($data->cardback ?? []);
        $count = max(count($fronts), count($backs));
        for ($index = 0; $index < $count; $index++) {
            $front = trim((string) ($fronts[$index] ?? ""));
            $back = trim((string) ($backs[$index] ?? ""));
            if ($front === "" && $back === "") {
                continue;
            }
            $items[] = ["front" => $front, "back" => $back];
        }

        $data->data1 = content_form_mapper::encode_items($items);
        $data->auxint1 = count($items);
        content_form_mapper::unset_fields($data, [
            "cardfront",
            "cardback",
            "card_repeats",
            "card_add_fields",
        ]);
        return $data;
    }

    /**
     * Adds flashcard items to the template context.
     */
    protected function export_data(bool $editing): array {
        $data = parent::export_data($editing);
        $items = content_form_mapper::decode_items($this->record->data1 ?? "[]");
        $normalizeditems = [];
        foreach ($items as $index => $item) {
            $normalizeditems[] = [
                "index" => $index,
                "front" => s($item["front"] ?? ""),
                "back" => s($item["back"] ?? ""),
            ];
        }
        $data["items"] = $normalizeditems;
        $data["hasitems"] = !empty($normalizeditems);
        return $data;
    }

    /**
     * Updates flashcard completion evidence.
     */
    public function update_completion_evidence(stdClass $progress, float $metric, array $details): stdClass {
        if ($this->record->completiontype === static::$collectioncompletiontype) {
            return $this->update_collection_completion_evidence($progress, $details);
        }
        return parent::update_completion_evidence($progress, $metric, $details);
    }

    /**
     * Validates flashcard completion evidence.
     */
    public function completion_evidence_is_valid(stdClass $progress): bool {
        if ($this->record->completiontype === static::$collectioncompletiontype) {
            return $this->collection_completion_evidence_is_valid($progress);
        }
        return parent::completion_evidence_is_valid($progress);
    }

    /**
     * Loads flashcard interaction from this subplugin.
     */
    public static function require_page_assets(stdClass $bookflow, bool $editing): void {
        global $PAGE;
        if (!$editing) {
            $PAGE->requires->js_call_amd(
                "bookflowcontent_flashcards/flashcards",
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
            "bookflowcontent_flashcards/flashcards",
            $this->export_data($editing)
        );
    }

    /**
     * Gets the localized content type name.
     */
    public static function get_name(): string {
        return get_string("pluginname", "bookflowcontent_flashcards");
    }

}
