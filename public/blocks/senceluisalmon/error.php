<?php
require_once('../../config.php');
global $PAGE, $OUTPUT, $CFG, $DB, $USER, $SESSION;

$courseid = required_param('id', PARAM_INT);
$glosa_id = optional_param('GlosaError', '0', PARAM_TEXT);

$payload = array(
    'DATOS_ENVIADOS_A_SENCE' => array(
        'RutOtec'   => get_config('block_senceluisalmon', 'otec_rut'),
        'Token'     => get_config('block_senceluisalmon', 'otec_token'),
        'Ambiente'  => get_config('block_senceluisalmon', 'ambiente'),
        'UrlError'  => $CFG->wwwroot . '/blocks/senceluisalmon/error.php?id=' . $courseid,
        'UrlRetoma' => $CFG->wwwroot . '/blocks/senceluisalmon/exito.php?id=' . $courseid
    ),
    'DATOS_RECIBIDOS_DESDE_SENCE' => $_POST // Captura todo lo que SENCE devolvió por POST
);

$log = new stdClass();
$log->userid = $USER->id;
$log->courseid = $courseid;
$log->eventtype = isset($SESSION->sence_sesion_id) ? 'cierre' : 'inicio';
$log->status = 'error';
$log->glosa = $glosa_id;
$log->timecreated = time();
$log->payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$DB->insert_record('block_senceluisalmon_log', $log);

// Diccionario de errores extraído del Manual Técnico SENCE v1.1.5
$errores_sence = array(
    "200" => "El POST tiene uno o más parámetros mandatorios sin información o mal escritos.",
    "201" => "La URL de Retoma y/o URL de Error no tienen información.",
    "202" => "La URL de Retoma tiene formato incorrecto.",
    "203" => "La URL de Error tiene formato incorrecto.",
    "204" => "El Código SENCE tiene menos de 10 caracteres o no es un código válido.",
    "205" => "El Código Curso tiene menos de 7 caracteres o no es un código válido.",
    "206" => "La línea de capacitación es incorrecta.",
    "207" => "El Run Alumno tiene formato incorrecto o dígito verificador erróneo.",
    "208" => "Run Alumno no está autorizado para realizar el curso.",
    "209" => "El Rut OTEC tiene formato incorrecto o dígito verificador erróneo.",
    "211" => "El Token no pertenece al OTEC.",
    "212" => "El Token no está vigente.",
    "300" => "Error interno no clasificado en SENCE.",
    "301" => "No se pudo registrar el ingreso/cierre. Línea de capacitación o Código de Curso incorrecto.",
    "302" => "No se pudo validar la información del Organismo (OTEC).",
    "303" => "El Token no existe o su formato es incorrecto.",
    "304" => "No se pudieron verificar los datos enviados a SENCE.",
    "305" => "No se pudo registrar la información en los sistemas de SENCE.",
    "306" => "El Código Curso no corresponde al código SENCE informado.",
    "307" => "El Código Curso no tiene modalidad E-learning.",
    "308" => "El Código Curso no corresponde al RUT OTEC.",
    "309" => "Las fechas de ejecución del curso no corresponden a la fecha actual.",
    "310" => "El Código Curso está en estado Terminado o Anulado.",
    "311" => "El RUT ingresado en Clave Única no coincide con el RUT del alumno en el curso.",
    "312" => "No se pudo completar la autenticación con Clave Única.",
    "313" => "URL de Cierre de sesión incorrecta."
);

// Obtener el mensaje descriptivo
$mensaje_error = isset($errores_sence[$glosa_id]) ? $errores_sence[$glosa_id] : "Error no identificado (Código: $glosa_id)";

// ENVÍO DE CORREO AL ADMINISTRADOR
$support_email = get_config('block_senceluisalmon', 'support_email');
if (!empty($support_email)) {
    $support_user = new stdClass();
    $support_user->id = -99;
    $support_user->email = $support_email;
    $support_user->mailformat = 1;
    $support_user->deleted = 0;
    $support_user->suspended = 0;

    $asunto = "Alerta SENCE: Error en curso ID {$courseid}";

    $mensaje_html = "<h3>Error en Validación SENCE</h3>";
    $mensaje_html .= "<p>El alumno <strong>{$USER->firstname} {$USER->lastname}</strong> (RUT: {$USER->idnumber}) no pudo registrar su asistencia.</p>";
    $mensaje_html .= "<ul>";
    $mensaje_html .= "<li><strong>Código de Error:</strong> {$glosa_id}</li>";
    $mensaje_html .= "<li><strong>Mensaje SENCE:</strong> {$mensaje_error}</li>";
    $mensaje_html .= "</ul>";
    $mensaje_html .= "<p>Revisa el Historial SENCE en el aula virtual para ver el JSON técnico.</p>";

    email_to_user($support_user, core_user::get_noreply_user(), $asunto, strip_tags($mensaje_html), $mensaje_html);
}

// Configuración de página para evitar errores de contexto
$context = context_course::instance($courseid);
$PAGE->set_url('/blocks/senceluisalmon/error.php', array('id' => $courseid));
$PAGE->set_context($context);
$PAGE->set_title("Problema de Asistencia SENCE");
$PAGE->set_heading("Error en Registro de Asistencia");

echo $OUTPUT->header();

// Mostrar el error de forma amigable al alumno
echo $OUTPUT->box_start('generalbox alert alert-danger');
echo html_writer::tag('h4', "Atención: No se pudo registrar la asistencia");
echo html_writer::tag('p', "Código de error: " . s($glosa_id));
echo html_writer::tag('p', html_writer::tag('strong', $mensaje_error));
echo $OUTPUT->box_end();

// Botón para volver al curso
echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $courseid)));

echo $OUTPUT->footer();
