<?php

include("../config/conexion.php");
require_once("csrf.php");

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../encuesta_categoria2.php");
    exit();

}

// Puntos de la parte 1
$puntos1 = $_SESSION["puntos1"];

// Puntos de la parte 2
$puntos2 =
    $_POST["p6"] +
    $_POST["p7"] +
    $_POST["p8"] +
    $_POST["p9"] +
    $_POST["p10"];

// Total de puntos
$total = $puntos1 + $puntos2;

// Guardamos el total por si lo queremos mostrar luego
$_SESSION["puntos_totales"] = $total;

// Calculamos el porcentaje
$porcentaje = ($total / 50) * 100;

$_SESSION["porcentaje"] = $porcentaje;

// Asignar categoría

if ($porcentaje <= 19) {

    $_SESSION["id_categoria"] = 1; // Principiante

} elseif ($porcentaje <= 34) {

    $_SESSION["id_categoria"] = 2; // 7ta

} elseif ($porcentaje <= 49) {

    $_SESSION["id_categoria"] = 3; // 6ta

} elseif ($porcentaje <= 64) {

    $_SESSION["id_categoria"] = 4; // 5ta

} elseif ($porcentaje <= 79) {

    $_SESSION["id_categoria"] = 5; // 4ta

} elseif ($porcentaje <= 89) {

    $_SESSION["id_categoria"] = 6; // 3ra

} elseif ($porcentaje <= 95) {

    $_SESSION["id_categoria"] = 7; // 2da

} else {

    $_SESSION["id_categoria"] = 8; // 1ra

}

header("Location: guardar_usuario.php");
exit();

?>