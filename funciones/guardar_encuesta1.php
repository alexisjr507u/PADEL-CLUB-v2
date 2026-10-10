<?php

include("../config/conexion.php");
require_once("csrf.php");

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../encuesta_categoria.php");
    exit();

}

if ($_POST["p1"] == "nunca") {

    $_SESSION["id_categoria"] = 1;

    header("Location: guardar_usuario.php");
    exit();

}

$_SESSION["puntos1"] =
    $_POST["p1"] +
    $_POST["p2"] +
    $_POST["p3"] +
    $_POST["p4"] +
    $_POST["p5"];

header("Location: ../encuesta_categoria2.php");
exit();

?>