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
 * Configuración por curso del bloque SENCE.
 *
 * Reúne las opciones de las dos integraciones anteriores: la de grupos
 * (block_senceluisalmon) y la de un código por curso (block_sence).
 *
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/senceeducaxpert/block_senceeducaxpert.php');

class block_senceeducaxpert_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        global $DB;

        $course = $this->page->course;
        $context = context_course::instance($course->id);
        $c = 'block_senceeducaxpert';

        $mform->addElement('header', 'config_header', get_string('config_header', $c));
        $mform->addElement('static', 'course_id_display', get_string('config_courseid', $c),
            s($course->id) . ' — ' . get_string('config_courseidnumber', $c) . ': ' . s($course->idnumber ?: '—'));

        // Modo de trabajo.
        $mform->addElement('select', 'config_modo', get_string('config_modo', $c), [
            'grupos' => get_string('config_modo_grupos', $c),
            'curso' => get_string('config_modo_curso', $c),
        ]);
        $mform->setDefault('config_modo', 'grupos');
        $mform->addHelpButton('config_modo', 'config_modo', $c);

        // Solo modo "curso".
        $mform->addElement('text', 'config_codsence', get_string('config_codsence', $c), ['size' => 20]);
        $mform->setType('config_codsence', PARAM_ALPHANUMEXT);
        $mform->hideIf('config_codsence', 'config_modo', 'neq', 'curso');

        $mform->addElement('text', 'config_codigocurso', get_string('config_codigocurso', $c), ['size' => 20]);
        $mform->setType('config_codigocurso', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('config_codigocurso', 'config_codigocurso', $c);
        $mform->hideIf('config_codigocurso', 'config_modo', 'neq', 'curso');

        $mform->addElement('date_selector', 'config_fecha_termino', get_string('config_fecha_termino', $c), ['optional' => true]);
        $mform->addHelpButton('config_fecha_termino', 'config_fecha_termino', $c);
        $mform->hideIf('config_fecha_termino', 'config_modo', 'neq', 'curso');

        // Comunes.
        $mform->addElement('select', 'config_linea', get_string('config_linea', $c), block_senceeducaxpert::linea_options());
        $mform->setDefault('config_linea', '3');
        $mform->addElement('static', 'info_linea', '',
            html_writer::tag('small', get_string('config_linea_desc', $c), array('class' => 'text-muted')));

        $mform->addElement('select', 'config_ambiente', get_string('config_ambiente', $c), [
            'global' => get_string('config_ambiente_global', $c),
            'test' => get_string('ambiente_test', $c),
            'prod' => get_string('ambiente_prod', $c),
        ]);
        $mform->setDefault('config_ambiente', 'global');

        $mform->addElement('selectyesno', 'config_cierre', get_string('config_cierre', $c));
        $mform->setDefault('config_cierre', 0);
        $mform->addHelpButton('config_cierre', 'config_cierre', $c);

        $roles = [0 => get_string('config_rol_todos', $c)];
        foreach (role_fix_names(get_all_roles($context), $context, ROLENAME_ALIAS) as $role) {
            $roles[$role->id] = $role->localname;
        }
        $mform->addElement('select', 'config_rol', get_string('config_rol', $c), $roles);
        $mform->setDefault('config_rol', 0);
        $mform->addHelpButton('config_rol', 'config_rol', $c);

        $grupos = [0 => get_string('none')];
        foreach (groups_get_all_groups($course->id) as $g) {
            $grupos[$g->id] = format_string($g->name);
        }
        $mform->addElement('select', 'config_grupo_eximido', get_string('config_grupo_eximido', $c), $grupos);
        $mform->setDefault('config_grupo_eximido', 0);
        $mform->addHelpButton('config_grupo_eximido', 'config_grupo_eximido', $c);

        $mform->addElement('text', 'config_support_email', get_string('config_support_email', $c), ['size' => 40]);
        $mform->setType('config_support_email', PARAM_EMAIL);
        $mform->addElement('text', 'config_support_whatsapp', get_string('config_support_whatsapp', $c), ['size' => 20]);
        $mform->setType('config_support_whatsapp', PARAM_TEXT);
        $mform->addElement('static', 'info_support', '',
            html_writer::tag('small', get_string('config_support_desc', $c), array('class' => 'text-muted')));
    }

    /**
     * Las instancias de v2.x guardaban la línea como "config_linea"; se
     * muestra en el campo nuevo para no perderla al editar.
     *
     * @param array|stdClass $defaults
     */
    public function set_data($defaults) {
        if (!empty($this->block->config) && is_object($this->block->config)
                && !isset($this->block->config->linea) && !empty($this->block->config->config_linea)) {
            $this->block->config->linea = $this->block->config->config_linea;
        }
        parent::set_data($defaults);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (($data['config_modo'] ?? 'grupos') === 'curso') {
            if (strlen(trim($data['config_codsence'] ?? '')) < 10) {
                $errors['config_codsence'] = get_string('error_codsence', 'block_senceeducaxpert');
            }
            $codigocurso = trim($data['config_codigocurso'] ?? '') ?: trim((string) $this->page->course->idnumber);
            if (strlen($codigocurso) < 7) {
                $errors['config_codigocurso'] = get_string('error_codigocurso', 'block_senceeducaxpert');
            }
        }
        return $errors;
    }
}
