<?php
class block_senceluisalmon_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        global $COURSE;

        $mform->addElement('header', 'config_header', 'Configuración de Instancia SENCE');

        // 1. Mostrar ID del Curso (Solo informativo)
        $mform->addElement('static', 'course_id_display', 'ID del Curso Moodle:', $COURSE->id);

        // 2. Línea de Capacitación (Único parámetro configurable aquí)
        $options = array(
            '3' => '3 - Impulsa Personas (Franquicia Tributaria)',
            '1' => '1 - Programas Sociales / Becas Laborales'
        );
        $mform->addElement('select', 'config_linea', 'Línea de Capacitación', $options);
        
        // Establecer por defecto la opción 3 según manual 
        $mform->setDefault('config_linea', '3');
        
        $mform->addElement('static', 'info_linea', '', 
            html_writer::tag('small', 'Por defecto se utiliza la línea 3 para Franquicia Tributaria.', array('class' => 'text-muted')));
    }
}
