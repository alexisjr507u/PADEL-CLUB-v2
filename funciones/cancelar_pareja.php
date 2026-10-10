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

$id_pareja = intval($_POST['id_pareja'] ?? 0);
$esAdmin = ($_SESSION['id_rol'] == 1);

if ($id_torneo <= 0 || $id_pareja <= 0) {
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
        "SELECT t.nombre, t.tipo, c.id_usuario AS id_organizador
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

    if (!in_array($torneo['tipo'], ['Parejas', 'Mixto'], true)) {
        throw new Exception("no_existe");
    }


    // ==========================================
    // PAREJA A CANCELAR (con sus dos inscripciones)
    // ==========================================

    $stmtPareja = mysqli_prepare(
        $conexion,
        "SELECT p.id_pareja, p.id_inscripcion_1, p.id_inscripcion_2,
                i1.id_usuario AS id_usuario_1, i2.id_usuario AS id_usuario_2
         FROM pareja p
         INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
         INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
         WHERE p.id_pareja = ? AND p.id_torneo = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtPareja, "ii", $id_pareja, $id_torneo);
    mysqli_stmt_execute($stmtPareja);
    $pareja = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPareja));

    if (!$pareja) {
        throw new Exception("no_existe");
    }


    // ==========================================
    // CANCELAR AMBAS INSCRIPCIONES
    // ==========================================

    $stmtCancelarInsc = mysqli_prepare(
        $conexion,
        "UPDATE inscripcion SET estado = 'Cancelada' WHERE id_inscripcion IN (?, ?) AND estado = 'Confirmada'"
    );
    mysqli_stmt_bind_param($stmtCancelarInsc, "ii", $pareja['id_inscripcion_1'], $pareja['id_inscripcion_2']);
    mysqli_stmt_execute($stmtCancelarInsc);


    // ==========================================
    // BORRAR LA PAREJA
    // (no queda columna de estado en `pareja`; la fase de grupos
    // lee directamente de esta tabla asumiendo que toda fila
    // presente está confirmada, así que hay que eliminarla acá
    // para no dejar una pareja "cancelada" apareciendo como activa)
    // ==========================================

    $stmtBorrarPareja = mysqli_prepare($conexion, "DELETE FROM pareja WHERE id_pareja = ?");
    mysqli_stmt_bind_param($stmtBorrarPareja, "i", $id_pareja);
    mysqli_stmt_execute($stmtBorrarPareja);

    if (mysqli_stmt_affected_rows($stmtBorrarPareja) !== 1) {
        // Otra solicitud ya la canceló justo antes.
        throw new Exception("no_existe");
    }


    // ==========================================
    // NOTIFICAR A AMBOS INTEGRANTES
    // ==========================================

    $mensaje = 'Tu participación en pareja en el torneo ' . $torneo['nombre'] . ' fue cancelada por la organización.';

    $stmtNotif = mysqli_prepare(
        $conexion,
        "INSERT INTO notificacion (id_usuario_destino, tipo, mensaje, id_torneo)
         VALUES (?, 'invitacion_rechazada', ?, ?)"
    );

    foreach ([$pareja['id_usuario_1'], $pareja['id_usuario_2']] as $idUsuarioAfectado) {
        mysqli_stmt_bind_param($stmtNotif, "isi", $idUsuarioAfectado, $mensaje, $id_torneo);
        mysqli_stmt_execute($stmtNotif);
    }

    mysqli_commit($conexion);

    header("Location: ../gestionar_participantes.php?id_torneo=$id_torneo&accion=pareja_cancelada");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al cancelar pareja $id_pareja: " . $motivo);
    }

    header("Location: ../gestionar_participantes.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
