<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");


// ==========================================
// SEGURIDAD: organizador o administrador
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
    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=token");
    exit;
}

$esAdmin = ($_SESSION['id_rol'] == 1);
$nombre = trim($_POST['nombre'] ?? '');

if ($id_torneo <= 0 || $nombre === '' || mb_strlen($nombre) > 50) {
    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=datos_invalidos");
    exit;
}


mysqli_begin_transaction($conexion);

try {

    $stmtTorneo = mysqli_prepare(
        $conexion,
        "SELECT c.id_usuario AS id_organizador
         FROM torneos t
         INNER JOIN club c ON c.id_club = t.id_club
         WHERE t.id_torneo = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtTorneo, "i", $id_torneo);
    mysqli_stmt_execute($stmtTorneo);
    $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTorneo));

    if (!$torneo) {
        throw new Exception("no_existe");
    }

    if (!$esAdmin && (int) $torneo['id_organizador'] !== (int) $_SESSION['id_usuario']) {
        throw new Exception("sin_permiso");
    }

    $stmtOrden = mysqli_prepare(
        $conexion,
        "SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente FROM grupo WHERE id_torneo = ?"
    );
    mysqli_stmt_bind_param($stmtOrden, "i", $id_torneo);
    mysqli_stmt_execute($stmtOrden);
    $siguienteOrden = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtOrden))['siguiente'];

    $stmtCrear = mysqli_prepare(
        $conexion,
        "INSERT INTO grupo (id_torneo, nombre, orden) VALUES (?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmtCrear, "isi", $id_torneo, $nombre, $siguienteOrden);

    if (!mysqli_stmt_execute($stmtCrear)) {
        throw new Exception("error_bd");
    }

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=grupo_creado");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al crear grupo en torneo $id_torneo: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
