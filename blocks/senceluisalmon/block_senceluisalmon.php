<?php
                        defined('MOODLE_INTERNAL') || die();

                        class block_senceluisalmon extends block_base
                        {
                            public function init()
                            {
                                $this->title = 'Portal SENCE 2026';
                            }

                            public function has_config()
                            {
                                return true;
                            }

                            public function instance_allow_config()
                            {
                                return true;
                            }

                            public function instance_allow_multiple()
                            {
                                return false;
                            }

                            public function get_content()
                            {
                                global $USER, $CFG, $DB, $PAGE;
                                if ($this->content !== null) return $this->content;
                                $this->content = new stdClass();
                                $this->content->text = '';

                                $courseid = $this->page->course->id;
                                $context = context_course::instance($courseid);
                                $is_gestor = has_capability('moodle/course:update', $context);

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

                                if ($is_gestor) {
                                    $this->content->text .= $this->get_manager_view($courseid);
                                    return $this->content;
                                }

                                $this->content->text .= $this->get_student_view($courseid);
                                return $this->content;
                            }

                            private function get_manager_view($courseid)
                            {
                                global $DB, $CFG;
                                $url_logo = $CFG->wwwroot . '/blocks/senceluisalmon/image.png';

                                $html = html_writer::start_div('text-center mb-3');
                                $html .= '<img src="' . $url_logo . '" style="max-width: 120px; margin-bottom: 10px;">';

                                $disclaimer = '<strong>💡 Consideraciones Importantes de Configuración:</strong><br/>';
                                $disclaimer .= '1. <strong>Declaración Jurada:</strong> El día exacto que configure como "Término", el sistema exigirá obligatoriamente la <a href="https://lce.sence.cl/CertificadoAsistencia/" target="_blank" style="font-weight:bold; text-decoration:underline;">Declaración Jurada</a> al alumno para entrar. Si desea que ese día aún marquen asistencia normal, sume un día adicional a la fecha límite.<br/>';
                                $disclaimer .= '2. <strong>Asistencia Única:</strong> El sistema permite registrar la asistencia <strong>una sola vez al día</strong>. Si requiere que un alumno marque nuevamente hoy, deberá utilizar el botón "Liberar" de forma individual o grupal para habilitarlo de nuevo.';
                                $html .= html_writer::tag('div', $disclaimer, array('class' => 'alert alert-warning small p-3 text-left mb-3 shadow-sm'));

                                $html .= html_writer::tag('div', '<strong>Administración de Grupos</strong>', array('class' => 'text-dark mb-1'));

                                $url_config = $CFG->wwwroot . '/admin/settings.php?section=blocksettingsenceluisalmon';
                                $html .= '<div class="mt-2 mb-3">';
                                $html .= '<a href="' . $url_config . '" target="_blank" class="btn btn-warning btn-sm w-100 shadow-sm" style="font-weight:bold;">';
                                $html .= '⚙️ Configuración / Historial';
                                $html .= '</a>';
                                $html .= '</div>';
                                $html .= html_writer::end_div();

                                $groups = groups_get_all_groups($courseid);

                                $html_en_curso = '';
                                $html_terminados = '';
                                $html_inactivos = '';

                                $cont_curso = 0;
                                $cont_terminados = 0;
                                $cont_inactivos = 0;

                                $hoy_ts = strtotime('today');

                                foreach ($groups as $g) {
                                    $codigo = !empty($g->idnumber) ? $g->idnumber : $g->name;
                                    if (empty($codigo)) continue;

                                    $config = $DB->get_record('block_senceluisalmon_groups', array('groupid' => $g->id));
                                    $fecha_ts = ($config) ? $config->fecha_limite : 0;
                                    $fecha_val = ($fecha_ts > 0) ? date('Y-m-d', $fecha_ts) : '';
                                    $cod_forzado_val = ($config && isset($config->codigo_forzado)) ? s($config->codigo_forzado) : '';

                                    $members = groups_get_members($g->id, 'u.id, u.firstname, u.lastname, u.idnumber');
                                    $count = count($members);

                                    $grupo_html = html_writer::start_div('card mb-2 p-2 border-left-info shadow-sm bg-white');
                                    $grupo_html .= html_writer::tag('div', '<strong>Grupo:</strong> ' . s($g->name), array('class' => 'small'));

                                    // UI de Configuración del grupo
                                    $grupo_html .= '<div class="mt-2 mb-2 p-2 bg-light border rounded small">';
                                    $grupo_html .= '<div class="mb-1">Término: <input type="date" class="form-control form-control-sm" id="date_' . $g->id . '" value="' . $fecha_val . '"></div>';
                                    $grupo_html .= '<div class="mb-1" title="Si lo llenas, reemplaza al ID del curso">Forzar CodSence: <input type="text" class="form-control form-control-sm" id="cod_' . $g->id . '" value="' . $cod_forzado_val . '" placeholder="Dejar vacío por defecto"></div>';
                                    $grupo_html .= '<button class="btn btn-sm btn-success w-100 mt-1" onclick="saveGroupConfig(' . $g->id . ')">Guardar Config</button>';
                                    $grupo_html .= '</div>';

                                    $grupo_html .= html_writer::tag('button', "Ver Alumnos ({$count})", array(
                                        'class' => 'btn btn-sm btn-outline-primary w-100',
                                        'onclick' => "document.getElementById('grupo-alumnos-{$g->id}').classList.toggle('d-none')"
                                    ));

                                    // NUEVO BOTÓN: Liberar Todo el Grupo
                                    $grupo_html .= html_writer::tag('button', "Liberar Todo el Grupo", array(
                                        'class' => 'btn btn-sm btn-outline-danger w-100 mt-1',
                                        'title' => 'Borrará el éxito de hoy para todos en este grupo',
                                        'onclick' => "resetGroupSence({$g->id}, {$courseid})"
                                    ));

                                    $grupo_html .= html_writer::start_div('d-none mt-2 p-2 bg-light border rounded small', array('id' => "grupo-alumnos-{$g->id}"));
                                    if ($count > 0) {
                                        foreach ($members as $m) {
                                            $rut_mostrar = !empty($m->idnumber) ? $m->idnumber : '<span class="text-danger">Sin RUT</span>';

                                            $url_edit = $CFG->wwwroot . '/user/editadvanced.php?id=' . $m->id . '&course=' . $courseid;
                                            $grupo_html .= html_writer::start_div('d-flex justify-content-between align-items-center border-bottom pb-1 mb-1');
                                            $grupo_html .= html_writer::start_div('text-truncate', array('style' => 'max-width: 55%; line-height: 1.1;'));
                                            $grupo_html .= "<strong>{$m->firstname} {$m->lastname}</strong><br><span class='text-muted' style='font-size: 0.85em;'>RUT: {$rut_mostrar}</span>";
                                            $grupo_html .= html_writer::end_div();

                                            // Botones de acción del alumno
                                            $grupo_html .= '<div class="btn-group">';
                                            $grupo_html .= '<a href="' . $url_edit . '" target="_blank" class="btn btn-info btn-sm py-0 px-1 text-white" style="font-size: 0.8em;">Editar</a>';
                                            $grupo_html .= '<button onclick="resetUserSence(' . $m->id . ', ' . $courseid . ')" class="btn btn-danger btn-sm py-0 px-1 text-white" style="font-size: 0.8em;" title="Borrar registro de asistencia de hoy">Liberar</button>';
                                            $grupo_html .= '</div>';

                                            $grupo_html .= html_writer::end_div();
                                        }
                                    } else {
                                        $grupo_html .= html_writer::tag('div', 'Sin alumnos.', array('class' => 'text-muted text-center py-2'));
                                    }
                                    $grupo_html .= html_writer::end_div();
                                    $grupo_html .= html_writer::end_div();

                                    if ($fecha_ts == 0) {
                                        $html_inactivos .= $grupo_html;
                                        $cont_inactivos++;
                                    } else if ($fecha_ts < $hoy_ts) {
                                        $html_terminados .= $grupo_html;
                                        $cont_terminados++;
                                    } else {
                                        $html_en_curso .= $grupo_html;
                                        $cont_curso++;
                                    }
                                }

                                $html .= $this->build_admin_category('🟢 En Curso / Activos', $cont_curso, $html_en_curso, 'cat-curso', true, 'success');
                                $html .= $this->build_admin_category('🔴 Terminados', $cont_terminados, $html_terminados, 'cat-terminados', false, 'danger');
                                $html .= $this->build_admin_category('⚪ Inactivos / No SENCE', $cont_inactivos, $html_inactivos, 'cat-inactivos', false, 'secondary');

                                // Scripts JS
                                $html .= '<script>
            function saveGroupConfig(gid) { 
                let d = document.getElementById("date_"+gid).value;
                let c = document.getElementById("cod_"+gid).value;
                fetch("' . $CFG->wwwroot . '/blocks/senceluisalmon/save_group_config.php?gid="+gid+"&date="+d+"&cod="+encodeURIComponent(c))
                .then(() => { location.reload(); }); 
            }
            function resetUserSence(uid, cid) {
                if(confirm("¿Estás seguro de que quieres LIBERAR a este alumno? Esto borrará su asistencia exitosa de hoy.")) {
                    fetch("' . $CFG->wwwroot . '/blocks/senceluisalmon/reset_user.php?uid="+uid+"&cid="+cid)
                    .then(() => { location.reload(); }); 
                }
            }
            function resetGroupSence(gid, cid) {
                if(confirm("¿Estás seguro de que quieres LIBERAR A TODO EL GRUPO? Se borrará el registro de asistencia de hoy para todos.")) {
                    fetch("' . $CFG->wwwroot . '/blocks/senceluisalmon/reset_group.php?gid="+gid+"&cid="+cid)
                    .then(() => { location.reload(); }); 
                }
            }
        </script>';

                                return $html;
                            }

                            private function build_admin_category($title, $count, $content, $id, $is_open, $color)
                            {
                                if ($count == 0) $content = '<div class="small text-muted text-center p-2">No hay grupos aquí.</div>';
                                $display = $is_open ? '' : 'd-none';
                                $btn_class = "btn btn-sm btn-{$color} w-100 text-left font-weight-bold d-flex justify-content-between align-items-center mb-1 shadow-sm";

                                $html = '<div class="mb-2">';
                                $html .= '<button class="' . $btn_class . '" onclick="document.getElementById(\'' . $id . '\').classList.toggle(\'d-none\')">';
                                $html .= '<span>' . $title . '</span> <span class="badge badge-light text-dark">' . $count . '</span>';
                                $html .= '</button>';
                                $html .= '<div id="' . $id . '" class="' . $display . ' p-1 border rounded bg-light">';
                                $html .= $content;
                                $html .= '</div></div>';
                                return $html;
                            }

                            private function get_student_view($courseid)
                            {
                                global $USER, $DB, $CFG;
                                $user_groups = groups_get_all_groups($courseid, $USER->id);
                                if (empty($user_groups)) return '';

                                $html = '';
                                $hoy_ts = strtotime('today');
                                $lock_needed = false;
                                $modal_body = '';

                                foreach ($user_groups as $g) {
                                    $codigo_grupo = !empty($g->idnumber) ? $g->idnumber : $g->name;
                                    if (strlen($codigo_grupo) < 7) continue;

                                    $group_config = $DB->get_record('block_senceluisalmon_groups', array('groupid' => $g->id));
                                    $fecha_limite = ($group_config) ? $group_config->fecha_limite : 0;

                                    $cod_sence_principal = $this->page->course->idnumber;
                                    if ($group_config && !empty($group_config->codigo_forzado)) {
                                        $cod_sence_principal = $group_config->codigo_forzado;
                                    }

                                    // --- SOLUCIÓN PROBLEMA 1: SALTAR INACTIVOS ---
                                    if ($fecha_limite == 0) continue;
                                    if ($hoy_ts > $fecha_limite) continue;

                                    // NUEVO: Calcular hace exactamente 2 horas (7200 segundos)
                                    $limite_dos_horas = time() - 7200;

                                    $success_today = $DB->record_exists_select(
                                        'block_senceluisalmon_log',
                                        "userid = ? AND courseid = ? AND eventtype = 'inicio' AND status = 'exito' AND timecreated >= ?",
                                        array($USER->id, $courseid, $limite_dos_horas)
                                    );

                                    if (!$success_today) {
                                        $lock_needed = true;
                                        if ($fecha_limite > 0 && $hoy_ts == $fecha_limite) {
                                            $modal_body .= $this->render_dj_ui($codigo_grupo, $cod_sence_principal, $courseid);
                                        } else {
                                            $modal_body .= $this->render_login_ui($codigo_grupo, $cod_sence_principal, $courseid);
                                        }
                                    } else {
                                        $url_logo = $CFG->wwwroot . '/blocks/senceluisalmon/image.png';
                                        $html .= html_writer::start_div('alert alert-success py-2 small text-center shadow-sm');
                                        $html .= '<img src="' . $url_logo . '" style="max-width: 90px; margin-bottom: 8px;"><br>';
                                        $html .= '✅ <strong>Asistencia SENCE Registrada</strong><br><span class="text-muted">(' . s($codigo_grupo) . ')</span>';
                                        $html .= html_writer::end_div();
                                    }
                                }

                                if ($lock_needed) {
                                    $overlay = html_writer::start_div('sence-lock-overlay', array('id' => 'senceLock'));
                                    $overlay .= html_writer::tag('div', $modal_body, array('class' => 'sence-modal-content'));
                                    $overlay .= html_writer::end_div();

                                    $overlay .= '<script>
                document.body.classList.add("sence-locked");
                var senceModal = document.getElementById("senceLock");
                if (senceModal) { document.body.appendChild(senceModal); }
            </script>';

                                    return $overlay . $html;
                                }

                                return $html;
                            }

                            private function render_login_ui($codigo_grupo, $cod_sence_principal, $courseid)
                            {
                                global $CFG;
                                $url_home = $CFG->wwwroot . '/my/courses.php';
                                $url_logo = $CFG->wwwroot . '/blocks/senceluisalmon/image.png';

                                $html = '<img src="' . $url_logo . '" alt="Logo SENCE" style="max-width: 180px; margin-bottom: 20px;">';
                                $html .= '<h3>Control de Asistencia SENCE</h3>';
                                $html .= '<p>Para acceder al contenido del curso hoy, es obligatorio registrar su asistencia en el portal oficial.</p>';

                                $html .= $this->get_login_form($codigo_grupo, $cod_sence_principal, $courseid);
                                $html .= '<a href="' . $url_home . '" class="btn btn-outline-secondary mt-3 w-100">Regresar a Mis Cursos</a>';

                                $support_email = get_config('block_senceluisalmon', 'support_email');
                                $support_wa = get_config('block_senceluisalmon', 'support_whatsapp');

                                if (!empty($support_email) || !empty($support_wa)) {
                                    $html .= '<div class="mt-4 p-2 bg-light border rounded small text-muted text-left">';
                                    $html .= '<strong>¿Necesitas ayuda?</strong><br>';
                                    if (!empty($support_wa)) {
                                        $numero_limpio = preg_replace('/[^0-9]/', '', $support_wa);
                                        $html .= '📞 WhatsApp: <a href="https://wa.me/' . $numero_limpio . '" target="_blank">' . $support_wa . '</a><br>';
                                    }
                                    if (!empty($support_email)) {
                                        $html .= '📧 Correo: <a href="mailto:' . $support_email . '">' . $support_email . '</a>';
                                    }
                                    $html .= '</div>';
                                }

                                return $html;
                            }

                            private function render_dj_ui($codigo_grupo, $cod_sence_principal, $courseid)
                            {
                                global $CFG;
                                $url_home = $CFG->wwwroot . '/my/courses.php';
                                $url_logo = $CFG->wwwroot . '/blocks/senceluisalmon/image.png';

                                $html = '<img src="' . $url_logo . '" alt="Logo SENCE" style="max-width: 180px; margin-bottom: 20px;">';
                                $html .= '<h3>Finalización de Curso SENCE</h3>';
                                $html .= '<p>Hoy es el último día de capacitación. Según normativa SENCE, debe generar su <strong>Declaración Jurada</strong> para finalizar.</p>';
                                $html .= '<a href="https://lce.sence.cl/CertificadoAsistencia/" target="_blank" class="btn btn-danger btn-lg w-100 shadow" onclick="processDJ(' . $courseid . ')">Generar Declaración Jurada SENCE</a>';

                                $html .= '<a href="' . $url_home . '" class="btn btn-outline-secondary mt-3 w-100">Regresar a Mis Cursos</a>';

                                $html .= '<script>
        function processDJ(cid) {
            fetch("' . $CFG->wwwroot . '/blocks/senceluisalmon/process_dj.php?id=" + cid)
            .then(() => {
                document.getElementById("senceLock").style.display="none"; 
                document.body.classList.remove("sence-locked");
                window.location.reload(); 
            });
        }
        </script>';

                                $support_email = get_config('block_senceluisalmon', 'support_email');
                                $support_wa = get_config('block_senceluisalmon', 'support_whatsapp');

                                if (!empty($support_email) || !empty($support_wa)) {
                                    $html .= '<div class="mt-4 p-2 bg-light border rounded small text-muted text-left">';
                                    $html .= '<strong>¿Necesitas ayuda?</strong><br>';
                                    if (!empty($support_wa)) {
                                        $numero_limpio = preg_replace('/[^0-9]/', '', $support_wa);
                                        $html .= '📞 WhatsApp: <a href="https://wa.me/' . $numero_limpio . '" target="_blank">' . $support_wa . '</a><br>';
                                    }
                                    if (!empty($support_email)) {
                                        $html .= '📧 Correo: <a href="mailto:' . $support_email . '">' . $support_email . '</a>';
                                    }
                                    $html .= '</div>';
                                }

                                return $html;
                            }

                            private function get_login_form($codigo_grupo, $cod_sence_principal, $courseid)
                            {
                                global $USER, $CFG, $DB; // <--- Agregamos $DB aquí

                                $ambiente = get_config('block_senceluisalmon', 'ambiente');

                                if ($ambiente === 'test') {
                                    $url = 'https://sistemas.sence.cl/rcetest/Registro/IniciarSesion';
                                    $valor_codsence = '-1';
                                    $valor_codigocurso = '-1';
                                    $btn_texto = 'Iniciar Sesión (Modo Prueba)';
                                    $btn_clase = 'btn btn-warning btn-lg w-100 shadow text-dark';
                                } else {
                                    $url = 'https://sistemas.sence.cl/rce/Registro/IniciarSesion';
                                    $valor_codsence = $cod_sence_principal;
                                    $valor_codigocurso = $codigo_grupo;
                                    $btn_texto = 'Iniciar Sesión SENCE';
                                    $btn_clase = 'btn btn-primary btn-lg w-100 shadow';
                                }

                                // --- SOLUCIÓN PROBLEMA 3: RUT DEL ALUMNO DESDE LA BD ---
                                // Al consultar a la base de datos en tiempo real evitamos el error de la caché de sesión de Moodle.
                                $rut_estudiante = $DB->get_field('user', 'idnumber', array('id' => $USER->id));

                                $fields = array(
                                    'RutOtec' => get_config('block_senceluisalmon', 'otec_rut'),
                                    'Token' => get_config('block_senceluisalmon', 'otec_token'),
                                    'CodSence' => $valor_codsence,
                                    'CodigoCurso' => $valor_codigocurso,
                                    'LineaCapacitacion' => 3,
                                    'RunAlumno' => $rut_estudiante, // <--- Este campo ahora irá lleno sí o sí
                                    'IdSesionAlumno' => $USER->id . '-' . time(),
                                    'UrlRetoma' => $CFG->wwwroot . '/blocks/senceluisalmon/exito.php?id=' . $courseid,
                                    'UrlError' => $CFG->wwwroot . '/blocks/senceluisalmon/error.php?id=' . $courseid
                                );

                                $f = '<form method="post" action="' . $url . '">';
                                foreach ($fields as $n => $v) {
                                    $f .= '<input type="hidden" name="' . $n . '" value="' . s($v) . '">';
                                }
                                $f .= '<input type="submit" value="' . $btn_texto . '" class="' . $btn_clase . '">';
                                $f .= '</form>';

                                return $f;
                            }
                        }
