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

if ($id_torneo <= 0 || $id_grupo <= 0) {
    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=datos_invalidos");
    exit;
}


mysqli_begin_transaction($conexion);

try {

    $stmtGrupo = mysqli_prepare(
        $conexion,
        "SELECT g.id_grupo, c.id_usuario AS id_organizador
         FROM grupo g
         INNER JOIN torneos t ON t.id_torneo = g.id_torneo
         INNER JOIN club c ON c.id_club = t.id_club
         WHERE g.id_grupo = ? AND g.id_torneo = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtGrupo, "ii", $id_grupo, $id_torneo);
    mysqli_stmt_execute($stmtGrupo);
    $grupo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtGrupo));

    if (!$grupo) {
        throw new Exception("no_existe");
    }

    if (!$esAdmin && (int) $grupo['id_organizador'] !== (int) $_SESSION['id_usuario']) {
        throw new Exception("sin_permiso");
    }

    // El ON DELETE CASCADE de grupo_inscripcion/grupo_pareja hacia grupo
    // limpia las asignaciones solas; las inscripciones y parejas del
    // torneo no se tocan en ningún momento.
    $stmtEliminar = mysqli_prepare($conexion, "DELETE FROM grupo WHERE id_grupo = ?");
    mysqli_stmt_bind_param($stmtEliminar, "i", $id_grupo);

    if (!mysqli_stmt_execute($stmtEliminar)) {
        throw new Exception("error_bd");
    }

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=grupo_eliminado");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al eliminar grupo $id_grupo: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
