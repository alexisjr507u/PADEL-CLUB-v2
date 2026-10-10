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
    header("Location: ../gestionar_participantes.php?id_torneo=$id_torneo&accion=token");
    exit;
}

$id_inscripcion = intval($_POST['id_inscripcion'] ?? 0);
$esAdmin = ($_SESSION['id_rol'] == 1);

if ($id_torneo <= 0 || $id_inscripcion <= 0) {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}


mysqli_begin_transaction($conexion);

try {

    // ==========================================
    // TORNEO + PERMISO
    // ==========================================

    $stmtTorneo = mysqli_prepare(
        $conexion,
        "SELECT t.tipo, c.id_usuario AS id_organizador
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

    if ($torneo['tipo'] !== 'Individual') {
        throw new Exception("no_existe");
    }


    // ==========================================
    // INSCRIPCIÓN A CANCELAR
    // ==========================================

    $stmtInsc = mysqli_prepare(
        $conexion,
        "SELECT id_usuario, estado FROM inscripcion
         WHERE id_inscripcion = ? AND id_torneo = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtInsc, "ii", $id_inscripcion, $id_torneo);
    mysqli_stmt_execute($stmtInsc);
    $inscripcion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtInsc));

    if (!$inscripcion || $inscripcion['estado'] !== 'Confirmada') {
        // Ya cancelada, o no pertenece a este torneo: no hay nada que hacer.
        throw new Exception("no_existe");
    }

    $stmtCancelar = mysqli_prepare(
        $conexion,
        "UPDATE inscripcion SET estado = 'Cancelada' WHERE id_inscripcion = ? AND estado = 'Confirmada'"
    );
    mysqli_stmt_bind_param($stmtCancelar, "i", $id_inscripcion);
    mysqli_stmt_execute($stmtCancelar);

    if (mysqli_stmt_affected_rows($stmtCancelar) !== 1) {
        // Otra solicitud ya la canceló justo antes: no liberamos el cupo dos veces.
        throw new Exception("no_existe");
    }


    // ==========================================
    // NOTIFICAR AL AFECTADO
    // ==========================================

    $stmtNombreTorneo = mysqli_prepare($conexion, "SELECT nombre FROM torneos WHERE id_torneo = ?");
    mysqli_stmt_bind_param($stmtNombreTorneo, "i", $id_torneo);
    mysqli_stmt_execute($stmtNombreTorneo);
    $nombreTorneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtNombreTorneo))['nombre'];

    $mensaje = 'Tu inscripción al torneo ' . $nombreTorneo . ' fue cancelada por la organización.';

    $stmtNotif = mysqli_prepare(
        $conexion,
        "INSERT INTO notificacion (id_usuario_destino, tipo, mensaje, id_torneo)
         VALUES (?, 'invitacion_rechazada', ?, ?)"
    );
    mysqli_stmt_bind_param($stmtNotif, "isi", $inscripcion['id_usuario'], $mensaje, $id_torneo);
    mysqli_stmt_execute($stmtNotif);

    mysqli_commit($conexion);

    header("Location: ../gestionar_participantes.php?id_torneo=$id_torneo&accion=cancelada");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al cancelar inscripcion $id_inscripcion: " . $motivo);
    }

    header("Location: ../gestionar_participantes.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
