// This file is part of Moodle - http://moodle.org/
/**
 * Tracks download completion.
 *
 * @package flexbookcontent_download
 */

define(["mod_flexbook/progress_tracker"], function(ProgressTracker) {
    const init = function(flexbookId) {
        document.addEventListener("click", function(event) {
            const link = event.target.closest("[data-flexbook-download]");
            if (!link) {
                return;
            }
            ProgressTracker.complete(
                flexbookId,
                Number(link.dataset.flexbookDownload),
                100,
                {clicked: true}
            );
        });
    };

    return {init: init};
});
