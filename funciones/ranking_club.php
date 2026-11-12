<?php

require_once __DIR__ . '/ranking_general.php';

/* =========================================================
   RANKING POR CLUB (solo lectura)

   No usa tablas nuevas ni modifica ningún puntaje: se calcula al consultar a partir de
   ranking_puntos (una fila por jugador y torneo, ya liquidada y con su flag `anulado`), uniendo
   cada torneo con su club por la relación real torneos.id_club -> club.id_club.

   Qué muestra el ranking de un club:
   - Solo a los jugadores que tienen resultados en torneos de ESE club, con su categoría EN ese club.
   - Los puntos que ganaron en torneos de ese club (nunca los de otros clubes).
   - Filtro 'historico' (toda la trayectoria en el club) o 'temporada' (temporada vigente DE ESE club).
   - Los descalificados (filas anuladas) no suman, igual que en el ranking general.
   - Mismo desempate que el ranking general: puntos, campeonatos, partidos ganados,
     diferencia de sets y diferencia de games; si sigue igual, comparten posición.

   Es la vista pública de un club; el ranking general y por categoría de un club salen de ranking_general.php y ranking_puntos.php.
   ========================================================= */

/**
 * Clubes registrados con sus números reales (también los que aún no tienen torneos).
 * Con $idPropietario solo devuelve los clubes de ese organizador; con $idMiembro, solo los clubes a los que pertenece ese jugador.
 */
function rankingClubesRegistrados($conexion, $idPropietario = null, $idMiembro = null) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT c.id_club, c.nombre,
                (SELECT COUNT(*) FROM torneos t WHERE t.id_club = c.id_club) AS torneos,
                (SELECT COUNT(*) FROM torneos t WHERE t.id_club = c.id_club AND t.estado = 'Finalizado') AS finalizados,
                (SELECT COUNT(DISTINCT rp.id_usuario)
                   FROM ranking_puntos rp JOIN torneos t ON t.id_torneo = rp.id_torneo
                  WHERE t.id_club = c.id_club AND rp.anulado = 0) AS jugadores
         FROM club c
         WHERE (? IS NULL OR c.id_usuario = ?)
           AND (? IS NULL OR EXISTS (SELECT 1 FROM usuario_club uc WHERE uc.id_club = c.id_club AND uc.id_usuario = ?))
         ORDER BY c.nombre ASC"
    );

    mysqli_stmt_bind_param($stmt, "iiii", $idPropietario, $idPropietario, $idMiembro, $idMiembro);
    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    foreach ($filas as $k => $f) {
        $filas[$k]['id_club'] = (int) $f['id_club'];
        $filas[$k]['torneos'] = (int) $f['torneos'];
        $filas[$k]['finalizados'] = (int) $f['finalizados'];
        $filas[$k]['jugadores'] = (int) $f['jugadores'];
    }

    return $filas;

}


/**
 * Clubes cuyo ranking puede ver quien está logueado (el rol se confirma contra la base, no solo en la sesión):
 * - administrador: todos los clubes registrados;
 * - organizador: solamente SUS clubes (los otros no le corresponden);
 * - participante: solamente los clubes a los que pertenece (usuario_club);
 * - sin sesión: ninguno.
 */
function rankingClubesVisibles($conexion) {

    $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

    if ($idUsuario <= 0) {
        return [];
    }

    $stmt = mysqli_prepare($conexion, "SELECT id_rol FROM usuario WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmt, "i", $idUsuario);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $rol = (int) ($fila['id_rol'] ?? 0);

    if ($rol === 1) {
        return rankingClubesRegistrados($conexion);
    }

    if ($rol === 2) {
        return rankingClubesRegistrados($conexion, $idUsuario);
    }

    if ($rol === 3) {
        return rankingClubesRegistrados($conexion, null, $idUsuario);
    }

    return [];

}


/**
 * Ranking de un club.
 *
 * @param int    $idClub id de un club existente
 * @param string $filtro 'historico' | 'temporada'
 * @param int|null $idTemporada temporada de ESE club (actual o anterior); si no pertenece al club se usa la abierta
 * @return array|null null si el club no existe; si existe:
 *   ['club' => [id_club, nombre], 'filtro', 'temporada' => fila|null, 'torneos', 'finalizados', 'filas' => [...]]
 *   Cada fila: id_usuario, nombre, apellido, nombre_completo, categoria, puntos, torneos, campeonatos,
 *   pg, pp, dif_sets, dif_games, posicion, empate.
 */
function rankingDeClubPublico($conexion, $idClub, $filtro = 'historico', $idTemporada = null) {

    $filtro = rankingGeneralFiltro($filtro);
    $idClub = (int) $idClub;

    $stmtClub = mysqli_prepare($conexion, "SELECT id_club, nombre FROM club WHERE id_club = ?");
    mysqli_stmt_bind_param($stmtClub, "i", $idClub);
    mysqli_stmt_execute($stmtClub);
    $club = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtClub));
    mysqli_stmt_close($stmtClub);

    if (!$club) {
        return null;
    }

    $temporada = $idTemporada !== null ? temporadaDeClub($conexion, $idClub, (int) $idTemporada) : null;
    $temporada = $temporada ?: temporadaAbierta($conexion, $idClub);

    $stmtT = mysqli_prepare(
        $conexion,
        "SELECT COUNT(*) AS torneos, COALESCE(SUM(estado = 'Finalizado'), 0) AS finalizados FROM torneos WHERE id_club = ?"
    );
    mysqli_stmt_bind_param($stmtT, "i", $idClub);
    mysqli_stmt_execute($stmtT);
    $resumen = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT));
    mysqli_stmt_close($stmtT);

    $resultado = [
        'club' => ['id_club' => (int) $club['id_club'], 'nombre' => $club['nombre']],
        'filtro' => $filtro,
        'temporada' => $temporada,
        'torneos' => (int) $resumen['torneos'],
        'finalizados' => (int) $resumen['finalizados'],
        'filas' => [],
    ];

    // Filtro de temporada: sin temporada vigente no hay nada que mostrar en ese alcance.
    $idTemporada = null;

    if ($filtro === 'temporada') {

        if (!$temporada) {
            return $resultado;
        }

        $idTemporada = (int) $temporada['id_temporada'];

    }

    $sql = "SELECT u.id_usuario, u.nombre, u.apellido, c.nombre AS categoria,
                   COALESCE(SUM(rp.puntos_grupos + rp.puntos_cruces), 0) AS puntos,
                   COUNT(rp.id_ranking_puntos) AS torneos,
                   COALESCE(SUM(rp.instancia = 'Campeon'), 0) AS camp,
                   COALESCE(SUM(rp.partidos_ganados), 0) AS pg,
                   COALESCE(SUM(rp.partidos_perdidos), 0) AS pp,
                   COALESCE(SUM(CAST(rp.sets_favor AS SIGNED) - CAST(rp.sets_contra AS SIGNED)), 0) AS ds,
                   COALESCE(SUM(CAST(rp.games_favor AS SIGNED) - CAST(rp.games_contra AS SIGNED)), 0) AS dg
            FROM ranking_puntos rp
            INNER JOIN torneos t ON t.id_torneo = rp.id_torneo
            INNER JOIN usuario u ON u.id_usuario = rp.id_usuario
            LEFT JOIN usuario_club uc ON uc.id_usuario = u.id_usuario AND uc.id_club = ?
            LEFT JOIN categoria c ON c.id_categoria = uc.id_categoria
            WHERE t.id_club = ? AND rp.id_club = ? AND rp.anulado = 0"
         . ($idTemporada !== null ? " AND rp.id_temporada = ?" : "") . "
            GROUP BY u.id_usuario, u.nombre, u.apellido, c.nombre";

    $stmt = mysqli_prepare($conexion, $sql);

    if ($idTemporada !== null) {
        mysqli_stmt_bind_param($stmt, "iiii", $idClub, $idClub, $idClub, $idTemporada);
    } else {
        mysqli_stmt_bind_param($stmt, "iii", $idClub, $idClub, $idClub);
    }

    mysqli_stmt_execute($stmt);
    $crudas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $filas = [];

    foreach ($crudas as $r) {

        $categoria = $r['categoria'];

        if ($categoria === null) {
            $categoria = 'Sin categoría';
        }

        $filas[] = [
            'id_usuario' => (int) $r['id_usuario'],
            'nombre' => $r['nombre'],
            'apellido' => $r['apellido'],
            'nombre_completo' => trim($r['nombre'] . ' ' . $r['apellido']),
            'categoria' => $categoria,
            'puntos' => (int) $r['puntos'],
            'torneos' => (int) $r['torneos'],
            'campeonatos' => (int) $r['camp'],
            'pg' => (int) $r['pg'],
            'pp' => (int) $r['pp'],
            'dif_sets' => (int) $r['ds'],
            'dif_games' => (int) $r['dg'],
        ];

    }

    // rankingGeneralPosiciones() compara $fila[$alcance]; se reutiliza con un alcance propio.
    $paraPosiciones = array_map(fn($f) => $f + ['x' => [
        'puntos' => $f['puntos'], 'campeonatos' => $f['campeonatos'], 'pg' => $f['pg'],
        'dif_sets' => $f['dif_sets'], 'dif_games' => $f['dif_games'],
    ]], $filas);

    $posiciones = rankingGeneralPosiciones($paraPosiciones, 'x');

    foreach ($filas as $k => $f) {
        $filas[$k]['posicion'] = $posiciones[$f['id_usuario']][0];
        $filas[$k]['empate'] = $posiciones[$f['id_usuario']][1];
    }

    usort($filas, function ($x, $y) {
        return $x['posicion'] <=> $y['posicion'] ?: strcasecmp($x['nombre_completo'], $y['nombre_completo']);
    });

    $resultado['filas'] = $filas;

    return $resultado;

}
