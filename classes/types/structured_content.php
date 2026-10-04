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
 * Shared base for ordered title/content collections.
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
 * Generic structured item behaviour.
 */
abstract class structured_content extends template_content {
    /** @var string Completion rule associated with visiting all items. */
    protected static string $collectioncompletiontype = "";

    /**
     * Adds repeated title/editor item fields.
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
            $mform->createElement("text", "itemtitle", get_string("itemtitle", "mod_bookflow"), ["size" => 56]),
            $mform->createElement(
                "editor",
                "itemcontent",
                get_string("itemcontent", "mod_bookflow"),
                ["rows" => 10],
                $editoroptions
            ),
        ];
        $options = [
            "itemtitle" => ["type" => PARAM_TEXT],
            "itemcontent" => [
                "type" => PARAM_RAW,
                "default" => [
                    "text" => "",
                    "format" => FORMAT_HTML,
                    "itemid" => $structureddraftid,
                ],
            ],
        ];
        $form->repeat_content_elements(
            $repeat,
            $repeatcount,
            $options,
            "item_repeats",
            "item_add_fields",
            get_string("additem", "mod_bookflow")
        );
    }

    /**
     * Validates at least one structured item.
     */
    public static function validate_form(array $data, array $files): array {
        $count = content_form_mapper::count_nonempty_pairs(
            (array) ($data["itemtitle"] ?? []),
            (array) ($data["itemcontent"] ?? [])
        );
        if ($count === 0) {
            return ["itemtitle[0]" => get_string("interactiveitemrequired", "mod_bookflow")];
        }
        return [];
    }

    /**
     * Prepares the shared structured editor draft.
     */
    public static function prepare_structured_draft(
        ?stdClass $content,
        context_module $context,
        array $editoroptions
    ): int {
        return content_form_mapper::prepare_shared_editor_draft(
            $content,
            $context,
            $editoroptions
        );
    }

    /**
     * Gets repeated item count.
     */
    public static function get_repeat_count(?stdClass $content): int {
        if (!$content) {
            return 2;
        }
        return max(2, count(content_form_mapper::decode_items($content->data1 ?? "[]")));
    }

    /**
     * Prepares structured fields.
     */
    public static function prepare_form_data(
        stdClass $record,
        context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        return content_form_mapper::prepare_structured_items_data(
            $record,
            $context,
            $editoroptions,
            $structureddraftid
        );
    }

    /**
     * Converts structured fields to storage.
     */
    public static function to_record(stdClass $data): stdClass {
        content_form_mapper::reset_storage_fields($data);

        $items = [];
        $titles = (array) ($data->itemtitle ?? []);
        $contents = (array) ($data->itemcontent ?? []);
        $count = max(count($titles), count($contents));
        for ($index = 0; $index < $count; $index++) {
            $title = trim((string) ($titles[$index] ?? ""));
            $content = content_form_mapper::editor_text($contents[$index] ?? "");
            if ($title === "" && $content === "") {
                continue;
            }
            $items[] = [
                "title" => $title,
                "content" => $content,
            ];
        }

        $data->data1 = content_form_mapper::encode_items($items);
        $data->auxint1 = count($items);
        content_form_mapper::unset_fields($data, [
            "itemtitle",
            "itemcontent",
            "item_repeats",
            "item_add_fields",
        ]);
        return $data;
    }

    /**
     * Saves structured editor content and files.
     */
    public static function save_draft_data1(
        stdClass $submitted,
        int $itemid,
        context_module $context,
        array $editoroptions,
        array $fileoptions
    ): ?string {
        return content_form_mapper::save_structured_items_data1(
            $submitted,
            $itemid,
            $context,
            $editoroptions
        );
    }

    /**
     * Adds normalized structured items to the template context.
     */
    protected function export_data(bool $editing): array {
        $data = parent::export_data($editing);
        $items = content_form_mapper::decode_items($this->get_rewritten_primary_data());
        $normalizeditems = [];

        foreach ($items as $index => $item) {
            $normalizeditems[] = [
                "index" => $index,
                "title" => s($item["title"] ?? ""),
                "content" => format_text($item["content"] ?? "", FORMAT_HTML, [
                    "context" => $this->context,
                ]),
                "first" => $index === 0,
            ];
        }

        $data["items"] = $normalizeditems;
        $data["hasitems"] = !empty($normalizeditems);
        return $data;
    }

    /**
     * Updates visited-item completion evidence.
     */
    public function update_completion_evidence(stdClass $progress, float $metric, array $details): stdClass {
        if (static::$collectioncompletiontype !== ""
                && $this->record->completiontype === static::$collectioncompletiontype) {
            return $this->update_collection_completion_evidence($progress, $details);
        }
        return parent::update_completion_evidence($progress, $metric, $details);
    }

    /**
     * Validates visited-item completion evidence.
     */
    public function completion_evidence_is_valid(stdClass $progress): bool {
        if (static::$collectioncompletiontype !== ""
                && $this->record->completiontype === static::$collectioncompletiontype) {
            return $this->collection_completion_evidence_is_valid($progress);
        }
        return parent::completion_evidence_is_valid($progress);
    }

    /**
     * Gets file areas owned by structured editor content.
     */
    public static function get_fileareas(): array {
        return ["content"];
    }
}
