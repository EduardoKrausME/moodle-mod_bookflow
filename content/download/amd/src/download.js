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
 * Tracks download completion.
 *
 * @package bookflowcontent_download
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["mod_bookflow/progress_tracker"], function(ProgressTracker) {
    const init = function(bookflowId) {
        document.addEventListener("click", function(event) {
            const link = event.target.closest("[data-bookflow-download]");
            if (!link) {
                return;
            }
            ProgressTracker.complete(
                bookflowId,
                Number(link.dataset.bookflowDownload),
                100,
                {clicked: true}
            );
        });
    };

    return {init: init};
});
