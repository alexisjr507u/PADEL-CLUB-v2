<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("club_categoria.php");
require_once("error_flujo.php");


// ==========================================
// SEGURIDAD: sesión + rol participante
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
$id_usuario = (int) $_SESSION['id_usuario'];

if ($id_torneo <= 0) {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}


// ==========================================
// TRANSACCIÓN: bloquear el torneo para evitar
// que dos inscripciones simultáneas superen el cupo
// ==========================================

try {

    mysqli_begin_transaction($conexion);

    $stmtTorneo = mysqli_prepare(
        $conexion,
        "SELECT tipo, estado, max_participantes, id_categoria, id_club
         FROM torneos
         WHERE id_torneo = ?
         FOR UPDATE"
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

    if ($torneo['tipo'] !== 'Individual') {
        // Parejas/Mixto se implementa en la próxima etapa.
        throw new Exception("no_individual");
    }

    // La fila se bloquea (después del torneo) para que dos pedidos simultáneos no se pisen.
    $stmtExiste = mysqli_prepare(
        $conexion,
        "SELECT id_inscripcion, estado FROM inscripcion WHERE id_torneo = ? AND id_usuario = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtExiste, "ii", $id_torneo, $id_usuario);
    mysqli_stmt_execute($stmtExiste);
    $inscripcionPrevia = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtExiste));

    // Quien fue descalificado de este torneo no puede volver a inscribirse.
    if ($inscripcionPrevia && $inscripcionPrevia['estado'] === 'Descalificada') {
        throw new Exception("descalificado");
    }

    // Una fila vieja en Rechazada (o Pendiente, de cuando el organizador aprobaba solicitudes)
    // se vuelve a confirmar. Confirmada o Cancelada cuentan como inscripción existente.
    // Un participante retirado por la organización (torneo todavía abierto) puede volver a inscribirse.
    if ($inscripcionPrevia && !in_array($inscripcionPrevia['estado'], ['Rechazada', 'Pendiente', 'Retirada'], true)) {
        throw new Exception("ya_inscripto");
    }

    // ==========================================
    // CATEGORÍA: se lee de la base (nunca del navegador).
    //  - Se usa la categoría del jugador en el club del torneo; Confirmada y Legado
    //    valen igual (Legado: usuarios anteriores al sistema por clubes).
    // ==========================================

    $stmtUsuario = mysqli_prepare(
        $conexion,
        "SELECT id_usuario FROM usuario WHERE id_usuario = ? AND id_rol = 3 FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtUsuario, "i", $id_usuario);
    mysqli_stmt_execute($stmtUsuario);
    $usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUsuario));

    if (!$usuario) {
        throw new Exception("no_autorizado");
    }

    // La categoría que cuenta es la del jugador EN EL CLUB DEL TORNEO (usuario_club), nunca una categoría global:
    // si no pertenece al club, no tiene categoría allí o no coincide con la del torneo, no se inscribe.
    $membresiaClub = membresiaEnClub($conexion, $id_usuario, (int) $torneo['id_club'], true);
    $motivoCategoria = motivoSinCategoriaEnClub($membresiaClub, (int) $torneo['id_categoria']);

    if ($motivoCategoria !== '') {
        throw new Exception($motivoCategoria);
    }

    $stmtCupo = mysqli_prepare(
        $conexion,
        "SELECT COUNT(*) AS n FROM inscripcion WHERE id_torneo = ? AND estado = 'Confirmada'"
    );
    mysqli_stmt_bind_param($stmtCupo, "i", $id_torneo);
    mysqli_stmt_execute($stmtCupo);
    $confirmados = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCupo))['n'];

    if ($confirmados >= (int) $torneo['max_participantes']) {
        throw new Exception("cupo_completo");
    }

    // La inscripción individual queda CONFIRMADA en el acto: no la aprueba el organizador.
    // El cupo recién se contó con el torneo bloqueado, así que dos inscripciones
    // simultáneas no pueden pasarse del máximo.
    if ($inscripcionPrevia) {

        $stmtInsert = mysqli_prepare(
            $conexion,
            "UPDATE inscripcion SET estado = 'Confirmada', fecha_inscripcion = NOW()
             WHERE id_inscripcion = ? AND estado IN ('Rechazada', 'Pendiente', 'Retirada')"
        );
        mysqli_stmt_bind_param($stmtInsert, "i", $inscripcionPrevia['id_inscripcion']);

    } else {

        $stmtInsert = mysqli_prepare(
            $conexion,
            "INSERT INTO inscripcion (id_torneo, id_usuario, estado) VALUES (?, ?, 'Confirmada')"
        );
        mysqli_stmt_bind_param($stmtInsert, "ii", $id_torneo, $id_usuario);

    }

    if (!mysqli_stmt_execute($stmtInsert)) {
        throw new Exception("error_bd");
    }

    actividadInscripcion($conexion, $id_torneo, $id_usuario);

    mysqli_commit($conexion);

    header("Location: ../paneles.php?seccion=torneos&inscripcion=exito");
    exit;

} catch (Throwable $e) {

    $contextoInscripcion = "inscribir_torneo torneo=$id_torneo usuario=$id_usuario";

    rollbackSeguro($conexion, $contextoInscripcion);

    // Solo viajan códigos propios de esta lista; cualquier error de MySQL o inesperado se registra completo (error_log)
    // y sale como error_bd (o conflicto_temporal si fue un interbloqueo / tiempo de espera). Nunca el mensaje original.
    $motivo = codigoDeErrorSeguro($e, [
        'no_existe', 'cerrado', 'no_individual', 'descalificado', 'ya_inscripto', 'no_autorizado',
        'categoria_incompatible', 'sin_categoria', 'no_pertenece_club', 'cupo_completo', 'error_bd',
    ], $contextoInscripcion);

    header("Location: ../paneles.php?seccion=torneos&error=" . urlencode($motivo));
    exit;

}
