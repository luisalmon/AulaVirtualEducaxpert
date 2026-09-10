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
 * Course list block.
 *
 * @package    block_course_list
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->dirroot}/user/lib.php");
require_once("{$CFG->dirroot}/group/lib.php");
require_once("mcrip.php");
use block_sence\fetcher;

class block_sence extends block_base 
{
    function init() 
	{
        $this->title = get_string('sence', 'block_sence');
    }

    function has_config() 
    {
        return true;
    }

    function get_content() 
    {
        global $CFG, $USER, $DB, $OUTPUT, $THEME, $PAGE, $SITE;

        $id         = optional_param('id', 0, PARAM_INT);
        $id         = $PAGE->course->id;
		$curso      = "";
		$rut        = $USER->idnumber;
		$sence      = $this->config->sence_curso;
		$ccurso     = $PAGE->course->idnumber;
		$linea      = $this->config->sence_linea; 
		$nroles     = 0;
		$ngrupo     = 0;
		$edicion    = $USER->editing;
		
		$array_acc  = array(0=>array(1,'X','X','V'),
							1=>array(2,'X','X','V'),
							2=>array(3,'V','X','V'),
							3=>array(4,'V','V','X'),
							4=>array(5,'V','V','X'),
							5=>array(6,'V','V','X'),
							6=>array(7,'V','V','X'),
							7=>array(8,'V','V','X'));

		if ($this->config->sence_productivo == 0)
		{ 
			$modo = "t"; 
			$sence = "-1";
			$ccurso = "-1";
		} 
		else { $modo = ""; }

		if ($this->config->sence_cierre == 0) { $cierre = 0; } else { $cierre = 1; }

		$_SESSION["ModuloSence".$id] = "SI";
		$info_usuario = user_get_user_details($USER, $PAGE->course);
		$info_roles = $info_usuario["roles"];
        $sw_role = false;
		$nroles = count($info_roles);
		$nrol = 100;
		if ($this->config->sence_rol != -1)
		{
			foreach ($info_roles as $rolew) 
            {
				if ($nrol>=$rolew["roleid"])
				{
					$nrol = $rolew["roleid"];
				}
				if ($rolew["roleid"] == $this->config->sence_rol)
                {
					$sw_role = true; 
                } 
            }
		}
		else
        {
            $sw_role = true;
        }
		$sw_grupo = false;
		$info_grupos = groups_get_user_groups($id, $USER->id);
		$info_grupos = $info_grupos[0];
		$ngrupo = count($info_grupos);
		if ($this->config->sence_grupo != -1)
		{
			for ($i=0;$i<$ngrupo; $i++)
			{
				if ($info_grupos[$i] == $this->config->sence_grupo)
				{
					$sw_grupo = true;    
				} 
			}
		}

		if($this->content !== NULL) 
		{
			return $this->content;
		}

		$courses = enrol_get_my_courses();
		$this->content = new stdClass;
		$date = date_create();

		$html .= '<LINK href="'.$CFG->wwwroot.'/blocks/sence/styles.css" rel="stylesheet" type="text/css">';
		$html .= '<div style="text-align: center;width:100%;">';
		$html .= '<img src="'.$CFG->wwwroot.'/blocks/sence/logo_sence.png">';
		$html .= '<div style="text-align: right;font-size:10px;width:100%;">v3.20220605</div>';

        $html .= '<a class="list-group-item list-group-item-action  " href="https://www.itic.cl/wp/" data-key="" data-isexpandable="0" data-indent="0" data-showdivider="1" data-type="60" data-nodetype="0" data-collapse="0" data-forceopen="0" data-isactive="0" data-hidden="0" data-preceedwithhr="0" id="s_yui_3_17_2_1_1641316925024_665">';
        $html .= '<div class="ml-0" id="s_yui_3_17_2_1_1641316925024_664">';
        $html .= '<div class="media" id="s_yui_3_17_2_1_1641316925024_663">';
        $html .= '<span class="media-left">';
        $html .= '<i class="icon fa fa-plus-square fa-fw " aria-hidden="true"></i>';
        $html .= '</span>';
        $html .= '<span class="media-body" style="color:green; font-size:8px;" id="s_yui_3_17_2_1_1641316925024_662">desarrollado por Itic.cl</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</a>';
                        
		$opciones = $array_acc[$nrol-1];

		$html_mensajes = "";
		if ($opciones[3]=="V")
		{
			$html_mensajes .= "<br/><table class='demoTable' style='height: 54px;'>".
					 		  "<thead><tr><td><span style='color:DARKBLUE;'>".
							   "Configuración</td></tr></thead><tr><td style='text-align:left;'><ul>";
			$html_mensajes .= "<li>".get_string('txtcurso', 'block_sence').", valor ingresado (".
			                  $sence.")";
			if ($sence=="" || strlen($sence)<7)
			{
				$html_mensajes .= ", <span style='color:red'>falta definir el parámetro o el largo ingresado no es correcto mínimo es de ".
								  "7 caracteres</span>";
			}
			$html_mensajes .= ".</li>";
			$html_mensajes .= "<li>Código curso, valor ingresado (".
			                  $ccurso.")";
			if ($ccurso=="" || strlen($ccurso)<7)
			{
				$html_mensajes .= ", <span style='color:red'>falta definir el parámetro o el largo ingresado no es correcto mínimo es de ".
								  "7 caracteres</span>";
			}
			$html_mensajes .= ".</li>";
			$html_mensajes .= "<li>".get_string('txtcierre', 'block_sence').", valor ingresado (".
									 ($this->config->sence_cierre==1?"Si":"No").")</li>";
			$options = array(1=>"1 - Programas Sociales o Becas Laborales",
							 3=>"3 - Franquicia Tributaria",
                             6=>"6 - FPT");
			$html_mensajes .= "<li>".get_string('txtlinea', 'block_sence').", valor ingresado (".
							  $options[$this->config->sence_linea].")</li>";
			$html_mensajes .= "<li>".get_string('txtcorreo', 'block_sence').", valor ingresado (".
							  $this->config->sence_correo.")";
			if ($this->config->sence_correo=="")
			{
				$html_mensajes .= ", <span style='color:red'>falta definir el parámetro</span>";
			}
			$html_mensajes .= ".</li>";
			$html_mensajes .= "<li>".get_string('txtwhatsap', 'block_sence').", valor ingresado (".
							  $this->config->sence_whatsap.")";
			if ($this->config->sence_whatsap=="")
			{
				$html_mensajes .= ", <span style='color:red'>falta definir el parámetro</span>";
			}
			$html_mensajes .= ".</li>";
			$html_mensajes .= "<li>".get_string('txtproductivo', 'block_sence').", valor ingresado (".
									 ($this->config->sence_productivo==1?"Si":"No").")</li>";
			$html_mensajes .= "<li>".get_string('txtgrupo', 'block_sence').", valor ingresado (";	   
			$grupos = $DB->get_records('groups', array('courseid'=>$this->page->course->id), 'name');
			foreach ($grupos as $grupo) 
			{
				if ($grupo->id == $this->config->sence_grupo)
				{
					$html_mensajes .= $grupo->name;		
				}
			}
			$html_mensajes .= ").</li>";
			$html_mensajes .= "<li>".get_string('txtrol', 'block_sence').", valor ingresado (";	   
			if ($this->config->sence_rol==-1)
			{
				$html_mensajes .= "Todos";
			}
			else
			{
				$roles = role_fix_names(get_all_roles(), $systemcontext, ROLENAME_ORIGINAL);
				foreach ($roles as $role) 
				{
					if ($role->id == $this->config->sence_rol)
					{
						$html_mensajes .= $role->localname;		
					}
				}
			}
			$html_mensajes .= ").</li>";
			$html_mensajes .= "</ul></td></tr></table>";
		}

		$_SESSION["ModuloSenceF".$id] = '';

		$sw_boton = true;
		if ($opciones[1]=="V")
		{
			if (!$sw_role || $sw_grupo)
			{
				$html .= '<p/><div style="color:#2f6473;background-color:#def2f8;border-color: #d1edf6;position: relative;';
				$html .= 'padding: .75rem 1.25rem;margin-bottom: 1rem;border: 0 solid transparent;">';
				if (!$sw_role && $nroles==0)
				{
					$html .= '<p>El usuario no esta matriculado en este curso</p>';
					if ($opciones[2]=="V")
					{
						$_SESSION["ModuloSenceF".$id] = 'filter:blur(6px)';
					}
				}
				if (!$sw_role && $nroles>0)
				{
					$html .= '<p>Su rol no requiere registro de asistencia en el módulo SENCE</p>';
					$_SESSION["ModuloSenceF".$id] = '';
				}
				if ($sw_grupo)
				{
					$html .= '<p>Su usuario pertenece a un grupo que esta eximido del registro al módulo SENCE.</p>';
					$_SESSION["ModuloSenceF".$id] = '';
				}
				$html .= '</div><p/>';
				$sw_boton = false;
			}
		}
        if ($_SESSION["swlogueado".$id]=="SI")
        {
            $html .= '<div style="color:#2f6473;background-color:#def2f8;border-color: #d1edf6;position: relative;';
            $html .= 'padding: .75rem 1.25rem;margin-bottom: 1rem;border: 0 solid transparent;">';
            $html .= 'Su sesión se ha iniciado de forma satisfactoria, ha marcado la asistencia correctamente en sence';
            $html .= '</div>';
        }
		if ($sw_boton || (!$sw_boton && $opciones[2]=="X"))
		{
			if ($opciones[1]=="V")
			{
				if ($_SESSION["swlogueado".$id]=="SI")
				{
					$boton = "Cerrar Sesión";
					$urlsence = desencriptar($CFG->sence["urlcierresence".$modo]);
					$llamada = "C";
					$_SESSION["ModuloSenceF".$id] = '';
				}
				else
				{
					$urlsence = desencriptar($CFG->sence["urliniciosence".$modo]);
					$boton = "Iniciar Sesión";
					$llamada = "I";
					if ($opciones[2]=="V")
					{
						$_SESSION["ModuloSenceF".$id] = 'filter:blur(6px)';
					}
				}			
				$html .= '<form name = "formPost" method="post" action="'.$urlsence.'" />';
				$html .= '<input type="hidden" name="RutOtec" value="'.desencriptar($CFG->sence["rutotec"]).'" />';
				$html .= '<input type="hidden" name="Token" value="'.desencriptar($CFG->sence["token"]).'" />';
				$html .= '<input type="hidden" name="CodSence" value="'.$sence.'" />';
				$html .= '<input type="hidden" name="CodigoCurso" value="'.$ccurso.'" />';
				$html .= '<input type="hidden" name="LineaCapacitacion" value="'.$linea.'" />';
				$html .= '<input type="hidden" name="RunAlumno" value="'.$rut.'" />';
				$html .= '<input type="hidden" name="IdSesionAlumno" value="'.date_timestamp_get($date).'" />';
				if ($llamada=="C")
				{
					$html .= '<input type="hidden" name="IdSesionSence" value="'.$_SESSION["IdSesionSence".$id].'"/>';
				}
				$html .= '<input type="hidden" name="UrlRetoma" value="'.$CFG->wwwroot.desencriptar($CFG->sence["urlexito"]).'?'.
							'r='.$CFG->wwwroot.
							'&id='.$id.
							'&t='.$llamada.
							'&s='.$_SESSION["swlogueado".$id].'"/>';
				$html .= '<input type="hidden" name="UrlError" value="'.$CFG->wwwroot.desencriptar($CFG->sence["urlfracaso"]).'?'.
							'r='.$CFG->wwwroot.
							'&id='.$id.
							'&c='.$this->config->sence_correo.
							'&i='.$USER->idnumber.' - '.$USER->firstname.' '.$USER->lastname.'_SEPARA_'.
								'email: '.$USER->email.'_SEPARA_'.
								'Fonos: '.$USER->phone1.' - '.$USER->phone2.
							'&u='.$PAGE->course->id.' - '.$PAGE->course->fullname.
							'&s='.$_SESSION["swlogueado".$id].'"/>';
				if ($cierre==0)
				{
					if ($boton=="Cerrar Sesión")
					{
						$html .= '<div style="color:#2f6473;background-color:#def2f8;border-color: #d1edf6;position: relative;';
						$html .= 'padding: .75rem 1.25rem;margin-bottom: 1rem;border: 0 solid transparent;">';
						$html .= 'Su sesión se ha iniciado de forma satisfactoria, ha marcado la asistencia correctamente en sence';
						$html .= '</div>';
					}			
					else
					{
						$html .= '<input type="submit" class="btn_sence" value="'.$boton.'"/>';
					}
				}	
				else
				{
					$html .= '<input type="submit" class="btn_sence" value="'.$boton.'"/>';
				}
				$html .= '</form><br/>';
				if ($_SESSION["errorvisible".$id]!="")
				{
					$terror1 = "Uno o más parámetros mandatorios estan sin información o incorrectos. ".
								"(contacte al administrador del curso.";
					$errores_salida=array(
								"100"=>"Contraseña incorrecta o el usuario no tiene Clave SENCE.",
								"200"=>$terror1,"201"=>$terror1,"202"=>$terror1,"203"=>$terror1,
								"204"=>"El Código SENCE tiene menos de 10 caracteres y/o no es código válido.",
								"205"=>"El Código Curso tiene menos de 7 caracteres y/o no es código válido.",
								"206"=>"La línea de capacitación es incorrecta.",
								"207"=>"El Run Alumno tiene formato incorrecto, o tiene el dígito verificador incorrecto.",
								"208"=>"El Run Alumno no está autorizado para realizar el curso.",
								"209"=>"Uno o más parámetros mandatorios estan sin información o incorrectos. (contacte al administrador del curso).",
								"210"=>"Expiró el tiempo disponible para el ingreso de RUT y Contraseña. El tiempo disponible es de tres minutos.",
								"211"=>$terror1,"212"=>$terror1,"300"=>$terror1,
								"301"=>"No se pudo registrar el ingreso o cierre de sesión. Esto ocurre cuando la Línea de Capacitación es incorrecta, o el Código de Curso es incorrecto.",
								"302"=>"No se pudo validar la información del Organismo, se debe reportar al SENCE con la mayor cantidad de antecedentes disponibles.",
								"303"=>$terror1,"304"=>$terror1,"305"=>$terror1,"306"=>$terror1,
								"307"=>"El Código Curso no tiene modalidad E-Learning.",
								"308"=>"El Código Curso no corresponde al RUT OTEC.",
								"309"=>"Las fechas de ejecución comunicadas para el Código Curso no corresponden a la fecha", 
								"310"=>"El Código Curso está en estado Terminado o Anulado");
					$html .= '<div style="color: #6e211e;background-color: #f6d9d8;border-color: #f3c9c8;position: relative;';
					$html .= 'padding: .75rem 1.25rem;margin-bottom: 1rem;border: 0 solid transparent;">';
					$html .= $errores_salida[$_SESSION["errorvisible".$id]].'</div>';
					$_SESSION["errorvisible".$id]="";
				}
				$html .= '</div>';
				$html .= '<a  style="margin:auto;width:100%;" href="https://api.whatsapp.com/send?phone='.$this->config->sence_whatsap.'&text=Contacto%20desde%20plataforma%20virtual" target="_blank"><img style="margin: auto;width:50%;" class="btn_w" src="'.$CFG->wwwroot.'/blocks/sence/contactanos.png"></a>';
				//<ui style="padding-left:30px;"><b>Enlaces de Interés</b><li style="padding-left:50px;"><a  href="https://cus.sence.cl/Account/Registrar">Registrar CS</a><br/></li>'; 
				//$html .= '<li style="padding-left:50px;"><a href="https://cus.sence.cl/Account/RecuperarClave">Recuperar CS</a><br/></li>'; 
				//$html .= '<li style="padding-left:50px;"><a href="https://cus.sence.cl/Account/CambiarClave">Cambiar CS</a><br/></li>'; 
				//$html .= '<li style="padding-left:50px;"><a href="https://cus.sence.cl/Account/ActualizarDatos">Actualizar Datos</a><br/></li>'; 
				//$html .= '</ui>'; 
			}
			$html .= '<br/>';
		}
		$html .= $html_mensajes;
		if ($modo == "t")
		{
			$html .= "<p><br/></p><table class='demoTable'style='height:54px;'>". 
					"<thead style='background-color:red;'><tr><td style='color: white; background-color: red;'>".
					"<strong>Atención<strong></td>".
					"</tr></thead><tbody><tr><td style='text-align:left;'>".
					"</div>El módulo se esta ejecutando en modo test</td></tr></tbody></table>";
		}
		$this->content->text = $html;
		$this->content->footer = "";
        return $this->content;
    }

    function instance_allow_config() 
    {
    	return true;
	}

}