<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");


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
$id_grupo = intval($_POST['id_grupo'] ?? 0);
$direccion = $_POST['direccion'] ?? '';

if ($id_torneo <= 0 || $id_grupo <= 0 || !in_array($direccion, ['subir', 'bajar'], true)) {
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

    $stmtActual = mysqli_prepare(
        $conexion,
        "SELECT id_grupo, orden FROM grupo WHERE id_grupo = ? AND id_torneo = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtActual, "ii", $id_grupo, $id_torneo);
    mysqli_stmt_execute($stmtActual);
    $grupoActual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtActual));

    if (!$grupoActual) {
        throw new Exception("no_existe");
    }

    $operador = ($direccion === 'subir') ? '<' : '>';
    $ordenVecino = ($direccion === 'subir') ? 'DESC' : 'ASC';

    $stmtVecino = mysqli_prepare(
        $conexion,
        "SELECT id_grupo, orden FROM grupo
         WHERE id_torneo = ? AND orden $operador ?
         ORDER BY orden $ordenVecino
         LIMIT 1
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtVecino, "ii", $id_torneo, $grupoActual['orden']);
    mysqli_stmt_execute($stmtVecino);
    $grupoVecino = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtVecino));

    if ($grupoVecino) {

        $stmtSwap = mysqli_prepare(
            $conexion,
            "UPDATE grupo SET orden = ? WHERE id_grupo = ?"
        );

        $ordenActual = (int) $grupoActual['orden'];
        $ordenVecinoVal = (int) $grupoVecino['orden'];

        mysqli_stmt_bind_param($stmtSwap, "ii", $ordenVecinoVal, $grupoActual['id_grupo']);
        if (!mysqli_stmt_execute($stmtSwap)) {
            throw new Exception("error_bd");
        }

        mysqli_stmt_bind_param($stmtSwap, "ii", $ordenActual, $grupoVecino['id_grupo']);
        if (!mysqli_stmt_execute($stmtSwap)) {
            throw new Exception("error_bd");
        }

    }
    // Si no hay vecino, el grupo ya está en el extremo: no hay nada que hacer.

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=grupo_reordenado");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al reordenar grupo $id_grupo: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
