<?php

defined('MOODLE_INTERNAL') or die();

function xmldb_local_reactforum_upgrade($oldversion) {
    /**
     * @var \moodle_database $DB
     */
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2023041000) {
        $tablereactions = new xmldb_table('reactforum_reactions');
        if ($dbman->table_exists($tablereactions)) {
            $dbman->rename_table($tablereactions, 'reactforum_buttons');
        }

        $tableuserreactions = new xmldb_table('reactforum_user_reactions');
        if ($dbman->table_exists($tableuserreactions)) {
            $dbman->rename_table($tableuserreactions, 'reactforum_reacted');
        }

        $tablereacted = new xmldb_table('reactforum_reacted');
        $field = new xmldb_field('user', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        if ($dbman->field_exists($tablereacted, $field)) {
            $dbman->rename_field($tablereacted, $field, 'userid');
        }
        $index = new xmldb_index('user', XMLDB_INDEX_NOTUNIQUE, ['user']);
        if ($dbman->index_exists($tablereacted, $index)) {
            $dbman->drop_index($tablereacted, $index);
            $newindex = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $dbman->add_index($tablereacted, $newindex);
        }

        upgrade_plugin_savepoint(true, 2023041000, 'local', 'reactforum');
    }

    if ($oldversion < 2023050801) {
        $table = new xmldb_table('reactforum_metadata');
        $field = new xmldb_field('changeable', XMLDB_TYPE_INTEGER, '10', null, null, null, 1, 'delayedcounter');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2023050801, 'local', 'reactforum');
    }
}
