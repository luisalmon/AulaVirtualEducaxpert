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
 * Integración de asistencia SENCE (Chile) para cursos EducaXpert.
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert) <lalmonacid@educaxpert.cl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_senceeducaxpert extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_senceeducaxpert');
    }

    public function has_config() {
        return true;
    }

    public function instance_allow_config() {
        return true;
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function get_content() {
        global $DB;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';

        $courseid = $this->page->course->id;
        $context = context_course::instance($courseid);
        $isgestor = has_capability('moodle/course:update', $context);

        $style = "
            .sence-lock-overlay {
                position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
                z-index: 2147483647 !important;
                display: flex; align-items: center; justify-content: center;
                pointer-events: auto !important;
            }
            .sence-modal-content {
                background: #ffffff; padding: 50px; border-radius: 24px;
                max-width: 500px; width: 90%; text-align: center;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                border: 1px solid rgba(255, 255, 255, 0.3);
                pointer-events: auto !important;
            }
            body.sence-locked { overflow: hidden !important; }
            body.sence-locked #page-wrapper, body.sence-locked .fixed-top, body.sence-locked .nav-drawer {
                pointer-events: none !important; user-select: none !important;
            }
            .sence-modal-content h3 { color: #1d2125; font-weight: 700; margin-bottom: 20px; }
            .sence-modal-content p { color: #6a737b; margin-bottom: 30px; font-size: 1.1rem; }
        ";
        $this->content->text .= html_writer::tag('style', $style);

        if ($isgestor) {
            $this->content->text .= $this->get_manager_view($courseid);
        } else {
            $this->content->text .= $this->get_student_view($courseid);
        }

        return $this->content;
    }

    private function get_manager_view($courseid) {
        global $DB, $CFG;

        $urllogo = $CFG->wwwroot . '/blocks/senceeducaxpert/image.png';
        $sesskey = sesskey();

        $html = html_writer::start_div('text-center mb-3');
        $html .= '<img src="' . s($urllogo) . '" style="max-width: 120px; margin-bottom: 10px;">';

        $disclaimer = '<strong>💡 Consideraciones Importantes de Configuración:</strong><br/>';
        $disclaimer .= '1. <strong>Declaración Jurada:</strong> El día exacto que configure como "Término", el sistema exigirá obligatoriamente la <a href="https://lce.sence.cl/CertificadoAsistencia/" target="_blank" rel="noopener" style="font-weight:bold; text-decoration:underline;">Declaración Jurada</a> al alumno para entrar. Si desea que ese día aún marquen asistencia normal, sume un día adicional a la fecha límite.<br/>';
        $disclaimer .= '2. <strong>Asistencia Única:</strong> El sistema permite registrar la asistencia <strong>una sola vez al día</strong>. Si requiere que un alumno marque nuevamente hoy, deberá utilizar el botón "Liberar" de forma individual o grupal para habilitarlo de nuevo.';
        $html .= html_writer::tag('div', $disclaimer, array('class' => 'alert alert-warning small p-3 text-left mb-3 shadow-sm'));

        $html .= html_writer::tag('div', '<strong>Administración de Grupos</strong>', array('class' => 'text-dark mb-1'));

        $urlconfig = $CFG->wwwroot . '/admin/settings.php?section=blocksettingsenceeducaxpert';
        $html .= '<div class="mt-2 mb-3">';
        $html .= '<a href="' . s($urlconfig) . '" target="_blank" rel="noopener" class="btn btn-warning btn-sm w-100 shadow-sm" style="font-weight:bold;">';
        $html .= '⚙️ Configuración / Historial';
        $html .= '</a>';
        $html .= '</div>';
        $html .= html_writer::end_div();

        $groups = groups_get_all_groups($courseid);

        $htmlencurso = '';
        $htmlterminados = '';
        $htmlinactivos = '';

        $contcurso = 0;
        $contterminados = 0;
        $continactivos = 0;

        $hoyts = strtotime('today');

        foreach ($groups as $g) {
            $codigo = !empty($g->idnumber) ? $g->idnumber : $g->name;
            if (empty($codigo)) {
                continue;
            }

            $config = $DB->get_record('block_senceeducaxpert_groups', array('groupid' => $g->id));
            $fechats = ($config) ? $config->fecha_limite : 0;
            $fechaval = ($fechats > 0) ? date('Y-m-d', $fechats) : '';
            $codforzadoval = ($config && isset($config->codigo_forzado)) ? s($config->codigo_forzado) : '';

            $members = groups_get_members($g->id, 'u.id, u.firstname, u.lastname, u.idnumber');
            $count = count($members);

            $grupohtml = html_writer::start_div('card mb-2 p-2 border-left-info shadow-sm bg-white');
            $grupohtml .= html_writer::tag('div', '<strong>Grupo:</strong> ' . s($g->name), array('class' => 'small'));

            // UI de configuración del grupo.
            $grupohtml .= '<div class="mt-2 mb-2 p-2 bg-light border rounded small">';
            $grupohtml .= '<div class="mb-1">Término: <input type="date" class="form-control form-control-sm" id="date_' . $g->id . '" value="' . s($fechaval) . '"></div>';
            $grupohtml .= '<div class="mb-1" title="Si lo llenas, reemplaza al ID del curso">Forzar CodSence: <input type="text" class="form-control form-control-sm" id="cod_' . $g->id . '" value="' . $codforzadoval . '" placeholder="Dejar vacío por defecto"></div>';
            $grupohtml .= '<button class="btn btn-sm btn-success w-100 mt-1" onclick="saveGroupConfigSenceEducaxpert(' . $g->id . ')">Guardar Config</button>';
            $grupohtml .= '</div>';

            $grupohtml .= html_writer::tag('button', "Ver Alumnos ({$count})", array(
                'class' => 'btn btn-sm btn-outline-primary w-100',
                'onclick' => "document.getElementById('grupo-alumnos-{$g->id}').classList.toggle('d-none')",
            ));

            $grupohtml .= html_writer::tag('button', "Liberar Todo el Grupo", array(
                'class' => 'btn btn-sm btn-outline-danger w-100 mt-1',
                'title' => 'Borrará el éxito de hoy para todos en este grupo',
                'onclick' => "resetGroupSenceEducaxpert({$g->id}, {$courseid})",
            ));

            $grupohtml .= html_writer::start_div('d-none mt-2 p-2 bg-light border rounded small', array('id' => "grupo-alumnos-{$g->id}"));
            if ($count > 0) {
                foreach ($members as $m) {
                    $rutmostrar = !empty($m->idnumber) ? s($m->idnumber) : '<span class="text-danger">Sin RUT</span>';
                    $nombremostrar = s(fullname($m));

                    $urledit = $CFG->wwwroot . '/user/editadvanced.php?id=' . $m->id . '&course=' . $courseid;
                    $grupohtml .= html_writer::start_div('d-flex justify-content-between align-items-center border-bottom pb-1 mb-1');
                    $grupohtml .= html_writer::start_div('text-truncate', array('style' => 'max-width: 55%; line-height: 1.1;'));
                    $grupohtml .= "<strong>{$nombremostrar}</strong><br><span class='text-muted' style='font-size: 0.85em;'>RUT: {$rutmostrar}</span>";
                    $grupohtml .= html_writer::end_div();

                    $grupohtml .= '<div class="btn-group">';
                    $grupohtml .= '<a href="' . s($urledit) . '" target="_blank" rel="noopener" class="btn btn-info btn-sm py-0 px-1 text-white" style="font-size: 0.8em;">Editar</a>';
                    $grupohtml .= '<button onclick="resetUserSenceEducaxpert(' . $m->id . ', ' . $courseid . ')" class="btn btn-danger btn-sm py-0 px-1 text-white" style="font-size: 0.8em;" title="Borrar registro de asistencia de hoy">Liberar</button>';
                    $grupohtml .= '</div>';

                    $grupohtml .= html_writer::end_div();
                }
            } else {
                $grupohtml .= html_writer::tag('div', 'Sin alumnos.', array('class' => 'text-muted text-center py-2'));
            }
            $grupohtml .= html_writer::end_div();
            $grupohtml .= html_writer::end_div();

            if ($fechats == 0) {
                $htmlinactivos .= $grupohtml;
                $continactivos++;
            } else if ($fechats < $hoyts) {
                $htmlterminados .= $grupohtml;
                $contterminados++;
            } else {
                $htmlencurso .= $grupohtml;
                $contcurso++;
            }
        }

        $html .= $this->build_admin_category('🟢 En Curso / Activos', $contcurso, $htmlencurso, 'cat-curso', true, 'success');
        $html .= $this->build_admin_category('🔴 Terminados', $contterminados, $htmlterminados, 'cat-terminados', false, 'danger');
        $html .= $this->build_admin_category('⚪ Inactivos / No SENCE', $continactivos, $htmlinactivos, 'cat-inactivos', false, 'secondary');

        $html .= html_writer::tag('div', get_string('credits', 'block_senceeducaxpert'),
            array('class' => 'text-muted text-center small mt-3', 'style' => 'opacity:.6;'));

        // sesskey se pasa en cada llamada para que los endpoints puedan validarla (CSRF).
        $html .= '<script>
            const SENCE_EDUCAXPERT_SESSKEY = ' . json_encode($sesskey) . ';
            function saveGroupConfigSenceEducaxpert(gid) {
                let d = document.getElementById("date_"+gid).value;
                let c = document.getElementById("cod_"+gid).value;
                fetch("' . $CFG->wwwroot . '/blocks/senceeducaxpert/save_group_config.php?gid="+gid+"&date="+encodeURIComponent(d)+"&cod="+encodeURIComponent(c)+"&sesskey="+SENCE_EDUCAXPERT_SESSKEY)
                .then(() => { location.reload(); });
            }
            function resetUserSenceEducaxpert(uid, cid) {
                if (confirm("¿Estás seguro de que quieres LIBERAR a este alumno? Esto borrará su asistencia exitosa de hoy.")) {
                    fetch("' . $CFG->wwwroot . '/blocks/senceeducaxpert/reset_user.php?uid="+uid+"&cid="+cid+"&sesskey="+SENCE_EDUCAXPERT_SESSKEY)
                    .then(() => { location.reload(); });
                }
            }
            function resetGroupSenceEducaxpert(gid, cid) {
                if (confirm("¿Estás seguro de que quieres LIBERAR A TODO EL GRUPO? Se borrará el registro de asistencia de hoy para todos.")) {
                    fetch("' . $CFG->wwwroot . '/blocks/senceeducaxpert/reset_group.php?gid="+gid+"&cid="+cid+"&sesskey="+SENCE_EDUCAXPERT_SESSKEY)
                    .then(() => { location.reload(); });
                }
            }
        </script>';

        return $html;
    }

    private function build_admin_category($title, $count, $content, $id, $isopen, $color) {
        if ($count == 0) {
            $content = '<div class="small text-muted text-center p-2">No hay grupos aquí.</div>';
        }
        $display = $isopen ? '' : 'd-none';
        $btnclass = "btn btn-sm btn-{$color} w-100 text-left font-weight-bold d-flex justify-content-between align-items-center mb-1 shadow-sm";

        $html = '<div class="mb-2">';
        $html .= '<button class="' . $btnclass . '" onclick="document.getElementById(\'' . $id . '\').classList.toggle(\'d-none\')">';
        $html .= '<span>' . $title . '</span> <span class="badge badge-light text-dark">' . $count . '</span>';
        $html .= '</button>';
        $html .= '<div id="' . $id . '" class="' . $display . ' p-1 border rounded bg-light">';
        $html .= $content;
        $html .= '</div></div>';
        return $html;
    }

    private function get_student_view($courseid) {
        global $USER, $DB, $CFG;

        $usergroups = groups_get_all_groups($courseid, $USER->id);
        if (empty($usergroups)) {
            return '';
        }

        $html = '';
        $hoyts = strtotime('today');
        $lockneeded = false;
        $modalbody = '';

        foreach ($usergroups as $g) {
            $codigogrupo = !empty($g->idnumber) ? $g->idnumber : $g->name;
            if (strlen($codigogrupo) < 7) {
                continue;
            }

            $groupconfig = $DB->get_record('block_senceeducaxpert_groups', array('groupid' => $g->id));
            $fechalimite = ($groupconfig) ? $groupconfig->fecha_limite : 0;

            $codsenceprincipal = $this->page->course->idnumber;
            if ($groupconfig && !empty($groupconfig->codigo_forzado)) {
                $codsenceprincipal = $groupconfig->codigo_forzado;
            }

            // Grupos sin fecha configurada (inactivos) no exigen registro.
            if ($fechalimite == 0) {
                continue;
            }
            if ($hoyts > $fechalimite) {
                continue;
            }

            $limitedoshoras = time() - 7200;

            $successtoday = $DB->record_exists_select(
                'block_senceeducaxpert_log',
                "userid = ? AND courseid = ? AND eventtype = 'inicio' AND status = 'exito' AND timecreated >= ?",
                array($USER->id, $courseid, $limitedoshoras)
            );

            if (!$successtoday) {
                $lockneeded = true;
                if ($fechalimite > 0 && $hoyts == $fechalimite) {
                    $modalbody .= $this->render_dj_ui($codigogrupo, $codsenceprincipal, $courseid);
                } else {
                    $modalbody .= $this->render_login_ui($codigogrupo, $codsenceprincipal, $courseid);
                }
            } else {
                $urllogo = $CFG->wwwroot . '/blocks/senceeducaxpert/image.png';
                $html .= html_writer::start_div('alert alert-success py-2 small text-center shadow-sm');
                $html .= '<img src="' . s($urllogo) . '" style="max-width: 90px; margin-bottom: 8px;"><br>';
                $html .= '✅ <strong>Asistencia SENCE Registrada</strong><br><span class="text-muted">(' . s($codigogrupo) . ')</span>';
                $html .= html_writer::end_div();
            }
        }

        if ($lockneeded) {
            $overlay = html_writer::start_div('sence-lock-overlay', array('id' => 'senceEducaxpertLock'));
            $overlay .= html_writer::tag('div', $modalbody, array('class' => 'sence-modal-content'));
            $overlay .= html_writer::end_div();

            $overlay .= '<script>
                document.body.classList.add("sence-locked");
                var senceEducaxpertModal = document.getElementById("senceEducaxpertLock");
                if (senceEducaxpertModal) { document.body.appendChild(senceEducaxpertModal); }
            </script>';

            return $overlay . $html;
        }

        return $html;
    }

    private function render_login_ui($codigogrupo, $codsenceprincipal, $courseid) {
        global $CFG;

        $urlhome = $CFG->wwwroot . '/my/courses.php';
        $urllogo = $CFG->wwwroot . '/blocks/senceeducaxpert/image.png';

        $html = '<img src="' . s($urllogo) . '" alt="Logo SENCE" style="max-width: 180px; margin-bottom: 20px;">';
        $html .= '<h3>Control de Asistencia SENCE</h3>';
        $html .= '<p>Para acceder al contenido del curso hoy, es obligatorio registrar su asistencia en el portal oficial.</p>';

        $html .= $this->get_login_form($codigogrupo, $codsenceprincipal, $courseid);
        $html .= '<a href="' . s($urlhome) . '" class="btn btn-outline-secondary mt-3 w-100">Regresar a Mis Cursos</a>';

        $html .= $this->render_support_box();

        return $html;
    }

    private function render_dj_ui($codigogrupo, $codsenceprincipal, $courseid) {
        global $CFG;

        $urlhome = $CFG->wwwroot . '/my/courses.php';
        $urllogo = $CFG->wwwroot . '/blocks/senceeducaxpert/image.png';
        $sesskey = sesskey();

        $html = '<img src="' . s($urllogo) . '" alt="Logo SENCE" style="max-width: 180px; margin-bottom: 20px;">';
        $html .= '<h3>Finalización de Curso SENCE</h3>';
        $html .= '<p>Hoy es el último día de capacitación. Según normativa SENCE, debe generar su <strong>Declaración Jurada</strong> para finalizar.</p>';
        $html .= '<a href="https://lce.sence.cl/CertificadoAsistencia/" target="_blank" rel="noopener" class="btn btn-danger btn-lg w-100 shadow" onclick="processDJSenceEducaxpert(' . $courseid . ')">Generar Declaración Jurada SENCE</a>';

        $html .= '<a href="' . s($urlhome) . '" class="btn btn-outline-secondary mt-3 w-100">Regresar a Mis Cursos</a>';

        $html .= '<script>
            function processDJSenceEducaxpert(cid) {
                fetch("' . $CFG->wwwroot . '/blocks/senceeducaxpert/process_dj.php?id=" + cid + "&sesskey=' . $sesskey . '")
                .then(() => {
                    var overlay = document.getElementById("senceEducaxpertLock");
                    if (overlay) { overlay.style.display = "none"; }
                    document.body.classList.remove("sence-locked");
                    window.location.reload();
                });
            }
        </script>';

        $html .= $this->render_support_box();

        return $html;
    }

    private function render_support_box() {
        $supportemail = get_config('block_senceeducaxpert', 'support_email');
        $supportwa = get_config('block_senceeducaxpert', 'support_whatsapp');

        if (empty($supportemail) && empty($supportwa)) {
            return '';
        }

        $html = '<div class="mt-4 p-2 bg-light border rounded small text-muted text-left">';
        $html .= '<strong>¿Necesitas ayuda?</strong><br>';
        if (!empty($supportwa)) {
            $numerolimpio = preg_replace('/[^0-9]/', '', $supportwa);
            $html .= '📞 WhatsApp: <a href="https://wa.me/' . s($numerolimpio) . '" target="_blank" rel="noopener">' . s($supportwa) . '</a><br>';
        }
        if (!empty($supportemail)) {
            $html .= '📧 Correo: <a href="mailto:' . s($supportemail) . '">' . s($supportemail) . '</a>';
        }
        $html .= '</div>';

        return $html;
    }

    private function get_login_form($codigogrupo, $codsenceprincipal, $courseid) {
        global $USER, $CFG, $DB;

        $ambiente = get_config('block_senceeducaxpert', 'ambiente');
        $lineacapacitacion = !empty($this->config->config_linea) ? $this->config->config_linea : '3';

        if ($ambiente === 'test') {
            $url = 'https://sistemas.sence.cl/rcetest/Registro/IniciarSesion';
            $valorcodsence = '-1';
            $valorcodigocurso = '-1';
            $btntexto = 'Iniciar Sesión (Modo Prueba)';
            $btnclase = 'btn btn-warning btn-lg w-100 shadow text-dark';
        } else {
            $url = 'https://sistemas.sence.cl/rce/Registro/IniciarSesion';
            $valorcodsence = $codsenceprincipal;
            $valorcodigocurso = $codigogrupo;
            $btntexto = 'Iniciar Sesión SENCE';
            $btnclase = 'btn btn-primary btn-lg w-100 shadow';
        }

        // Se consulta la BD directamente para evitar el RUT desactualizado por caché de sesión.
        $rutestudiante = $DB->get_field('user', 'idnumber', array('id' => $USER->id));

        // SENCE vuelve a exito.php/error.php mediante un POST entre sitios: el
        // navegador no manda la cookie de sesión de Moodle en ese salto
        // (SameSite), así que require_login() ahí no reconocería al usuario.
        // Se identifica en su lugar con un token de un solo uso.
        $returntoken = self::create_return_token($USER->id, $courseid);

        $fields = array(
            'RutOtec' => get_config('block_senceeducaxpert', 'otec_rut'),
            'Token' => get_config('block_senceeducaxpert', 'otec_token'),
            'CodSence' => $valorcodsence,
            'CodigoCurso' => $valorcodigocurso,
            'LineaCapacitacion' => $lineacapacitacion,
            'RunAlumno' => $rutestudiante,
            'IdSesionAlumno' => $USER->id . '-' . time(),
            'UrlRetoma' => $CFG->wwwroot . '/blocks/senceeducaxpert/exito.php?id=' . $courseid . '&t=' . $returntoken,
            'UrlError' => $CFG->wwwroot . '/blocks/senceeducaxpert/error.php?id=' . $courseid . '&t=' . $returntoken,
        );

        $f = '<form method="post" action="' . s($url) . '">';
        foreach ($fields as $n => $v) {
            $f .= '<input type="hidden" name="' . s($n) . '" value="' . s($v) . '">';
        }
        $f .= '<input type="submit" value="' . s($btntexto) . '" class="' . $btnclase . '">';
        $f .= '</form>';

        return $f;
    }

    /**
     * Crea un token de un solo uso que identifica a un alumno/curso, para
     * usar en UrlRetoma/UrlError. SENCE vuelve por un POST entre sitios y
     * el navegador no manda la cookie de sesión de Moodle en ese salto, así
     * que exito.php/error.php no pueden depender de require_login() para
     * saber quién es -se identifican con este token en su lugar-.
     *
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    public static function create_return_token(int $userid, int $courseid): string {
        global $DB;

        $record = new stdClass();
        $record->token = random_string(64);
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->timecreated = time();

        $DB->insert_record('block_senceeducaxpert_tokens', $record);

        return $record->token;
    }

    /**
     * Resuelve y consume (borra) un token de retorno. Un token solo sirve
     * una vez y caduca a los 30 minutos.
     *
     * @param string $token
     * @param int $courseid El curso esperado, debe coincidir con el del token.
     * @return stdClass|null Objeto con ->userid si el token es válido, null si no.
     */
    public static function resolve_return_token(string $token, int $courseid): ?stdClass {
        global $DB;

        if (empty($token)) {
            return null;
        }

        $record = $DB->get_record('block_senceeducaxpert_tokens', ['token' => $token]);
        if (!$record) {
            return null;
        }

        // Un solo uso: se borra se use o no (evita reintentos/replay).
        $DB->delete_records('block_senceeducaxpert_tokens', ['id' => $record->id]);

        $treintaminutos = 1800;
        if ($record->timecreated < (time() - $treintaminutos)) {
            return null;
        }

        if ((int) $record->courseid !== $courseid) {
            return null;
        }

        return $record;
    }
}
