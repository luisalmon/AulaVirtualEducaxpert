<?php
require_once('../../config.php');
require_login(); // Aseguramos que el usuario esté logueado en Moodle
global $SESSION, $CFG, $DB, $USER;

$courseid = required_param('id', PARAM_INT);
$idsesionsence = optional_param('IdSesionSence', null, PARAM_RAW);

// --- 1. VALIDACIÓN DE SEGURIDAD ANTITRAMPAS ---
// SENCE SIEMPRE envía una petición POST cuando retorna a la página de éxito.
// Si alguien entra tipeando la URL (petición GET) o la petición viene vacía, lo bloqueamos.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {

    // Registrar el intento de salto (trampa) en el historial para auditoría
    $log_trampa = new stdClass();
    $log_trampa->userid = $USER->id;
    $log_trampa->courseid = $courseid;
    $log_trampa->eventtype = 'inicio';
    $log_trampa->status = 'error';
    $log_trampa->glosa = 'Bloqueado: Intento de acceso directo por URL a exito.php sin pasar por SENCE.';
    $log_trampa->payload = json_encode(['SERVER_METHOD' => $_SERVER['REQUEST_METHOD'], 'USER_AGENT' => $_SERVER['HTTP_USER_AGENT']]);
    $log_trampa->timecreated = time();
    $DB->insert_record('block_senceluisalmon_log', $log_trampa);

    // Redirigir al curso inmediatamente sin darle el éxito
    redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
    exit;
}
// ----------------------------------------------

// 2. Recopilar datos técnicos para el historial (Solo llega aquí si es POST legítimo)
$payload = array(
    'INFO_OTEC' => array(
        'RutOtec' => get_config('block_senceluisalmon', 'otec_rut'),
        'Token'   => get_config('block_senceluisalmon', 'otec_token')
    ),
    'RESPUESTA_SENCE' => $_POST // Captura IdSesionSence, FechaHora, etc.
);

// 3. Registrar actividad
$log = new stdClass();
$log->userid = $USER->id;
$log->courseid = $courseid;
$log->eventtype = isset($SESSION->sence_sesion_id) ? 'cierre' : 'inicio';
$log->status = 'exito';
$log->glosa = 'Conexión Exitosa';
$log->payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$log->timecreated = time();

$DB->insert_record('block_senceluisalmon_log', $log);

// 4. Gestionar la sesión en Moodle
if ($idsesionsence) {
    $SESSION->sence_sesion_id = $idsesionsence;
} else {
    unset($SESSION->sence_sesion_id);
}

redirect($CFG->wwwroot . '/course/view.php?id=' . $courseid);
