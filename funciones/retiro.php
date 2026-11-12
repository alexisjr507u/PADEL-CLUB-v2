<?php

require_once __DIR__ . '/descalificacion.php';

/* =========================================================
   RETIRO DE PARTICIPANTES (solo antes de que empiece el torneo)

   Con el torneo Abierto (o Cerrado, sin empezar) la organización puede retirar a un participante.
   NO es una descalificación:
   - La inscripción queda «Retirada» (con quién, cuándo y por qué) y deja de ocupar cupo.
   - Puede volver a inscribirse mientras el torneo esté abierto (ver inscribir_torneo.php).
   - No genera ni anula puntos y no toca ningún ranking: si ya hubiera algún partido jugado o puntos de
     ese jugador en el torneo, el retiro se rechaza (en ese caso corresponde descalificar con el torneo En curso).
   - Si ya estaba en un grupo, se lo quita del grupo y se borran SOLO sus partidos sin jugar (el
     fixture del grupo conviene regenerarlo). Nunca se borra un resultado.
   - En torneos de parejas se retira a la pareja completa y se elimina el registro de la pareja
     (como ya se hacía al cancelar una pareja), para no dejar una pareja incompleta.
   ========================================================= */


/**
 * Retira a un participante (torneo Individual: $id es id_inscripcion) o a una pareja (Parejas/Mixto: id_pareja).
 * Todo en UNA transacción. Solo administrador (rol 1) u organizador (rol 2) dueño del club del torneo.
 *
 * @return array ['ok' => true, ...] o ['ok' => false, 'error' => código]
 *   errores: sin_permiso, no_existe, ya_retirado, motivo_largo, estado_no_permite, tiene_partidos_jugados, error_bd
 */
function retirarDeTorneo($conexion, $idTorneo, $id, $idActor, $rolActor, $motivo) {

    $idTorneo = (int) $idTorneo;
    $id = (int) $id;
    $idActor = (int) $idActor;
    $rolActor = (int) $rolActor;

    if (!in_array($rolActor, [1, 2], true) || $idTorneo <= 0 || $id <= 0 || $idActor <= 0) {
        return ['ok' => false, 'error' => 'sin_permiso'];
    }

    $motivo = trim((string) $motivo);

    if (descalificacionLargo($motivo) > DESCALIFICACION_MOTIVO_MAX) {
        return ['ok' => false, 'error' => 'motivo_largo'];
    }

    $motivoGuardar = ($motivo === '') ? null : $motivo;

    mysqli_begin_transaction($conexion);

    try {

        // El estado del torneo se lee de la base (con el torneo bloqueado), nunca del navegador.
        $stmt = mysqli_prepare(
            $conexion,
            "SELECT t.nombre, t.tipo, t.estado, c.id_usuario AS id_organizador
             FROM torneos t INNER JOIN club c ON c.id_club = t.id_club
             WHERE t.id_torneo = ? FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, "i", $idTorneo);
        mysqli_stmt_execute($stmt);
        $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$torneo) {
            throw new Exception('no_existe');
        }

        $stmt = mysqli_prepare($conexion, "SELECT id_rol FROM usuario WHERE id_usuario = ?");
        mysqli_stmt_bind_param($stmt, "i", $idActor);
        mysqli_stmt_execute($stmt);
        $actor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$actor || (int) $actor['id_rol'] !== $rolActor) {
            throw new Exception('sin_permiso');
        }

        if ($rolActor === 2 && (int) $torneo['id_organizador'] !== $idActor) {
            throw new Exception('sin_permiso');
        }

        if (!in_array($torneo['estado'], ['Abierto', 'Cerrado'], true)) {
            throw new Exception('estado_no_permite');
        }

        $esIndividual = ($torneo['tipo'] === 'Individual');

        // Inscripciones afectadas (una persona, o las dos de la pareja).
        if ($esIndividual) {

            $stmt = mysqli_prepare(
                $conexion,
                "SELECT id_inscripcion, id_usuario, estado FROM inscripcion
                 WHERE id_inscripcion = ? AND id_torneo = ? FOR UPDATE"
            );

        } else {

            $stmt = mysqli_prepare(
                $conexion,
                "SELECT i.id_inscripcion, i.id_usuario, i.estado
                 FROM pareja p
                 INNER JOIN inscripcion i ON i.id_inscripcion IN (p.id_inscripcion_1, p.id_inscripcion_2)
                 WHERE p.id_pareja = ? AND p.id_torneo = ? FOR UPDATE"
            );

        }

        mysqli_stmt_bind_param($stmt, "ii", $id, $idTorneo);
        mysqli_stmt_execute($stmt);
        $inscripciones = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        if (empty($inscripciones)) {
            throw new Exception('no_existe');
        }

        foreach ($inscripciones as $i) {
            if ($i['estado'] === 'Retirada') {
                throw new Exception('ya_retirado');
            }
            if ($i['estado'] !== 'Confirmada') {
                throw new Exception('no_existe');
            }
        }

        $idsInscripcion = array_map(fn($i) => (int) $i['id_inscripcion'], $inscripciones);
        $idsUsuario = array_map(fn($i) => (int) $i['id_usuario'], $inscripciones);
        $marcasU = implode(',', array_fill(0, count($idsUsuario), '?'));
        $marcasI = implode(',', array_fill(0, count($idsInscripcion), '?'));

        // Un retiro no toca rankings: si ya hay puntos vigentes de esta gente en este torneo, no corresponde.
        $parametros = array_merge([$idTorneo], $idsUsuario);
        $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM ranking_puntos WHERE id_torneo = ? AND anulado = 0 AND id_usuario IN ($marcasU)");
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($parametros)), ...$parametros);
        mysqli_stmt_execute($stmt);

        if ((int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'] > 0) {
            throw new Exception('tiene_partidos_jugados');
        }

        // Su lugar en grupos y partidos.
        $partidosEliminados = 0;

        if ($esIndividual) {

            $stmt = mysqli_prepare($conexion, "SELECT id_grupo_inscripcion FROM grupo_inscripcion WHERE id_inscripcion IN ($marcasI) FOR UPDATE");
            mysqli_stmt_bind_param($stmt, str_repeat('i', count($idsInscripcion)), ...$idsInscripcion);
            mysqli_stmt_execute($stmt);
            $entrantes = array_map('intval', array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC), 'id_grupo_inscripcion'));
            $tablaPartidos = 'partido_individual';
            $tablaEntrantes = 'grupo_inscripcion';
            $colEntrante = 'id_grupo_inscripcion';

        } else {

            $stmt = mysqli_prepare($conexion, "SELECT gp.id_grupo_pareja FROM grupo_pareja gp WHERE gp.id_pareja = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $entrantes = array_map('intval', array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC), 'id_grupo_pareja'));
            $tablaPartidos = 'partido_pareja';
            $tablaEntrantes = 'grupo_pareja';
            $colEntrante = 'id_grupo_pareja';

        }

        if (!empty($entrantes)) {

            $marcasE = implode(',', array_fill(0, count($entrantes), '?'));
            $tiposE = str_repeat('i', count($entrantes) * 2);
            $parametrosE = array_merge($entrantes, $entrantes);

            $stmt = mysqli_prepare(
                $conexion,
                "SELECT COUNT(*) AS n FROM $tablaPartidos
                 WHERE estado = 'Jugado' AND (id_entrante_1 IN ($marcasE) OR id_entrante_2 IN ($marcasE))"
            );
            mysqli_stmt_bind_param($stmt, $tiposE, ...$parametrosE);
            mysqli_stmt_execute($stmt);

            if ((int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'] > 0) {
                throw new Exception('tiene_partidos_jugados');
            }

            // Solo partidos SIN jugar (no hay resultados que perder).
            $del = mysqli_prepare(
                $conexion,
                "DELETE FROM $tablaPartidos
                 WHERE estado = 'Pendiente' AND (id_entrante_1 IN ($marcasE) OR id_entrante_2 IN ($marcasE))"
            );
            mysqli_stmt_bind_param($del, $tiposE, ...$parametrosE);
            mysqli_stmt_execute($del);
            $partidosEliminados = (int) mysqli_stmt_affected_rows($del);

            if ($esIndividual) {
                $delGrupo = mysqli_prepare($conexion, "DELETE FROM grupo_inscripcion WHERE id_inscripcion IN ($marcasI)");
                mysqli_stmt_bind_param($delGrupo, str_repeat('i', count($idsInscripcion)), ...$idsInscripcion);
                mysqli_stmt_execute($delGrupo);
            }

        }

        // 1) Estado + quién, cuándo y por qué (no usa las columnas de descalificación).
        $upd = mysqli_prepare(
            $conexion,
            "UPDATE inscripcion
             SET estado = 'Retirada', motivo_retiro = ?, id_retirador = ?, fecha_retiro = NOW()
             WHERE id_inscripcion = ? AND estado = 'Confirmada'"
        );

        foreach ($idsInscripcion as $idInsc) {

            mysqli_stmt_bind_param($upd, "sii", $motivoGuardar, $idActor, $idInsc);
            mysqli_stmt_execute($upd);

            if (mysqli_stmt_affected_rows($upd) !== 1) {
                throw new Exception('ya_retirado'); // otra solicitud lo retiró justo antes
            }

        }

        // 2) Parejas: se elimina el registro de la pareja (sus grupos se borran en cascada).
        if (!$esIndividual) {

            $delPareja = mysqli_prepare($conexion, "DELETE FROM pareja WHERE id_pareja = ?");
            mysqli_stmt_bind_param($delPareja, "i", $id);
            mysqli_stmt_execute($delPareja);

            if (mysqli_stmt_affected_rows($delPareja) !== 1) {
                throw new Exception('ya_retirado');
            }

        }

        // 3) Aviso a los retirados.
        $mensaje = 'Fuiste retirado del torneo ' . $torneo['nombre'] . ' por la organización. Podés volver a inscribirte mientras esté abierto.';

        if ($motivoGuardar !== null) {
            $mensaje = 'Fuiste retirado del torneo ' . $torneo['nombre'] . ' por la organización. Motivo: ' . $motivoGuardar;
        }

        if (descalificacionLargo($mensaje) > 255) {
            $mensaje = (function_exists('mb_substr') ? mb_substr($mensaje, 0, 252, 'UTF-8') : substr($mensaje, 0, 252)) . '...';
        }

        $notif = mysqli_prepare(
            $conexion,
            "INSERT INTO notificacion (id_usuario_destino, tipo, mensaje, id_torneo) VALUES (?, 'retiro', ?, ?)"
        );

        foreach ($idsUsuario as $idU) {
            mysqli_stmt_bind_param($notif, "isi", $idU, $mensaje, $idTorneo);
            mysqli_stmt_execute($notif);
        }

        actividadBajaDeTorneo($conexion, 'retiro', $idTorneo, $idsUsuario, $motivoGuardar, $idActor);

        mysqli_commit($conexion);

        return [
            'ok' => true,
            'usuarios' => $idsUsuario,
            'inscripciones' => $idsInscripcion,
            'partidos_eliminados' => $partidosEliminados,
        ];

    } catch (Throwable $e) {

        mysqli_rollback($conexion);

        $codigo = $e->getMessage();

        if (!in_array($codigo, ['sin_permiso', 'no_existe', 'ya_retirado', 'motivo_largo', 'estado_no_permite', 'tiene_partidos_jugados'], true)) {
            error_log("Error al retirar (torneo $idTorneo, id $id): " . $codigo);
            $codigo = 'error_bd';
        }

        return ['ok' => false, 'error' => $codigo];

    }

}
