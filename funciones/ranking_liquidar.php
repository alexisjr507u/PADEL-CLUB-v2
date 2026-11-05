<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("ranking_puntos.php");


// ==========================================
// SEGURIDAD: organizador del torneo o administrador
// (el dueño se comprueba dentro de liquidarPuntosTorneo, con el torneo bloqueado)
// ==========================================

if (!isset($_SESSION['id_usuario']) || !in_array((int) ($_SESSION['id_rol'] ?? 0), [1, 2], true)) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

$id_torneo = intval($_POST['id_torneo'] ?? 0);

if ($id_torneo <= 0) {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=token#ranking-puntos");
    exit;
}

$resultado = liquidarPuntosTorneo(
    $conexion,
    $id_torneo,
    (int) $_SESSION['id_usuario'],
    ((int) $_SESSION['id_rol'] === 1)
);

$codigos = [
    'ya_liquidado' => 'ranking_ya_liquidado',
    'sin_partidos' => 'ranking_sin_partidos',
    'partidos_pendientes' => 'ranking_partidos_pendientes',
    'falta_final' => 'ranking_falta_final',
    'resultado_invalido' => 'ranking_resultado_invalido',
    'sin_permiso' => 'sin_permiso',
    'no_existe' => 'sin_permiso',
    'error_bd' => 'error_bd',
];

$accion = $resultado['ok'] ? 'ranking_liquidado' : ($codigos[$resultado['error']] ?? 'error_bd');

header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=$accion#ranking-puntos");
exit;
