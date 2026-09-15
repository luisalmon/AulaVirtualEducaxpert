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
 * Registra el clic del alumno en "Generar Declaración Jurada SENCE".
 *
 * sesskey() impide que otro sitio/página dispare esto por CSRF a nombre del
 * alumno. OJO: no impide que el propio alumno visite esta URL a mano en vez
 * de generar la DJ de verdad -sesskey solo protege contra falsificación
 * entre sitios, no contra un uso deshonesto del propio usuario-; SENCE no
 * entrega nada firmado que permita verificar que la DJ se generó realmente.
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('id', PARAM_INT);
require_login($courseid);
require_sesskey();

global $DB, $USER;

$log = new stdClass();
$log->userid = $USER->id;
$log->courseid = $courseid;
$log->eventtype = 'inicio';
$log->status = 'exito';
$log->glosa = 'Declaración Jurada Generada (Clic)';
$log->payload = json_encode([
    'action' => 'click_dj',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
]);
$log->timecreated = time();

$DB->insert_record('block_senceeducaxpert_log', $log);

echo 'Success';
