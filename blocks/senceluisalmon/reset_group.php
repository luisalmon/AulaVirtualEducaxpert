<?php
require_once('../../config.php');
require_login();

$groupid = required_param('gid', PARAM_INT);
$courseid = required_param('cid', PARAM_INT);

// Verificar permisos de gestor/profesor
$context = context_course::instance($courseid);
if (!has_capability('moodle/course:update', $context)) {
    die('Acceso denegado');
}

global $DB;
$hoy_ts = strtotime('today');

// Obtener todos los alumnos del grupo
$members = groups_get_members($groupid, 'u.id');

if (!empty($members)) {
    $userids = array_keys($members);
    // Preparamos la consulta para eliminar a todos los IDs del grupo
    list($insql, $inparams) = $DB->get_in_or_equal($userids);
    $params = array_merge([$courseid, $hoy_ts], $inparams);
    
    // Eliminamos el registro de éxito solo para el día de hoy
    $DB->delete_records_select('block_senceluisalmon_log', "courseid = ? AND status = 'exito' AND timecreated >= ? AND userid $insql", $params);
}

echo "OK";
