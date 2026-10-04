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
 * content_sorting.js
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax", "core/notification"], function(Ajax, Notification) {
    const init = function(bookflowId, chapterId) {
        const list = document.querySelector("[data-region='content-list']");
        if (!list) {
            return;
        }
        let dragged = null;
        list.querySelectorAll(".bookflow-content").forEach(function(block) {
            block.draggable = true;
            block.addEventListener("dragstart", function() {
                dragged = block;
                block.classList.add("is-dragging");
            });
            block.addEventListener("dragend", function() {
                block.classList.remove("is-dragging");
                dragged = null;
            });
            block.addEventListener("dragover", function(event) {
                event.preventDefault();
                if (!dragged || dragged === block) {
                    return;
                }
                const box = block.getBoundingClientRect();
                list.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? block : block.nextSibling);
            });
        });
        list.addEventListener("drop", function(event) {
            event.preventDefault();
            const contentIds = Array.from(list.querySelectorAll(".bookflow-content"))
                .map(function(block) {
                    return Number(block.dataset.contentId);
                });
            Ajax.call([{
                methodname: "mod_bookflow_reorder_contents",
                args: {bookflowid: bookflowId, chapterid: chapterId, contentids: contentIds}
            }])[0].catch(Notification.exception);
        });
    };
    return {init: init};
});
