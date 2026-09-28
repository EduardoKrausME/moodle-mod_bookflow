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
 * Maps type-specific content forms to the generic flexbook_contents storage.
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\form;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->dirroot}/repository/lib.php");

use context_module;
use stdClass;

/**
 * Converts type-specific form fields to and from the generic content record.
 */
class content_form_mapper {
    /** @var array Content types that use the Moodle HTML editor. */
    private const EDITOR_TYPES = [
        "html",
        "callout",
        "disclosure",
        "question",
    ];

    /** @var array Content types that can store one uploaded source file. */
    private const FILE_TYPES = [
        "image",
        "video",
        "audio",
        "download",
    ];

    /** @var array Structured content types whose item bodies are HTML editors. */
    private const STRUCTURED_EDITOR_TYPES = [
        "accordion",
        "tabs",
    ];

    /**
     * Gets the filemanager options for one content type.
     *
     * @param string $type Content type.
     * @param array $baseoptions Base editor/file options.
     * @return array
     */
    public static function get_file_options(string $type, array $baseoptions): array {
        $acceptedtypes = match ($type) {
            "image" => ["image"],
            "video" => ["video"],
            "audio" => ["audio"],
            default => ["*"],
        };

        return [
            "subdirs" => false,
            "maxbytes" => $baseoptions["maxbytes"] ?? 0,
            "maxfiles" => 1,
            "accepted_types" => $acceptedtypes,
            "return_types" => FILE_INTERNAL,
        ];
    }

    /**
     * Prepares the shared draft area used by repeated HTML editors.
     *
     * All accordion or tab item editors share one draft area so newly added
     * rows can upload files without creating file areas that cannot be merged
     * safely into the content block.
     *
     * @param string $type Content type.
     * @param stdClass|null $content Existing content record.
     * @param context_module $context Activity context.
     * @param array $editoroptions Editor options.
     * @return int Draft area item id, or zero for non-structured content.
     */
    public static function prepare_structured_draft(
        string $type,
        ?stdClass $content,
        context_module $context,
        array $editoroptions
    ): int {
        if (!in_array($type, self::STRUCTURED_EDITOR_TYPES, true)) {
            return 0;
        }

        $draftitemid = 0;
        $submitted = data_submitted();
        if ($submitted && !empty($submitted->itemcontent) && is_array($submitted->itemcontent)) {
            foreach ($submitted->itemcontent as $editor) {
                $editor = (array) $editor;
                if (!empty($editor["itemid"])) {
                    $draftitemid = (int) $editor["itemid"];
                    break;
                }
            }
        }

        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            "mod_flexbook",
            "content",
            $content->id ?? null,
            $editoroptions
        );

        return $draftitemid;
    }

    /**
     * Gets the initial number of repeated fields for a structured block.
     *
     * @param stdClass|null $content Existing content record.
     * @param string $type Content type.
     * @return int
     */
    public static function get_repeat_count(?stdClass $content, string $type): int {
        if (!$content) {
            return in_array($type, ["accordion", "tabs", "flashcards", "question"], true) ? 2 : 0;
        }

        $json = $type === "question" ? ($content->data2 ?? "[]") : ($content->data1 ?? "[]");
        $items = json_decode($json, true);
        $count = is_array($items) ? count($items) : 0;

        return max(2, $count);
    }

    /**
     * Prepares an existing or new record for the type-specific form.
     *
     * @param stdClass $record Content record or new form defaults.
     * @param string $type Content type.
     * @param context_module $context Activity context.
     * @param array $editoroptions Editor options.
     * @param array $fileoptions Filemanager options.
     * @param int $structureddraftid Shared draft id for repeated HTML editors.
     * @return stdClass
     */
    public static function prepare_form_data(
        stdClass $record,
        string $type,
        context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        $itemid = (int) ($record->id ?? $record->contentid ?? 0);

        if (in_array($type, self::EDITOR_TYPES, true)) {
            $draftitemid = file_get_submitted_draft_itemid("data1_editor");
            $text = $record->data1 ?? "";

            $text = file_prepare_draft_area(
                $draftitemid,
                $context->id,
                "mod_flexbook",
                "content",
                $itemid ?: null,
                $editoroptions,
                $text
            );

            $record->data1_editor = [
                "text" => $text,
                "format" => FORMAT_HTML,
                "itemid" => $draftitemid,
            ];
        }

        if (in_array($type, self::FILE_TYPES, true)) {
            $filearea = self::get_filearea($type);
            $draftitemid = file_get_submitted_draft_itemid("sourcefile");

            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                "mod_flexbook",
                $filearea,
                $itemid ?: null,
                $fileoptions
            );

            $record->sourcefile = $draftitemid;
            $source = trim((string) ($record->data1 ?? ""));
            $record->sourceurl = str_starts_with($source, "@@PLUGINFILE@@") ? "" : $source;
        }

        switch ($type) {
            case "markdown":
            case "code":
                $record->rawcontent = (string) ($record->data1 ?? "");
                break;

            case "accordion":
            case "tabs":
                $items = self::decode_items($record->data1 ?? "[]");
                $rowcount = max(2, count($items));
                $draftitemid = $structureddraftid;
                if (!$draftitemid) {
                    file_prepare_draft_area(
                        $draftitemid,
                        $context->id,
                        "mod_flexbook",
                        "content",
                        $itemid ?: null,
                        $editoroptions
                    );
                }
                $record->itemtitle = [];
                $record->itemcontent = [];
                for ($index = 0; $index < $rowcount; $index++) {
                    $item = $items[$index] ?? [];
                    $record->itemtitle[] = (string) ($item["title"] ?? "");
                    $text = file_prepare_draft_area(
                        $draftitemid,
                        $context->id,
                        "mod_flexbook",
                        "content",
                        $itemid ?: null,
                        $editoroptions,
                        (string) ($item["content"] ?? "")
                    );
                    $record->itemcontent[] = [
                        "text" => $text ?? "",
                        "format" => FORMAT_HTML,
                        "itemid" => $draftitemid,
                    ];
                }
                break;

            case "flashcards":
                $items = self::decode_items($record->data1 ?? "[]");
                $record->cardfront = array_column($items, "front");
                $record->cardback = array_column($items, "back");
                break;

            case "question":
                $options = self::decode_items($record->data2 ?? "[]");
                $configuration = json_decode($record->data3 ?? "{}", true);
                if (!is_array($configuration)) {
                    $configuration = [];
                }
                $answer = $configuration["answer"] ?? null;
                $record->optiontext = [];
                $record->optionvalue = [];
                $record->optioncorrect = [];
                foreach ($options as $index => $option) {
                    $value = (string) ($option["value"] ?? $index);
                    $record->optiontext[] = (string) ($option["title"] ?? "");
                    $record->optionvalue[] = $value;
                    $record->optioncorrect[] = $answer !== null && $answer == $value ? 1 : 0;
                }
                $record->questionfeedback = (string) ($configuration["feedback"] ?? "");
                break;

            case "image":
                $record->imagealt = (string) ($record->data2 ?? "");
                $record->caption = (string) ($record->data3 ?? "");
                break;

            case "video":
                $record->captionsurl = (string) ($record->data2 ?? "");
                $record->transcript = (string) ($record->data3 ?? "");
                break;

            case "audio":
                $record->transcript = (string) ($record->data3 ?? "");
                break;

            case "download":
                $record->filedescription = (string) ($record->data2 ?? "");
                break;
        }

        return $record;
    }

    /**
     * Converts submitted type-specific fields to database fields.
     *
     * Draft files and editor text are finalised separately after an item id is known.
     *
     * @param stdClass $data Submitted form data.
     * @param string $type Content type.
     * @return stdClass
     */
    public static function to_record(stdClass $data, string $type): stdClass {
        $data->data1 = "";
        $data->data2 = "";
        $data->data3 = "";
        $data->auxint1 = 0;

        switch ($type) {
            case "html":
            case "callout":
                break;

            case "disclosure":
                $data->auxint1 = 1;
                break;

            case "markdown":
            case "code":
                $data->data1 = (string) ($data->rawcontent ?? "");
                break;

            case "accordion":
            case "tabs":
                $items = [];
                $titles = (array) ($data->itemtitle ?? []);
                $contents = (array) ($data->itemcontent ?? []);
                $count = max(count($titles), count($contents));
                for ($i = 0; $i < $count; $i++) {
                    $title = trim((string) ($titles[$i] ?? ""));
                    $content = self::editor_text($contents[$i] ?? "");
                    if ($title === "" && $content === "") {
                        continue;
                    }
                    $items[] = [
                        "title" => $title,
                        "content" => $content,
                    ];
                }
                $data->data1 = self::encode_items($items);
                $data->auxint1 = count($items);
                break;

            case "flashcards":
                $items = [];
                $fronts = (array) ($data->cardfront ?? []);
                $backs = (array) ($data->cardback ?? []);
                $count = max(count($fronts), count($backs));
                for ($i = 0; $i < $count; $i++) {
                    $front = trim((string) ($fronts[$i] ?? ""));
                    $back = trim((string) ($backs[$i] ?? ""));
                    if ($front === "" && $back === "") {
                        continue;
                    }
                    $items[] = [
                        "front" => $front,
                        "back" => $back,
                    ];
                }
                $data->data1 = self::encode_items($items);
                $data->auxint1 = count($items);
                break;

            case "question":
                $options = [];
                $texts = (array) ($data->optiontext ?? []);
                $values = (array) ($data->optionvalue ?? []);
                $correct = (array) ($data->optioncorrect ?? []);
                $usedvalues = [];
                $answer = null;
                foreach ($texts as $index => $text) {
                    $text = trim((string) $text);
                    if ($text === "") {
                        continue;
                    }

                    $value = trim((string) ($values[$index] ?? ""));
                    if ($value === "" || isset($usedvalues[$value])) {
                        $value = "option_" . $index;
                    }
                    $usedvalues[$value] = true;

                    $options[] = [
                        "title" => $text,
                        "value" => $value,
                    ];
                    if (!empty($correct[$index])) {
                        $answer = $value;
                    }
                }

                $data->data2 = self::encode_items($options);
                $data->data3 = json_encode([
                    "answer" => $answer,
                    "feedback" => trim((string) ($data->questionfeedback ?? "")),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case "image":
                $data->data1 = trim((string) ($data->sourceurl ?? ""));
                $data->data2 = trim((string) ($data->imagealt ?? ""));
                $data->data3 = trim((string) ($data->caption ?? ""));
                break;

            case "video":
                $data->data1 = trim((string) ($data->sourceurl ?? ""));
                $data->data2 = trim((string) ($data->captionsurl ?? ""));
                $data->data3 = trim((string) ($data->transcript ?? ""));
                break;

            case "audio":
                $data->data1 = trim((string) ($data->sourceurl ?? ""));
                $data->data3 = trim((string) ($data->transcript ?? ""));
                break;

            case "download":
                $data->data1 = trim((string) ($data->sourceurl ?? ""));
                $data->data2 = trim((string) ($data->filedescription ?? ""));
                break;
        }

        self::unset_form_only_fields($data);
        return $data;
    }

    /**
     * Saves draft editor or source files and returns the final data1 value.
     *
     * @param stdClass $submitted Original submitted form data.
     * @param string $type Content type.
     * @param int $itemid Content block id.
     * @param context_module $context Activity context.
     * @param array $editoroptions Editor options.
     * @param array $fileoptions Filemanager options.
     * @return string|null Null when the type has no draft-backed primary field.
     */
    public static function save_draft_data1(
        stdClass $submitted,
        string $type,
        int $itemid,
        context_module $context,
        array $editoroptions,
        array $fileoptions
    ): ?string {
        if (in_array($type, self::STRUCTURED_EDITOR_TYPES, true)) {
            $titles = (array) ($submitted->itemtitle ?? []);
            $contents = (array) ($submitted->itemcontent ?? []);
            $draftitemid = 0;
            foreach ($contents as $content) {
                if (is_array($content) && !empty($content["itemid"])) {
                    $draftitemid = (int) $content["itemid"];
                    break;
                }
            }

            if ($draftitemid) {
                file_save_draft_area_files(
                    $draftitemid,
                    $context->id,
                    "mod_flexbook",
                    "content",
                    $itemid,
                    $editoroptions
                );
            }

            $items = [];
            $count = max(count($titles), count($contents));
            for ($index = 0; $index < $count; $index++) {
                $title = trim((string) ($titles[$index] ?? ""));
                $text = self::editor_text($contents[$index] ?? "");
                if ($draftitemid && $text !== "") {
                    $text = file_rewrite_urls_to_pluginfile($text, $draftitemid);
                }
                if ($title === "" && $text === "") {
                    continue;
                }
                $items[] = [
                    "title" => $title,
                    "content" => $text,
                ];
            }

            return self::encode_items($items);
        }

        if (in_array($type, self::EDITOR_TYPES, true)) {
            $editor = (array) ($submitted->data1_editor ?? []);
            $draftitemid = (int) ($editor["itemid"] ?? 0);
            $text = (string) ($editor["text"] ?? "");

            return file_save_draft_area_files(
                $draftitemid,
                $context->id,
                "mod_flexbook",
                "content",
                $itemid,
                $editoroptions,
                $text
            ) ?? "";
        }

        if (!in_array($type, self::FILE_TYPES, true)) {
            return null;
        }

        $draftitemid = (int) ($submitted->sourcefile ?? 0);
        $filearea = self::get_filearea($type);
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            "mod_flexbook",
            $filearea,
            $itemid,
            $fileoptions
        );

        $files = get_file_storage()->get_area_files(
            $context->id,
            "mod_flexbook",
            $filearea,
            $itemid,
            "filepath, filename",
            false
        );

        if ($files) {
            $file = reset($files);
            return "@@PLUGINFILE@@" . $file->get_filepath() . $file->get_filename();
        }

        return trim((string) ($submitted->sourceurl ?? ""));
    }

    /**
     * Gets the permanent file area used by a source-backed content type.
     *
     * @param string $type Content type.
     * @return string
     */
    private static function get_filearea(string $type): string {
        return $type === "download" ? "download" : "content";
    }

    /**
     * Extracts the text part from an editor value.
     *
     * @param mixed $value Form value.
     * @return string
     */
    private static function editor_text($value): string {
        if (is_array($value)) {
            return trim((string) ($value["text"] ?? ""));
        }
        return trim((string) $value);
    }

    /**
     * Decodes a JSON item array safely.
     *
     * @param string $json JSON data.
     * @return array
     */
    private static function decode_items(string $json): array {
        $items = json_decode($json, true);
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, "is_array"));
    }

    /**
     * Encodes structured item data.
     *
     * @param array $items Items.
     * @return string
     */
    private static function encode_items(array $items): string {
        return json_encode(
            array_values($items),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Removes fields that only belong to the Moodle form.
     *
     * @param stdClass $data Record data.
     * @return void
     */
    private static function unset_form_only_fields(stdClass $data): void {
        foreach ([
            "data1_editor",
            "sourcefile",
            "sourceurl",
            "rawcontent",
            "itemtitle",
            "itemcontent",
            "item_repeats",
            "item_add_fields",
            "cardfront",
            "cardback",
            "card_repeats",
            "card_add_fields",
            "optiontext",
            "optionvalue",
            "optioncorrect",
            "option_repeats",
            "option_add_fields",
            "questionfeedback",
            "imagealt",
            "caption",
            "captionsurl",
            "transcript",
            "filedescription",
        ] as $field) {
            unset($data->{$field});
        }
    }
}
