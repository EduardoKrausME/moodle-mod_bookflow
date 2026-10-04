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
 * video_progress.js
 *
 * @package   bookflowcontent_video
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax", "core/notification"], function(Ajax, Notification) {
    const init = function(bookflowId) {
        document.querySelectorAll(".bookflow-content video").forEach(function(media) {
            const block = media.closest(".bookflow-content");
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
                    methodname: "mod_bookflow_mark_content_completed",
                    args: {
                        bookflowid: bookflowId,
                        contentid: Number(block.dataset.contentId),
                        metric: percentage,
                        details: JSON.stringify({
                            watchedSeconds: Math.floor(media.currentTime),
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
