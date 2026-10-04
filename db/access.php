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
 * access.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$capabilities = [
    "mod/bookflow:addinstance" => [
        "riskbitmask" => RISK_XSS,
        "captype" => "write",
        "contextlevel" => CONTEXT_COURSE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW],
        "clonepermissionsfrom" => "moodle/course:manageactivities",
    ],
    "mod/bookflow:view" => [
        "captype" => "read",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => [
            "guest" => CAP_ALLOW,
            "student" => CAP_ALLOW,
            "teacher" => CAP_ALLOW,
            "editingteacher" => CAP_ALLOW,
            "manager" => CAP_ALLOW,
        ],
    ],
    "mod/bookflow:managechapters" => [
        "riskbitmask" => RISK_XSS,
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW],
    ],
    "mod/bookflow:managecontent" => [
        "riskbitmask" => RISK_XSS,
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW],
    ],
    "mod/bookflow:editunsafecontent" => [
        "riskbitmask" => RISK_XSS,
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["manager" => CAP_ALLOW],
    ],
    "mod/bookflow:viewreports" => [
        "riskbitmask" => RISK_PERSONAL,
        "captype" => "read",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["teacher" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW],
    ],
    "mod/bookflow:export" => [
        "captype" => "read",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["teacher" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW],
    ],
    "mod/bookflow:import" => [
        "riskbitmask" => RISK_XSS,
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW],
    ],
    "mod/bookflow:viewallnotes" => [
        "riskbitmask" => RISK_PERSONAL,
        "captype" => "read",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["manager" => CAP_ALLOW],
    ],
];
