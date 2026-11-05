<?php

include("../config/conexion.php");
require_once("csrf.php");
require_once("clasificacion_categoria.php");

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../encuesta_categoria.php");
    exit();

}

// Respuestas anteriores (si repite la encuesta) no deben mezclarse con las nuevas.
unset($_SESSION["encuesta_parte1"], $_SESSION["clasificacion"], $_SESSION["id_categoria"], $_SESSION["categoria_estado"]);

$p1 = $_POST["p1"] ?? '';

if ($p1 === "nunca") {

    // Nunca jugó: Principiante, confirmada, sin revisión.
    $_SESSION["id_categoria"] = 1;
    $_SESSION["categoria_estado"] = 'Confirmada';

    header("Location: guardar_usuario.php");
    exit();

}

// Solo se aceptan letras que existen en la encuesta; los puntos los calcula el servidor.
$preguntas = preguntasEncuesta();
$parte1 = [];

for ($numero = 1; $numero <= 5; $numero++) {

    $letra = $_POST["p$numero"] ?? '';

    if (!is_string($letra) || $letra === 'nunca' || !isset($preguntas[$numero]['opciones'][$letra])) {

        header("Location: ../encuesta_categoria.php");
        exit();

    }

    $parte1[$numero] = $letra;

}

$_SESSION["encuesta_parte1"] = $parte1;

header("Location: ../encuesta_categoria2.php");
exit();

?>
