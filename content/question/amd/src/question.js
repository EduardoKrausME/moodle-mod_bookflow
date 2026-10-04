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
 * Handles BookFlow question submission.
 *
 * @package bookflowcontent_question
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(
    ["core/ajax", "core/notification", "mod_bookflow/progress_tracker"],
    function(Ajax, Notification, ProgressTracker) {
        const init = function(bookflowId) {
            document.addEventListener("submit", function(event) {
                const form = event.target.closest("[data-bookflow-question]");
                if (!form) {
                    return;
                }

                event.preventDefault();
                const selected = form.querySelector("input[name='answer']:checked");
                if (!selected) {
                    return;
                }

                Ajax.call([{
                    methodname: "bookflowcontent_question_submit_answer",
                    args: {
                        bookflowid: bookflowId,
                        contentid: Number(form.dataset.bookflowQuestion),
                        answer: JSON.stringify(selected.value)
                    }
                }])[0].then(function(result) {
                    const feedback = form.querySelector("[data-region='question-feedback']");
                    if (feedback) {
                        feedback.textContent = result.feedback;
                    }
                    return Ajax.call([{
                        methodname: "mod_bookflow_get_user_progress",
                        args: {bookflowid: bookflowId}
                    }])[0];
                }).then(ProgressTracker.updateProgress).catch(Notification.exception);
            });
        };

        return {init: init};
    }
);
