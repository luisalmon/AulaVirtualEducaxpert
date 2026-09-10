<?php
    $errores=array(
						"100"=>"Contraseña incorrecta o el usuario no tiene Clave SENCE.",
						"200"=>"El POST tiene uno o más parámetros mandatorios sin información. Esto también ocurre cuando un parámetro está mal escrito (por ejemplo, RutAlumno en lugar de RunAlumno), o cuando se ingresan sólo espacios en blanco en un parámetro obligatorio.",
						"201"=>"La URL de Retoma y/o URL de Error no tienen información. Ambos parámetros son obligatorios en todos los POST.",
						"202"=>"La URL de Retoma tiene formato incorrecto.",
						"203"=>"La URL de Error tiene formato incorrecto.",
						"204"=>"El Código SENCE tiene menos de 10 caracteres y/o no es código válido.",
						"205"=>"El Código Curso tiene menos de 7 caracteres y/o no es código válido.",
						"206"=>"La línea de capacitación es incorrecta.",
						"207"=>"El Run Alumno tiene formato incorrecto, o tiene el dígito verificador incorrecto.",
						"208"=>"El Run Alumno no está autorizado para realizar el curso.",
						"209"=>"El Rut OTEC tiene formato incorrecto, o tiene el dígito verificador incorrecto.",
						"210"=>"Expiró el tiempo disponible para el ingreso de RUT y Contraseña. El tiempo disponible es de tres minutos.",
						"211"=>"El Token no pertenece al OTEC.",
						"212"=>"El Token no está vigente.",
						"300"=>"Error interno no clasificado, se debe reportar al SENCE con la mayor cantidad de antecedentes disponibles.",
						"301"=>"No se pudo registrar el ingreso o cierre de sesión. Esto ocurre cuando la Línea de Capacitación es incorrecta, o el Código de Curso es incorrecto.",
						"302"=>"No se pudo validar la información del Organismo, se debe reportar al SENCE con la mayor cantidad de antecedentes disponibles.",
						"303"=>"El Token no existe, o su formato es incorrecto.",
						"304"=>"No se pudieron verificar los datos enviados, se debe reportar al SENCE con la mayor cantidad de antecedentes disponibles.",
						"305"=>"No se pudo registrar la información, se debe reportar al SENCE con la mayor cantidad de antecedentes disponibles.",
						"306"=>"El Código Curso no corresponde al Código SENCE.",
						"307"=>"El Código Curso no tiene modalidad E-Learning.",
						"308"=>"El Código Curso no corresponde al RUT OTEC.",
						"309"=>"Las fechas de ejecución comunicadas para el Código Curso no corresponden a la fecha", 
						"310"=>"El Código Curso está en estado Terminado o Anulado");
	$to = $_GET["c"];
	$subject = "Error al acceder al curso";
	$headers = "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
	
	$message  = "<html><body><ul><li>Al iniciar el acceso al curso se genero el siguiente problema.</li></ul>";
	$message .= "<table style='width: 90%; border-color: black;' border='1'><tbody>";
	$message .= "<tr><td style='text-align: center;' colspan='4'>&nbsp;Informacion del Error</td></tr>";
	$message .= "<tr><td style='width: 70px;'>Alumno&nbsp;</td><td style='width: 10px;'>:&nbsp;</td>";
	$message .= "<td colspan='2'>&nbsp;<strong>".str_replace('_SEPARA_','<br/>',$_GET["i"])."</strong></td></tr>";
	$message .= "<tr><td style='width: 70px;'>Curso</td><td style='width: 10px;'>:&nbsp;</td>";
	$message .= "<td colspan='2'>&nbsp;<strong>".$_GET["u"]."</strong></td></tr>";
	$message .= "<tr><td style='width: 70px;'>Error&nbsp;</td><td style='width: 10px;'>:&nbsp;</td>";
	$message .= "<td colspan='2'>&nbsp;n&uacute;mero<strong>".$_POST["GlosaError"]."</strong></td></tr>";
	$message .= "<tr><td style='width: 70px;'>Motivo&nbsp;</td><td style='width: 10px;'>:&nbsp;</td>";
	$message .= "<td colspan='2'>&nbsp;<span style='color: #ff6600;'><strong>".$errores[$_POST["GlosaError"]];
	$message .= "</strong></span></td></tr></tbody></table></body></html>";

	mail($to, $subject, $message, $headers);
	$dir = $_GET["r"]."/course/view.php?".
             "id=".$_GET["id"].
             "&psence=F".
             "&ssence=".$_GET["s"].
			 "&esence=".$_POST["GlosaError"];
    header('Location: '.$dir);
    exit();
?>