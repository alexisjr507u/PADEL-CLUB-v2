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
$tipo = $_POST['tipo'] ?? '';
$id_elemento = intval($_POST['id_elemento'] ?? 0);
$id_grupo_destino = intval($_POST['id_grupo_destino'] ?? 0);

if (
    $id_torneo <= 0 ||
    $id_elemento <= 0 ||
    $id_grupo_destino <= 0 ||
    !in_array($tipo, ['inscripcion', 'pareja'], true)
) {
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

    // El grupo destino debe existir y pertenecer a este mismo torneo.
    $stmtGrupoDestino = mysqli_prepare(
        $conexion,
        "SELECT id_grupo FROM grupo WHERE id_grupo = ? AND id_torneo = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtGrupoDestino, "ii", $id_grupo_destino, $id_torneo);
    mysqli_stmt_execute($stmtGrupoDestino);

    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmtGrupoDestino))) {
        throw new Exception("grupo_no_existe");
    }

    if ($tipo === 'inscripcion') {

        // La asignación actual debe existir y pertenecer a este torneo
        // (vía la inscripción), para no mover algo de otro torneo.
        $stmtActual = mysqli_prepare(
            $conexion,
            "SELECT gi.id_grupo_inscripcion
             FROM grupo_inscripcion gi
             INNER JOIN inscripcion insc ON insc.id_inscripcion = gi.id_inscripcion
             WHERE gi.id_inscripcion = ? AND insc.id_torneo = ?
             FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmtActual, "ii", $id_elemento, $id_torneo);
        mysqli_stmt_execute($stmtActual);

        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmtActual))) {
            throw new Exception("elemento_no_asignado");
        }

        $stmtMover = mysqli_prepare(
            $conexion,
            "UPDATE grupo_inscripcion SET id_grupo = ? WHERE id_inscripcion = ?"
        );
        mysqli_stmt_bind_param($stmtMover, "ii", $id_grupo_destino, $id_elemento);

    } else {

        $stmtActual = mysqli_prepare(
            $conexion,
            "SELECT gp.id_grupo_pareja
             FROM grupo_pareja gp
             INNER JOIN pareja p ON p.id_pareja = gp.id_pareja
             WHERE gp.id_pareja = ? AND p.id_torneo = ?
             FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmtActual, "ii", $id_elemento, $id_torneo);
        mysqli_stmt_execute($stmtActual);

        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmtActual))) {
            throw new Exception("elemento_no_asignado");
        }

        $stmtMover = mysqli_prepare(
            $conexion,
            "UPDATE grupo_pareja SET id_grupo = ? WHERE id_pareja = ?"
        );
        mysqli_stmt_bind_param($stmtMover, "ii", $id_grupo_destino, $id_elemento);

    }

    if (!mysqli_stmt_execute($stmtMover)) {
        throw new Exception("error_bd");
    }

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=movido");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al mover $tipo $id_elemento al grupo $id_grupo_destino: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
