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
 * Instalación: trae los datos de las integraciones SENCE anteriores
 * (block_senceluisalmon y block_sence) si existen en este sitio.
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Se ejecuta una vez, justo después de crear las tablas de install.xml.
 *
 * @return bool
 */
function xmldb_block_senceeducaxpert_install() {
    $r = \block_senceeducaxpert\local\migracion::ejecutar();
    mtrace('block_senceeducaxpert: migración desde los bloques SENCE anteriores: ' . json_encode($r));
    return true;
}
