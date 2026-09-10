<?php
require_once('../../config.php');
require_login();

$courseid = required_param('id', PARAM_INT);
global $DB, $USER;

// Insertar registro de éxito manual por clic en DJ
$log = new stdClass();
$log->userid = $USER->id;
$log->courseid = $courseid;
$log->eventtype = 'inicio';
$log->status = 'exito';
$log->glosa = 'Declaración Jurada Generada (Clic)';
$log->payload = json_encode(['action' => 'click_dj', 'user_agent' => $_SERVER['HTTP_USER_AGENT']]);
$log->timecreated = time();

$DB->insert_record('block_senceluisalmon_log', $log);

echo "Success";
