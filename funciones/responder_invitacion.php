<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("enviar_invitacion.php");


// ==========================================
// SEGURIDAD
// ==========================================

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 3) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=notificaciones");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../paneles.php?seccion=notificaciones&error=token");
    exit;
}

$id_invitacion = intval($_POST['id_invitacion'] ?? 0);
$accion = $_POST['accion'] ?? '';
$id_usuario_actual = (int) $_SESSION['id_usuario'];

if ($id_invitacion <= 0 || !in_array($accion, ['aceptar', 'rechazar'], true)) {
    header("Location: ../paneles.php?seccion=notificaciones");
    exit;
}


mysqli_begin_transaction($conexion);

try {

    // ==========================================
    // BLOQUEAR LA INVITACIÓN Y VERIFICAR QUE SEA
    // REALMENTE DEL USUARIO QUE LA ESTÁ RESPONDIENDO
    // ==========================================

    $stmtInvitacion = mysqli_prepare(
        $conexion,
        "SELECT i.id_invitacion, i.id_torneo, i.id_inscripcion_invita, i.estado, i.fecha_vencimiento,
                i.id_usuario_invitado,
                insc.id_usuario AS id_usuario_titular,
                t.nombre AS nombre_torneo, t.estado AS estado_torneo,
                t.id_categoria, t.max_participantes
         FROM invitacion i
         INNER JOIN inscripcion insc ON insc.id_inscripcion = i.id_inscripcion_invita
         INNER JOIN torneos t ON t.id_torneo = i.id_torneo
         WHERE i.id_invitacion = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtInvitacion, "i", $id_invitacion);
    mysqli_stmt_execute($stmtInvitacion);
    $invitacion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtInvitacion));

    if (!$invitacion) {
        throw new Exception("no_existe");
    }

    if ((int) $invitacion['id_usuario_invitado'] !== $id_usuario_actual) {
        // No es tu invitación: no decimos más que esto.
        throw new Exception("no_autorizado");
    }

    if ($invitacion['estado'] !== 'Pendiente') {
        throw new Exception("invitacion_no_pendiente");
    }

    if (strtotime($invitacion['fecha_vencimiento']) < time()) {

        $stmtVencer = mysqli_prepare(
            $conexion,
            "UPDATE invitacion SET estado = 'Vencida' WHERE id_invitacion = ? AND estado = 'Pendiente'"
        );
        mysqli_stmt_bind_param($stmtVencer, "i", $id_invitacion);
        mysqli_stmt_execute($stmtVencer);

        // A diferencia de los demás errores, acá SÍ queremos conservar
        // el cambio (marcarla como vencida), así que confirmamos en vez
        // de dejar que el catch general haga ROLLBACK.
        mysqli_commit($conexion);

        header("Location: ../paneles.php?seccion=notificaciones&error=invitacion_vencida");
        exit;

    }


    // ==========================================
    // RECHAZAR
    // ==========================================

    if ($accion === 'rechazar') {

        $stmtRechazar = mysqli_prepare(
            $conexion,
            "UPDATE invitacion SET estado = 'Rechazada' WHERE id_invitacion = ? AND estado = 'Pendiente'"
        );
        mysqli_stmt_bind_param($stmtRechazar, "i", $id_invitacion);
        mysqli_stmt_execute($stmtRechazar);

        if (mysqli_stmt_affected_rows($stmtRechazar) !== 1) {
            throw new Exception("invitacion_no_pendiente");
        }

        $stmtTitularDatos = mysqli_prepare(
            $conexion,
            "SELECT nombre, apellido, email FROM usuario WHERE id_usuario = ?"
        );
        mysqli_stmt_bind_param($stmtTitularDatos, "i", $invitacion['id_usuario_titular']);
        mysqli_stmt_execute($stmtTitularDatos);
        $datosTitular = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTitularDatos));

        $stmtMiNombre = mysqli_prepare($conexion, "SELECT nombre, apellido FROM usuario WHERE id_usuario = ?");
        mysqli_stmt_bind_param($stmtMiNombre, "i", $id_usuario_actual);
        mysqli_stmt_execute($stmtMiNombre);
        $miNombre = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMiNombre));

        $mensajeNotificacion =
            $miNombre['nombre'] . ' ' . $miNombre['apellido'] .
            ' rechazó tu invitación para el torneo ' . $invitacion['nombre_torneo'] . '.';

        $stmtNotif = mysqli_prepare(
            $conexion,
            "INSERT INTO notificacion (id_usuario_destino, tipo, mensaje, id_torneo, id_invitacion)
             VALUES (?, 'invitacion_rechazada', ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmtNotif,
            "isii",
            $invitacion['id_usuario_titular'],
            $mensajeNotificacion,
            $invitacion['id_torneo'],
            $id_invitacion
        );
        mysqli_stmt_execute($stmtNotif);

        $stmtMarcarLeida = mysqli_prepare(
            $conexion,
            "UPDATE notificacion SET leida = 1 WHERE id_usuario_destino = ? AND id_invitacion = ?"
        );
        mysqli_stmt_bind_param($stmtMarcarLeida, "ii", $id_usuario_actual, $id_invitacion);
        mysqli_stmt_execute($stmtMarcarLeida);

        mysqli_commit($conexion);

        if ($datosTitular) {
            enviarAvisoRespuestaInvitacion(
                $datosTitular['email'],
                $miNombre['nombre'] . ' ' . $miNombre['apellido'],
                $invitacion['nombre_torneo'],
                false
            );
        }

        header("Location: ../paneles.php?seccion=notificaciones&invitacion=rechazada");
        exit;

    }


    // ==========================================
    // ACEPTAR
    // ==========================================

    if ($invitacion['estado_torneo'] !== 'Abierto') {
        throw new Exception("cerrado");
    }

    $stmtMiCategoria = mysqli_prepare($conexion, "SELECT id_categoria FROM usuario WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmtMiCategoria, "i", $id_usuario_actual);
    mysqli_stmt_execute($stmtMiCategoria);
    $miCategoria = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMiCategoria));

    if ((int) $miCategoria['id_categoria'] !== (int) $invitacion['id_categoria']) {
        throw new Exception("categoria_incompatible");
    }

    $stmtYaInscripto = mysqli_prepare(
        $conexion,
        "SELECT id_inscripcion FROM inscripcion WHERE id_torneo = ? AND id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmtYaInscripto, "ii", $invitacion['id_torneo'], $id_usuario_actual);
    mysqli_stmt_execute($stmtYaInscripto);

    if (mysqli_num_rows(mysqli_stmt_get_result($stmtYaInscripto)) > 0) {
        throw new Exception("ya_inscripto");
    }

    $stmtCupo = mysqli_prepare(
        $conexion,
        "SELECT COUNT(*) AS n FROM inscripcion WHERE id_torneo = ? AND estado = 'Confirmada'"
    );
    mysqli_stmt_bind_param($stmtCupo, "i", $invitacion['id_torneo']);
    mysqli_stmt_execute($stmtCupo);
    $confirmados = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCupo))['n'];

    if ($confirmados + 2 > (int) $invitacion['max_participantes']) {
        throw new Exception("cupo_completo");
    }

    // Crear la inscripción del que acepta, ya confirmada.
    $stmtInsc = mysqli_prepare(
        $conexion,
        "INSERT INTO inscripcion (id_torneo, id_usuario, estado) VALUES (?, ?, 'Confirmada')"
    );
    mysqli_stmt_bind_param($stmtInsc, "ii", $invitacion['id_torneo'], $id_usuario_actual);
    mysqli_stmt_execute($stmtInsc);
    $id_inscripcion_acepta = mysqli_insert_id($conexion);

    // Confirmar la inscripción del titular.
    $stmtConfirmarTitular = mysqli_prepare(
        $conexion,
        "UPDATE inscripcion SET estado = 'Confirmada' WHERE id_inscripcion = ?"
    );
    mysqli_stmt_bind_param($stmtConfirmarTitular, "i", $invitacion['id_inscripcion_invita']);
    mysqli_stmt_execute($stmtConfirmarTitular);

    // Crear la pareja (el trigger valida torneo/usuarios/duplicados).
    $stmtPareja = mysqli_prepare(
        $conexion,
        "INSERT INTO pareja (id_torneo, id_inscripcion_1, id_inscripcion_2) VALUES (?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmtPareja,
        "iii",
        $invitacion['id_torneo'],
        $invitacion['id_inscripcion_invita'],
        $id_inscripcion_acepta
    );

    if (!mysqli_stmt_execute($stmtPareja)) {
        throw new Exception("pareja_incompatible");
    }

    $stmtAceptar = mysqli_prepare(
        $conexion,
        "UPDATE invitacion SET estado = 'Aceptada' WHERE id_invitacion = ? AND estado = 'Pendiente'"
    );
    mysqli_stmt_bind_param($stmtAceptar, "i", $id_invitacion);
    mysqli_stmt_execute($stmtAceptar);

    if (mysqli_stmt_affected_rows($stmtAceptar) !== 1) {
        throw new Exception("invitacion_no_pendiente");
    }

    $stmtTitularDatos = mysqli_prepare(
        $conexion,
        "SELECT nombre, apellido, email FROM usuario WHERE id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmtTitularDatos, "i", $invitacion['id_usuario_titular']);
    mysqli_stmt_execute($stmtTitularDatos);
    $datosTitular = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTitularDatos));

    $stmtMiNombre = mysqli_prepare($conexion, "SELECT nombre, apellido FROM usuario WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmtMiNombre, "i", $id_usuario_actual);
    mysqli_stmt_execute($stmtMiNombre);
    $miNombre = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMiNombre));

    $mensajeNotificacion =
        $miNombre['nombre'] . ' ' . $miNombre['apellido'] .
        ' aceptó tu invitación. Ya están inscriptos como pareja en el torneo ' .
        $invitacion['nombre_torneo'] . '.';

    $stmtNotif = mysqli_prepare(
        $conexion,
        "INSERT INTO notificacion (id_usuario_destino, tipo, mensaje, id_torneo, id_invitacion)
         VALUES (?, 'invitacion_aceptada', ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmtNotif,
        "isii",
        $invitacion['id_usuario_titular'],
        $mensajeNotificacion,
        $invitacion['id_torneo'],
        $id_invitacion
    );
    mysqli_stmt_execute($stmtNotif);

    $stmtMarcarLeida = mysqli_prepare(
        $conexion,
        "UPDATE notificacion SET leida = 1 WHERE id_usuario_destino = ? AND id_invitacion = ?"
    );
    mysqli_stmt_bind_param($stmtMarcarLeida, "ii", $id_usuario_actual, $id_invitacion);
    mysqli_stmt_execute($stmtMarcarLeida);

    mysqli_commit($conexion);

    if ($datosTitular) {
        enviarAvisoRespuestaInvitacion(
            $datosTitular['email'],
            $miNombre['nombre'] . ' ' . $miNombre['apellido'],
            $invitacion['nombre_torneo'],
            true
        );
    }

    header("Location: ../paneles.php?seccion=notificaciones&invitacion=aceptada");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd' || $motivo === 'no_existe') {
        error_log("Error al responder invitacion $id_invitacion: " . $motivo);
    }

    header("Location: ../paneles.php?seccion=notificaciones&error=" . urlencode($motivo));
    exit;

}
