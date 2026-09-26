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
 * chapter_navigation.js
 *
 * @package   mod_flexbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    const init = function() {
        document.addEventListener("keydown", function(event) {
            if (event.altKey && event.key === "ArrowLeft") {
                const previous = document.querySelector(".flexbook-chapter-navigation a:first-child");
                if (previous) {
                    previous.click();
                }
            }
            if (event.altKey && event.key === "ArrowRight") {
                const links = document.querySelectorAll(".flexbook-chapter-navigation a");
                const next = links.length ? links[links.length - 1] : null;
                if (next) {
                    next.click();
                }
            }
        });
        if (window.location.hash) {
            const target = document.querySelector(window.location.hash);
            if (target) {
                target.focus({preventScroll: true});
                target.scrollIntoView({behavior: "smooth", block: "start"});
            }
        }
    };
    return {init: init};
});
