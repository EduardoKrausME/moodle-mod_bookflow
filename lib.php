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

defined('MOODLE_INTERNAL') || die;

/**
 * lib.php
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_flexbook\instance_manager;

/**
 * constant
 */
define("FLEXBOOK_NUMBERING_NONE", 0);
/**
 * constant
 */
define("FLEXBOOK_NUMBERING_NUMERIC", 1);
/**
 * constant
 */
define("FLEXBOOK_COMPLETION_PERCENTAGE", 0);
/**
 * constant
 */
define("FLEXBOOK_COMPLETION_REQUIRED", 1);
/**
 * constant
 */
define("FLEXBOOK_COMPLETION_COMBINED", 2);
/**
 * constant
 */
define("FLEXBOOK_COMPLETION_CHAPTERS", 3);

/**
 * flexbook_supports
 *
 * @param string $feature
 * @return bool|null
 */
function flexbook_supports(string $feature): ?bool {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_RESOURCE,
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => false,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_CONTENT,
        default => null,
    };
}

/**
 * flexbook_add_instance
 *
 * @param stdClass $data
 * @param mod_flexbook_mod_form|null $mform
 * @return int
 */
function flexbook_add_instance(stdClass $data, ?mod_flexbook_mod_form $mform = null): int {
    return instance_manager::create($data);
}

/**
 * flexbook_update_instance
 *
 * @param stdClass $data
 * @param mod_flexbook_mod_form|null $mform
 * @return bool
 */
function flexbook_update_instance(stdClass $data, ?mod_flexbook_mod_form $mform = null): bool {
    return instance_manager::update($data);
}

/**
 * flexbook_delete_instance
 *
 * @param int $id
 * @return bool
 */
function flexbook_delete_instance(int $id): bool {
    return instance_manager::delete($id);
}

/**
 * flexbook_get_coursemodule_info
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 * @throws dml_exception
 */
function flexbook_get_coursemodule_info(stdClass $coursemodule): ?cached_cm_info {
    global $DB;

    $flexbook = $DB->get_record(
        "flexbook",
        ["id" => $coursemodule->instance],
        "id, name, intro, introformat, completionmode, completionpercentage"
    );
    if (!$flexbook) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $flexbook->name;
    $info->customdata = [
        "completionmode" => (int) $flexbook->completionmode,
        "completionpercentage" => (int) $flexbook->completionpercentage,
        "customcompletionrules" => [
            "completionmode" => 1,
        ],
    ];
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro("flexbook", $flexbook, $coursemodule->id, false);
    }
    return $info;
}

/**
 * flexbook_extend_settings_navigation
 *
 * @param settings_navigation $settings
 * @param navigation_node $node
 * @return void
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 */
function flexbook_extend_settings_navigation(settings_navigation $settings, navigation_node $node): void {
    global $PAGE;

    if (!$PAGE->cm || $PAGE->cm->modname != "flexbook") {
        return;
    }
    $context = context_module::instance($PAGE->cm->id);
    if (has_capability("mod/flexbook:managechapters", $context)) {
        $node->add(
            get_string("managechapters", "mod_flexbook"),
            new moodle_url("/mod/flexbook/chapters.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
    if (has_capability("mod/flexbook:import", $context)) {
        $node->add(
            get_string("import", "mod_flexbook"),
            new moodle_url("/mod/flexbook/import.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
    if (has_capability("mod/flexbook:export", $context)) {
        $node->add(
            get_string("export", "mod_flexbook"),
            new moodle_url("/mod/flexbook/export.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
    if (has_capability("mod/flexbook:viewreports", $context)) {
        $node->add(
            get_string("reports", "mod_flexbook"),
            new moodle_url("/mod/flexbook/report.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
}

/**
 * flexbook_pluginfile
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return void
 * @throws coding_exception
 * @throws moodle_exception
 * @throws require_login_exception
 * @throws required_capability_exception
 */
function flexbook_pluginfile(
    stdClass $course,
    stdClass $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): void {
    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }
    require_login($course, true, $cm);
    require_capability("mod/flexbook:view", $context);

    $allowed = ["intro", "content", "cover", "download"];
    if (!in_array($filearea, $allowed)) {
        send_file_not_found();
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = "/" . implode("/", $args) . "/";
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, "mod_flexbook", $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload || $filearea == "download", $options);
}
