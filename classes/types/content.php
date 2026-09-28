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
 * content.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\types;

use context_module;
use mod_flexbook\form\content_form;
use renderer_base;
use stdClass;

/**
 * Defines the contract implemented by FlexBook content types.
 */
abstract class content {
    /**
     * Initializes the content instance.
     *
     * @param stdClass $record Record data.
     * @param stdClass $flexbook FlexBook record.
     * @param context_module $context Module context.
     */
    public function __construct(
        /** @var stdClass */
        protected readonly stdClass $record,
        /** @var stdClass */
        protected readonly stdClass $flexbook,
        /** @var context_module */
        protected readonly context_module $context
    ) {
    }

    /**
     * Gets the unique content type identifier.
     *
     * @return string
     */
    abstract public static function get_type(): string;

    /**
     * Gets the localized content type name.
     *
     * @return string
     */
    abstract public static function get_name(): string;

    /**
     * Checks whether the content type is safe for standard editing.
     *
     * @return bool
     */
    abstract public static function is_safe(): bool;

    /**
     * Checks whether the current user can create the content type.
     *
     * @param chapter|null $chapter Chapter wrapper.
     * @param stdClass $flexbook FlexBook record.
     * @param context_module $context Module context.
     * @return bool
     */
    abstract public static function can_create(
        ?chapter $chapter,
        stdClass $flexbook,
        context_module $context
    ): bool;

    /**
     * Gets completion rules supported by this content type.
     *
     * @return array
     */
    public static function get_completion_options(): array {
        return [
            "view" => get_string("completeonview", "mod_flexbook"),
            "timed" => get_string("completeontime", "mod_flexbook"),
            "manual" => get_string("completemanually", "mod_flexbook"),
            "none" => get_string("donottrack", "mod_flexbook"),
        ];
    }

    /**
     * Adds type-specific fields to the content form.
     *
     * @param content_form $form Content form.
     * @param array $editoroptions Editor options.
     * @param array $fileoptions File manager options.
     * @param int $repeatcount Initial repeated field count.
     * @param int $structureddraftid Shared structured editor draft id.
     * @return void
     */
    public static function add_form_fields(
        content_form $form,
        array $editoroptions,
        array $fileoptions,
        int $repeatcount,
        int $structureddraftid
    ): void {
    }

    /**
     * Validates type-specific submitted fields.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array
     */
    public static function validate_form(array $data, array $files): array {
        return [];
    }

    /**
     * Gets file manager options for this content type.
     *
     * @param array $baseoptions Base file options.
     * @return array
     */
    public static function get_file_options(array $baseoptions): array {
        return [
            "subdirs" => false,
            "maxbytes" => $baseoptions["maxbytes"] ?? 0,
            "maxfiles" => 1,
            "accepted_types" => ["*"],
            "return_types" => FILE_INTERNAL,
        ];
    }

    /**
     * Prepares a shared draft area used by structured content.
     *
     * @param stdClass|null $content Existing content record.
     * @param context_module $context Module context.
     * @param array $editoroptions Editor options.
     * @return int
     */
    public static function prepare_structured_draft(
        ?stdClass $content,
        context_module $context,
        array $editoroptions
    ): int {
        return 0;
    }

    /**
     * Gets the initial repeated-field count.
     *
     * @param stdClass|null $content Existing content record.
     * @return int
     */
    public static function get_repeat_count(?stdClass $content): int {
        return 0;
    }

    /**
     * Prepares stored data for the type-specific form.
     *
     * @param stdClass $record Content record.
     * @param context_module $context Module context.
     * @param array $editoroptions Editor options.
     * @param array $fileoptions File manager options.
     * @param int $structureddraftid Shared structured editor draft id.
     * @return stdClass
     */
    public static function prepare_form_data(
        stdClass $record,
        context_module $context,
        array $editoroptions,
        array $fileoptions,
        int $structureddraftid = 0
    ): stdClass {
        return $record;
    }

    /**
     * Converts submitted type-specific fields to storage fields.
     *
     * @param stdClass $data Submitted form data.
     * @return stdClass
     */
    public static function to_record(stdClass $data): stdClass {
        return $data;
    }

    /**
     * Finalizes draft-backed data after a content id exists.
     *
     * @param stdClass $submitted Submitted form data.
     * @param int $itemid Content block id.
     * @param context_module $context Module context.
     * @param array $editoroptions Editor options.
     * @param array $fileoptions File manager options.
     * @return string|null Final data1 value, or null when no draft-backed value exists.
     */
    public static function save_draft_data1(
        stdClass $submitted,
        int $itemid,
        context_module $context,
        array $editoroptions,
        array $fileoptions
    ): ?string {
        return null;
    }

    /**
     * Gets file areas owned by this content type.
     *
     * @return array
     */
    public static function get_fileareas(): array {
        return [];
    }

    /**
     * Gets file areas that must always be served as downloads.
     *
     * @return array
     */
    public static function get_forcedownload_fileareas(): array {
        return [];
    }

    /**
     * Runs after a content record has been created.
     *
     * @param int $contentid Content id.
     * @return void
     */
    public static function after_create(int $contentid): void {
    }

    /**
     * Runs before a content record is updated.
     *
     * @param stdClass $current Current record.
     * @param stdClass $data New record data.
     * @return void
     */
    public static function before_update(stdClass $current, stdClass $data): void {
    }

    /**
     * Runs after a content record has been updated.
     *
     * @param int $contentid Content id.
     * @return void
     */
    public static function after_update(int $contentid): void {
    }

    /**
     * Runs before a content record is deleted or changes type.
     *
     * @param int $contentid Content id.
     * @return void
     */
    public static function before_delete(int $contentid): void {
    }

    /**
     * Requires browser assets needed by this content type.
     *
     * @param stdClass $flexbook FlexBook record.
     * @param bool $editing Whether editing mode is enabled.
     * @return void
     */
    public static function require_page_assets(stdClass $flexbook, bool $editing): void {
    }

    /**
     * Normalizes generic client evidence before it is stored as progress.
     *
     * Concrete subplugins override this method for custom completion semantics.
     *
     * @param stdClass $progress Existing user progress record.
     * @param float $metric Progress metric reported by the client.
     * @param array $details Additional evidence reported by the client.
     * @return stdClass
     */
    public function update_completion_evidence(stdClass $progress, float $metric, array $details): stdClass {
        $stored = $this->decode_progress_details($progress);
        $completiontype = $this->record->completiontype;

        if ($completiontype === "manual" && !empty($details["manual"])) {
            $stored["manual"] = true;
        } else if ($completiontype === "timed") {
            $elapsed = max(0, time() - (int) $progress->firstaccess);
            $progress->timeviewed = max((int) $progress->timeviewed, min(86400, $elapsed));
            $stored["visibleSeconds"] = $progress->timeviewed;
        }

        $progress->details = json_encode($stored);
        return $progress;
    }

    /**
     * Validates generic completion evidence.
     *
     * Concrete subplugins override this method for custom completion semantics.
     *
     * @param stdClass $progress User progress record.
     * @return bool
     */
    public function completion_evidence_is_valid(stdClass $progress): bool {
        $completiontype = $this->record->completiontype;
        $details = $this->decode_progress_details($progress);

        if (in_array($completiontype, ["view", "open"], true)) {
            return (int) $progress->status >= 1;
        }
        if ($completiontype === "manual") {
            return !empty($details["manual"]);
        }
        if ($completiontype === "timed") {
            $required = (int) $this->record->completionvalue;
            return $required > 0 && (int) $progress->timeviewed >= $required;
        }
        return false;
    }

    /**
     * Stores generic click completion evidence for subplugins that use it.
     *
     * @param stdClass $progress Existing progress.
     * @param array $details Client evidence.
     * @return stdClass
     */
    protected function update_click_completion_evidence(stdClass $progress, array $details): stdClass {
        $stored = $this->decode_progress_details($progress);
        if (!empty($details["clicked"]) || !empty($details["visited"])) {
            $stored["clicked"] = true;
        }
        $progress->details = json_encode($stored);
        return $progress;
    }

    /**
     * Validates click completion evidence.
     *
     * @param stdClass $progress Existing progress.
     * @return bool
     */
    protected function click_completion_evidence_is_valid(stdClass $progress): bool {
        $details = $this->decode_progress_details($progress);
        return !empty($details["clicked"]);
    }

    /**
     * Stores verified media progress for subplugins that use percentage/end completion.
     *
     * @param stdClass $progress Existing progress.
     * @param float $metric Client percentage.
     * @param array $details Media evidence.
     * @return stdClass
     */
    protected function update_media_completion_evidence(
        stdClass $progress,
        float $metric,
        array $details
    ): stdClass {
        $stored = $this->decode_progress_details($progress);
        $now = time();
        $position = max(
            is_numeric($details["watchedSeconds"] ?? null) ? (float) $details["watchedSeconds"] : 0,
            is_numeric($details["playedSeconds"] ?? null) ? (float) $details["playedSeconds"] : 0
        );
        $duration = is_numeric($details["duration"] ?? null) ? (float) $details["duration"] : 0;

        if ($duration > 0 && $position >= 0) {
            $duration = min(604800, $duration);
            $position = min($duration, $position);
            $trustedduration = (float) ($stored["_mediaDuration"] ?? $duration);
            if ($trustedduration <= 0) {
                $trustedduration = $duration;
            }

            $lastat = (int) ($stored["_serverMetricAt"] ?? $progress->firstaccess);
            $lastposition = (float) ($stored["_clientPosition"] ?? 0);
            $verified = (float) ($stored["_verifiedSeconds"] ?? 0);
            $elapsed = max(0, $now - $lastat);
            $positiondelta = max(0, $position - $lastposition);
            $allowance = ($elapsed * 4) + 2;
            $verified += min($positiondelta, $allowance);
            $verified = min($trustedduration, $verified);

            $progress->progress = max(
                (float) $progress->progress,
                round(min(100, ($verified / $trustedduration) * 100), 2)
            );
            $progress->timeviewed = max(
                (int) $progress->timeviewed,
                min(86400, (int) round($verified))
            );
            $stored["_mediaDuration"] = $trustedduration;
            $stored["_clientPosition"] = $position;
            $stored["_verifiedSeconds"] = $verified;
            $stored["_serverMetricAt"] = $now;
        } else {
            $progress->progress = max(
                (float) $progress->progress,
                min(100, max(0, $metric))
            );
        }

        $progress->details = json_encode($stored);
        return $progress;
    }

    /**
     * Validates media completion against a required percentage.
     *
     * @param stdClass $progress Existing progress.
     * @param float $required Required percentage.
     * @return bool
     */
    protected function media_completion_evidence_is_valid(stdClass $progress, float $required): bool {
        return $required > 0 && (float) $progress->progress >= $required;
    }

    /**
     * Stores visited collection items for interactive subplugins.
     *
     * @param stdClass $progress Existing progress.
     * @param array $details Client evidence.
     * @return stdClass
     */
    protected function update_collection_completion_evidence(
        stdClass $progress,
        array $details
    ): stdClass {
        $stored = $this->decode_progress_details($progress);
        $limit = max(0, (int) $this->record->auxint1);
        $visited = $stored["visited"] ?? [];
        if (!is_array($visited)) {
            $visited = [];
        }

        $reported = $details["visited"] ?? [];
        if (is_array($reported) && $limit > 0) {
            foreach (array_slice($reported, 0, 1000) as $index) {
                if (!is_numeric($index)) {
                    continue;
                }
                $index = (int) $index;
                if ($index >= 0 && $index < $limit) {
                    $visited[] = $index;
                }
            }
        }

        $visited = array_values(array_unique($visited));
        $stored["visited"] = $visited;
        $progress->progress = $limit > 0
            ? round(min(100, count($visited) / $limit * 100), 2)
            : 0;
        $progress->details = json_encode($stored);
        return $progress;
    }

    /**
     * Validates visited collection item evidence.
     *
     * @param stdClass $progress Existing progress.
     * @return bool
     */
    protected function collection_completion_evidence_is_valid(stdClass $progress): bool {
        $required = max(0, (int) $this->record->auxint1);
        $details = $this->decode_progress_details($progress);
        $visited = $details["visited"] ?? [];
        return $required > 0
            && is_array($visited)
            && count(array_unique($visited)) >= $required;
    }

    /**
     * Decodes stored progress details safely.
     *
     * @param stdClass $progress Existing progress.
     * @return array
     */
    private function decode_progress_details(stdClass $progress): array {
        $details = json_decode($progress->details ?? "{}", true);
        return is_array($details) ? $details : [];
    }

    /**
     * Exports this content as Markdown.
     *
     * @return string
     */
    public function export_markdown(): string {
        return trim(html_to_text($this->record->data1 ?? "", 0, false)) . "\n\n";
    }

    /**
     * Renders extra HTML used only by exports.
     *
     * @param bool $showanswers Whether protected answers may be shown.
     * @return string
     */
    public function render_export_extra(bool $showanswers): string {
        return "";
    }

    /**
     * Estimates study time for this content block in seconds.
     *
     * @return int
     */
    public function estimate_time(): int {
        if (!empty($this->record->estimatedtime)) {
            return (int) $this->record->estimatedtime;
        }
        $words = count(
            preg_split("/\\s+/u", trim(html_to_text($this->record->data1 ?? "", 0, false))) ?: []
        );
        return (int) ceil($words / 200 * 60);
    }

    /**
     * Gets optional user-report columns contributed by this content type.
     *
     * @return array
     */
    public static function get_user_report_columns(): array {
        return [];
    }

    /**
     * Gets optional user-report values keyed by user id.
     *
     * @param int $flexbookid FlexBook id.
     * @return array
     */
    public static function get_user_report_data(int $flexbookid): array {
        return [];
    }

    /**
     * Renders the content block with its Mustache template.
     *
     * @param renderer_base $output Moodle renderer used to render the Mustache template.
     * @param bool $editing Whether editing controls are enabled.
     * @return string
     */
    abstract public function render(renderer_base $output, bool $editing): string;
}
