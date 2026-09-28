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
 * accordion.js
 *
 * @package   flexbookcontent_accordion
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax"], function(Ajax) {
    const init = function(flexbookId, trackProgress) {
        document.querySelectorAll(".flexbook-content-accordion").forEach(function(block) {
            const visited = new Set();
            block.querySelectorAll(".accordion-button").forEach(function(button) {
                button.addEventListener("click", function() {
                    const panel = button.closest(".accordion-item").querySelector(".accordion-collapse");
                    const open = !panel.classList.contains("show");
                    panel.classList.toggle("show", open);
                    button.classList.toggle("collapsed", !open);
                    button.setAttribute("aria-expanded", open ? "true" : "false");
                    if (open) {
                        visited.add(Number(button.dataset.itemIndex));
                        if (!trackProgress) {
                            return;
                        }
                        Ajax.call([{
                            methodname: "mod_flexbook_mark_content_completed",
                            args: {
                                flexbookid: flexbookId,
                                contentid: Number(block.dataset.contentId),
                                metric: visited.size,
                                details: JSON.stringify({visited: Array.from(visited)})
                            }
                        }])[0].catch(function() {});
                    }
                });
            });
        });
    };
    return {init: init};
});
