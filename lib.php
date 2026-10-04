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
 * lib.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_bookflow\content_type_manager;
use mod_bookflow\instance_manager;

/**
 * constant
 */
define("BOOKFLOW_NUMBERING_NONE", 0);
/**
 * constant
 */
define("BOOKFLOW_NUMBERING_NUMERIC", 1);
/**
 * constant
 */
define("BOOKFLOW_COMPLETION_PERCENTAGE", 0);
/**
 * constant
 */
define("BOOKFLOW_COMPLETION_REQUIRED", 1);
/**
 * constant
 */
define("BOOKFLOW_COMPLETION_COMBINED", 2);
/**
 * constant
 */
define("BOOKFLOW_COMPLETION_CHAPTERS", 3);

/**
 * bookflow_supports
 *
 * @param string $feature
 * @return bool|null
 */
function bookflow_supports(string $feature): ?bool {
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
 * bookflow_add_instance
 *
 * @param stdClass $data
 * @param mod_bookflow_mod_form|null $mform
 * @return int
 */
function bookflow_add_instance(stdClass $data, ?mod_bookflow_mod_form $mform = null): int {
    return instance_manager::create($data);
}

/**
 * bookflow_update_instance
 *
 * @param stdClass $data
 * @param mod_bookflow_mod_form|null $mform
 * @return bool
 */
function bookflow_update_instance(stdClass $data, ?mod_bookflow_mod_form $mform = null): bool {
    return instance_manager::update($data);
}

/**
 * bookflow_delete_instance
 *
 * @param int $id
 * @return bool
 */
function bookflow_delete_instance(int $id): bool {
    return instance_manager::delete($id);
}

/**
 * bookflow_get_coursemodule_info
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|null
 * @throws dml_exception
 */
function bookflow_get_coursemodule_info(stdClass $coursemodule): ?cached_cm_info {
    global $DB;

    $bookflow = $DB->get_record(
        "bookflow",
        ["id" => $coursemodule->instance],
        "id, name, intro, introformat, completionmode, completionpercentage"
    );
    if (!$bookflow) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $bookflow->name;
    $info->customdata = [
        "completionmode" => (int) $bookflow->completionmode,
        "completionpercentage" => (int) $bookflow->completionpercentage,
        "customcompletionrules" => [
            "completionmode" => 1,
        ],
    ];
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro("bookflow", $bookflow, $coursemodule->id, false);
    }
    return $info;
}

/**
 * bookflow_extend_settings_navigation
 *
 * @param settings_navigation $settings
 * @param navigation_node $node
 * @return void
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 */
function bookflow_extend_settings_navigation(settings_navigation $settings, navigation_node $node): void {
    global $PAGE;

    if (!$PAGE->cm || $PAGE->cm->modname != "bookflow") {
        return;
    }
    $context = context_module::instance($PAGE->cm->id);
    if (has_capability("mod/bookflow:managechapters", $context)) {
        $node->add(
            get_string("managechapters", "mod_bookflow"),
            new moodle_url("/mod/bookflow/chapters.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
    if (has_capability("mod/bookflow:import", $context)) {
        $node->add(
            get_string("import", "mod_bookflow"),
            new moodle_url("/mod/bookflow/import.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
    if (has_capability("mod/bookflow:export", $context)) {
        $node->add(
            get_string("export", "mod_bookflow"),
            new moodle_url("/mod/bookflow/export.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
    if (has_capability("mod/bookflow:viewreports", $context)) {
        $node->add(
            get_string("reports", "mod_bookflow"),
            new moodle_url("/mod/bookflow/report.php", ["id" => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING
        );
    }
}

/**
 * bookflow_pluginfile
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
function bookflow_pluginfile(
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
    require_capability("mod/bookflow:view", $context);

    $allowed = ["intro", "cover"];
    $forcedownloadareas = [];
    foreach (content_type_manager::get_classes() as $classname) {
        $allowed = array_merge($allowed, $classname::get_fileareas());
        $forcedownloadareas = array_merge(
            $forcedownloadareas,
            $classname::get_forcedownload_fileareas()
        );
    }
    $allowed = array_values(array_unique($allowed));
    $forcedownloadareas = array_values(array_unique($forcedownloadareas));

    if (!in_array($filearea, $allowed, true)) {
        send_file_not_found();
    }

    if (count($args) < 2) {
        send_file_not_found();
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? "/" . implode("/", $args) . "/" : "/";
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, "mod_bookflow", $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file(
        $file,
        0,
        0,
        $forcedownload || in_array($filearea, $forcedownloadareas, true),
        $options
    );
}
