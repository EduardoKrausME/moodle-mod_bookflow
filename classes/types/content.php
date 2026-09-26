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
     * Normalizes client evidence before it is stored as progress.
     *
     * Content types may override this when their completion evidence can be
     * validated more precisely by the server.
     *
     * @param stdClass $progress Existing user progress record.
     * @param float $metric Progress metric reported by the client.
     * @param array $details Additional evidence reported by the client.
     * @return stdClass
     */
    public function update_completion_evidence(stdClass $progress, float $metric, array $details): stdClass {
        $stored = json_decode($progress->details ?? "{}", true);
        if (!is_array($stored)) {
            $stored = [];
        }

        $completiontype = $this->record->completiontype;
        $now = time();

        if ($completiontype == "manual") {
            if (!empty($details["manual"])) {
                $stored["manual"] = true;
            }
        } else if ($completiontype == "click") {
            if (!empty($details["clicked"]) || !empty($details["download"])
                    || !empty($details["visited"])) {
                $stored["clicked"] = true;
            }
        } else if ($completiontype == "timed") {
            $elapsed = max(0, $now - (int) $progress->firstaccess);
            $progress->timeviewed = max((int) $progress->timeviewed, min(86400, $elapsed));
            $stored["visibleSeconds"] = $progress->timeviewed;
        } else if (in_array($completiontype, ["percent", "end"])) {
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
        } else if (in_array($completiontype, ["allitems", "alltabs", "allcards"])) {
            $limit = max(0, (int) $this->record->auxint1);
            $visited = $stored["visited"] ?? [];
            if (!is_array($visited)) {
                $visited = [];
            }
            $reported = $details["visited"] ?? [];
            if (is_array($reported) && $limit > 0) {
                foreach (array_slice($reported, 0, 1000) as $index) {
                    if (is_numeric($index)) {
                        $index = (int) $index;
                        if ($index >= 0 && $index < $limit) {
                            $visited[] = $index;
                        }
                    }
                }
            }
            $visited = array_values(array_unique($visited));
            $stored["visited"] = $visited;
            $progress->progress = $limit > 0
                ? round(min(100, count($visited) / $limit * 100), 2)
                : 0;
        }

        $progress->details = json_encode($stored);
        return $progress;
    }

    /**
     * Validates whether stored evidence satisfies the completion rule.
     *
     * @param stdClass $progress User progress record.
     * @return bool
     */
    public function completion_evidence_is_valid(stdClass $progress): bool {
        $completiontype = $this->record->completiontype;
        $details = json_decode($progress->details ?? "{}", true);
        if (!is_array($details)) {
            $details = [];
        }

        if (in_array($completiontype, ["view", "open"])) {
            return (int) $progress->status >= 1;
        }
        if ($completiontype == "manual") {
            return !empty($details["manual"]);
        }
        if ($completiontype == "click") {
            return !empty($details["clicked"]);
        }
        if ($completiontype == "timed") {
            $required = (int) $this->record->completionvalue;
            return $required > 0 && (int) $progress->timeviewed >= $required;
        }
        if (in_array($completiontype, ["percent", "end"])) {
            $required = $completiontype == "end" ? 100 : (float) $this->record->completionvalue;
            return $required > 0 && (float) $progress->progress >= $required;
        }
        if (in_array($completiontype, ["allitems", "alltabs", "allcards"])) {
            $required = max(0, (int) $this->record->auxint1);
            $visited = $details["visited"] ?? [];
            return $required > 0 && is_array($visited) && count(array_unique($visited)) >= $required;
        }
        return false;
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
