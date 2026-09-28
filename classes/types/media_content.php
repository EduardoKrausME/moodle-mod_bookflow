<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Shared completion behaviour for time-based media.
 *
 * @package mod_flexbook
 */

namespace mod_flexbook\types;

use stdClass;

/**
 * Generic media content behaviour.
 */
abstract class media_content extends source_content {
    /**
     * Validates media completion settings.
     */
    public static function validate_form(array $data, array $files): array {
        $errors = parent::validate_form($data, $files);
        if (in_array($data["completiontype"] ?? "", ["percent", "end"], true)
                && (($data["completionvalue"] ?? 0) <= 0 || ($data["completionvalue"] ?? 0) > 100)) {
            $errors["completionvalue"] = get_string("percentageerror", "mod_flexbook");
        }
        return $errors;
    }

    /**
     * Updates media completion evidence.
     */
    public function update_completion_evidence(stdClass $progress, float $metric, array $details): stdClass {
        if (in_array($this->record->completiontype, ["percent", "end"], true)) {
            return $this->update_media_completion_evidence($progress, $metric, $details);
        }
        return parent::update_completion_evidence($progress, $metric, $details);
    }

    /**
     * Validates media completion evidence.
     */
    public function completion_evidence_is_valid(stdClass $progress): bool {
        if (in_array($this->record->completiontype, ["percent", "end"], true)) {
            $required = $this->record->completiontype === "end"
                ? 100.0
                : (float) $this->record->completionvalue;
            return $this->media_completion_evidence_is_valid($progress, $required);
        }
        return parent::completion_evidence_is_valid($progress);
    }
}
