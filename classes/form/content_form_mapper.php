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
 * Delegates content form mapping to registered content subplugins and provides generic helpers.
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_bookflow\form;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->dirroot}/repository/lib.php");

use coding_exception;
use context_module;
use mod_bookflow\content_type_manager;
use stdClass;

/**
 * Generic form mapping helpers plus subplugin delegation.
 */
class content_form_mapper {
    /**
     * Gets filemanager options from the selected subplugin.
     *
     * @param string $type Content type.
     * @param array $baseoptions Base options.
     * @return array
     */
    public static function get_file_options(string $type, array $baseoptions): array {
        $classname = self::get_content_class($type);
        return $classname::get_file_options($baseoptions);
    }

    /**
     * Prepares a structured draft through the selected subplugin.
     */
    public static function prepare_structured_draft(
        string $type,
        ?stdClass $content,
        context_module $context,
        array $editoroptions
    ): int {
        $classname = self::get_content_class($type);
        return $classname::prepare_structured_draft($content, $context, $editoroptions);
    }

    /**
     * Gets repeat count through the selected subplugin.
     */
    public static function get_repeat_count(?stdClass $content, string $type): int {
        $classname = self::get_content_class($type);
        return $classname::get_repeat_count($content);
    }

    /**
     * Prepares form data through the selected subplugin.
     */
    public static function prepare_form_data(
        stdClass $record,
        string $type,
        context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        $classname = self::get_content_class($type);
        return $classname::prepare_form_data(
            $record,
            $context,
            $editoroptions,
            $fileoptions,
            $structureddraftid
        );
    }

    /**
     * Converts submitted fields through the selected subplugin.
     */
    public static function to_record(stdClass $data, string $type): stdClass {
        $classname = self::get_content_class($type);
        return $classname::to_record($data);
    }

    /**
     * Finalizes draft data through the selected subplugin.
     */
    public static function save_draft_data1(
        stdClass $submitted,
        string $type,
        int $itemid,
        context_module $context,
        array $editoroptions,
        array $fileoptions
    ): ?string {
        $classname = self::get_content_class($type);
        return $classname::save_draft_data1(
            $submitted,
            $itemid,
            $context,
            $editoroptions,
            $fileoptions
        );
    }

    /**
     * Builds standard single-file options.
     *
     * @param array $baseoptions Base options.
     * @param array $acceptedtypes Accepted Moodle file types.
     * @return array
     */
    public static function build_file_options(array $baseoptions, array $acceptedtypes = ["*"]): array {
        return [
            "subdirs" => false,
            "maxbytes" => $baseoptions["maxbytes"] ?? 0,
            "maxfiles" => 1,
            "accepted_types" => $acceptedtypes,
            "return_types" => FILE_INTERNAL,
        ];
    }

    /**
     * Prepares a Moodle HTML editor backed by the content file area.
     */
    public static function prepare_editor_data(
        stdClass $record,
        context_module $context,
        array $editoroptions
    ): stdClass {
        $itemid = (int) ($record->id ?? $record->contentid ?? 0);
        $draftitemid = file_get_submitted_draft_itemid("data1_editor");
        $text = file_prepare_draft_area(
            $draftitemid,
            $context->id,
            "mod_bookflow",
            "content",
            $itemid ?: null,
            $editoroptions,
            $record->data1 ?? ""
        );

        $record->data1_editor = [
            "text" => $text,
            "format" => FORMAT_HTML,
            "itemid" => $draftitemid,
        ];
        return $record;
    }

    /**
     * Saves a Moodle HTML editor value and draft files.
     */
    public static function save_editor_data1(
        stdClass $submitted,
        int $itemid,
        context_module $context,
        array $editoroptions
    ): string {
        $editor = (array) ($submitted->data1_editor ?? []);
        return file_save_draft_area_files(
            (int) ($editor["itemid"] ?? 0),
            $context->id,
            "mod_bookflow",
            "content",
            $itemid,
            $editoroptions,
            (string) ($editor["text"] ?? "")
        ) ?? "";
    }

    /**
     * Prepares one uploaded source plus external URL.
     */
    public static function prepare_source_data(
        stdClass $record,
        context_module $context,
        array $fileoptions,
        string $filearea
    ): stdClass {
        $itemid = (int) ($record->id ?? $record->contentid ?? 0);
        $draftitemid = file_get_submitted_draft_itemid("sourcefile");
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            "mod_bookflow",
            $filearea,
            $itemid ?: null,
            $fileoptions
        );

        $record->sourcefile = $draftitemid;
        $source = trim((string) ($record->data1 ?? ""));
        $record->sourceurl = str_starts_with($source, "@@PLUGINFILE@@") ? "" : $source;
        return $record;
    }

    /**
     * Saves one uploaded source and returns its @@PLUGINFILE@@ value or the external URL.
     */
    public static function save_source_data1(
        stdClass $submitted,
        int $itemid,
        context_module $context,
        array $fileoptions,
        string $filearea
    ): string {
        $draftitemid = (int) ($submitted->sourcefile ?? 0);
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            "mod_bookflow",
            $filearea,
            $itemid,
            $fileoptions
        );

        $files = get_file_storage()->get_area_files(
            $context->id,
            "mod_bookflow",
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
     * Checks whether a source file or URL is present.
     */
    public static function has_source(array $data): bool {
        if (trim((string) ($data["sourceurl"] ?? "")) !== "") {
            return true;
        }
        if (empty($data["sourcefile"])) {
            return false;
        }
        $info = file_get_draft_area_info((int) $data["sourcefile"]);
        return !empty($info["filecount"]);
    }

    /**
     * Prepares the shared draft area used by structured HTML items.
     */
    public static function prepare_shared_editor_draft(
        ?stdClass $content,
        context_module $context,
        array $editoroptions
    ): int {
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
            "mod_bookflow",
            "content",
            $content->id ?? null,
            $editoroptions
        );
        return $draftitemid;
    }

    /**
     * Prepares title/editor structured items.
     */
    public static function prepare_structured_items_data(
        stdClass $record,
        context_module $context,
        array $editoroptions,
        int $structureddraftid
    ): stdClass {
        $itemid = (int) ($record->id ?? $record->contentid ?? 0);
        $items = self::decode_items($record->data1 ?? "[]");
        $rowcount = max(2, count($items));
        $draftitemid = $structureddraftid;
        if (!$draftitemid) {
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                "mod_bookflow",
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
                "mod_bookflow",
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
        return $record;
    }

    /**
     * Saves title/editor structured items.
     */
    public static function save_structured_items_data1(
        stdClass $submitted,
        int $itemid,
        context_module $context,
        array $editoroptions
    ): string {
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
                "mod_bookflow",
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

    /**
     * Resets generic storage fields before a subplugin fills them.
     */
    public static function reset_storage_fields(stdClass $data): void {
        $data->data1 = "";
        $data->data2 = "";
        $data->data3 = "";
        $data->auxint1 = 0;
    }

    /**
     * Removes form-only fields.
     *
     * @param stdClass $data Record data.
     * @param array $fields Field names.
     * @return void
     */
    public static function unset_fields(stdClass $data, array $fields): void {
        foreach ($fields as $field) {
            unset($data->{$field});
        }
    }

    /**
     * Counts non-empty repeated pairs.
     */
    public static function count_nonempty_pairs(array $left, array $right): int {
        $count = max(count($left), count($right));
        $nonempty = 0;
        for ($index = 0; $index < $count; $index++) {
            if (self::editor_text($left[$index] ?? "") !== ""
                    || self::editor_text($right[$index] ?? "") !== "") {
                $nonempty++;
            }
        }
        return $nonempty;
    }

    /**
     * Extracts editor text or plain scalar text.
     *
     * @param mixed $value Field value.
     * @return string
     */
    public static function editor_text($value): string {
        if (is_array($value)) {
            return trim((string) ($value["text"] ?? ""));
        }
        return trim((string) $value);
    }

    /**
     * Decodes a JSON item array.
     */
    public static function decode_items(string $json): array {
        $items = json_decode($json, true);
        if (!is_array($items)) {
            return [];
        }
        return array_values(array_filter($items, "is_array"));
    }

    /**
     * Encodes item data.
     */
    public static function encode_items(array $items): string {
        return json_encode(
            array_values($items),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Resolves a registered content class.
     */
    private static function get_content_class(string $type): string {
        $classes = content_type_manager::get_classes();
        if ($type === "" || !isset($classes[$type])) {
            throw new coding_exception("Unknown BookFlow content type: {$type}");
        }
        return $classes[$type];
    }
}
