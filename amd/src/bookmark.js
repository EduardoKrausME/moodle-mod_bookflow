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
 * bookmark.js
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax", "core/notification"], function(Ajax, Notification) {
    const init = function(flexbookId) {
        document.addEventListener("click", function(event) {
            const button = event.target.closest("[data-action='flexbook-bookmark']");
            if (!button) {
                return;
            }
            const bookmarked = button.getAttribute("aria-pressed") === "true";
            const request = bookmarked ? {
                methodname: "mod_flexbook_delete_bookmark",
                args: {
                    flexbookid: flexbookId,
                    bookmarkid: Number(button.dataset.bookmarkId)
                }
            } : {
                methodname: "mod_flexbook_create_bookmark",
                args: {
                    flexbookid: flexbookId,
                    itemtype: button.dataset.itemType,
                    chapterid: Number(button.dataset.chapterId || 0),
                    contentid: Number(button.dataset.contentId || 0)
                }
            };
            button.disabled = true;
            Ajax.call([request])[0].then(function() {
                window.location.reload();
                return true;
            }).catch(function(error) {
                button.disabled = false;
                Notification.exception(error);
            });
        });
    };
    return {init: init};
});
