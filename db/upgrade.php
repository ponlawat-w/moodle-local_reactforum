<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * local_reactforum database upgrade steps.
 *
 * @package     local_reactforum
 * @copyright   2026 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade steps for local_reactforum.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_reactforum_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2023050801) {
        $table = new xmldb_table('local_reactforum_settings');
        $field = new xmldb_field('changeable', XMLDB_TYPE_INTEGER, '10', null, null, null, 1, 'delayedcounter');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2023050801, 'local', 'reactforum');
    }

    if ($oldversion < 2026041200) {
        $renames = [
            'reactforum_metadata' => 'local_reactforum_settings',
            'reactforum_buttons' => 'local_reactforum_reactions',
            'reactforum_reacted' => 'local_reactforum_user_reactions',
        ];

        foreach ($renames as $oldname => $newname) {
            $table = new xmldb_table("{$oldname}");
            if ($dbman->table_exists($table)) {
                $dbman->rename_table($table, $newname);
            }
        }

        upgrade_plugin_savepoint(true, 2026041200, 'local', 'reactforum');
    }

    return true;
}
