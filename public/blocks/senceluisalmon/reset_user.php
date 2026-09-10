<?php
require_once('../../config.php');
require_login();

$courseid = required_param('cid', PARAM_INT);
$userid = required_param('uid', PARAM_INT);

// Verificamos que quien hace esto tenga permisos de profesor/gestor en el curso
$context = context_course::instance($courseid);
if (!has_capability('moodle/course:update', $context)) {
    die('Acceso denegado');
}

global $DB;
$hoy_ts = strtotime('today');

// Eliminamos cualquier registro de "éxito" de este usuario en este curso durante el día de hoy
$DB->delete_records_select('block_senceluisalmon_log', "userid = ? AND courseid = ? AND status = 'exito' AND timecreated >= ?", array($userid, $courseid, $hoy_ts));

echo "OK";
