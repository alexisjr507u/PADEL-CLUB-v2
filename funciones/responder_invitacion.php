<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("club_categoria.php");
require_once("enviar_invitacion.php");
require_once("error_flujo.php");


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


// Códigos propios que este flujo puede mostrar (lista blanca). Cualquier otro error se registra y sale como error_bd.
$codigosPermitidosResponder = [
    'no_existe', 'no_autorizado', 'invitacion_no_pendiente', 'invitacion_cancelada', 'cerrado',
    'categoria_incompatible', 'no_pertenece_club', 'sin_categoria', 'ya_inscripto', 'cupo_completo',
    'pareja_incompatible', 'error_bd',
];
$contextoResponder = "responder_invitacion invitacion=$id_invitacion usuario=$id_usuario_actual";

try {

    // El torneo de la invitación se averigua FUERA de la transacción (lectura simple, sin bloqueo): si fuera la primera lectura de la
    // transacción fijaría la instantánea antes de esperar el bloqueo y el recuento de cupo vería datos viejos.
    $stmtTorneoDeInv = mysqli_prepare($conexion, "SELECT id_torneo FROM invitacion WHERE id_invitacion = ?");
    mysqli_stmt_bind_param($stmtTorneoDeInv, "i", $id_invitacion);
    mysqli_stmt_execute($stmtTorneoDeInv);
    $filaTorneoDeInv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTorneoDeInv));
    mysqli_stmt_close($stmtTorneoDeInv);

    mysqli_begin_transaction($conexion);

} catch (Throwable $e) {

    // La lectura previa también está cubierta: un error de SQL acá no puede quedar sin manejar ni mostrar nada interno.
    rollbackSeguro($conexion, $contextoResponder . ' (lectura previa)');

    $motivo = codigoDeErrorSeguro($e, $codigosPermitidosResponder, $contextoResponder . ' (lectura previa)');

    header("Location: ../paneles.php?seccion=notificaciones&error=" . urlencode($motivo));
    exit;

}

try {

    // El torneo se bloquea PRIMERO (mismo orden que invitar_companero.php e inscribir_torneo.php): así aceptar/rechazar una
    // invitación y que el titular la reemplace al mismo tiempo se atienden de a uno, sin interbloqueos.
    if ($filaTorneoDeInv) {
        $stmtBloqueoTorneo = mysqli_prepare($conexion, "SELECT id_torneo FROM torneos WHERE id_torneo = ? FOR UPDATE");
        $idTorneoDeInv = (int) $filaTorneoDeInv['id_torneo'];
        mysqli_stmt_bind_param($stmtBloqueoTorneo, "i", $idTorneoDeInv);
        mysqli_stmt_execute($stmtBloqueoTorneo);
        mysqli_stmt_get_result($stmtBloqueoTorneo);
        mysqli_stmt_close($stmtBloqueoTorneo);
    }

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
                t.id_categoria, t.id_club, t.max_participantes,
                (i.fecha_vencimiento < NOW()) AS vencida
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

    // Cancelada por quien invitó (eligió otro compañero): no se puede aceptar ni rechazar como si siguiera vigente.
    if ($invitacion['estado'] === 'Cancelada') {
        throw new Exception("invitacion_cancelada");
    }

    if ($invitacion['estado'] !== 'Pendiente') {
        throw new Exception("invitacion_no_pendiente");
    }

    // El vencimiento se compara en MySQL (NOW()): fecha_vencimiento se guardó con la hora de Argentina y PHP trabaja en UTC.
    if ((int) $invitacion['vencida'] === 1) {

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

        // El rechazo ya está confirmado: un fallo al avisar por mail no puede convertirse en un mensaje de error.
        if ($datosTitular) {
            try {
                enviarAvisoRespuestaInvitacion(
                    $datosTitular['email'],
                    $miNombre['nombre'] . ' ' . $miNombre['apellido'],
                    $invitacion['nombre_torneo'],
                    false
                );
            } catch (Throwable $eMail) {
                error_log("[$contextoResponder] Falló el aviso por mail del rechazo: " . $eMail->getMessage());
            }
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

    // Quien acepta debe pertenecer al club del torneo y tener allí la categoría del torneo (confirmada).
    $motivoMiCategoria = motivoSinCategoriaEnClub(
        membresiaEnClub($conexion, $id_usuario_actual, (int) $invitacion['id_club'], true),
        (int) $invitacion['id_categoria']
    );

    if ($motivoMiCategoria !== '') {
        throw new Exception($motivoMiCategoria);
    }

    $stmtYaInscripto = mysqli_prepare(
        $conexion,
        "SELECT id_inscripcion FROM inscripcion WHERE id_torneo = ? AND id_usuario = ? AND estado <> 'Retirada'"
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

    // Si el que acepta había sido retirado antes, se reutiliza su inscripción (hay una sola por torneo y usuario).
    $stmtRetirada = mysqli_prepare(
        $conexion,
        "SELECT id_inscripcion FROM inscripcion WHERE id_torneo = ? AND id_usuario = ? AND estado = 'Retirada' FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtRetirada, "ii", $invitacion['id_torneo'], $id_usuario_actual);
    mysqli_stmt_execute($stmtRetirada);
    $filaRetirada = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtRetirada));

    if ($filaRetirada) {

        $id_inscripcion_acepta = (int) $filaRetirada['id_inscripcion'];
        $stmtReconfirmar = mysqli_prepare($conexion, "UPDATE inscripcion SET estado = 'Confirmada', fecha_inscripcion = NOW() WHERE id_inscripcion = ?");
        mysqli_stmt_bind_param($stmtReconfirmar, "i", $id_inscripcion_acepta);
        mysqli_stmt_execute($stmtReconfirmar);

    } else {

        // Crear la inscripción del que acepta, ya confirmada.
        $stmtInsc = mysqli_prepare(
            $conexion,
            "INSERT INTO inscripcion (id_torneo, id_usuario, estado) VALUES (?, ?, 'Confirmada')"
        );
        mysqli_stmt_bind_param($stmtInsc, "ii", $invitacion['id_torneo'], $id_usuario_actual);
        mysqli_stmt_execute($stmtInsc);
        $id_inscripcion_acepta = mysqli_insert_id($conexion);

    }

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

    // El trigger de pareja corta con SQLSTATE 45000 cuando las inscripciones no son válidas para formar una pareja: eso es
    // pareja_incompatible. Cualquier otro error de SQL sigue hacia el manejo general (error_bd / conflicto_temporal).
    $parejaCreada = false;

    try {
        $parejaCreada = mysqli_stmt_execute($stmtPareja);
    } catch (mysqli_sql_exception $eTrigger) {
        if ($eTrigger->getSqlState() !== '45000') {
            throw $eTrigger;
        }
        error_log("[$contextoResponder] Pareja rechazada por la base de datos (SQLSTATE 45000): " . $eTrigger->getMessage());
    }

    if (!$parejaCreada) {
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

    actividadParejaFormada($conexion, $invitacion['id_torneo'], $id_usuario_actual, $invitacion['id_usuario_titular']);

    mysqli_commit($conexion);

    // La aceptación ya está confirmada (commit): un fallo al avisar por mail no puede mostrarse como si la operación hubiera fallado.
    if ($datosTitular) {
        try {
            enviarAvisoRespuestaInvitacion(
                $datosTitular['email'],
                $miNombre['nombre'] . ' ' . $miNombre['apellido'],
                $invitacion['nombre_torneo'],
                true
            );
        } catch (Throwable $eMail) {
            error_log("[$contextoResponder] Falló el aviso por mail de la aceptación: " . $eMail->getMessage());
        }
    }

    header("Location: ../paneles.php?seccion=notificaciones&invitacion=aceptada");
    exit;

} catch (Throwable $e) {

    rollbackSeguro($conexion, $contextoResponder);

    // Solo viajan códigos propios de la lista blanca; los errores de MySQL o inesperados se registran completos (error_log)
    // y salen como error_bd (o conflicto_temporal ante interbloqueo / tiempo de espera). Nunca el mensaje original.
    $motivo = codigoDeErrorSeguro($e, $codigosPermitidosResponder, $contextoResponder);

    header("Location: ../paneles.php?seccion=notificaciones&error=" . urlencode($motivo));
    exit;

}
