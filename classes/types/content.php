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
     * Renders the content block with its Mustache template.
     *
     * @param renderer_base $output Moodle renderer used to render the Mustache template.
     * @param bool $editing Whether editing controls are enabled.
     * @return string
     */
    abstract public function render(renderer_base $output, bool $editing): string;
}
