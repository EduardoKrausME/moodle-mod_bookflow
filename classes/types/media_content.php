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
 * Shared completion behaviour for time-based media.
 *
 * @package mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\types;

use stdClass;

/**
 * Generic media content behaviour.
 */
abstract class media_content extends source_content {
    /**
     * Uses stored media duration when available.
     */
    public function estimate_time(): int {
        if (!empty($this->record->estimatedtime)) {
            return (int) $this->record->estimatedtime;
        }
        if (!empty($this->record->auxint2)) {
            return (int) $this->record->auxint2;
        }
        return parent::estimate_time();
    }

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
