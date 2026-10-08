<?php

include("../config/conexion.php");

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