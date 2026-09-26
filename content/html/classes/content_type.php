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
 * content_type.php
 *
 * @package   flexbookcontent_html
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace flexbookcontent_html;

use flexbookcontent_html\types\html;
use mod_flexbook\hook\content_types;

/**
 * Registers the HTML content type in FlexBook.
 */
class content_type {
    /**
     * Registers the content type.
     *
     * @param content_types $hook Content type registry.
     * @return void
     */
    public static function register(content_types $hook): void {
        $hook->register("html", html::class);
    }
}
