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
 * Guarda la fecha límite y el código SENCE forzado de un grupo.
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $DB;

$groupid = required_param('gid', PARAM_INT);
$datestr = optional_param('date', '', PARAM_RAW);
$codforzado = optional_param('cod', '', PARAM_TEXT);

$group = $DB->get_record('groups', array('id' => $groupid), '*', MUST_EXIST);

require_login($group->courseid);
require_sesskey();

$context = context_course::instance($group->courseid);
if (!is_siteadmin() && !has_capability('moodle/course:update', $context)) {
    die('Acceso denegado');
}

$timestamp = !empty($datestr) ? strtotime($datestr) : 0;

$record = $DB->get_record('block_senceeducaxpert_groups', ['groupid' => $groupid]);
if ($record) {
    $record->fecha_limite = $timestamp;
    $record->codigo_forzado = trim($codforzado);
    $DB->update_record('block_senceeducaxpert_groups', $record);
} else {
    $new = new stdClass();
    $new->groupid = $groupid;
    $new->fecha_limite = $timestamp;
    $new->codigo_forzado = trim($codforzado);
    $DB->insert_record('block_senceeducaxpert_groups', $new);
}

echo 'OK';
