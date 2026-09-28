// This file is part of Moodle - http://moodle.org/
/**
 * Handles FlexBook question submission.
 *
 * @package flexbookcontent_question
 */

define(
    ["core/ajax", "core/notification", "mod_flexbook/progress_tracker"],
    function(Ajax, Notification, ProgressTracker) {
        const init = function(flexbookId) {
            document.addEventListener("submit", function(event) {
                const form = event.target.closest("[data-flexbook-question]");
                if (!form) {
                    return;
                }

                event.preventDefault();
                const selected = form.querySelector("input[name='answer']:checked");
                if (!selected) {
                    return;
                }

                Ajax.call([{
                    methodname: "mod_flexbook_submit_question_answer",
                    args: {
                        flexbookid: flexbookId,
                        contentid: Number(form.dataset.flexbookQuestion),
                        answer: JSON.stringify(selected.value)
                    }
                }])[0].then(function(result) {
                    const feedback = form.querySelector("[data-region='question-feedback']");
                    if (feedback) {
                        feedback.textContent = result.feedback;
                    }
                    return Ajax.call([{
                        methodname: "mod_flexbook_get_user_progress",
                        args: {flexbookid: flexbookId}
                    }])[0];
                }).then(ProgressTracker.updateProgress).catch(Notification.exception);
            });
        };

        return {init: init};
    }
);
