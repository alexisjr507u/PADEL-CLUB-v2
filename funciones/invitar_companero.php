<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("club_categoria.php");
require_once("enviar_invitacion.php");


// ==========================================
// SEGURIDAD
// ==========================================

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 3) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../paneles.php?seccion=torneos&error=token");
    exit;
}

$id_torneo = intval($_POST['id_torneo'] ?? 0);
$email_invitado = trim($_POST['email_companero'] ?? '');
$id_usuario_titular = (int) $_SESSION['id_usuario'];

if ($id_torneo <= 0 || !filter_var($email_invitado, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../paneles.php?seccion=torneos&error=email_invalido");
    exit;
}


// ==========================================
// DATOS DEL TITULAR (para el email/notificación)
// ==========================================

$stmtTitular = mysqli_prepare(
    $conexion,
    "SELECT nombre, apellido, email FROM usuario WHERE id_usuario = ?"
);
mysqli_stmt_bind_param($stmtTitular, "i", $id_usuario_titular);
mysqli_stmt_execute($stmtTitular);
$titular = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTitular));

if (!$titular) {
    header("Location: ../login.php");
    exit;
}

if (strcasecmp($titular['email'], $email_invitado) === 0) {
    header("Location: ../paneles.php?seccion=torneos&error=invitacion_a_si_mismo");
    exit;
}


mysqli_begin_transaction($conexion);

try {

    // ==========================================
    // TORNEO
    // ==========================================

    $stmtTorneo = mysqli_prepare(
        $conexion,
        "SELECT nombre, tipo, estado, id_categoria, id_club, max_participantes FROM torneos WHERE id_torneo = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtTorneo, "i", $id_torneo);
    mysqli_stmt_execute($stmtTorneo);
    $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTorneo));

    if (!$torneo) {
        throw new Exception("no_existe");
    }

    if ($torneo['estado'] !== 'Abierto') {
        throw new Exception("cerrado");
    }

    if (!in_array($torneo['tipo'], ['Parejas', 'Mixto'], true)) {
        throw new Exception("tipo_invalido");
    }

    // La categoría del titular se valida EN EL CLUB DEL TORNEO (pertenencia y categoría).
    $motivoTitular = motivoSinCategoriaEnClub(
        membresiaEnClub($conexion, $id_usuario_titular, (int) $torneo['id_club'], true),
        (int) $torneo['id_categoria']
    );

    if ($motivoTitular !== '') {
        throw new Exception($motivoTitular);
    }


    // ==========================================
    // INSCRIPCIÓN DEL TITULAR (crear o reutilizar)
    // ==========================================

    $stmtInscExiste = mysqli_prepare(
        $conexion,
        "SELECT id_inscripcion, estado FROM inscripcion WHERE id_torneo = ? AND id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmtInscExiste, "ii", $id_torneo, $id_usuario_titular);
    mysqli_stmt_execute($stmtInscExiste);
    $inscripcionExistente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtInscExiste));

    if ($inscripcionExistente && $inscripcionExistente['estado'] === 'Confirmada') {
        throw new Exception("ya_inscripto");
    }

    // Una descalificación no se revierte reinscribiéndose.
    if ($inscripcionExistente && $inscripcionExistente['estado'] === 'Descalificada') {
        throw new Exception("descalificado");
    }

    // CUPO: la pareja necesita 2 lugares. La fila del torneo está bloqueada (FOR UPDATE) desde el principio, así que dos
    // pedidos simultáneos (invitar, inscribirse o aceptar) se atienden de a uno y no pueden pasarse del máximo.
    $stmtCupo = mysqli_prepare(
        $conexion,
        "SELECT COUNT(*) AS n FROM inscripcion WHERE id_torneo = ? AND estado = 'Confirmada'"
    );
    mysqli_stmt_bind_param($stmtCupo, "i", $id_torneo);
    mysqli_stmt_execute($stmtCupo);
    $confirmados = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCupo))['n'];

    if ($confirmados + 2 > (int) $torneo['max_participantes']) {
        throw new Exception("torneo_lleno");
    }

    if ($inscripcionExistente) {

        $id_inscripcion_titular = (int) $inscripcionExistente['id_inscripcion'];

        if ($inscripcionExistente['estado'] !== 'Pendiente') {

            $stmtReactivar = mysqli_prepare(
                $conexion,
                "UPDATE inscripcion SET estado = 'Pendiente' WHERE id_inscripcion = ?"
            );
            mysqli_stmt_bind_param($stmtReactivar, "i", $id_inscripcion_titular);
            mysqli_stmt_execute($stmtReactivar);

        }

    } else {

        $stmtInscCrear = mysqli_prepare(
            $conexion,
            "INSERT INTO inscripcion (id_torneo, id_usuario, estado) VALUES (?, ?, 'Pendiente')"
        );
        mysqli_stmt_bind_param($stmtInscCrear, "ii", $id_torneo, $id_usuario_titular);
        mysqli_stmt_execute($stmtInscCrear);
        $id_inscripcion_titular = mysqli_insert_id($conexion);

    }


    // ==========================================
    // BUSCAR SI EL INVITADO YA TIENE CUENTA
    // ==========================================

    $stmtInvitado = mysqli_prepare(
        $conexion,
        "SELECT id_usuario, nombre, apellido FROM usuario WHERE email = ?"
    );
    mysqli_stmt_bind_param($stmtInvitado, "s", $email_invitado);
    mysqli_stmt_execute($stmtInvitado);
    $invitado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtInvitado));

    $id_usuario_invitado = null;

    if ($invitado) {

        // Si ya tiene cuenta, su categoría también se valida EN EL CLUB DEL TORNEO. (Quien todavía no tiene cuenta
        // se valida cuando acepte la invitación: ahí debe unirse al club para tener su categoría en él.)
        $motivoInvitado = motivoSinCategoriaEnClub(
            membresiaEnClub($conexion, (int) $invitado['id_usuario'], (int) $torneo['id_club']),
            (int) $torneo['id_categoria']
        );

        if ($motivoInvitado !== '') {
            throw new Exception('companero_' . $motivoInvitado);
        }

        $stmtInvitadoInscripto = mysqli_prepare(
            $conexion,
            "SELECT id_inscripcion FROM inscripcion WHERE id_torneo = ? AND id_usuario = ? AND estado <> 'Retirada'"
        );
        mysqli_stmt_bind_param($stmtInvitadoInscripto, "ii", $id_torneo, $invitado['id_usuario']);
        mysqli_stmt_execute($stmtInvitadoInscripto);

        if (mysqli_num_rows(mysqli_stmt_get_result($stmtInvitadoInscripto)) > 0) {
            throw new Exception("companero_ya_inscripto");
        }

        $id_usuario_invitado = (int) $invitado['id_usuario'];

    }


    // ==========================================
    // CREAR LA INVITACIÓN
    // (si ya hay una Pendiente de esta inscripción, el indice
    // unico de invitacion.pendiente_marca va a rechazar el INSERT)
    // ==========================================

    // Como máximo UNA invitación pendiente por titular y torneo. Si ya hay otra:
    //  - a la MISMA persona: no se hace nada (invitacion_duplicada; la original queda intacta);
    //  - ya vencida (pasaron los 7 días): se marca Vencida, que es lo que es;
    //  - a OTRA persona, todavía vigente: se CANCELA (queda en la base con estado Cancelada) y se crea la nueva.
    // Todo dentro de la misma transacción: si la nueva invitación no se puede crear, la anterior sigue Pendiente
    // (el rollback del catch deshace la cancelación). El torneo está bloqueado (FOR UPDATE) desde el principio, así que dos
    // pedidos simultáneos del mismo titular se atienden de a uno.
    $invitacionReemplazada = false;

    $stmtPendiente = mysqli_prepare(
        $conexion,
        "SELECT id_invitacion, id_usuario_invitado, email_invitado, (fecha_vencimiento < NOW()) AS vencida
         FROM invitacion
         WHERE id_inscripcion_invita = ? AND estado = 'Pendiente'
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtPendiente, "i", $id_inscripcion_titular);
    mysqli_stmt_execute($stmtPendiente);
    $invitacionPendiente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPendiente));

    if ($invitacionPendiente) {

        if ((int) $invitacionPendiente['vencida'] === 1) {

            $nuevoEstadoAnterior = 'Vencida';

        } else {

            $mismaPersona =
                strcasecmp($invitacionPendiente['email_invitado'], $email_invitado) === 0
                || ($id_usuario_invitado !== null
                    && $invitacionPendiente['id_usuario_invitado'] !== null
                    && (int) $invitacionPendiente['id_usuario_invitado'] === (int) $id_usuario_invitado);

            if ($mismaPersona) {
                throw new Exception("invitacion_duplicada");
            }

            $nuevoEstadoAnterior = 'Cancelada';
            $invitacionReemplazada = true;

        }

        $stmtCerrarAnterior = mysqli_prepare(
            $conexion,
            "UPDATE invitacion SET estado = ? WHERE id_invitacion = ? AND estado = 'Pendiente'"
        );
        mysqli_stmt_bind_param($stmtCerrarAnterior, "si", $nuevoEstadoAnterior, $invitacionPendiente['id_invitacion']);
        mysqli_stmt_execute($stmtCerrarAnterior);

        if (mysqli_stmt_affected_rows($stmtCerrarAnterior) !== 1) {
            throw new Exception("error_bd");
        }

    }

    $stmtInvitacion = mysqli_prepare(
        $conexion,
        "INSERT INTO invitacion (id_torneo, id_inscripcion_invita, email_invitado, id_usuario_invitado)
         VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmtInvitacion,
        "iisi",
        $id_torneo,
        $id_inscripcion_titular,
        $email_invitado,
        $id_usuario_invitado
    );

    // mysqli lanza excepción ante un error de SQL: el índice único (una pendiente por inscripción) también protege de pedidos
    // simultáneos que pasen la comprobación anterior. El error SQL nunca sale hacia el usuario.
    try {
        mysqli_stmt_execute($stmtInvitacion);
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() === 1062) {
            throw new Exception("invitacion_duplicada");
        }
        error_log("Error SQL al crear invitacion (torneo $id_torneo): " . $e->getMessage());
        throw new Exception("error_bd");
    }

    $id_invitacion = mysqli_insert_id($conexion);


    // ==========================================
    // NOTIFICACIÓN INTERNA (solo si ya tiene cuenta)
    // ==========================================

    if ($id_usuario_invitado) {

        $mensajeNotificacion =
            $titular['nombre'] . ' ' . $titular['apellido'] .
            ' te invita a formar pareja para participar en el torneo ' .
            $torneo['nombre'] . ' de The Bull Padel Club.';

        $stmtNotif = mysqli_prepare(
            $conexion,
            "INSERT INTO notificacion (id_usuario_destino, tipo, mensaje, id_torneo, id_invitacion)
             VALUES (?, 'invitacion_pendiente', ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmtNotif,
            "isii",
            $id_usuario_invitado,
            $mensajeNotificacion,
            $id_torneo,
            $id_invitacion
        );
        mysqli_stmt_execute($stmtNotif);

    }

    mysqli_commit($conexion);

    // El envío del mail va DESPUÉS del commit: si falla, no queremos
    // perder la invitación ya guardada en la base de datos. Pero sí
    // avisamos distinto según si el mail salió o no, en vez de mostrar
    // siempre "éxito" sin importar el resultado real del envío.
    $nombreTitularCompleto = $titular['nombre'] . ' ' . $titular['apellido'];

    if ($id_usuario_invitado) {

        $mailEnviado = enviarInvitacionRegistrado($email_invitado, $nombreTitularCompleto, $torneo['nombre']);

        if (!$mailEnviado) {
            error_log("Invitacion $id_invitacion guardada, pero fallo el mail a usuario registrado ($email_invitado).");
        }

        // El destinatario ya tiene cuenta: aunque el mail falle, la
        // invitación sigue siendo visible y accionable desde sus
        // Notificaciones, así que esto no deja de ser una operación
        // exitosa para el titular.
        header("Location: ../paneles.php?seccion=torneos&invitacion=" . ($invitacionReemplazada ? 'reemplazada' : 'exito'));
        exit;

    }

    $urlRegistro = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') .
        $_SERVER['HTTP_HOST'] .
        dirname(dirname($_SERVER['SCRIPT_NAME'])) .
        '/registro_participante.php'
    );

    $mailEnviado = enviarInvitacionNuevoUsuario($email_invitado, $nombreTitularCompleto, $torneo['nombre'], $urlRegistro);

    if (!$mailEnviado) {

        // Acá sí importa: si el correo no le llegó, esa persona no
        // tiene ninguna otra forma de enterarse de la invitación
        // (todavía no tiene cuenta para verla en Notificaciones).
        error_log("Invitacion $id_invitacion guardada, pero fallo el mail a usuario nuevo ($email_invitado).");

        header("Location: ../paneles.php?seccion=torneos&invitacion=guardada_sin_mail");
        exit;

    }

    header("Location: ../paneles.php?seccion=torneos&invitacion=" . ($invitacionReemplazada ? 'reemplazada' : 'exito'));
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    // Solo salen códigos propios de este flujo. Cualquier error interno (SQL u otro) se registra y se muestra como error_bd,
    // sin mensajes de MySQL ni datos internos en la URL.
    if ($e instanceof mysqli_sql_exception || !preg_match('/^[a-z_]+$/', $motivo)) {
        error_log("Error inesperado al invitar companero (torneo $id_torneo): " . $e->getMessage());
        $motivo = 'error_bd';
    }

    if ($motivo === 'error_bd' || $motivo === 'no_existe') {
        error_log("Error al invitar companero (torneo $id_torneo): " . $motivo);
    }

    header("Location: ../paneles.php?seccion=torneos&error=" . urlencode($motivo));
    exit;

}
