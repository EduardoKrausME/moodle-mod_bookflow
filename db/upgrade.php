<?php
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
 * upgrade.php
 *
 * @package   mod_bookflow
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade mod_bookflow.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_bookflow_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026092801) {
        $table = new xmldb_table("bookflow");
        $field = new xmldb_field(
            "defaultcontenttype",
            XMLDB_TYPE_CHAR,
            "50",
            null,
            XMLDB_NOTNULL,
            null,
            null,
            "numbering"
        );

        $dbman->change_field_default($table, $field);

        upgrade_mod_savepoint(true, 2026092801, "bookflow");
    }

    if ($oldversion < 2026092802) {
        $table = new xmldb_table("bookflow_highlights");
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_mod_savepoint(true, 2026092802, "bookflow");
    }

    return true;
}
