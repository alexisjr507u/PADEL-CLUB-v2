<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("retiro.php");


// ==========================================
// SEGURIDAD: solo organizador (de su torneo) o administrador.
// Un participante no puede retirarse ni retirar a nadie. El estado del torneo se
// vuelve a leer de la base dentro de retirarDeTorneo(): solo Abierto/Cerrado.
// ==========================================

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['id_rol'], [1, 2], true)) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

$id_torneo = intval($_POST['id_torneo'] ?? 0);

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../gestionar_participantes.php?id_torneo=$id_torneo&accion=token");
    exit;
}

// Torneo individual: id_inscripcion. Parejas/Mixto: id_pareja (se retira a los dos).
$id = intval($_POST['id_inscripcion'] ?? ($_POST['id_pareja'] ?? 0));
$motivo = (string) ($_POST['motivo'] ?? '');

if ($id_torneo <= 0 || $id <= 0) {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

$resultado = retirarDeTorneo(
    $conexion,
    $id_torneo,
    $id,
    (int) $_SESSION['id_usuario'],
    (int) $_SESSION['id_rol'],
    $motivo
);

if (!$resultado['ok']) {
    header("Location: ../gestionar_participantes.php?id_torneo=$id_torneo&accion=" . urlencode($resultado['error']));
    exit;
}

header(
    "Location: ../gestionar_participantes.php?id_torneo=$id_torneo&accion=retirado"
    . "&pe=" . (int) $resultado['partidos_eliminados']
);
exit;
