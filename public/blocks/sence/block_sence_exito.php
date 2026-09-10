<?php
	$dir = $_GET["r"]."/course/view.php?".
             "id=".$_GET["id"].
             "&tsence=".$_GET["t"].
             "&ssence=".$_GET["s"].
             "&psence=E".
             "&isence=".$_POST["IdSesionSence"];
    header('Location: '.$dir);
    exit();
?>