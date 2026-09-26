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
 * content_type_manager.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook;

use context_module;
use core\di;
use core\hook\manager;
use core_component;
use mod_flexbook\hook\content_types;
use mod_flexbook\types\content;
use moodle_exception;
use stdClass;

/**
 * Discovers and creates registered FlexBook content types.
 */
class content_type_manager {
    /**
     * Types
     */
    private const CORE_TYPES = [
        "html",
        "markdown",
        "callout",
        "image",
        "video",
        "audio",
        "download",
        "code",
        "accordion",
        "tabs",
        "disclosure",
        "flashcards",
        "question",
    ];

    /**
     * Discovers all core and extension content type classes.
     *
     * @return array
     */
    public static function get_classes(): array {
        $hook = new content_types();
        foreach (self::CORE_TYPES as $type) {
            $classname = "\\mod_flexbook\\types\\{$type}";
            $hook->register($type, $classname);
        }

        foreach (core_component::get_plugin_list("flexbookcontent") as $name => $path) {
            $classname = "\\flexbookcontent_{$name}\\\content_type";
            if (class_exists($classname) && method_exists($classname, "register")) {
                $classname::register($hook);
            }
        }

        if (class_exists("\\core\\hook\\manager")) {
            di::get(manager::class)->dispatch($hook);
        }
        return $hook->get_classes();
    }

    /**
     * Gets localized options for the content type selector.
     *
     * @return array
     */
    public static function get_type_options(): array {
        $options = [];
        foreach (self::get_classes() as $type => $classname) {
            $options[$type] = $classname::get_name();
        }
        return $options;
    }

    /**
     * Creates a content type object for a database record.
     *
     * @param stdClass $record Record data.
     * @param stdClass $flexbook FlexBook record.
     * @param context_module $context Module context.
     * @return content
     */
    public static function create_content(
        stdClass $record,
        stdClass $flexbook,
        context_module $context
    ): content {
        $classes = self::get_classes();
        if (!isset($classes[$record->type])) {
            throw new moodle_exception("unknowncontenttype", "mod_flexbook", "", $record->type);
        }
        $classname = $classes[$record->type];
        return new $classname($record, $flexbook, $context);
    }
}
