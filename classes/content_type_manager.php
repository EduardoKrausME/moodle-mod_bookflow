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
     * Content types bundled with the FlexBook activity.
     */
    private const BUNDLED_TYPES = [
        "accordion",
        "audio",
        "callout",
        "code",
        "disclosure",
        "download",
        "flashcards",
        "html",
        "image",
        "markdown",
        "question",
        "tabs",
        "video",
    ];

    /**
     * Discovers all core and extension content type classes.
     *
     * @return array
     */
    public static function get_classes(): array {
        $hook = new content_types();
        $plugins = core_component::get_plugin_list("flexbookcontent");

        // The component cache can still describe the previous code tree immediately after a Git update.
        // Keep the bundled types usable until Moodle completes plugin discovery during upgrade/cache purge.
        if (!$plugins) {
            self::register_bundled_types($hook);
        }

        foreach ($plugins as $name => $path) {
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
     * Registers content types shipped inside the activity when the component registry is stale.
     *
     * @param content_types $hook Content type registry.
     * @return void
     */
    private static function register_bundled_types(content_types $hook): void {
        foreach (self::BUNDLED_TYPES as $type) {
            $file = dirname(__DIR__) . "/content/{$type}/classes/types/{$type}.php";
            if (!is_readable($file)) {
                continue;
            }

            require_once($file);
            $classname = "\\flexbookcontent_{$type}\\types\\{$type}";
            if (class_exists($classname, false)) {
                $hook->register($type, $classname);
            }
        }
    }

    /**
     * Gets localized options for the content type selector.
     *
     * @return array
     */
    public static function get_type_options(): array {
        $options = [];
        foreach (self::get_classes() as $type => $classname) {
            if (in_array($type, self::BUNDLED_TYPES, true)) {
                $options[$type] = get_string("contenttype{$type}", "mod_flexbook");
            } else {
                $options[$type] = $classname::get_name();
            }
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
