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
 * flashcards.php
 *
 * @package   flexbookcontent_flashcards
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace flexbookcontent_flashcards\types;

use mod_flexbook\types\template_content;

/**
 * FlexBook Flashcards content type.
 */
class flashcards extends template_content {

    /** @var string */
    protected static string $type = "flashcards";

    /**
     * Gets the localized content type name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string("pluginname", "flexbookcontent_flashcards");
    }
}
