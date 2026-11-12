<?php

require_once __DIR__ . '/ranking_puntos.php';

/* =========================================================
   TEMPORADAS DEL RANKING POR CLUB: "Finalizar temporada"

   Cada club tiene SU temporada. Al finalizar la temporada de un club:
   - El ÚLTIMO del ranking de cada categoría DE ESE CLUB desciende una categoría
     (la más baja, Principiante, no puede descender). El descenso cambia solo
     la membresía del jugador en ese club y queda en categoria_historial con el club.
   - Los puntos de todos EN ESE CLUB vuelven a 0: los puntos de la temporada cerrada
     no se borran (siguen en ranking_puntos con su id_temporada), solo dejan
     de contar en el ranking vigente del club.
   - Se abre la temporada siguiente DE ESE CLUB.
   Las temporadas, puntos y categorías de los demás clubes no se tocan.

   Quién cuenta como "último" (por categoría de un club):
   - Solo quienes jugaron al menos un torneo del club en la temporada (quien no compitió
     no es "último": no se lo castiga por no haber jugado).
   - Tiene que haber al menos 2 competidores y el último no puede estar
     empatado con el primero.
   - Si el último está empatado en TODOS los criterios con otros, descienden todos
     los que comparten esa última posición.
   ========================================================= */


/**
 * Quiénes descenderían si se finalizara ahora la temporada de ese club. No escribe nada.
 * @return array lista de ['id_usuario','nombre','de','de_nombre','a','a_nombre','puntos','posicion','competidores']
 */
function previsualizarCierreTemporada($conexion, $idClub) {

    $categorias = mysqli_fetch_all(
        mysqli_query($conexion, "SELECT id_categoria, nombre FROM categoria ORDER BY id_categoria ASC"),
        MYSQLI_ASSOC
    );

    $descensos = [];

    foreach ($categorias as $i => $cat) {

        // La categoría más baja no tiene a dónde descender.
        if ($i === 0) {
            continue;
        }

        $idCat = (int) $cat['id_categoria'];

        $competidores = array_values(array_filter(
            rankingDeCategoria($conexion, (int) $idClub, $idCat),
            fn($f) => $f['torneos'] >= 1
        ));

        if (count($competidores) < 2) {
            continue;
        }

        $ultima = $competidores[count($competidores) - 1]['posicion'];

        if ($ultima === $competidores[0]['posicion']) {
            continue;
        }

        $inferior = $categorias[$i - 1];

        foreach ($competidores as $f) {

            if ($f['posicion'] !== $ultima) {
                continue;
            }

            $descensos[] = [
                'id_usuario' => $f['id_usuario'],
                'nombre' => $f['nombre_completo'],
                'de' => $idCat,
                'de_nombre' => $cat['nombre'],
                'a' => (int) $inferior['id_categoria'],
                'a_nombre' => $inferior['nombre'],
                'puntos' => $f['puntos'],
                'posicion' => $f['posicion'],
                'competidores' => count($competidores),
            ];

        }

    }

    return $descensos;

}


/**
 * Finaliza la temporada vigente DE UN CLUB (una sola transacción): aplica los descensos en ese club, la cierra y abre
 * la siguiente del mismo club. Valida en el servidor que la persona pueda gestionar ese club (administrador: cualquiera;
 * organizador: solo los suyos). Quien llama valida además sesión y CSRF.
 *
 * @param int $idClub              el club cuya temporada se cierra
 * @param int $idTemporadaEsperada la temporada que la persona vio al pulsar el botón; si ya se cerró
 *                                 (doble clic, otro usuario) no se vuelve a cerrar la siguiente.
 * @return array ['ok' => true, 'temporada' => nombre, 'descensos' => [...]] o ['ok' => false, 'error' => código]
 *         códigos: sin_permiso | sin_temporada | temporada_ya_cerrada | error_bd
 */
function cerrarTemporada($conexion, $idUsuario, $idClub, $idTemporadaEsperada) {

    $idClub = (int) $idClub;

    if ($idClub <= 0 || !puedeGestionarClub($conexion, $idUsuario, $idClub)) {
        return ['ok' => false, 'error' => 'sin_permiso'];
    }

    mysqli_begin_transaction($conexion);

    try {

        // Solo la temporada abierta de ESTE club (las de los demás clubes no se miran ni se bloquean).
        $stmt = mysqli_prepare($conexion, "SELECT id_temporada, nombre FROM temporada WHERE id_club = ? AND estado = 'Abierta' FOR UPDATE");
        mysqli_stmt_bind_param($stmt, "i", $idClub);
        mysqli_stmt_execute($stmt);
        $temporada = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$temporada) {
            throw new Exception('sin_temporada');
        }

        $idTemporada = (int) $temporada['id_temporada'];

        if ($idTemporada !== (int) $idTemporadaEsperada) {
            throw new Exception('temporada_ya_cerrada');
        }

        $descensos = previsualizarCierreTemporada($conexion, $idClub);
        $stmtUltimo = mysqli_prepare($conexion, "SELECT COALESCE(MAX(id_ranking_puntos), 0) AS m FROM ranking_puntos WHERE id_club = ?");
        mysqli_stmt_bind_param($stmtUltimo, "i", $idClub);
        mysqli_stmt_execute($stmtUltimo);
        $ultimoId = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUltimo))['m'];

        $stmtHistorial = mysqli_prepare(
            $conexion,
            "INSERT INTO categoria_historial
                (id_usuario, id_categoria_anterior, id_categoria_nueva, tipo, motivo,
                 id_temporada, puntos_alcanzados, ultimo_id_ranking_puntos, id_club)
             VALUES (?, ?, ?, 'Descenso por temporada', ?, ?, ?, ?, ?)"
        );
        $stmtCambio = mysqli_prepare($conexion, "UPDATE usuario_club SET id_categoria = ? WHERE id_usuario = ? AND id_club = ? AND id_categoria = ?");

        $descensosAplicados = 0;

        foreach ($descensos as $d) {

            $idU = (int) $d['id_usuario'];

            // La membresía del jugador en ESTE club (nunca la de otro club).
            $m = membresiaEnClub($conexion, $idU, $idClub, true);

            // Si cambió de categoría mientras tanto, no se lo toca.
            if (!$m || $m['id_categoria'] === null || (int) $m['id_categoria'] !== $d['de']) {
                continue;
            }

            $motivo = 'Último del ranking de ' . $d['de_nombre'] . ' al finalizar la ' . $temporada['nombre']
                . ' (' . $d['puntos'] . ' puntos, puesto ' . $d['posicion'] . ' de ' . $d['competidores'] . ')';

            mysqli_stmt_bind_param($stmtHistorial, "iiisiiii", $idU, $d['de'], $d['a'], $motivo, $idTemporada, $d['puntos'], $ultimoId, $idClub);
            mysqli_stmt_execute($stmtHistorial);

            mysqli_stmt_bind_param($stmtCambio, "iiii", $d['a'], $idU, $idClub, $d['de']);
            mysqli_stmt_execute($stmtCambio);

            if (mysqli_stmt_affected_rows($stmtCambio) !== 1) {
                throw new Exception('error_bd');
            }

            $descensosAplicados++;

            actividadCategoriaAutomatica(
                $conexion, $idU, $d['de'], $d['a'],
                'último del ranking de ' . $d['de_nombre'] . ' al finalizar la ' . $temporada['nombre'],
                null, $idClub
            );

        }

        $stmtCierre = mysqli_prepare(
            $conexion,
            "UPDATE temporada SET estado = 'Cerrada', fecha_cierre = NOW(), id_usuario_cierre = ?
             WHERE id_temporada = ? AND id_club = ? AND estado = 'Abierta'"
        );
        mysqli_stmt_bind_param($stmtCierre, "iii", $idUsuario, $idTemporada, $idClub);
        mysqli_stmt_execute($stmtCierre);

        if (mysqli_stmt_affected_rows($stmtCierre) !== 1) {
            throw new Exception('temporada_ya_cerrada');
        }

        $nombreNueva = abrirTemporadaDeClub($conexion, $idClub);

        actividadTemporada(
            $conexion, 'temporada_cerrada', $temporada['nombre'], $idUsuario,
            $descensosAplicados > 0 ? ' (' . $descensosAplicados . ($descensosAplicados === 1 ? ' jugador bajó' : ' jugadores bajaron') . ' de categoría)' : '',
            $idClub
        );
        actividadTemporada($conexion, 'temporada_creada', $nombreNueva, $idUsuario, '', $idClub);

        mysqli_commit($conexion);

        return ['ok' => true, 'temporada' => $temporada['nombre'], 'nueva' => $nombreNueva, 'descensos' => $descensos];

    } catch (Throwable $e) {

        mysqli_rollback($conexion);

        $codigo = $e->getMessage();

        if (!in_array($codigo, ['sin_temporada', 'temporada_ya_cerrada'], true)) {
            error_log("Error al finalizar la temporada del club $idClub: " . $codigo);
            $codigo = 'error_bd';
        }

        return ['ok' => false, 'error' => $codigo];

    }

}


/** Ascensos por puntos ocurridos durante una temporada (más recientes primero). La temporada ya pertenece a un solo club. */
function ascensosDeTemporada($conexion, $idTemporada) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT CONCAT(u.nombre, ' ', u.apellido) AS nombre, ca.nombre AS de, cn.nombre AS a,
                h.puntos_alcanzados, t.nombre AS torneo, h.fecha
         FROM categoria_historial h
         INNER JOIN usuario u ON u.id_usuario = h.id_usuario
         INNER JOIN categoria ca ON ca.id_categoria = h.id_categoria_anterior
         INNER JOIN categoria cn ON cn.id_categoria = h.id_categoria_nueva
         LEFT JOIN torneos t ON t.id_torneo = h.id_torneo
         WHERE h.id_temporada = ? AND h.tipo = 'Ascenso por puntos'
         ORDER BY h.id_historial DESC"
    );
    mysqli_stmt_bind_param($stmt, "i", $idTemporada);
    mysqli_stmt_execute($stmt);

    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

}


/** Última temporada cerrada de ese club con los ascensos y descensos que tuvo (null si todavía no se cerró ninguna). */
function ultimoCierreTemporada($conexion, $idClub) {

    $stmtT = mysqli_prepare(
        $conexion,
        "SELECT t.id_temporada, t.nombre, t.fecha_cierre, CONCAT(u.nombre, ' ', u.apellido) AS cerro
         FROM temporada t LEFT JOIN usuario u ON u.id_usuario = t.id_usuario_cierre
         WHERE t.id_club = ? AND t.estado = 'Cerrada' ORDER BY t.id_temporada DESC LIMIT 1"
    );
    mysqli_stmt_bind_param($stmtT, "i", $idClub);
    mysqli_stmt_execute($stmtT);
    $t = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT));
    mysqli_stmt_close($stmtT);

    if (!$t) {
        return null;
    }

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT CONCAT(u.nombre, ' ', u.apellido) AS nombre, ca.nombre AS de, cn.nombre AS a, h.puntos_alcanzados
         FROM categoria_historial h
         INNER JOIN usuario u ON u.id_usuario = h.id_usuario
         INNER JOIN categoria ca ON ca.id_categoria = h.id_categoria_anterior
         INNER JOIN categoria cn ON cn.id_categoria = h.id_categoria_nueva
         WHERE h.id_temporada = ? AND h.tipo = 'Descenso por temporada'
         ORDER BY h.id_categoria_anterior DESC, h.id_historial ASC"
    );
    $idT = (int) $t['id_temporada'];
    mysqli_stmt_bind_param($stmt, "i", $idT);
    mysqli_stmt_execute($stmt);
    $t['descensos'] = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    $t['ascensos'] = ascensosDeTemporada($conexion, $idT);

    return $t;

}
