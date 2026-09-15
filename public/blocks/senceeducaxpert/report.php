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
 * Historial de conexiones SENCE (solo administradores del sitio).
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
global $DB, $OUTPUT, $PAGE, $CFG;

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$sql = "SELECT l.*, u.firstname, u.lastname, u.idnumber, c.fullname as coursename
          FROM {block_senceeducaxpert_log} l
          JOIN {user} u ON l.userid = u.id
          JOIN {course} c ON l.courseid = c.id
      ORDER BY l.timecreated DESC";

$logs = $DB->get_records_sql($sql);

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

$PAGE->set_url('/blocks/senceeducaxpert/report.php');
$PAGE->set_context($context);
$PAGE->set_title('Auditoría Técnica SENCE');
$PAGE->set_heading('Historial de Transmisiones SENCE');

echo $OUTPUT->header();

echo '<link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.dataTables.min.css">';
echo html_writer::tag('style', '
    .payload-pre { background: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 12px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
    .badge-exito { background-color: #28a745 !important; color: white !important; padding: 5px 10px; border-radius: 15px; font-size: 11px; display: inline-block; }
    .badge-error { background-color: #dc3545 !important; color: white !important; padding: 5px 10px; border-radius: 15px; font-size: 11px; display: inline-block; }
    #tablaHistorialSenceEducaxpert a { text-decoration: none; color: #0056b3; }
    #tablaHistorialSenceEducaxpert a:hover { text-decoration: underline; }
');

echo html_writer::start_div('mb-4');
echo html_writer::tag('h3', '<i class="fa fa-database"></i> Registro de Actividad');
echo html_writer::end_div();

$table = new html_table();
$table->id = 'tablaHistorialSenceEducaxpert';
$table->attributes['class'] = 'table table-hover table-striped shadow-sm';
$table->head = array('Fecha / Hora', 'Participante (RUT)', 'Curso', 'Tipo', 'Resultado', 'Detalle', 'Técnico');

foreach ($logs as $log) {
    $fecha = userdate($log->timecreated, '%d/%m/%Y %H:%M:%S');
    $fechaordenada = '<span style="display:none;">' . $log->timecreated . '</span>' . $fecha;

    $badgeclass = ($log->status == 'exito') ? 'badge-exito' : 'badge-error';
    $statushtml = html_writer::tag('span', strtoupper($log->status), array('class' => $badgeclass));

    $urlusuario = new moodle_url('/user/editadvanced.php', array('id' => $log->userid));
    $userlink = html_writer::link($urlusuario, '<strong>' . s(fullname($log)) . '</strong>', array('target' => '_blank', 'rel' => 'noopener'));
    $userdisplay = $userlink . "<br/><small class='text-muted'>" . s($log->idnumber) . '</small>';

    $urlcurso = new moodle_url('/course/view.php', array('id' => $log->courseid));
    $coursedisplay = html_writer::link($urlcurso, s($log->coursename), array('target' => '_blank', 'rel' => 'noopener'));

    $glosamostrar = s($log->glosa);
    if ($log->status == 'error' && is_numeric($log->glosa) && isset($erroressence[$log->glosa])) {
        $glosamostrar = '<strong>Error ' . s($log->glosa) . ':</strong> ' . s($erroressence[$log->glosa]);
    }

    $modalid = 'modal_' . $log->id;
    $btnjson = '<button type="button" class="btn btn-sm btn-dark" data-toggle="modal" data-target="#' . $modalid . '">
                    <i class="fa fa-code"></i> Ver JSON
                 </button>';

    $payloaddata = json_decode($log->payload);
    $jsonpretty = ($payloaddata) ? json_encode($payloaddata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No hay datos registrados';

    echo '
    <div class="modal fade" id="' . $modalid . '" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Datos Técnicos de Transmisión - ID ' . (int) $log->id . '</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body text-left">
            <p><strong>Carga de datos enviada a SENCE:</strong></p>
            <pre class="payload-pre">' . s($jsonpretty) . '</pre>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>';

    $table->data[] = array(
        $fechaordenada,
        $userdisplay,
        $coursedisplay,
        '<strong>' . s(strtoupper($log->eventtype)) . '</strong>',
        $statushtml,
        $glosamostrar,
        $btnjson,
    );
}

if (empty($logs)) {
    echo $OUTPUT->notification('No hay registros de actividad todavía.', 'info');
} else {
    echo html_writer::table($table);
}

echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
echo '<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>';
echo '<script>
    $(document).ready(function() {
        $("#tablaHistorialSenceEducaxpert").DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            },
            "order": [[ 0, "desc" ]],
            "columnDefs": [
                { "orderable": true, "targets": 0 }
            ],
            "pageLength": 25
        });
    });
</script>';

echo $OUTPUT->footer();
