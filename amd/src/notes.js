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
 * notes.js
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax", "core/notification"], function(Ajax, Notification) {
    const init = function(flexbookId) {
        document.addEventListener("submit", function(event) {
            const form = event.target.closest("[data-region='flexbook-note-form']");
            if (!form) {
                return;
            }
            event.preventDefault();
            const data = new FormData(form);
            const selection = window.getSelection();
            Ajax.call([{
                methodname: "mod_flexbook_create_note",
                args: {
                    flexbookid: flexbookId,
                    chapterid: Number(data.get("chapterid") || 0),
                    contentid: Number(data.get("contentid") || 0),
                    note: data.get("note"),
                    selectiontext: data.get("selectiontext")
                        || (selection ? selection.toString().trim() : "")
                }
            }])[0].then(function() {
                form.reset();
                return true;
            }).catch(Notification.exception);
        });
    };
    return {init: init};
});
