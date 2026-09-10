<?php
require_once('../../config.php');
require_login();

$groupid = required_param('gid', PARAM_INT);
$date_str = optional_param('date', '', PARAM_RAW);
$cod_forzado = optional_param('cod', '', PARAM_TEXT);

$timestamp = !empty($date_str) ? strtotime($date_str) : 0;
$context = context_system::instance();

if (!is_siteadmin() && !has_capability('moodle/course:update', context_course::instance($DB->get_field('groups', 'courseid', ['id' => $groupid])))) {
    die('Acceso denegado');
}

$record = $DB->get_record('block_senceluisalmon_groups', ['groupid' => $groupid]);
if ($record) {
    $record->fecha_limite = $timestamp;
    $record->codigo_forzado = trim($cod_forzado);
    $DB->update_record('block_senceluisalmon_groups', $record);
} else {
    $new = new stdClass();
    $new->groupid = $groupid;
    $new->fecha_limite = $timestamp;
    $new->codigo_forzado = trim($cod_forzado);
    $DB->insert_record('block_senceluisalmon_groups', $new);
}
echo "OK";
