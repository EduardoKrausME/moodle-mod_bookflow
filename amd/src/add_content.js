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
 * Progressive enhancement for the add content link.
 *
 * The link always points to content.php so it still works when JavaScript
 * is unavailable. When JavaScript is active, this module intercepts the
 * click and opens the content type chooser as a modal.
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    let initialised = false;
    let activeModal = null;
    let activeTrigger = null;

    const getBackdrop = function(modal) {
        return document.querySelector('[data-bookflow-modal-backdrop="' + modal.id + '"]');
    };

    const hideModal = function(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove("show");
        modal.style.display = "none";
        modal.setAttribute("aria-hidden", "true");
        modal.removeAttribute("aria-modal");

        const backdrop = getBackdrop(modal);
        if (backdrop) {
            backdrop.remove();
        }

        document.body.classList.remove("modal-open");

        if (activeModal === modal) {
            activeModal = null;
            if (activeTrigger) {
                activeTrigger.focus();
            }
            activeTrigger = null;
        }
    };

    const showModal = function(modal, trigger) {
        if (!modal) {
            return false;
        }

        activeModal = modal;
        activeTrigger = trigger;

        modal.style.display = "block";
        modal.removeAttribute("aria-hidden");
        modal.setAttribute("aria-modal", "true");
        modal.classList.add("show");
        document.body.classList.add("modal-open");

        if (!getBackdrop(modal)) {
            const backdrop = document.createElement("div");
            backdrop.className = "modal-backdrop fade show";
            backdrop.setAttribute("data-bookflow-modal-backdrop", modal.id);
            backdrop.addEventListener("click", function() {
                hideModal(modal);
            });
            document.body.appendChild(backdrop);
        }

        const firstLink = modal.querySelector(".modal-body a");
        if (firstLink) {
            firstLink.focus();
        }

        return true;
    };

    const init = function() {
        if (initialised) {
            return;
        }
        initialised = true;

        document.addEventListener("click", function(event) {
            const trigger = event.target.closest('[data-action="bookflow-add-content"]');
            if (trigger) {
                const modalId = trigger.getAttribute("data-modal-id");
                const modal = modalId ? document.getElementById(modalId) : null;

                if (modal && showModal(modal, trigger)) {
                    event.preventDefault();
                }
                return;
            }

            const close = event.target.closest('[data-action="bookflow-close-add-content"]');
            if (close) {
                event.preventDefault();
                hideModal(close.closest(".modal"));
            }
        });

        document.addEventListener("keydown", function(event) {
            if (event.key === "Escape" && activeModal) {
                event.preventDefault();
                hideModal(activeModal);
            }
        });
    };

    return {init: init};
});
