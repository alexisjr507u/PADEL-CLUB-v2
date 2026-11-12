<?php

require_once __DIR__ . '/ranking_puntos.php';

/* =========================================================
   RANKING GENERAL DE UN CLUB: todos los jugadores del club, de cualquier categoría.

   Cada club tiene su propio ranking: solo cuentan los puntos ganados en torneos de ESE club (ranking_puntos.id_club)
   y la temporada es la temporada abierta de ese club. Nunca se suma nada de otro club.

   No usa tablas nuevas: se calcula al consultar a partir de ranking_puntos, que ya guarda UNA
   fila por jugador y torneo con los puntos del resultado final (grupos + una sola instancia de
   cruces). Por eso no hay puntos duplicados por partido o cruce, y no se aplican multiplicadores:
   un campeonato da lo mismo en cualquier categoría.

   Dos puntajes independientes:
   - Puntos totales: TODAS las filas del jugador en ese club, de cualquier categoría y temporada. Nunca se
     reinician: subir o bajar de categoría o cerrar una temporada no les quita nada.
   - Puntos de temporada: solo las filas de la temporada vigente del club (se reinician al abrir otra).

   Filtro (define con qué puntaje se ordena y se calculan los desempates):
   - 'historico': puntos totales y estadísticas de toda la trayectoria.
   - 'temporada': puntos y estadísticas de la temporada vigente.

   Desempate, en este orden: más puntos, más campeonatos ganados, más partidos ganados, mejor
   diferencia de sets, mejor diferencia de games. Si siguen iguales comparten posición (1, 2, 2, 4).

   Aparecen TODOS los participantes que pertenecen al club (usuario_club), también con 0 puntos. La categoría es la
   ACTUAL del jugador EN ESE CLUB (usuario_club.id_categoria), no la que figuraba en un resultado anterior.
   ========================================================= */

const RANKING_GENERAL_FILTROS = ['historico', 'temporada'];

function rankingGeneralFiltro($valor) {

    return in_array($valor, RANKING_GENERAL_FILTROS, true) ? $valor : 'historico';

}


/** Clave de comparación (mayor = mejor) para un alcance: 'h' (histórico) o 't' (temporada). */
function rankingGeneralClave(array $fila, $alcance) {

    $a = $fila[$alcance];

    return [$a['puntos'], $a['campeonatos'], $a['pg'], $a['dif_sets'], $a['dif_games']];

}


/** Posiciones estilo competencia (1, 2, 2, 4) para un alcance. Devuelve [id_usuario => [posicion, empate]]. */
function rankingGeneralPosiciones(array $filas, $alcance) {

    usort($filas, function ($x, $y) use ($alcance) {
        $cmp = rankingGeneralClave($y, $alcance) <=> rankingGeneralClave($x, $alcance);
        return $cmp !== 0 ? $cmp : strcasecmp($x['nombre_completo'], $y['nombre_completo']);
    });

    $posiciones = [];
    $posicion = 0;
    $n = count($filas);

    foreach ($filas as $k => $f) {

        $igualAnterior = $k > 0 && rankingGeneralClave($filas[$k - 1], $alcance) === rankingGeneralClave($f, $alcance);

        if (!$igualAnterior) {
            $posicion = $k + 1;
        }

        $posiciones[$f['id_usuario']] = $posicion;

    }

    $cuenta = array_count_values($posiciones);
    $salida = [];

    foreach ($posiciones as $id => $p) {
        $salida[$id] = [$p, $cuenta[$p] > 1];
    }

    return $salida;

}


/**
 * Ranking general completo de un club.
 *
 * @param int    $idClub club existente (los que no existen devuelven un ranking vacío)
 * @param string $filtro 'historico' | 'temporada'
 * @param int|null $idTemporada temporada de ESE club a consultar (la actual o una anterior). Si no pertenece al club o no se
 *                 indica, se usa la temporada abierta del club.
 * @return array ['filtro' => ..., 'temporada' => fila|null, 'filas' => [...ordenadas...]]
 *   Cada fila: id_usuario, nombre, apellido, nombre_completo, id_categoria, categoria,
 *   puntos_temporada, puntos_totales, puntos (del filtro activo), campeonatos, pg, pp, torneos,
 *   dif_sets, dif_games (del filtro activo), posicion, empate, pos_historico, pos_temporada.
 */
function rankingGeneral($conexion, $idClub, $filtro = 'historico', $idTemporada = null) {

    $filtro = rankingGeneralFiltro($filtro);
    $idClub = (int) $idClub;
    $temporada = $idTemporada !== null ? temporadaDeClub($conexion, $idClub, (int) $idTemporada) : null;
    $temporada = $temporada ?: temporadaAbierta($conexion, $idClub);
    $idTemporada = $temporada ? (int) $temporada['id_temporada'] : 0;

    // $idTemporada sale de la base (entero), no del navegador.
    $enTemporada = "rp.id_temporada = $idTemporada";

    $sql = "SELECT u.id_usuario, u.nombre, u.apellido, uc.id_categoria, c.nombre AS categoria,

                COALESCE(SUM(rp.puntos_grupos + rp.puntos_cruces), 0) AS h_puntos,
                COUNT(rp.id_ranking_puntos) AS h_torneos,
                COALESCE(SUM(rp.instancia = 'Campeon'), 0) AS h_camp,
                COALESCE(SUM(rp.partidos_ganados), 0) AS h_pg,
                COALESCE(SUM(rp.partidos_perdidos), 0) AS h_pp,
                COALESCE(SUM(CAST(rp.sets_favor AS SIGNED) - CAST(rp.sets_contra AS SIGNED)), 0) AS h_ds,
                COALESCE(SUM(CAST(rp.games_favor AS SIGNED) - CAST(rp.games_contra AS SIGNED)), 0) AS h_dg,

                COALESCE(SUM(CASE WHEN $enTemporada THEN rp.puntos_grupos + rp.puntos_cruces END), 0) AS t_puntos,
                COALESCE(SUM(CASE WHEN $enTemporada THEN 1 END), 0) AS t_torneos,
                COALESCE(SUM(CASE WHEN $enTemporada THEN rp.instancia = 'Campeon' END), 0) AS t_camp,
                COALESCE(SUM(CASE WHEN $enTemporada THEN rp.partidos_ganados END), 0) AS t_pg,
                COALESCE(SUM(CASE WHEN $enTemporada THEN rp.partidos_perdidos END), 0) AS t_pp,
                COALESCE(SUM(CASE WHEN $enTemporada THEN CAST(rp.sets_favor AS SIGNED) - CAST(rp.sets_contra AS SIGNED) END), 0) AS t_ds,
                COALESCE(SUM(CASE WHEN $enTemporada THEN CAST(rp.games_favor AS SIGNED) - CAST(rp.games_contra AS SIGNED) END), 0) AS t_dg

            FROM usuario_club uc
            INNER JOIN usuario u ON u.id_usuario = uc.id_usuario AND u.id_rol = 3
            LEFT JOIN categoria c ON c.id_categoria = uc.id_categoria
            LEFT JOIN ranking_puntos rp ON rp.id_usuario = u.id_usuario AND rp.id_club = uc.id_club AND rp.anulado = 0
            WHERE uc.id_club = ?
            GROUP BY u.id_usuario, u.nombre, u.apellido, uc.id_categoria, c.nombre";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $idClub);
    mysqli_stmt_execute($stmt);
    $crudas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $filas = [];

    foreach ($crudas as $r) {

        $categoria = $r['categoria'];

        if ($categoria === null) {
            $categoria = 'Sin categoría';
        }

        $alcance = function ($p) use ($r) {
            return [
                'puntos' => (int) $r[$p . '_puntos'],
                'torneos' => (int) $r[$p . '_torneos'],
                'campeonatos' => (int) $r[$p . '_camp'],
                'pg' => (int) $r[$p . '_pg'],
                'pp' => (int) $r[$p . '_pp'],
                'dif_sets' => (int) $r[$p . '_ds'],
                'dif_games' => (int) $r[$p . '_dg'],
            ];
        };

        $filas[] = [
            'id_usuario' => (int) $r['id_usuario'],
            'nombre' => $r['nombre'],
            'apellido' => $r['apellido'],
            'nombre_completo' => trim($r['nombre'] . ' ' . $r['apellido']),
            'id_categoria' => $r['id_categoria'] === null ? null : (int) $r['id_categoria'],
            'categoria' => $categoria,
            'h' => $alcance('h'),
            't' => $alcance('t'),
        ];

    }

    $posH = rankingGeneralPosiciones($filas, 'h');
    $posT = rankingGeneralPosiciones($filas, 't');
    $activo = ($filtro === 'temporada') ? 't' : 'h';
    $posActiva = ($filtro === 'temporada') ? $posT : $posH;

    foreach ($filas as $k => $f) {

        $a = $f[$activo];
        $id = $f['id_usuario'];

        $filas[$k] += [
            'puntos_totales' => $f['h']['puntos'],
            'puntos_temporada' => $f['t']['puntos'],
            'puntos' => $a['puntos'],
            'campeonatos' => $a['campeonatos'],
            'pg' => $a['pg'],
            'pp' => $a['pp'],
            'torneos' => $a['torneos'],
            'dif_sets' => $a['dif_sets'],
            'dif_games' => $a['dif_games'],
            'posicion' => $posActiva[$id][0],
            'empate' => $posActiva[$id][1],
            'pos_historico' => $posH[$id][0],
            'pos_temporada' => $posT[$id][0],
        ];

    }

    usort($filas, function ($x, $y) {
        return $x['posicion'] <=> $y['posicion'] ?: strcasecmp($x['nombre_completo'], $y['nombre_completo']);
    });

    return ['filtro' => $filtro, 'id_club' => $idClub, 'temporada' => $temporada, 'filas' => $filas];

}
