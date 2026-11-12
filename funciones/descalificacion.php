<?php

/* =========================================================
   DESCALIFICACIONES DE PARTICIPANTES

   Las bajas de un torneo las decide la organización (o el administrador): el
   participante NO puede darse de baja. Descalificar a alguien:

   - Marca su inscripción como «Descalificada» y guarda quién, cuándo y por qué.
   - Le quita TODOS los puntos de ESE torneo en el ranking por categoría y en el
     general, sin importar cuánto haya avanzado (incluso si fue campeón). Los puntos
     de otros torneos no se tocan.
   - Conserva todo el historial: los partidos jugados y sus resultados NO se borran
     ni se modifican, y los rivales conservan lo que ya ganaron o perdieron contra él.
   - Impide que siga jugando: no clasifica ni se le generan partidos nuevos, y sus
     partidos todavía pendientes se dan por ganados (W.O.) a su rival para que el
     torneo pueda seguir.

   Los puntos ya sumados no se borran: la fila de ranking_puntos queda marcada
   (anulado = 1) y todos los rankings ignoran las filas anuladas. Los puntos que se
   calculen después (sincronizarPuntosTorneo) no le generan ni le actualizan fila.

   Un torneo de parejas descalifica a la pareja completa (los dos integrantes).
   ========================================================= */

require_once __DIR__ . '/ranking_puntos.php';

const DESCALIFICACION_MOTIVO_MAX = 255;


/** Cantidad de caracteres de un texto (con mbstring si está disponible). */
function descalificacionLargo($texto) {

    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);

}


/**
 * Los partidos pendientes entre un descalificado y un rival que sigue en el torneo se dan
 * por ganados (W.O.) al rival. No toca partidos ya jugados ni partidos entre dos descalificados.
 * Se usa dentro de una transacción (quien llama hace commit/rollback). Devuelve cuántos resolvió.
 */
function resolverPartidosConDescalificados($conexion, $idTorneo) {

    $resueltos = 0;

    $consultas = [
        'partido_individual' => "SELECT p.id_partido, p.id_entrante_1, p.id_entrante_2,
                                        (i1.estado = 'Descalificada') AS d1, (i2.estado = 'Descalificada') AS d2
                                 FROM partido_individual p
                                 INNER JOIN grupo_inscripcion e1 ON e1.id_grupo_inscripcion = p.id_entrante_1
                                 INNER JOIN grupo_inscripcion e2 ON e2.id_grupo_inscripcion = p.id_entrante_2
                                 INNER JOIN inscripcion i1 ON i1.id_inscripcion = e1.id_inscripcion
                                 INNER JOIN inscripcion i2 ON i2.id_inscripcion = e2.id_inscripcion
                                 INNER JOIN grupo g ON g.id_grupo = e1.id_grupo
                                 WHERE g.id_torneo = ? AND p.estado = 'Pendiente'
                                 FOR UPDATE",
        'partido_pareja' => "SELECT p.id_partido, p.id_entrante_1, p.id_entrante_2,
                                    (a1.estado = 'Descalificada' OR b1.estado = 'Descalificada') AS d1,
                                    (a2.estado = 'Descalificada' OR b2.estado = 'Descalificada') AS d2
                             FROM partido_pareja p
                             INNER JOIN grupo_pareja e1 ON e1.id_grupo_pareja = p.id_entrante_1
                             INNER JOIN grupo_pareja e2 ON e2.id_grupo_pareja = p.id_entrante_2
                             INNER JOIN pareja pa1 ON pa1.id_pareja = e1.id_pareja
                             INNER JOIN pareja pa2 ON pa2.id_pareja = e2.id_pareja
                             INNER JOIN inscripcion a1 ON a1.id_inscripcion = pa1.id_inscripcion_1
                             INNER JOIN inscripcion b1 ON b1.id_inscripcion = pa1.id_inscripcion_2
                             INNER JOIN inscripcion a2 ON a2.id_inscripcion = pa2.id_inscripcion_1
                             INNER JOIN inscripcion b2 ON b2.id_inscripcion = pa2.id_inscripcion_2
                             INNER JOIN grupo g ON g.id_grupo = e1.id_grupo
                             WHERE g.id_torneo = ? AND p.estado = 'Pendiente'
                             FOR UPDATE",
    ];

    foreach ($consultas as $tabla => $sql) {

        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $idTorneo);
        mysqli_stmt_execute($stmt);
        $pendientes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        foreach ($pendientes as $p) {

            $d1 = (int) $p['d1'] === 1;
            $d2 = (int) $p['d2'] === 1;

            if ($d1 === $d2) {
                continue; // ninguno descalificado, o los dos: lo resuelve el organizador a mano
            }

            $idGanador = $d1 ? (int) $p['id_entrante_2'] : (int) $p['id_entrante_1'];
            $idPartido = (int) $p['id_partido'];

            $upd = mysqli_prepare(
                $conexion,
                "UPDATE $tabla
                 SET set1_games_1 = NULL, set1_games_2 = NULL, set2_games_1 = NULL, set2_games_2 = NULL,
                     set3_games_1 = NULL, set3_games_2 = NULL,
                     id_ganador = ?, resultado_tipo = 'WO', estado = 'Jugado', fecha_resultado = NOW()
                 WHERE id_partido = ? AND estado = 'Pendiente'"
            );
            mysqli_stmt_bind_param($upd, "ii", $idGanador, $idPartido);
            mysqli_stmt_execute($upd);

            $resueltos += mysqli_stmt_affected_rows($upd);

        }

    }

    return $resueltos;

}


/**
 * Descalifica a un participante (torneo Individual: $id es id_inscripcion) o a una pareja
 * (torneos de parejas: $id es id_pareja). Todo en UNA transacción.
 *
 * Solo puede hacerlo el administrador (rol 1) o el organizador (rol 2) dueño del club del torneo;
 * se valida acá, del lado del servidor, además de lo que haga quien llama.
 *
 * @return array ['ok' => true, ...detalle] o ['ok' => false, 'error' => código]
 *   errores: sin_permiso, no_existe, ya_descalificado, motivo_largo, estado_no_permite, error_bd
 */
function descalificarEnTorneo($conexion, $idTorneo, $id, $idActor, $rolActor, $motivo) {

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

        // Bloquea el torneo y comprueba que el actor sea quien corresponde.
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

        // El rol se confirma contra la base: no se confía solo en el dato de sesión.
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

        // Solo se descalifica con el torneo En curso (el estado sale de la base, no del navegador).
        // Antes de empezar corresponde retirar (funciones/retiro.php) y terminado ya no se modifica nada.
        if ($torneo['estado'] !== 'En curso') {
            throw new Exception('estado_no_permite');
        }

        // Inscripciones afectadas (una persona, o las dos de la pareja).
        if ($torneo['tipo'] === 'Individual') {

            $stmt = mysqli_prepare(
                $conexion,
                "SELECT id_inscripcion, id_usuario, estado FROM inscripcion
                 WHERE id_inscripcion = ? AND id_torneo = ? FOR UPDATE"
            );
            mysqli_stmt_bind_param($stmt, "ii", $id, $idTorneo);

        } else {

            $stmt = mysqli_prepare(
                $conexion,
                "SELECT i.id_inscripcion, i.id_usuario, i.estado
                 FROM pareja p
                 INNER JOIN inscripcion i ON i.id_inscripcion IN (p.id_inscripcion_1, p.id_inscripcion_2)
                 WHERE p.id_pareja = ? AND p.id_torneo = ? FOR UPDATE"
            );
            mysqli_stmt_bind_param($stmt, "ii", $id, $idTorneo);

        }

        mysqli_stmt_execute($stmt);
        $inscripciones = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

        if (empty($inscripciones)) {
            throw new Exception('no_existe');
        }

        foreach ($inscripciones as $i) {
            if ($i['estado'] === 'Descalificada') {
                throw new Exception('ya_descalificado');
            }
            if ($i['estado'] !== 'Confirmada') {
                throw new Exception('no_existe');
            }
        }

        $idsInscripcion = array_map(fn($i) => (int) $i['id_inscripcion'], $inscripciones);
        $idsUsuario = array_map(fn($i) => (int) $i['id_usuario'], $inscripciones);

        // 1) Estado + quién, cuándo y por qué.
        $upd = mysqli_prepare(
            $conexion,
            "UPDATE inscripcion
             SET estado = 'Descalificada', motivo_descalificacion = ?, id_descalificador = ?, fecha_descalificacion = NOW()
             WHERE id_inscripcion = ? AND estado = 'Confirmada'"
        );

        foreach ($idsInscripcion as $idInsc) {

            mysqli_stmt_bind_param($upd, "sii", $motivoGuardar, $idActor, $idInsc);
            mysqli_stmt_execute($upd);

            if (mysqli_stmt_affected_rows($upd) !== 1) {
                throw new Exception('ya_descalificado'); // otra solicitud lo descalificó justo antes
            }

        }

        // 2) Puntos de ESTE torneo ya otorgados: se anulan (la fila queda como constancia).
        $marcas = implode(',', array_fill(0, count($idsUsuario), '?'));
        $tipos = 'i' . str_repeat('i', count($idsUsuario));
        $parametros = array_merge([$idTorneo], $idsUsuario);

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT COALESCE(SUM(puntos_grupos + puntos_cruces), 0) AS puntos, COUNT(*) AS filas
             FROM ranking_puntos WHERE id_torneo = ? AND id_usuario IN ($marcas) AND anulado = 0 FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, $tipos, ...$parametros);
        mysqli_stmt_execute($stmt);
        $previos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        $upd = mysqli_prepare(
            $conexion,
            "UPDATE ranking_puntos SET anulado = 1, fecha_anulacion = NOW()
             WHERE id_torneo = ? AND id_usuario IN ($marcas) AND anulado = 0"
        );
        mysqli_stmt_bind_param($upd, $tipos, ...$parametros);
        mysqli_stmt_execute($upd);

        // ¿Esos puntos habían provocado un ascenso? No se revierte solo: se avisa.
        $stmt = mysqli_prepare(
            $conexion,
            "SELECT COUNT(*) AS n FROM categoria_historial
             WHERE id_torneo = ? AND tipo = 'Ascenso por puntos' AND id_usuario IN ($marcas)"
        );
        mysqli_stmt_bind_param($stmt, $tipos, ...$parametros);
        mysqli_stmt_execute($stmt);
        $ascensos = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];

        // 3) Partidos pendientes contra él: ganan los rivales por W.O. (lo jugado no se toca).
        $partidosWo = resolverPartidosConDescalificados($conexion, $idTorneo);

        // Los W.O. a favor de los rivales suman puntos en el acto (el descalificado ya quedó anulado más arriba).
        sincronizarPuntosTorneo($conexion, $idTorneo, $idActor);

        // 4) Aviso a los descalificados.
        $mensaje = 'Fuiste descalificado del torneo ' . $torneo['nombre'] . ' por la organización.';

        if ($motivoGuardar !== null) {
            $mensaje .= ' Motivo: ' . $motivoGuardar;
        }

        if (descalificacionLargo($mensaje) > 255) {
            $mensaje = (function_exists('mb_substr') ? mb_substr($mensaje, 0, 252, 'UTF-8') : substr($mensaje, 0, 252)) . '...';
        }

        $notif = mysqli_prepare(
            $conexion,
            "INSERT INTO notificacion (id_usuario_destino, tipo, mensaje, id_torneo) VALUES (?, 'descalificacion', ?, ?)"
        );

        foreach ($idsUsuario as $idU) {
            mysqli_stmt_bind_param($notif, "isi", $idU, $mensaje, $idTorneo);
            mysqli_stmt_execute($notif);
        }

        actividadBajaDeTorneo($conexion, 'descalificacion', $idTorneo, $idsUsuario, $motivoGuardar, $idActor);

        mysqli_commit($conexion);

        return [
            'ok' => true,
            'usuarios' => $idsUsuario,
            'inscripciones' => $idsInscripcion,
            'puntos_anulados' => (int) $previos['puntos'],
            'filas_anuladas' => (int) $previos['filas'],
            'ascensos_no_revertidos' => $ascensos,
            'partidos_wo' => $partidosWo,
        ];

    } catch (Throwable $e) {

        mysqli_rollback($conexion);

        $codigo = $e->getMessage();

        if (!in_array($codigo, ['sin_permiso', 'no_existe', 'ya_descalificado', 'motivo_largo', 'estado_no_permite'], true)) {
            error_log("Error al descalificar (torneo $idTorneo, id $id): " . $codigo);
            $codigo = 'error_bd';
        }

        return ['ok' => false, 'error' => $codigo];

    }

}


/**
 * Partidos que disputó una inscripción (individual) o pareja, con rival y resultado, para que la
 * organización pueda consultar el historial de un descalificado. No modifica nada.
 *
 * @return array lista de ['fase', 'rival', 'resultado' (Ganó/Perdió/Pendiente), 'marcador', 'wo', 'por_descalificacion']
 */
function partidosDeDescalificado($conexion, $idTorneo, $idInscripcionOPareja, $esIndividual, $fechaDescalificacion) {

    if ($esIndividual) {

        $sql = "SELECT p.fase, p.orden, p.estado, p.id_ganador, p.resultado_tipo, p.fecha_resultado,
                       p.set1_games_1, p.set1_games_2, p.set2_games_1, p.set2_games_2, p.set3_games_1, p.set3_games_2,
                       p.id_entrante_1, p.id_entrante_2, e1.id_inscripcion AS ins1, e2.id_inscripcion AS ins2,
                       CONCAT(u1.nombre, ' ', u1.apellido) AS nombre1, CONCAT(u2.nombre, ' ', u2.apellido) AS nombre2
                FROM partido_individual p
                INNER JOIN grupo_inscripcion e1 ON e1.id_grupo_inscripcion = p.id_entrante_1
                INNER JOIN grupo_inscripcion e2 ON e2.id_grupo_inscripcion = p.id_entrante_2
                INNER JOIN inscripcion i1 ON i1.id_inscripcion = e1.id_inscripcion
                INNER JOIN inscripcion i2 ON i2.id_inscripcion = e2.id_inscripcion
                INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
                INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
                WHERE i1.id_torneo = ? AND (e1.id_inscripcion = ? OR e2.id_inscripcion = ?)
                ORDER BY FIELD(p.fase, 'Grupos', 'Octavos', 'Cuartos', 'Semifinal', 'Final'), p.orden, p.id_partido";

    } else {

        $sql = "SELECT p.fase, p.orden, p.estado, p.id_ganador, p.resultado_tipo, p.fecha_resultado,
                       p.set1_games_1, p.set1_games_2, p.set2_games_1, p.set2_games_2, p.set3_games_1, p.set3_games_2,
                       p.id_entrante_1, p.id_entrante_2, e1.id_pareja AS ins1, e2.id_pareja AS ins2,
                       CONCAT(a1.nombre, ' ', a1.apellido, ' / ', b1.nombre, ' ', b1.apellido) AS nombre1,
                       CONCAT(a2.nombre, ' ', a2.apellido, ' / ', b2.nombre, ' ', b2.apellido) AS nombre2
                FROM partido_pareja p
                INNER JOIN grupo_pareja e1 ON e1.id_grupo_pareja = p.id_entrante_1
                INNER JOIN grupo_pareja e2 ON e2.id_grupo_pareja = p.id_entrante_2
                INNER JOIN pareja pa1 ON pa1.id_pareja = e1.id_pareja
                INNER JOIN pareja pa2 ON pa2.id_pareja = e2.id_pareja
                INNER JOIN inscripcion ia1 ON ia1.id_inscripcion = pa1.id_inscripcion_1
                INNER JOIN inscripcion ib1 ON ib1.id_inscripcion = pa1.id_inscripcion_2
                INNER JOIN inscripcion ia2 ON ia2.id_inscripcion = pa2.id_inscripcion_1
                INNER JOIN inscripcion ib2 ON ib2.id_inscripcion = pa2.id_inscripcion_2
                INNER JOIN usuario a1 ON a1.id_usuario = ia1.id_usuario
                INNER JOIN usuario b1 ON b1.id_usuario = ib1.id_usuario
                INNER JOIN usuario a2 ON a2.id_usuario = ia2.id_usuario
                INNER JOIN usuario b2 ON b2.id_usuario = ib2.id_usuario
                WHERE pa1.id_torneo = ? AND (e1.id_pareja = ? OR e2.id_pareja = ?)
                ORDER BY FIELD(p.fase, 'Grupos', 'Octavos', 'Cuartos', 'Semifinal', 'Final'), p.orden, p.id_partido";

    }

    $stmt = mysqli_prepare($conexion, $sql);
    $idTorneo = (int) $idTorneo;
    $idInscripcionOPareja = (int) $idInscripcionOPareja;
    mysqli_stmt_bind_param($stmt, "iii", $idTorneo, $idInscripcionOPareja, $idInscripcionOPareja);
    mysqli_stmt_execute($stmt);

    $lista = [];

    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $p) {

        $esLado1 = ((int) $p['ins1'] === $idInscripcionOPareja);
        $idPropio = $esLado1 ? (int) $p['id_entrante_1'] : (int) $p['id_entrante_2'];
        $esWo = ($p['resultado_tipo'] === 'WO');

        if ($p['estado'] !== 'Jugado') {
            $resultado = 'Pendiente';
        } else {
            $resultado = ((int) $p['id_ganador'] === $idPropio) ? 'Ganó' : 'Perdió';
        }

        $marcador = '';

        if ($p['estado'] === 'Jugado' && !$esWo) {

            foreach ([1, 2, 3] as $n) {

                $a = $p["set{$n}_games_1"];
                $b = $p["set{$n}_games_2"];

                if ($a === null || $b === null) {
                    continue;
                }

                // Marcador desde el punto de vista del descalificado.
                $marcador .= ($marcador === '' ? '' : ' ') . ($esLado1 ? "$a-$b" : "$b-$a");

            }

        }

        $lista[] = [
            'fase' => $p['fase'],
            'rival' => $esLado1 ? $p['nombre2'] : $p['nombre1'],
            'resultado' => $resultado,
            'marcador' => $marcador,
            'wo' => $esWo,
            // W.O. cargado automáticamente por la descalificación (no es un partido que jugó).
            'por_descalificacion' => $esWo && $fechaDescalificacion !== null && $p['fecha_resultado'] !== null
                && strtotime($p['fecha_resultado']) >= strtotime($fechaDescalificacion),
        ];

    }

    return $lista;

}
