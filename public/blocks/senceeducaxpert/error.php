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
 * URL de error de SENCE: destino externo (SENCE redirige aquí cuando su
 * registro falla). Requiere sesión activa -antes no la pedía, lo que
 * permitía a cualquiera, incluso sin login, insertar registros y disparar
 * el correo de soporte-.
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('id', PARAM_INT);
require_login($courseid);

global $PAGE, $OUTPUT, $CFG, $DB, $USER, $SESSION;

$glosaid = optional_param('GlosaError', '0', PARAM_TEXT);

$payload = array(
    'DATOS_ENVIADOS_A_SENCE' => array(
        'RutOtec' => get_config('block_senceeducaxpert', 'otec_rut'),
        'Token' => get_config('block_senceeducaxpert', 'otec_token'),
        'Ambiente' => get_config('block_senceeducaxpert', 'ambiente'),
        'UrlError' => $CFG->wwwroot . '/blocks/senceeducaxpert/error.php?id=' . $courseid,
        'UrlRetoma' => $CFG->wwwroot . '/blocks/senceeducaxpert/exito.php?id=' . $courseid,
    ),
    'DATOS_RECIBIDOS_DESDE_SENCE' => $_POST,
);

$log = new stdClass();
$log->userid = $USER->id;
$log->courseid = $courseid;
$log->eventtype = isset($SESSION->sence_sesion_id) ? 'cierre' : 'inicio';
$log->status = 'error';
$log->glosa = $glosaid;
$log->timecreated = time();
$log->payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$DB->insert_record('block_senceeducaxpert_log', $log);

// Diccionario de errores extraído del Manual Técnico SENCE v1.1.5.
$erroressence = array(
    '200' => 'El POST tiene uno o más parámetros mandatorios sin información o mal escritos.',
    '201' => 'La URL de Retoma y/o URL de Error no tienen información.',
    '202' => 'La URL de Retoma tiene formato incorrecto.',
    '203' => 'La URL de Error tiene formato incorrecto.',
    '204' => 'El Código SENCE tiene menos de 10 caracteres o no es un código válido.',
    '205' => 'El Código Curso tiene menos de 7 caracteres o no es un código válido.',
    '206' => 'La línea de capacitación es incorrecta.',
    '207' => 'El Run Alumno tiene formato incorrecto o dígito verificador erróneo.',
    '208' => 'Run Alumno no está autorizado para realizar el curso.',
    '209' => 'El Rut OTEC tiene formato incorrecto o dígito verificador erróneo.',
    '211' => 'El Token no pertenece al OTEC.',
    '212' => 'El Token no está vigente.',
    '300' => 'Error interno no clasificado en SENCE.',
    '301' => 'No se pudo registrar el ingreso/cierre. Línea de capacitación o Código de Curso incorrecto.',
    '302' => 'No se pudo validar la información del Organismo (OTEC).',
    '303' => 'El Token no existe o su formato es incorrecto.',
    '304' => 'No se pudieron verificar los datos enviados a SENCE.',
    '305' => 'No se pudo registrar la información en los sistemas de SENCE.',
    '306' => 'El Código Curso no corresponde al código SENCE informado.',
    '307' => 'El Código Curso no tiene modalidad E-learning.',
    '308' => 'El Código Curso no corresponde al RUT OTEC.',
    '309' => 'Las fechas de ejecución del curso no corresponden a la fecha actual.',
    '310' => 'El Código Curso está en estado Terminado o Anulado.',
    '311' => 'El RUT ingresado en Clave Única no coincide con el RUT del alumno en el curso.',
    '312' => 'No se pudo completar la autenticación con Clave Única.',
    '313' => 'URL de Cierre de sesión incorrecta.',
);

$mensajeerror = isset($erroressence[$glosaid]) ? $erroressence[$glosaid] : "Error no identificado (Código: {$glosaid})";

// Envío de correo al administrador.
$supportemail = get_config('block_senceeducaxpert', 'support_email');
if (!empty($supportemail)) {
    $supportuser = new stdClass();
    $supportuser->id = -99;
    $supportuser->email = $supportemail;
    $supportuser->mailformat = 1;
    $supportuser->deleted = 0;
    $supportuser->suspended = 0;

    $asunto = "Alerta SENCE: Error en curso ID {$courseid}";

    $mensajehtml = '<h3>Error en Validación SENCE</h3>';
    $mensajehtml .= '<p>El alumno <strong>' . s(fullname($USER)) . '</strong> (RUT: ' . s($USER->idnumber) . ') no pudo registrar su asistencia.</p>';
    $mensajehtml .= '<ul>';
    $mensajehtml .= '<li><strong>Código de Error:</strong> ' . s($glosaid) . '</li>';
    $mensajehtml .= '<li><strong>Mensaje SENCE:</strong> ' . s($mensajeerror) . '</li>';
    $mensajehtml .= '</ul>';
    $mensajehtml .= '<p>Revisa el Historial SENCE en el aula virtual para ver el JSON técnico.</p>';

    email_to_user($supportuser, core_user::get_noreply_user(), $asunto, strip_tags($mensajehtml), $mensajehtml);
}

$context = context_course::instance($courseid);
$PAGE->set_url('/blocks/senceeducaxpert/error.php', array('id' => $courseid));
$PAGE->set_context($context);
$PAGE->set_title('Problema de Asistencia SENCE');
$PAGE->set_heading('Error en Registro de Asistencia');

echo $OUTPUT->header();

echo $OUTPUT->box_start('generalbox alert alert-danger');
echo html_writer::tag('h4', 'Atención: No se pudo registrar la asistencia');
echo html_writer::tag('p', 'Código de error: ' . s($glosaid));
echo html_writer::tag('p', html_writer::tag('strong', s($mensajeerror)));
echo $OUTPUT->box_end();

echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $courseid)));

echo $OUTPUT->footer();
