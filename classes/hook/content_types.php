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
 * content_types.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_flexbook\hook;

use coding_exception;
use mod_flexbook\types\content;

/**
 * Collects content type registrations from FlexBook and extension plugins.
 */
class content_types {
    /** @var array */
    private array $classes = [];

    /**
     * Registers a content type class.
     *
     * @param string $type Content type identifier.
     * @param string $classname Content type class name.
     * @return void
     */
    public function register(string $type, string $classname): void {
        if (!preg_match("/^[a-z][a-z0-9_]*$/", $type)) {
            throw new coding_exception("Invalid FlexBook content type: {$type}");
        }
        if (!is_subclass_of($classname, content::class)) {
            throw new coding_exception("{$classname} must extend mod_flexbook\\types\\content");
        }
        if (isset($this->classes[$type]) && $this->classes[$type] != $classname) {
            throw new coding_exception("FlexBook content type already registered: {$type}");
        }
        $this->classes[$type] = $classname;
    }

    /**
     * Gets the registered content type classes.
     *
     * @return array
     */
    public function get_classes(): array {
        return $this->classes;
    }
}
