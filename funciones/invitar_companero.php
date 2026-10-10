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
    "SELECT nombre, apellido, email, id_categoria FROM usuario WHERE id_usuario = ?"
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
        "SELECT nombre, tipo, estado, id_categoria FROM torneos WHERE id_torneo = ? FOR UPDATE"
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

    if ((int) $titular['id_categoria'] !== (int) $torneo['id_categoria']) {
        throw new Exception("categoria_incompatible");
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
        "SELECT id_usuario, nombre, apellido, id_categoria FROM usuario WHERE email = ?"
    );
    mysqli_stmt_bind_param($stmtInvitado, "s", $email_invitado);
    mysqli_stmt_execute($stmtInvitado);
    $invitado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtInvitado));

    $id_usuario_invitado = null;

    if ($invitado) {

        if ((int) $invitado['id_categoria'] !== (int) $torneo['id_categoria']) {
            throw new Exception("companero_categoria_incompatible");
        }

        $stmtInvitadoInscripto = mysqli_prepare(
            $conexion,
            "SELECT id_inscripcion FROM inscripcion WHERE id_torneo = ? AND id_usuario = ?"
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

    if (!mysqli_stmt_execute($stmtInvitacion)) {

        if (mysqli_errno($conexion) === 1062) {
            throw new Exception("invitacion_duplicada");
        }

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
        header("Location: ../paneles.php?seccion=torneos&invitacion=exito");
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

    header("Location: ../paneles.php?seccion=torneos&invitacion=exito");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd' || $motivo === 'no_existe') {
        error_log("Error al invitar companero (torneo $id_torneo): " . $motivo);
    }

    header("Location: ../paneles.php?seccion=torneos&error=" . urlencode($motivo));
    exit;

}
