<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Pasos de actualización de block_senceeducaxpert.
 *
 * install.xml ya crea las tablas completas (incluido codigo_forzado), así
 * que una instalación nueva no pasa por aquí. Se deja la función para
 * futuras versiones.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_senceeducaxpert_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    return true;
}
