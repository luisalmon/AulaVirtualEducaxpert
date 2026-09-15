<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Pasos de actualización de block_senceeducaxpert.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_senceeducaxpert_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026091501) {
        // Tabla de tokens de un solo uso: identifica al alumno cuando SENCE
        // vuelve por POST entre sitios y la cookie de sesión no viaja.
        $table = new xmldb_table('block_senceeducaxpert_tokens');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('token', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('token', XMLDB_KEY_UNIQUE, ['token']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026091501, 'senceeducaxpert');
    }

    return true;
}
