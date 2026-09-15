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
 * Libera (borra) el registro de asistencia exitosa de hoy para todo un grupo.
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$groupid = required_param('gid', PARAM_INT);
$courseid = required_param('cid', PARAM_INT);

require_login($courseid);
require_sesskey();

$context = context_course::instance($courseid);
if (!has_capability('moodle/course:update', $context)) {
    die('Acceso denegado');
}

global $DB;
$hoyts = strtotime('today');

$members = groups_get_members($groupid, 'u.id');

if (!empty($members)) {
    $userids = array_keys($members);
    list($insql, $inparams) = $DB->get_in_or_equal($userids);
    $params = array_merge([$courseid, $hoyts], $inparams);

    $DB->delete_records_select('block_senceeducaxpert_log', "courseid = ? AND status = 'exito' AND timecreated >= ? AND userid $insql", $params);
}

echo 'OK';
