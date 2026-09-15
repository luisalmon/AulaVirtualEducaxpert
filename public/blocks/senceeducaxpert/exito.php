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
 * URL de retoma de SENCE: aquí redirige el portal SENCE tras un inicio/cierre
 * de sesión exitoso. Es un destino EXTERNO (SENCE hace el POST, no nuestro
 * propio JS), así que no lleva sesskey -no lo puede generar SENCE-; la
 * mitigación es exigir sesión Moodle activa + POST no vacío.
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('id', PARAM_INT);
require_login($courseid);

global $SESSION, $CFG, $DB, $USER;

$idsesionsence = optional_param('IdSesionSence', null, PARAM_RAW);

// Antitrampas: SENCE siempre hace un POST al retomar. Un GET (URL tecleada a
// mano) o un POST vacío se bloquean y se auditan.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
    $logtrampa = new stdClass();
    $logtrampa->userid = $USER->id;
    $logtrampa->courseid = $courseid;
    $logtrampa->eventtype = 'inicio';
    $logtrampa->status = 'error';
    $logtrampa->glosa = 'Bloqueado: Intento de acceso directo por URL a exito.php sin pasar por SENCE.';
    $logtrampa->payload = json_encode([
        'SERVER_METHOD' => $_SERVER['REQUEST_METHOD'],
        'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
    $logtrampa->timecreated = time();
    $DB->insert_record('block_senceeducaxpert_log', $logtrampa);

    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
}

$payload = array(
    'INFO_OTEC' => array(
        'RutOtec' => get_config('block_senceeducaxpert', 'otec_rut'),
        'Token' => get_config('block_senceeducaxpert', 'otec_token'),
    ),
    'RESPUESTA_SENCE' => $_POST,
);

$log = new stdClass();
$log->userid = $USER->id;
$log->courseid = $courseid;
$log->eventtype = isset($SESSION->sence_sesion_id) ? 'cierre' : 'inicio';
$log->status = 'exito';
$log->glosa = 'Conexión Exitosa';
$log->payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$log->timecreated = time();

$DB->insert_record('block_senceeducaxpert_log', $log);

if ($idsesionsence) {
    $SESSION->sence_sesion_id = $idsesionsence;
} else {
    unset($SESSION->sence_sesion_id);
}

redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
