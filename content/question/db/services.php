<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Question web services.
 *
 * @package flexbookcontent_question
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    "flexbookcontent_question_submit_answer" => [
        "classname" => "\\flexbookcontent_question\\external\\api",
        "methodname" => "submit_answer",
        "description" => get_string("wssubmitanswer", "flexbookcontent_question"),
        "type" => "write",
        "ajax" => true,
        "capabilities" => "mod/flexbook:view",
    ],
];
