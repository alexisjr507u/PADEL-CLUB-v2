<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("ranking_temporada.php");


// ==========================================
// SEGURIDAD: organizador o administrador (se valida en el servidor). Además, el organizador solo puede finalizar la temporada de SUS clubes:
// cerrarTemporada() lo confirma contra la base (administrador: cualquier club).
// ==========================================

if (!isset($_SESSION['id_usuario']) || !in_array((int) ($_SESSION['id_rol'] ?? 0), [1, 2], true)) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=ranking");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../paneles.php?seccion=ranking&accion=token");
    exit;
}

$idTemporada = intval($_POST['id_temporada'] ?? 0);
$idClub = intval($_POST['id_club'] ?? 0);

if ($idTemporada <= 0 || $idClub <= 0) {
    header("Location: ../paneles.php?seccion=ranking&accion=datos_invalidos");
    exit;
}

$resultado = cerrarTemporada($conexion, (int) $_SESSION['id_usuario'], $idClub, $idTemporada);

$accion = $resultado['ok'] ? 'temporada_cerrada' : $resultado['error'];

// Se vuelve al ranking del mismo club (si no tenía permiso sobre ese club, se vuelve sin club para no revelar nada).
$club = $resultado['ok'] || $resultado['error'] !== 'sin_permiso' ? '&club=' . $idClub : '';

header("Location: ../paneles.php?seccion=ranking&accion=" . urlencode($accion) . $club);
exit;
