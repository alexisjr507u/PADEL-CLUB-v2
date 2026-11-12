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

    // Respuestas incoherentes: no se asigna ninguna categoría arbitraria; vuelve a completar la encuesta.
    if (!empty($clasificacion['incoherente'])) {

        unset($_SESSION["encuesta_parte1"], $_SESSION["clasificacion"], $_SESSION["id_categoria"], $_SESSION["categoria_estado"]);

        header("Location: ../encuesta_categoria.php?error=incoherente");
        exit();

    }

    header("Location: ../encuesta_categoria2.php");
    exit();

}

$_SESSION["clasificacion"] = $clasificacion;
unset($_SESSION["encuesta_parte1"]);

// La categoría calculada se asigna directamente (4ta, 3ra, 2da y 1ra incluidas): no hay revisión ni aprobación.
$_SESSION["id_categoria"] = $clasificacion['id_categoria_estimada'];
$_SESSION["categoria_estado"] = 'Confirmada';

header("Location: guardar_usuario.php");
exit();

?>
