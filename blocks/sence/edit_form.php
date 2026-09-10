<?php

class block_sence_edit_form extends block_edit_form 
{ 
    protected function specific_definition($mform) 
	{
        global $DB;

        $mform->addElement('header', 'config_header', get_string('tituloheader', 'block_sence'));
        $mform->addElement('text', 'config_sence_curso', get_string('txtcurso', 'block_sence'));
        $mform->setType('config_sence_curso', PARAM_RAW);
        $mform->addElement('selectyesno', 'config_sence_cierre', get_string('txtcierre', 'block_sence'));
        $mform->setDefault('config_sence_cierre', 0);
        $options = array(1=>"1 - Programas Sociales o Becas Laborales",
                         3=>"3 - Franquicia Tributaria",
                         6=>"6 - FPT");
        $mform->addElement('select', 'config_sence_linea', get_string('txtlinea', 'block_sence'),$options);
        $mform->setDefault('config_sence_linea', 1);
        $mform->setType('config_sence_linea', PARAM_INT);
        $mform->addElement('text', 'config_sence_correo', get_string('txtcorreo', 'block_sence'));
        $mform->setType('config_sence_correo', PARAM_RAW);
        $mform->addElement('text', 'config_sence_whatsap', get_string('txtwhatsap', 'block_sence'));
        $mform->setType('config_sence_whatsap', PARAM_RAW);
        $mform->addElement('selectyesno', 'config_sence_productivo', get_string('txtproductivo', 'block_sence'));
        $mform->setDefault('config_sence_productivo', 0);

        $arr_grupos = array(-1=>"Ninguno");
        $grupos = $DB->get_records('groups', array('courseid'=>$this->page->course->id), 'name');
        foreach ($grupos as $grupo) 
        {
            $arr_grupos[$grupo->id] = $grupo->id." - ".$grupo->name; 
        }
        $mform->addElement('select', 'config_sence_grupo', get_string('txtgrupo', 'block_sence'),$arr_grupos);
        $mform->setDefault('config_sence_grupo', 0);
        $mform->setType('config_sence_grupo', PARAM_INT);

        $arr_roles = array(-1=>"Todos");
        $roles = role_fix_names(get_all_roles(), $systemcontext, ROLENAME_ORIGINAL);
        foreach ($roles as $role) 
        {
            $arr_roles[$role->id] = $role->id." - ".$role->localname; 
        }
        $mform->addElement('select', 'config_sence_rol', get_string('txtrol', 'block_sence'),$arr_roles);
        $mform->setDefault('config_sence_rol', 1);
        $mform->setType('config_sence_rol', PARAM_INT);
    }
}