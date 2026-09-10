<?php
require_once('../../config.php');
require_login();
global $DB, $OUTPUT, $PAGE, $CFG;

// Seguridad: Solo administradores pueden ver esto
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Consulta SQL para obtener el historial
$sql = "SELECT l.*, u.firstname, u.lastname, u.idnumber, c.fullname as coursename 
        FROM {block_senceluisalmon_log} l
        JOIN {user} u ON l.userid = u.id
        JOIN {course} c ON l.courseid = c.id
        ORDER BY l.timecreated DESC";

$logs = $DB->get_records_sql($sql);

// --- DICCIONARIO DE ERRORES SENCE ---
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

// --- CONFIGURACIÓN DE LA PÁGINA ---
$PAGE->set_url('/blocks/senceluisalmon/report.php');
$PAGE->set_context($context);
$PAGE->set_title("Auditoría Técnica SENCE");
$PAGE->set_heading("Historial de Transmisiones SENCE 2026");

echo $OUTPUT->header();

// Inyectamos CSS de DataTables y estilos personalizados
echo '<link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.dataTables.min.css">';
echo html_writer::tag('style', "
    .payload-pre { background: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 12px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
    .badge-exito { background-color: #28a745 !important; color: white !important; padding: 5px 10px; border-radius: 15px; font-size: 11px; display: inline-block; }
    .badge-error { background-color: #dc3545 !important; color: white !important; padding: 5px 10px; border-radius: 15px; font-size: 11px; display: inline-block; }
    #tablaHistorialSence a { text-decoration: none; color: #0056b3; }
    #tablaHistorialSence a:hover { text-decoration: underline; }
");

// --- ENCABEZADO ---
echo html_writer::start_div('mb-4');
echo html_writer::tag('h3', '<i class="fa fa-database"></i> Registro de Actividad');
echo html_writer::end_div();

// --- TABLA DE DATOS ---
$table = new html_table();
$table->id = 'tablaHistorialSence';
$table->attributes['class'] = 'table table-hover table-striped shadow-sm';
$table->head = array('Fecha / Hora', 'Participante (RUT)', 'Curso', 'Tipo', 'Resultado', 'Detalle', 'Técnico');

foreach ($logs as $log) {
  $fecha = userdate($log->timecreated, '%d/%m/%Y %H:%M:%S');

  // SOLUCIÓN: Agregamos el timestamp oculto para que DataTables ordene matemáticamente
  $fecha_ordenada = '<span style="display:none;">' . $log->timecreated . '</span>' . $fecha;

  $badge_class = ($log->status == 'exito') ? 'badge-exito' : 'badge-error';
  $status_html = html_writer::tag('span', strtoupper($log->status), array('class' => $badge_class));

  // 1. Enlace al perfil del usuario (Edit Advanced)
  $url_usuario = new moodle_url('/user/editadvanced.php', array('id' => $log->userid));
  $user_link = html_writer::link($url_usuario, "<strong>$log->firstname $log->lastname</strong>", array('target' => '_blank'));
  $user_display = $user_link . "<br/><small class='text-muted'>$log->idnumber</small>";

  // 2. Enlace al curso
  $url_curso = new moodle_url('/course/view.php', array('id' => $log->courseid));
  $course_display = html_writer::link($url_curso, $log->coursename, array('target' => '_blank'));

  // Convertir el número de error a mensaje si existe
  $glosa_mostrar = $log->glosa;
  if ($log->status == 'error' && is_numeric($log->glosa) && isset($errores_sence[$log->glosa])) {
    $glosa_mostrar = "<strong>Error {$log->glosa}:</strong> " . $errores_sence[$log->glosa];
  }

  // Botón para Modal JSON
  $modal_id = "modal_" . $log->id;
  $btn_json = '<button type="button" class="btn btn-sm btn-dark" data-toggle="modal" data-target="#' . $modal_id . '">
                    <i class="fa fa-code"></i> Ver JSON
                 </button>';

  // Preparación del JSON para el modal
  $payload_data = json_decode($log->payload);
  $json_pretty = ($payload_data) ? json_encode($payload_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : "No hay datos registrados";

  // Generación del modal
  echo '
    <div class="modal fade" id="' . $modal_id . '" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Datos Técnicos de Transmisión - ID ' . $log->id . '</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body text-left">
            <p><strong>Carga de datos enviada a SENCE:</strong></p>
            <pre class="payload-pre">' . s($json_pretty) . '</pre>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>';

  $table->data[] = array(
    $fecha_ordenada, // <--- Aquí usamos la nueva variable con el span oculto
    $user_display,
    $course_display,
    '<strong>' . strtoupper($log->eventtype) . '</strong>',
    $status_html,
    $glosa_mostrar,
    $btn_json
  );
}

if (empty($logs)) {
  echo $OUTPUT->notification("No hay registros de actividad todavía.", 'info');
} else {
  echo html_writer::table($table);
}

// Inyectar JS de DataTables e inicializar
echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
echo '<script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>';
echo '<script>
    $(document).ready(function() {
        $("#tablaHistorialSence").DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            },
            "order": [[ 0, "desc" ]], // Orden descendente en la primera columna (Fecha)
            "columnDefs": [
                { "orderable": true, "targets": 0 } // Asegura que la fecha sea ordenable
            ],
            "pageLength": 25 
        });
    });
</script>';

echo $OUTPUT->footer();
