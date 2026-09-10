<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_senceluisalmon_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026031332) {
        $table = new xmldb_table('block_senceluisalmon_groups');
        $field = new xmldb_field('codigo_forzado', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'fecha_limite');

        // Si el campo no existe, lo agregamos
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_block_savepoint(true, 2026031332, 'senceluisalmon');
    }

    return true;
}
