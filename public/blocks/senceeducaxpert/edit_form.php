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
 * @package    block_senceeducaxpert
 * @copyright  2026 Luis Almon (EducaXpert)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_senceeducaxpert_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        global $COURSE;

        $mform->addElement('header', 'config_header', get_string('config_header', 'block_senceeducaxpert'));

        $mform->addElement('static', 'course_id_display', 'ID del Curso Moodle:', $COURSE->id);

        // Única opción configurable por instancia: se envía a SENCE como
        // "LineaCapacitacion" (ver block_senceeducaxpert::get_login_form()).
        $options = array(
            '3' => '3 - Impulsa Personas (Franquicia Tributaria)',
            '1' => '1 - Programas Sociales / Becas Laborales',
        );
        $mform->addElement('select', 'config_linea', get_string('config_linea', 'block_senceeducaxpert'), $options);
        $mform->setDefault('config_linea', '3');
        $mform->addElement('static', 'info_linea', '',
            html_writer::tag('small', get_string('config_linea_desc', 'block_senceeducaxpert'), array('class' => 'text-muted')));
    }
}
