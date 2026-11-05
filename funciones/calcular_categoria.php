<?php

include("../config/conexion.php");
require_once("csrf.php");
require_once("clasificacion_categoria.php");

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../encuesta_categoria2.php");
    exit();

}

// La parte 2 requiere haber completado la parte 1 en esta sesión.
if (empty($_SESSION["encuesta_parte1"]) || !is_array($_SESSION["encuesta_parte1"])) {

    header("Location: ../encuesta_categoria.php");
    exit();

}

$respuestas = $_SESSION["encuesta_parte1"];

for ($numero = 6; $numero <= 10; $numero++) {
    $respuestas[$numero] = $_POST["p$numero"] ?? null;
}

$clasificacion = calcularClasificacionEncuesta($respuestas);

if (!$clasificacion['valido']) {

    header("Location: ../encuesta_categoria2.php");
    exit();

}

$_SESSION["clasificacion"] = $clasificacion;
unset($_SESSION["encuesta_parte1"]);

if ($clasificacion['requiere_revision']) {

    // Pendiente: no hay categoría definitiva hasta que la revise el administrador.
    // id_categoria queda vacío, así ninguna validación de categoría la da por buena.
    $_SESSION["id_categoria"] = null;
    $_SESSION["categoria_estado"] = 'Pendiente';

} else {

    $_SESSION["id_categoria"] = $clasificacion['id_categoria_estimada'];
    $_SESSION["categoria_estado"] = 'Confirmada';

}

header("Location: guardar_usuario.php");
exit();

?>
