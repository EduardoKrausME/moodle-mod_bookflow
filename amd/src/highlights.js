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
 * highlights.js
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/ajax", "core/notification"], function(Ajax, Notification) {
    const markQuote = function(block, quote, color, highlightId) {
        if (!quote || block.querySelector("[data-applied-highlight='" + highlightId + "']")) {
            return;
        }
        const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
        let node = walker.nextNode();
        while (node) {
            const index = node.nodeValue.indexOf(quote);
            if (index !== -1) {
                const range = document.createRange();
                range.setStart(node, index);
                range.setEnd(node, index + quote.length);
                const mark = document.createElement("mark");
                mark.className = "flexbook-highlight-" + color;
                mark.dataset.appliedHighlight = highlightId;
                try {
                    range.surroundContents(mark);
                } catch (error) {
                    return;
                }
                return;
            }
            node = walker.nextNode();
        }
    };

    const init = function(flexbookId) {
        document.querySelectorAll("[data-region='stored-highlights'] [data-highlight-id]").forEach(function(item) {
            const block = item.closest(".flexbook-content");
            markQuote(block, item.dataset.quote, item.dataset.color, item.dataset.highlightId);
        });
        document.addEventListener("click", function(event) {
            const button = event.target.closest("[data-action='flexbook-highlight']");
            if (!button) {
                return;
            }
            const selection = window.getSelection();
            const text = selection ? selection.toString().trim() : "";
            const block = button.closest(".flexbook-content");
            if (!text || !block) {
                return;
            }
            const range = selection.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
            Ajax.call([{
                methodname: "mod_flexbook_save_highlight",
                args: {
                    flexbookid: flexbookId,
                    chapterid: Number(document.querySelector(".flexbook-reader").dataset.chapterId),
                    contentid: Number(block.dataset.contentId),
                    selectiontext: text,
                    selector: JSON.stringify({quote: text}),
                    color: button.dataset.color || "yellow"
                }
            }])[0].then(function(result) {
                if (range && !range.collapsed) {
                    const mark = document.createElement("mark");
                    mark.className = "flexbook-highlight-" + (button.dataset.color || "yellow");
                    mark.dataset.appliedHighlight = result.id;
                    try {
                        range.surroundContents(mark);
                    } catch (error) {
                        return result;
                    }
                }
                if (selection) {
                    selection.removeAllRanges();
                }
                return result;
            }).catch(Notification.exception);
        });
    };
    return {init: init};
});
