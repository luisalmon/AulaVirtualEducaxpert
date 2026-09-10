<?php
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Nombre OTEC
    $settings->add(new admin_setting_configtext('block_senceluisalmon/otec_name', get_string('otec_name', 'block_senceluisalmon'), '', 'Educaxpert', PARAM_TEXT));

    // RUT OTEC
    $settings->add(new admin_setting_configtext('block_senceluisalmon/otec_rut', get_string('otec_rut', 'block_senceluisalmon'), '', '78007901-0', PARAM_TEXT));

    // Token
    // Token
    $settings->add(new admin_setting_configpasswordunmask('block_senceluisalmon/otec_token', get_string('otec_token', 'block_senceluisalmon'), '', ''));

    // --- NUEVOS CAMPOS AÑADIDOS ---
    // Correo de Contacto / Soporte
    $settings->add(new admin_setting_configtext('block_senceluisalmon/support_email', 'Correo de Soporte SENCE', 'A este correo llegarán las alertas de error y lo verán los alumnos.', '', PARAM_EMAIL));

    // WhatsApp de Soporte
    $settings->add(new admin_setting_configtext('block_senceluisalmon/support_whatsapp', 'WhatsApp de Soporte', 'Número de contacto para los alumnos (ej: +56912345678).', '', PARAM_TEXT));
    // ------------------------------

    // Botón / Link al Historial
    $url_reporte = $CFG->wwwroot . '/blocks/senceluisalmon/report.php';
    // Botón al Historial - Versión Centrada y Estilizada

    // Creamos un contenedor centrado con márgenes
    $boton_html = html_writer::start_div('w-100 text-center my-4 p-3', array('style' => 'background: #f8f9fa; border-radius: 10px; border: 1px dashed #dee2e6;'));

    $boton_html .= html_writer::link(
        $url_reporte,
        '<i class="fa fa-list-alt"></i> VER HISTORIAL DE CONEXIONES SENCE',
        array(
            'class' => 'btn btn-primary btn-lg px-5 shadow',
            'style' => 'border-radius: 30px; font-weight: bold; letter-spacing: 1px;'
        )
    );

    $boton_html .= html_writer::tag(
        'p',
        'Consulte aquí el registro de todos los inicios y cierres de sesión realizados.',
        array('class' => 'text-muted small mt-2')
    );

    $boton_html .= html_writer::end_div();

    $settings->add(new admin_setting_heading('block_senceluisalmon_report_link', '', $boton_html));
    $options = array(
        'prod' => 'Ambiente Producción (Real)',
        'test' => 'Ambiente Test (Pruebas)'
    );
    $settings->add(new admin_setting_configselect(
        'block_senceluisalmon/ambiente',
        'Ambiente de Trabajo',
        'Seleccione si desea usar las URL de Test o Producción.',
        'test',
        $options
    ));
}
