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
 * de sesión exitoso. SENCE hace un POST entre sitios (no nuestro propio JS),
 * y el navegador no manda la cookie de sesión de Moodle en ese salto
 * (SameSite) -así que NO se puede depender de require_login() para saber
 * quién es el alumno-. Se identifica con el token de un solo uso que se
 * generó justo antes de mandarlo a SENCE (ver
 * block_senceeducaxpert::create_return_token()).
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
// block_senceeducaxpert extends block_base: normalmente lo carga el gestor de
// bloques, pero aquí se usa la clase directamente (fuera de ese flujo) para
// llegar a sus métodos estáticos de token.
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/senceeducaxpert/block_senceeducaxpert.php');

global $CFG, $DB, $USER;

$courseid = required_param('id', PARAM_INT);
$tokenparam = optional_param('t', '', PARAM_ALPHANUM);
$idsesionsence = optional_param('IdSesionSence', null, PARAM_RAW);

$identity = block_senceeducaxpert::resolve_return_token($tokenparam, $courseid);

if ($identity !== null && (!isloggedin() || isguestuser() || $USER->id != $identity->userid)) {
    // Token válido: restablecemos la sesión real del alumno (misma función
    // que usa auth_userkey en este sitio) para que el resto de la página
    // -y la cookie que se manda de vuelta al navegador- se comporten como
    // si nunca se hubiera perdido la sesión.
    $returninguser = $DB->get_record('user', ['id' => $identity->userid], '*', MUST_EXIST);
    complete_user_login($returninguser);
}

if (!isloggedin() || isguestuser()) {
    // Sin token válido y sin sesión: acceso directo por URL, no la vuelta
    // real de SENCE. require_login() redirige al login, que es lo correcto
    // aquí.
    require_login($courseid);
}

$userid = $USER->id;

// Antitrampas: SENCE siempre hace un POST al retomar. Un GET (URL tecleada a
// mano) o un POST vacío se bloquean y se auditan.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
    $logtrampa = new stdClass();
    $logtrampa->userid = $userid;
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

// "Inicio" o "cierre": si ya hay un inicio exitoso hoy para este alumno y
// curso, esta vuelta es el cierre. No se puede usar $SESSION para esto -no
// hay continuidad de sesión en un POST entre sitios sin cookie-.
$hoyts = strtotime('today');
$yainicio = $DB->record_exists_select(
    'block_senceeducaxpert_log',
    "userid = ? AND courseid = ? AND eventtype = 'inicio' AND status = 'exito' AND timecreated >= ?",
    [$userid, $courseid, $hoyts]
);
$eventtype = $yainicio ? 'cierre' : 'inicio';

$payload = array(
    'INFO_OTEC' => array(
        'RutOtec' => get_config('block_senceeducaxpert', 'otec_rut'),
        'Token' => get_config('block_senceeducaxpert', 'otec_token'),
    ),
    'RESPUESTA_SENCE' => $_POST,
);

$log = new stdClass();
$log->userid = $userid;
$log->courseid = $courseid;
$log->eventtype = $eventtype;
$log->status = 'exito';
$log->glosa = 'Conexión Exitosa';
$log->payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$log->timecreated = time();

$DB->insert_record('block_senceeducaxpert_log', $log);

redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
