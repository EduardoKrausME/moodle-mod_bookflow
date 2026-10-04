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
 * Question external API.
 *
 * @package bookflowcontent_question
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bookflowcontent_question\external;

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use bookflowcontent_question\question_manager;

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->libdir}/externallib.php");

/**
 * Exposes question-specific AJAX operations.
 */
class api extends external_api {
    /**
     * Defines submit-answer parameters.
     */
    public static function submit_answer_parameters(): external_function_parameters {
        return new external_function_parameters([
            "bookflowid" => new external_value(PARAM_INT, "BookFlow id"),
            "contentid" => new external_value(PARAM_INT, "Content id"),
            "answer" => new external_value(PARAM_RAW, "JSON answer"),
        ]);
    }

    /**
     * Submits one answer.
     */
    public static function submit_answer(int $bookflowid, int $contentid, string $answer): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::submit_answer_parameters(),
            compact("bookflowid", "contentid", "answer")
        );

        $bookflow = $DB->get_record("bookflow", ["id" => $params["bookflowid"]], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance(
            "bookflow",
            $bookflow->id,
            $bookflow->course,
            false,
            MUST_EXIST
        );
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability("mod/bookflow:view", $context);

        return question_manager::submit_answer(
            $params["bookflowid"],
            $params["contentid"],
            $USER->id,
            $params["answer"]
        );
    }

    /**
     * Defines submit-answer return values.
     */
    public static function submit_answer_returns(): external_single_structure {
        return new external_single_structure([
            "iscorrect" => new external_value(PARAM_BOOL, "Answer correctness"),
            "attemptnumber" => new external_value(PARAM_INT, "Attempt number"),
            "feedback" => new external_value(PARAM_RAW, "Feedback"),
        ]);
    }
}
