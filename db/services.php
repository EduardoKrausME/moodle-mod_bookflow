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
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    "mod_bookflow_mark_contents_viewed" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "mark_contents_viewed",
        "description" => get_string("wsmark_contents_viewed", "mod_bookflow"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_mark_content_completed" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "mark_content_completed",
        "description" => get_string("wsmark_content_completed", "mod_bookflow"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_save_user_position" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "save_user_position",
        "description" => get_string("wssave_user_position", "mod_bookflow"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_get_user_progress" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "get_user_progress",
        "description" => get_string("wsget_user_progress", "mod_bookflow"),
        "type" => "read",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_get_chapter_progress" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "get_chapter_progress",
        "description" => get_string("wsget_chapter_progress", "mod_bookflow"),
        "type" => "read",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_create_bookmark" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "create_bookmark",
        "description" => get_string("wscreate_bookmark", "mod_bookflow"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_delete_bookmark" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "delete_bookmark",
        "description" => get_string("wsdelete_bookmark", "mod_bookflow"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_create_note" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "create_note",
        "description" => get_string("wscreate_note", "mod_bookflow"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_update_note" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "update_note",
        "description" => get_string("wsupdate_note", "mod_bookflow"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_delete_note" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "delete_note",
        "description" => get_string("wsdelete_note", "mod_bookflow"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_search_contents" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "search_contents",
        "description" => get_string("wssearch_contents", "mod_bookflow"),
        "type" => "read",
        "ajax" => true,
        "capabilities" => "mod/bookflow:view",
    ],
    "mod_bookflow_reorder_contents" => [
        "classname" => "\\mod_bookflow\\external\\api",
        "methodname" => "reorder_contents",
        "description" => get_string("wsreorder_contents", "mod_bookflow"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/bookflow:managecontent",
    ],
];
