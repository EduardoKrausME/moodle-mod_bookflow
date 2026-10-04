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
 * flashcards.js
 *
 * @package   bookflowcontent_flashcards
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax"], function(Ajax) {
    const init = function(bookflowId, trackProgress) {
        document.querySelectorAll(".bookflow-content-flashcards").forEach(function(block) {
            const visited = new Set();
            block.querySelectorAll(".bookflow-flashcard").forEach(function(card) {
                card.addEventListener("click", function() {
                    const flipped = card.getAttribute("aria-pressed") !== "true";
                    card.setAttribute("aria-pressed", flipped ? "true" : "false");
                    card.classList.toggle("is-flipped", flipped);
                    if (flipped) {
                        visited.add(Number(card.dataset.itemIndex));
                        if (!trackProgress) {
                            return;
                        }
                        Ajax.call([{
                            methodname: "mod_bookflow_mark_content_completed",
                            args: {
                                bookflowid: bookflowId,
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
