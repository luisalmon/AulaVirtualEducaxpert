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

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('block_senceeducaxpert/otec_name',
        get_string('otec_name', 'block_senceeducaxpert'), '', 'Educaxpert', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('block_senceeducaxpert/otec_rut',
        get_string('otec_rut', 'block_senceeducaxpert'), '', '78007901-0', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('block_senceeducaxpert/otec_token',
        get_string('otec_token', 'block_senceeducaxpert'), '', ''));

    $settings->add(new admin_setting_configtext('block_senceeducaxpert/support_email',
        get_string('support_email', 'block_senceeducaxpert'),
        get_string('support_email_desc', 'block_senceeducaxpert'), '', PARAM_EMAIL));

    $settings->add(new admin_setting_configtext('block_senceeducaxpert/support_whatsapp',
        get_string('support_whatsapp', 'block_senceeducaxpert'),
        get_string('support_whatsapp_desc', 'block_senceeducaxpert'), '', PARAM_TEXT));

    $urlreporte = $CFG->wwwroot . '/blocks/senceeducaxpert/report.php';

    $botonhtml = html_writer::start_div('w-100 text-center my-4 p-3',
        array('style' => 'background: #f8f9fa; border-radius: 10px; border: 1px dashed #dee2e6;'));

    $botonhtml .= html_writer::link(
        $urlreporte,
        '<i class="fa fa-list-alt"></i> ' . strtoupper(get_string('viewhistory', 'block_senceeducaxpert')),
        array(
            'class' => 'btn btn-primary btn-lg px-5 shadow',
            'style' => 'border-radius: 30px; font-weight: bold; letter-spacing: 1px;',
        )
    );

    $botonhtml .= html_writer::tag('p', get_string('viewhistory_desc', 'block_senceeducaxpert'),
        array('class' => 'text-muted small mt-2'));

    $botonhtml .= html_writer::end_div();

    $settings->add(new admin_setting_heading('block_senceeducaxpert_report_link', '', $botonhtml));

    $options = array(
        'prod' => get_string('ambiente_prod', 'block_senceeducaxpert'),
        'test' => get_string('ambiente_test', 'block_senceeducaxpert'),
    );
    $settings->add(new admin_setting_configselect(
        'block_senceeducaxpert/ambiente',
        get_string('ambiente', 'block_senceeducaxpert'),
        get_string('ambiente_desc', 'block_senceeducaxpert'),
        'test',
        $options
    ));

    $settings->add(new admin_setting_heading('block_senceeducaxpert_credits', '',
        html_writer::tag('p', get_string('credits', 'block_senceeducaxpert'),
            array('class' => 'text-muted small text-center'))));
}
