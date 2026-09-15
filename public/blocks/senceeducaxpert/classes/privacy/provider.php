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
 * Privacy Subsystem implementation for block_senceeducaxpert.
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_senceeducaxpert\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for block_senceeducaxpert.
 *
 * Los registros de block_senceeducaxpert_log están ligados a un usuario y a
 * un curso (contexto de curso), no a la instancia del bloque en sí.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe qué datos personales almacena este plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_senceeducaxpert_log',
            [
                'userid' => 'privacy:metadata:block_senceeducaxpert_log:userid',
                'courseid' => 'privacy:metadata:block_senceeducaxpert_log:courseid',
                'eventtype' => 'privacy:metadata:block_senceeducaxpert_log:eventtype',
                'status' => 'privacy:metadata:block_senceeducaxpert_log:status',
                'glosa' => 'privacy:metadata:block_senceeducaxpert_log:glosa',
                'timecreated' => 'privacy:metadata:block_senceeducaxpert_log:timecreated',
                'payload' => 'privacy:metadata:block_senceeducaxpert_log:payload',
            ],
            'privacy:metadata:block_senceeducaxpert_log'
        );

        // El payload viaja hacia/desde SENCE (RutOtec, Token, RunAlumno...).
        $collection->add_external_location_link(
            'sence.cl',
            [
                'RunAlumno' => 'privacy:metadata:block_senceeducaxpert_log:userid',
                'IdSesionAlumno' => 'privacy:metadata:block_senceeducaxpert_log:userid',
            ],
            'privacy:metadata:block_senceeducaxpert_log'
        );

        return $collection;
    }

    /**
     * Devuelve la lista de contextos (cursos) que contienen datos del usuario.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {block_senceeducaxpert_log} l ON l.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextcourse
                   AND l.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextcourse' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Devuelve la lista de usuarios con datos en un contexto dado.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $sql = "SELECT userid
                  FROM {block_senceeducaxpert_log}
                 WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Exporta los datos personales del usuario por contexto.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $records = $DB->get_records('block_senceeducaxpert_log', [
                'userid' => $user->id,
                'courseid' => $context->instanceid,
            ]);

            if (empty($records)) {
                continue;
            }

            $data = array_map(function($record) {
                return [
                    'eventtype' => $record->eventtype,
                    'status' => $record->status,
                    'glosa' => $record->glosa,
                    'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                ];
            }, array_values($records));

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_senceeducaxpert')],
                (object) ['registros' => $data]
            );
        }
    }

    /**
     * Borra TODOS los datos de un contexto (curso) dado.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $DB->delete_records('block_senceeducaxpert_log', ['courseid' => $context->instanceid]);
    }

    /**
     * Borra los datos de un usuario en los contextos aprobados.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }

            $DB->delete_records('block_senceeducaxpert_log', [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ]);
        }
    }

    /**
     * Borra los datos de varios usuarios aprobados en un contexto.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('block_senceeducaxpert_log', [
                'userid' => $userid,
                'courseid' => $context->instanceid,
            ]);
        }
    }
}
