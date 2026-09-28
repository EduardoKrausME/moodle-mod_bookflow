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
 * disclosure.js
 *
 * @package   flexbookcontent_disclosure
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax"], function(Ajax) {
    const init = function(flexbookId, trackProgress) {
        document.querySelectorAll(".flexbook-content-disclosure details").forEach(function(details) {
            details.addEventListener("toggle", function() {
                if (!details.open || !trackProgress) {
                    return;
                }
                const block = details.closest(".flexbook-content");
                Ajax.call([{
                    methodname: "mod_flexbook_mark_content_completed",
                    args: {
                        flexbookid: flexbookId,
                        contentid: Number(block.dataset.contentId),
                        metric: 100,
                        details: JSON.stringify({visited: [0]})
                    }
                }])[0].catch(function() {});
            });
        });
    };
    return {init: init};
});
