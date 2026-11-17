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
 * Dos modos de trabajo por curso (configuración de la instancia):
 *  - "grupos" (por defecto): cada grupo con ID de 7+ caracteres es una acción
 *    SENCE (CodigoCurso); el ID del curso es el CodSence salvo que el grupo lo
 *    fuerce; la fecha de término del grupo activa la Declaración Jurada.
 *  - "curso": un solo CodSence/CodigoCurso para todo el curso, como el bloque
 *    antiguo block_sence (itic.cl), con fecha de término opcional.
 *
 * Todo vive en el bloque: no depende de cambios en el núcleo de Moodle.
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert) <lalmonacid@educaxpert.cl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_senceeducaxpert extends block_base {

    /** @var int Tras un registro exitoso, el curso queda libre durante este tiempo (s). */
    const VENTANA = 7200;

    /** @var string Glosa con la que se marca un cierre de sesión SENCE explícito. */
    const GLOSA_CIERRE = 'Cierre de sesión SENCE';

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

    // ---------------------------------------------------------------------
    // Configuración.
    // ---------------------------------------------------------------------

    /**
     * Opciones de línea de capacitación SENCE.
     *
     * @return array
     */
    public static function linea_options(): array {
        return [
            '3' => '3 - Impulsa Personas (Franquicia Tributaria)',
            '1' => '1 - Programas Sociales / Becas Laborales',
            '6' => '6 - FPT',
        ];
    }

    /**
     * Normaliza la configuración de una instancia (con valores por defecto).
     *
     * @param stdClass|null $c Configuración guardada de la instancia.
     * @return stdClass
     */
    public static function normalize_config($c): stdClass {
        $c = is_object($c) ? $c : new stdClass();
        $n = new stdClass();
        $n->modo = (isset($c->modo) && $c->modo === 'curso') ? 'curso' : 'grupos';
        // Las versiones <= 2.2 leían "config_linea"; el formulario guarda "linea".
        $linea = (string) ($c->linea ?? ($c->config_linea ?? '3'));
        $n->linea = array_key_exists($linea, self::linea_options()) ? $linea : '3';
        $n->codsence = trim((string) ($c->codsence ?? ''));
        $n->codigocurso = trim((string) ($c->codigocurso ?? ''));
        $n->fecha_termino = (int) ($c->fecha_termino ?? 0);
        $n->rol = (int) ($c->rol ?? 0);
        $n->grupo_eximido = (int) ($c->grupo_eximido ?? 0);
        $n->cierre = !empty($c->cierre);
        $n->ambiente = in_array($c->ambiente ?? '', ['test', 'prod'], true) ? $c->ambiente : 'global';
        $n->support_email = trim((string) ($c->support_email ?? ''));
        $n->support_whatsapp = trim((string) ($c->support_whatsapp ?? ''));
        return $n;
    }

    /**
     * Configuración de la instancia del bloque en un curso (para exito.php,
     * error.php y demás páginas que no tienen el objeto del bloque).
     *
     * @param int $courseid
     * @return stdClass
     */
    public static function get_course_settings(int $courseid): stdClass {
        global $DB;
        $context = context_course::instance($courseid, IGNORE_MISSING);
        $config = null;
        if ($context) {
            $bi = $DB->get_record('block_instances',
                ['blockname' => 'senceeducaxpert', 'parentcontextid' => $context->id],
                'id, configdata', IGNORE_MULTIPLE);
            if ($bi && !empty($bi->configdata)) {
                $config = unserialize_object(base64_decode($bi->configdata));
            }
        }
        return self::normalize_config($config);
    }

    /**
     * Ambiente efectivo: el del curso si lo fuerza, si no el global.
     *
     * @param stdClass $settings
     * @return string 'prod' o 'test'
     */
    public static function effective_ambiente(stdClass $settings): string {
        if ($settings->ambiente !== 'global') {
            return $settings->ambiente;
        }
        return get_config('block_senceeducaxpert', 'ambiente') === 'prod' ? 'prod' : 'test';
    }

    /**
     * Contacto de soporte efectivo (el del curso o el global).
     *
     * @param stdClass $settings
     * @return array [email, whatsapp]
     */
    public static function effective_support(stdClass $settings): array {
        $email = $settings->support_email !== '' ? $settings->support_email
            : (string) get_config('block_senceeducaxpert', 'support_email');
        $wa = $settings->support_whatsapp !== '' ? $settings->support_whatsapp
            : (string) get_config('block_senceeducaxpert', 'support_whatsapp');
        return [$email, $wa];
    }

    /**
     * URLs del Registro de Asistencia (RCE) de SENCE.
     *
     * @param string $ambiente 'prod' o 'test'
     * @return array ['inicio' => url, 'cierre' => url]
     */
    public static function sence_urls(string $ambiente): array {
        $base = $ambiente === 'prod' ? 'https://sistemas.sence.cl/rce/Registro/' : 'https://sistemas.sence.cl/rcetest/Registro/';
        return ['inicio' => $base . 'IniciarSesion', 'cierre' => $base . 'CierreSesion'];
    }

    /**
     * Estado de asistencia del alumno en el curso.
     *
     *  - 'registrada': hay un registro exitoso dentro de la ventana.
     *  - 'cerrada': lo último exitoso dentro de la ventana fue un cierre de
     *    sesión SENCE explícito -debe volver a iniciar sesión-.
     *  - 'pendiente': no hay nada exitoso dentro de la ventana.
     *
     * @param int $userid
     * @param int $courseid
     * @return array [estado, último registro exitoso o null]
     */
    public static function attendance_state(int $userid, int $courseid): array {
        global $DB;
        $recs = $DB->get_records_select('block_senceeducaxpert_log',
            "userid = ? AND courseid = ? AND status = 'exito' AND timecreated >= ?",
            [$userid, $courseid, time() - self::VENTANA], 'timecreated DESC, id DESC', '*', 0, 1);
        $last = reset($recs);
        if (!$last) {
            return ['pendiente', null];
        }
        if ($last->eventtype === 'cierre' && $last->glosa === self::GLOSA_CIERRE) {
            return ['cerrada', $last];
        }
        return ['registrada', $last];
    }

    // ---------------------------------------------------------------------
    // Bloque.
    // ---------------------------------------------------------------------

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $courseid = $this->page->course->id;
        if ($courseid == SITEID) {
            return $this->content;
        }
        $context = context_course::instance($courseid);
        $settings = self::normalize_config($this->config ?? null);

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
                max-height: 95vh; overflow-y: auto;
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

        if (has_capability('moodle/course:update', $context)) {
            $this->content->text .= $this->get_manager_view($courseid, $settings);
        } else {
            $this->content->text .= $this->get_student_view($courseid, $settings);
        }

        return $this->content;
    }

    /**
     * Aviso visible cuando el curso trabaja contra el ambiente de test.
     *
     * @param stdClass $settings
     * @return string
     */
    private function render_test_banner(stdClass $settings): string {
        if (self::effective_ambiente($settings) !== 'test') {
            return '';
        }
        return html_writer::div('<strong>Atención:</strong> el módulo SENCE se está ejecutando en <strong>modo test</strong> '
            . '(no registra asistencia real).', 'alert alert-danger small p-2 text-left mb-2');
    }

    // ---------------------------------------------------------------------
    // Vista de gestor.
    // ---------------------------------------------------------------------

    private function get_manager_view(int $courseid, stdClass $settings): string {
        global $CFG;

        $urllogo = $CFG->wwwroot . '/blocks/senceeducaxpert/image.png';
        $sesskey = sesskey();

        $html = html_writer::start_div('text-center mb-3');
        $html .= '<img src="' . s($urllogo) . '" style="max-width: 120px; margin-bottom: 10px;">';
        $html .= $this->render_test_banner($settings);

        $disclaimer = '<strong>💡 Consideraciones Importantes de Configuración:</strong><br/>';
        $disclaimer .= '1. <strong>Declaración Jurada:</strong> El día exacto que configure como "Término", el sistema exigirá obligatoriamente la <a href="https://lce.sence.cl/CertificadoAsistencia/" target="_blank" rel="noopener" style="font-weight:bold; text-decoration:underline;">Declaración Jurada</a> al alumno para entrar. Si desea que ese día aún marquen asistencia normal, sume un día adicional a la fecha límite.<br/>';
        $disclaimer .= '2. <strong>Asistencia:</strong> tras registrar su asistencia, el alumno queda libre por 2 horas. Si requiere que un alumno marque nuevamente, use el botón "Liberar" de forma individual o grupal.';
        $html .= html_writer::tag('div', $disclaimer, array('class' => 'alert alert-warning small p-3 text-left mb-3 shadow-sm'));

        $html .= $this->render_config_summary($courseid, $settings);

        $urlconfig = $CFG->wwwroot . '/admin/settings.php?section=blocksettingsenceeducaxpert';
        $html .= '<div class="mt-2 mb-3">';
        $html .= '<a href="' . s($urlconfig) . '" target="_blank" rel="noopener" class="btn btn-warning btn-sm w-100 shadow-sm" style="font-weight:bold;">';
        $html .= '⚙️ Configuración / Historial';
        $html .= '</a>';
        $html .= '</div>';
        $html .= html_writer::end_div();

        if ($settings->modo === 'curso') {
            $html .= $this->get_manager_course_panel($courseid, $settings);
        } else {
            $html .= $this->get_manager_groups_panel($courseid);
        }

        $html .= html_writer::tag('div', get_string('credits', 'block_senceeducaxpert'),
            array('class' => 'text-muted text-center small mt-3', 'style' => 'opacity:.6;'));

        // La sesskey se pasa en cada llamada para que los endpoints la validen (CSRF).
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
                let texto = gid ? "¿Estás seguro de que quieres LIBERAR A TODO EL GRUPO? Se borrará el registro de asistencia de hoy para todos."
                                : "¿Estás seguro de que quieres LIBERAR A TODO EL CURSO? Se borrará el registro de asistencia de hoy para todos.";
                if (confirm(texto)) {
                    fetch("' . $CFG->wwwroot . '/blocks/senceeducaxpert/reset_group.php?gid="+gid+"&cid="+cid+"&sesskey="+SENCE_EDUCAXPERT_SESSKEY)
                    .then(() => { location.reload(); });
                }
            }
        </script>';

        return $html;
    }

    /**
     * Resumen de la configuración del curso con avisos de lo que está mal o
     * falta (equivale al cuadro "Configuración" del bloque antiguo).
     */
    private function render_config_summary(int $courseid, stdClass $settings): string {
        global $DB;
        $context = context_course::instance($courseid);
        $course = $this->page->course;
        $avisos = [];

        $items = [];
        $items[] = 'Modo: <strong>' . ($settings->modo === 'curso' ? 'un código para todo el curso' : 'por grupos') . '</strong>';
        if ($settings->modo === 'curso') {
            $codigocurso = $settings->codigocurso !== '' ? $settings->codigocurso : trim((string) $course->idnumber);
            $items[] = 'Código SENCE: <strong>' . s($settings->codsence ?: '—') . '</strong>';
            $items[] = 'Código curso: <strong>' . s($codigocurso ?: '—') . '</strong>'
                . ($settings->codigocurso === '' ? ' <span class="text-muted">(ID del curso)</span>' : '');
            $items[] = 'Término: <strong>' . ($settings->fecha_termino ? userdate($settings->fecha_termino, '%d-%m-%Y') : 'sin fecha') . '</strong>';
            if (strlen($settings->codsence) < 10) {
                $avisos[] = 'El Código SENCE debe tener al menos 10 caracteres.';
            }
            if (strlen($codigocurso) < 7) {
                $avisos[] = 'El Código curso debe tener al menos 7 caracteres.';
            }
        } else if (strlen(trim((string) $course->idnumber)) < 10) {
            $avisos[] = 'El ID del curso (Código SENCE por defecto) tiene menos de 10 caracteres: '
                . 'cada grupo activo debe forzar su CodSence.';
        }
        $lineas = self::linea_options();
        $items[] = 'Línea: <strong>' . s($lineas[$settings->linea]) . '</strong>';
        $ambiente = self::effective_ambiente($settings);
        $items[] = 'Ambiente: <strong>' . ($ambiente === 'prod' ? 'producción' : 'test') . '</strong>'
            . ($settings->ambiente === 'global' ? ' <span class="text-muted">(global)</span>' : ' <span class="text-muted">(forzado en este curso)</span>');
        $items[] = 'Botón de cierre de sesión: <strong>' . ($settings->cierre ? 'sí' : 'no') . '</strong>';
        $rolnombre = 'todos los alumnos';
        if ($settings->rol > 0 && ($role = $DB->get_record('role', ['id' => $settings->rol]))) {
            $rolnombre = role_get_name($role, $context);
        }
        $items[] = 'Deben marcar: <strong>' . s($rolnombre) . '</strong>';
        $gruponombre = 'ninguno';
        if ($settings->grupo_eximido > 0) {
            $gruponombre = groups_get_group_name($settings->grupo_eximido) ?: ('#' . $settings->grupo_eximido . ' (no existe)');
        }
        $items[] = 'Grupo eximido: <strong>' . s($gruponombre) . '</strong>';
        [$email, $wa] = self::effective_support($settings);
        $items[] = 'Contacto: <strong>' . s(trim($email . ' ' . $wa) ?: '—') . '</strong>';

        if (trim((string) get_config('block_senceeducaxpert', 'otec_rut')) === ''
                || trim((string) get_config('block_senceeducaxpert', 'otec_token')) === '') {
            $avisos[] = 'Faltan el RUT o el token de la OTEC en la configuración del bloque.';
        }

        $html = '<div class="card p-2 mb-2 small text-left"><div class="mb-1"><strong>Configuración del curso</strong>'
            . ' <span class="text-muted">(edite el bloque para cambiarla)</span></div><ul class="mb-1 pl-3">';
        foreach ($items as $item) {
            $html .= '<li>' . $item . '</li>';
        }
        $html .= '</ul>';
        foreach ($avisos as $aviso) {
            $html .= '<div class="text-danger">⚠ ' . s($aviso) . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Panel por grupos (modo "grupos").
     */
    private function get_manager_groups_panel(int $courseid): string {
        global $DB;

        $html = html_writer::tag('div', '<strong>Administración de Grupos</strong>', array('class' => 'text-dark mb-1'));

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
            if (strlen($codigo) < 7) {
                $grupohtml .= html_writer::tag('div', '⚠ Su ID (Código curso SENCE) tiene menos de 7 caracteres: no exige asistencia.',
                    array('class' => 'small text-danger'));
            }

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
            $grupohtml .= $this->render_members_list($members, $courseid);
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

        return $html;
    }

    /**
     * Panel del modo "curso": alumnos que deben marcar, con "Liberar".
     */
    private function get_manager_course_panel(int $courseid, stdClass $settings): string {
        $context = context_course::instance($courseid);
        $fields = 'u.id, u.firstname, u.lastname, u.idnumber, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';
        $users = get_enrolled_users($context, '', 0, $fields, 'u.lastname, u.firstname', 0, 0, true);
        $deben = [];
        foreach ($users as $u) {
            if (has_capability('moodle/course:update', $context, $u->id)) {
                continue;
            }
            if ($settings->grupo_eximido > 0 && groups_is_member($settings->grupo_eximido, $u->id)) {
                continue;
            }
            if ($settings->rol > 0 && !user_has_role_assignment($u->id, $settings->rol, $context->id)) {
                continue;
            }
            $deben[$u->id] = $u;
        }
        $count = count($deben);

        $html = html_writer::tag('div', '<strong>Alumnos que deben marcar asistencia</strong>', array('class' => 'text-dark mb-1'));
        $html .= html_writer::tag('button', "Ver Alumnos ({$count})", array(
            'class' => 'btn btn-sm btn-outline-primary w-100',
            'onclick' => "document.getElementById('curso-alumnos-{$courseid}').classList.toggle('d-none')",
        ));
        $html .= html_writer::tag('button', 'Liberar Todo el Curso', array(
            'class' => 'btn btn-sm btn-outline-danger w-100 mt-1',
            'title' => 'Borrará el éxito de hoy para todos en este curso',
            'onclick' => "resetGroupSenceEducaxpert(0, {$courseid})",
        ));
        $html .= html_writer::start_div('d-none mt-2 p-2 bg-light border rounded small', array('id' => "curso-alumnos-{$courseid}"));
        $html .= $this->render_members_list($deben, $courseid);
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Lista de alumnos con botones "Editar" y "Liberar".
     */
    private function render_members_list(array $members, int $courseid): string {
        global $CFG;
        if (!$members) {
            return html_writer::tag('div', 'Sin alumnos.', array('class' => 'text-muted text-center py-2'));
        }
        $html = '';
        foreach ($members as $m) {
            $rutmostrar = !empty($m->idnumber) ? s($m->idnumber) : '<span class="text-danger">Sin RUT</span>';
            $nombremostrar = s(fullname($m));
            $urledit = $CFG->wwwroot . '/user/editadvanced.php?id=' . $m->id . '&course=' . $courseid;
            $html .= html_writer::start_div('d-flex justify-content-between align-items-center border-bottom pb-1 mb-1');
            $html .= html_writer::start_div('text-truncate', array('style' => 'max-width: 55%; line-height: 1.1;'));
            $html .= "<strong>{$nombremostrar}</strong><br><span class='text-muted' style='font-size: 0.85em;'>RUT: {$rutmostrar}</span>";
            $html .= html_writer::end_div();
            $html .= '<div class="btn-group">';
            $html .= '<a href="' . s($urledit) . '" target="_blank" rel="noopener" class="btn btn-info btn-sm py-0 px-1 text-white" style="font-size: 0.8em;">Editar</a>';
            $html .= '<button onclick="resetUserSenceEducaxpert(' . $m->id . ', ' . $courseid . ')" class="btn btn-danger btn-sm py-0 px-1 text-white" style="font-size: 0.8em;" title="Borrar registro de asistencia de hoy">Liberar</button>';
            $html .= '</div>';
            $html .= html_writer::end_div();
        }
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
        $html .= '<span>' . $title . '</span> <span class="badge badge-light bg-light text-dark">' . $count . '</span>';
        $html .= '</button>';
        $html .= '<div id="' . $id . '" class="' . $display . ' p-1 border rounded bg-light">';
        $html .= $content;
        $html .= '</div></div>';
        return $html;
    }

    // ---------------------------------------------------------------------
    // Vista de alumno.
    // ---------------------------------------------------------------------

    /**
     * Acciones SENCE que este alumno debe registrar hoy en el curso.
     *
     * @return stdClass[] cada una con label, codsence, codigocurso y fecha
     */
    private function get_targets(int $courseid, stdClass $settings): array {
        global $USER, $DB;
        $course = $this->page->course;
        $hoyts = strtotime('today');
        $targets = [];

        if ($settings->modo === 'curso') {
            $codigocurso = $settings->codigocurso !== '' ? $settings->codigocurso : trim((string) $course->idnumber);
            if ($settings->codsence === '' || strlen($codigocurso) < 7) {
                return [];
            }
            if ($settings->fecha_termino > 0 && $hoyts > $settings->fecha_termino) {
                return [];
            }
            $targets[] = (object) [
                'label' => $codigocurso,
                'codsence' => $settings->codsence,
                'codigocurso' => $codigocurso,
                'fecha' => $settings->fecha_termino,
            ];
            return $targets;
        }

        foreach (groups_get_all_groups($courseid, $USER->id) as $g) {
            $codigogrupo = !empty($g->idnumber) ? $g->idnumber : $g->name;
            if (strlen($codigogrupo) < 7) {
                continue;
            }
            $groupconfig = $DB->get_record('block_senceeducaxpert_groups', array('groupid' => $g->id));
            $fechalimite = ($groupconfig) ? (int) $groupconfig->fecha_limite : 0;
            // Grupos sin fecha configurada (inactivos) o ya terminados no exigen registro.
            if ($fechalimite == 0 || $hoyts > $fechalimite) {
                continue;
            }
            $codsence = $course->idnumber;
            if ($groupconfig && !empty($groupconfig->codigo_forzado)) {
                $codsence = $groupconfig->codigo_forzado;
            }
            $targets[] = (object) [
                'label' => $codigogrupo,
                'codsence' => $codsence,
                'codigocurso' => $codigogrupo,
                'fecha' => $fechalimite,
            ];
        }
        return $targets;
    }

    /**
     * Motivo por el que este alumno no debe marcar (o null si debe).
     */
    private function get_exemption(int $courseid, stdClass $settings): ?string {
        global $USER;
        $context = context_course::instance($courseid);
        if ($settings->grupo_eximido > 0 && groups_is_member($settings->grupo_eximido, $USER->id)) {
            return 'Su usuario pertenece a un grupo que está eximido del registro de asistencia SENCE.';
        }
        if ($settings->rol > 0 && !user_has_role_assignment($USER->id, $settings->rol, $context->id)) {
            return 'Su rol no requiere registro de asistencia en el módulo SENCE.';
        }
        if ($settings->modo === 'curso' && !is_enrolled($context, $USER, '', true)) {
            return 'El usuario no está matriculado en este curso.';
        }
        return null;
    }

    private function get_student_view(int $courseid, stdClass $settings): string {
        global $USER, $CFG;

        $targets = $this->get_targets($courseid, $settings);
        if (empty($targets)) {
            return '';
        }

        $exencion = $this->get_exemption($courseid, $settings);
        if ($exencion !== null) {
            return html_writer::div(s($exencion), 'alert alert-info small p-2');
        }

        $html = $this->render_test_banner($settings);
        [$estado, $last] = self::attendance_state($USER->id, $courseid);
        $hoyts = strtotime('today');
        $urllogo = $CFG->wwwroot . '/blocks/senceeducaxpert/image.png';

        if ($estado !== 'registrada') {
            $modalbody = '';
            if ($estado === 'cerrada') {
                $modalbody .= html_writer::div('Cerró su sesión SENCE. Para seguir en el curso debe iniciarla nuevamente.',
                    'alert alert-info small');
            }
            foreach ($targets as $t) {
                if ($t->fecha > 0 && $hoyts == $t->fecha) {
                    $modalbody .= $this->render_dj_ui($courseid, $settings);
                } else {
                    $modalbody .= $this->render_login_ui($t, $courseid, $settings);
                }
            }

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

        foreach ($targets as $t) {
            $html .= html_writer::start_div('alert alert-success py-2 small text-center shadow-sm');
            $html .= '<img src="' . s($urllogo) . '" style="max-width: 90px; margin-bottom: 8px;"><br>';
            $html .= '✅ <strong>Asistencia SENCE Registrada</strong><br><span class="text-muted">(' . s($t->label) . ')</span>';
            $html .= html_writer::end_div();
        }
        if ($settings->cierre && $last && $last->eventtype === 'inicio') {
            $html .= $this->get_close_form(reset($targets), $courseid, $settings, $last);
        }
        $html .= $this->render_support_box($settings);

        return $html;
    }

    private function render_login_ui(stdClass $target, int $courseid, stdClass $settings): string {
        global $CFG;

        $urlhome = $CFG->wwwroot . '/my/courses.php';
        $urllogo = $CFG->wwwroot . '/blocks/senceeducaxpert/image.png';

        $html = '<img src="' . s($urllogo) . '" alt="Logo SENCE" style="max-width: 180px; margin-bottom: 20px;">';
        $html .= '<h3>Control de Asistencia SENCE</h3>';
        $html .= '<p>Para acceder al contenido del curso hoy, es obligatorio registrar su asistencia en el portal oficial.</p>';

        $html .= $this->get_login_form($target, $courseid, $settings);
        $html .= '<a href="' . s($urlhome) . '" class="btn btn-outline-secondary mt-3 w-100">Regresar a Mis Cursos</a>';

        $html .= $this->render_support_box($settings);

        return $html;
    }

    private function render_dj_ui(int $courseid, stdClass $settings): string {
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

        $html .= $this->render_support_box($settings);

        return $html;
    }

    private function render_support_box(stdClass $settings): string {
        [$supportemail, $supportwa] = self::effective_support($settings);

        if (empty($supportemail) && empty($supportwa)) {
            return '';
        }

        $html = '<div class="mt-4 p-2 bg-light border rounded small text-muted text-left">';
        $html .= '<strong>¿Necesitas ayuda?</strong><br>';
        if (!empty($supportwa)) {
            $numerolimpio = preg_replace('/[^0-9]/', '', $supportwa);
            $texto = rawurlencode('Contacto desde plataforma virtual');
            $html .= '📞 WhatsApp: <a href="https://wa.me/' . s($numerolimpio) . '?text=' . $texto . '" target="_blank" rel="noopener">' . s($supportwa) . '</a><br>';
        }
        if (!empty($supportemail)) {
            $html .= '📧 Correo: <a href="mailto:' . s($supportemail) . '">' . s($supportemail) . '</a>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Campos comunes del POST a SENCE.
     */
    private static function base_fields(string $ambiente, string $codsence, string $codigocurso, string $linea): array {
        global $USER, $DB;
        // Se consulta la BD directamente para evitar el RUT desactualizado por caché de sesión.
        $rutestudiante = $DB->get_field('user', 'idnumber', array('id' => $USER->id));
        return [
            'RutOtec' => get_config('block_senceeducaxpert', 'otec_rut'),
            'Token' => get_config('block_senceeducaxpert', 'otec_token'),
            'CodSence' => $ambiente === 'test' ? '-1' : $codsence,
            'CodigoCurso' => $ambiente === 'test' ? '-1' : $codigocurso,
            'LineaCapacitacion' => $linea,
            'RunAlumno' => $rutestudiante,
        ];
    }

    private static function render_form(string $url, array $fields, string $btntexto, string $btnclase): string {
        $f = '<form method="post" action="' . s($url) . '">';
        foreach ($fields as $n => $v) {
            $f .= '<input type="hidden" name="' . s($n) . '" value="' . s($v) . '">';
        }
        $f .= '<input type="submit" value="' . s($btntexto) . '" class="' . $btnclase . '">';
        $f .= '</form>';
        return $f;
    }

    private function get_login_form(stdClass $target, int $courseid, stdClass $settings): string {
        global $USER, $CFG;

        $ambiente = self::effective_ambiente($settings);
        $urls = self::sence_urls($ambiente);

        // SENCE vuelve a exito.php/error.php mediante un POST entre sitios: el
        // navegador no manda la cookie de sesión de Moodle en ese salto
        // (SameSite), así que require_login() ahí no reconocería al usuario.
        // Se identifica en su lugar con un token de un solo uso.
        $returntoken = self::create_return_token($USER->id, $courseid);

        $fields = self::base_fields($ambiente, $target->codsence, $target->codigocurso, $settings->linea);
        $fields['IdSesionAlumno'] = $USER->id . '-' . time();
        $fields['UrlRetoma'] = $CFG->wwwroot . '/blocks/senceeducaxpert/exito.php?id=' . $courseid . '&t=' . $returntoken . '&a=inicio';
        $fields['UrlError'] = $CFG->wwwroot . '/blocks/senceeducaxpert/error.php?id=' . $courseid . '&t=' . $returntoken . '&a=inicio';

        if ($ambiente === 'test') {
            return self::render_form($urls['inicio'], $fields, 'Iniciar Sesión (Modo Prueba)', 'btn btn-warning btn-lg w-100 shadow text-dark');
        }
        return self::render_form($urls['inicio'], $fields, 'Iniciar Sesión SENCE', 'btn btn-primary btn-lg w-100 shadow');
    }

    /**
     * Botón "Cerrar sesión SENCE" (CierreSesion), con el IdSesionSence que
     * SENCE devolvió al iniciar.
     */
    private function get_close_form(stdClass $target, int $courseid, stdClass $settings, stdClass $inicio): string {
        global $USER, $CFG;

        $payload = json_decode((string) $inicio->payload, true);
        $resp = is_array($payload) && isset($payload['RESPUESTA_SENCE']) && is_array($payload['RESPUESTA_SENCE'])
            ? $payload['RESPUESTA_SENCE'] : [];
        if (empty($resp['IdSesionSence'])) {
            return '';
        }

        $ambiente = self::effective_ambiente($settings);
        $urls = self::sence_urls($ambiente);
        $returntoken = self::create_return_token($USER->id, $courseid);

        $fields = self::base_fields($ambiente,
            (string) ($resp['CodSence'] ?? $target->codsence),
            (string) ($resp['CodigoCurso'] ?? $target->codigocurso),
            (string) ($resp['LineaCapacitacion'] ?? $settings->linea));
        $fields['IdSesionAlumno'] = (string) ($resp['IdSesionAlumno'] ?? ($USER->id . '-' . time()));
        $fields['IdSesionSence'] = (string) $resp['IdSesionSence'];
        $fields['UrlRetoma'] = $CFG->wwwroot . '/blocks/senceeducaxpert/exito.php?id=' . $courseid . '&t=' . $returntoken . '&a=cierre';
        $fields['UrlError'] = $CFG->wwwroot . '/blocks/senceeducaxpert/error.php?id=' . $courseid . '&t=' . $returntoken . '&a=cierre';

        return self::render_form($urls['cierre'], $fields, 'Cerrar Sesión SENCE', 'btn btn-outline-danger btn-sm w-100 mb-2');
    }

    // ---------------------------------------------------------------------
    // Tokens de retorno.
    // ---------------------------------------------------------------------

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

        // Limpieza: los tokens solo valen 30 minutos; los de más de un día sobran.
        $DB->delete_records_select('block_senceeducaxpert_tokens', 'timecreated < ?', [time() - DAYSECS]);

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
