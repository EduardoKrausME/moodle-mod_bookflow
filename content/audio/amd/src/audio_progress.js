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
 * audio_progress.js
 *
 * @package   flexbookcontent_audio
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax", "core/notification"], function(Ajax, Notification) {
    const init = function(flexbookId) {
        document.querySelectorAll(".flexbook-content audio").forEach(function(media) {
            const block = media.closest(".flexbook-content");
            let lastSent = -1;
            const send = function(force) {
                if (!media.duration || !Number.isFinite(media.duration)) {
                    return;
                }
                const percentage = Math.min(100, media.currentTime / media.duration * 100);
                const bucket = Math.floor(percentage / 5);
                if (!force && bucket === lastSent) {
                    return;
                }
                lastSent = bucket;
                Ajax.call([{
                    methodname: "mod_flexbook_mark_content_completed",
                    args: {
                        flexbookid: flexbookId,
                        contentid: Number(block.dataset.contentId),
                        metric: percentage,
                        details: JSON.stringify({
                            playedSeconds: Math.floor(media.currentTime),
                            duration: Math.floor(media.duration)
                        })
                    }
                }])[0].catch(function(error) {
                    if (force) {
                        Notification.exception(error);
                    }
                });
            };
            media.addEventListener("timeupdate", function() {
                send(false);
            });
            media.addEventListener("ended", function() {
                send(true);
            });
        });
    };
    return {init: init};
});
