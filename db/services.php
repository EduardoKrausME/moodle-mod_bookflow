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
 * services.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    "mod_flexbook_mark_contents_viewed" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "mark_contents_viewed",
        "description" => get_string("wsmark_contents_viewed", "mod_flexbook"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_mark_content_completed" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "mark_content_completed",
        "description" => get_string("wsmark_content_completed", "mod_flexbook"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_save_user_position" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "save_user_position",
        "description" => get_string("wssave_user_position", "mod_flexbook"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_get_user_progress" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "get_user_progress",
        "description" => get_string("wsget_user_progress", "mod_flexbook"),
        "type" => "read",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_get_chapter_progress" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "get_chapter_progress",
        "description" => get_string("wsget_chapter_progress", "mod_flexbook"),
        "type" => "read",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_create_bookmark" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "create_bookmark",
        "description" => get_string("wscreate_bookmark", "mod_flexbook"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_delete_bookmark" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "delete_bookmark",
        "description" => get_string("wsdelete_bookmark", "mod_flexbook"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_create_note" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "create_note",
        "description" => get_string("wscreate_note", "mod_flexbook"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_update_note" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "update_note",
        "description" => get_string("wsupdate_note", "mod_flexbook"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_delete_note" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "delete_note",
        "description" => get_string("wsdelete_note", "mod_flexbook"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_search_contents" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "search_contents",
        "description" => get_string("wssearch_contents", "mod_flexbook"),
        "type" => "read",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
    "mod_flexbook_reorder_contents" => [
        "classname" => "\\mod_flexbook\\external\\api",
        "methodname" => "reorder_contents",
        "description" => get_string("wsreorder_contents", "mod_flexbook"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:managecontent",
    ],
];
