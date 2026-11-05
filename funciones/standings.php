<?php

/* =========================================================
   LÓGICA COMPARTIDA DE PARTIDOS Y TABLA DE POSICIONES
   Usada por gestionar_grupos.php (carga/edición) y por
   ver_torneo.php (lectura), para que ambas pantallas calculen
   exactamente lo mismo.

   REGLAS (decididas por el sistema, no hay reglas "oficiales"
   cargadas por el organizador):
   - Partido a mejor de 3 sets. Si el set 1 y el set 2 los gana
     la misma pareja/participante, el set 3 no hace falta.
   - Victoria de partido = 3 puntos, derrota = 0 (no hay empates). Un W.O. cuenta como
     victoria/derrota pero sin sets ni games (no se inventa un marcador).
   - Orden de la tabla: PTS, diferencia de sets, diferencia de games,
     games ganados, enfrentamiento directo; si sigue igual se marca
     como empate y se ordena por orden de ingreso al grupo.
   - Clasifican CLASIFICAN_POR_GRUPO por grupo (el torneo no tiene una
     columna de configuración para esto).
   ========================================================= */


const CLASIFICAN_POR_GRUPO = 2;


/**
 * Un set de pádel válido se gana 6 games (con 2 de diferencia, hasta
 * 6-4) o 7 games (7-5, o 7-6 en tie-break). Cualquier otro marcador
 * (empate, diferencias imposibles, etc.) no es un resultado real.
 */
function esSetValidoPadel($g1, $g2) {

    $mayor = max($g1, $g2);
    $menor = min($g1, $g2);

    if ($mayor === 6 && $menor <= 4) {
        return true;
    }

    if ($mayor === 7 && ($menor === 5 || $menor === 6)) {
        return true;
    }

    return false;

}


/**
 * Valida y resuelve el resultado de un partido a partir de los
 * games de cada set. Devuelve siempre un array con 'valido'.
 */
function calcularResultadoSets(
    $set1_1, $set1_2,
    $set2_1, $set2_2,
    $set3_1, $set3_2
) {

    $sets = [
        [$set1_1, $set1_2],
        [$set2_1, $set2_2],
    ];

    foreach ($sets as $set) {
        foreach ($set as $valor) {
            if ($valor === null || $valor === '' || !is_numeric($valor)) {
                return ['valido' => false, 'error' => 'faltan_sets'];
            }
        }
    }

    $hayTercerSet = ($set3_1 !== null && $set3_1 !== '') || ($set3_2 !== null && $set3_2 !== '');

    if ($hayTercerSet) {
        if (!is_numeric($set3_1) || !is_numeric($set3_2)) {
            return ['valido' => false, 'error' => 'set_incompleto'];
        }
        $sets[] = [$set3_1, $set3_2];
    }

    $setsGanados1 = 0;
    $setsGanados2 = 0;
    $gamesGanados1 = 0;
    $gamesGanados2 = 0;

    foreach ($sets as $set) {

        $g1 = (int) $set[0];
        $g2 = (int) $set[1];

        if ($g1 < 0 || $g2 < 0 || !esSetValidoPadel($g1, $g2)) {
            return ['valido' => false, 'error' => 'set_invalido'];
        }

        $gamesGanados1 += $g1;
        $gamesGanados2 += $g2;

        if ($g1 > $g2) {
            $setsGanados1++;
        } else {
            $setsGanados2++;
        }

    }

    // Con 2 sets cargados, uno de los dos tiene que haberse llevado ambos
    // (si no, falta el tercer set para desempatar).
    if (count($sets) === 2 && $setsGanados1 !== 2 && $setsGanados2 !== 2) {
        return ['valido' => false, 'error' => 'falta_tercer_set'];
    }

    // Si los dos primeros sets los ganó la misma pareja, el partido ya terminó.
    if (count($sets) === 3 && (((int) $sets[0][0] > (int) $sets[0][1]) === ((int) $sets[1][0] > (int) $sets[1][1]))) {
        return ['valido' => false, 'error' => 'set_sobrante'];
    }

    if (count($sets) === 3 && ($setsGanados1 === $setsGanados2)) {
        // No debería poder pasar (3 sets siempre desempatan), pero por las dudas.
        return ['valido' => false, 'error' => 'resultado_inconcluso'];
    }

    $ganador = $setsGanados1 > $setsGanados2 ? 1 : 2;

    return [
        'valido' => true,
        'ganador' => $ganador,
        'sets_ganados_1' => $setsGanados1,
        'sets_ganados_2' => $setsGanados2,
        'games_ganados_1' => $gamesGanados1,
        'games_ganados_2' => $gamesGanados2,
    ];

}


/**
 * Resultado de un partido ya guardado, en el mismo formato que calcularResultadoSets().
 *
 * - Normal: se valida con los sets (calcularResultadoSets).
 * - W.O. (resultado_tipo = 'WO'): gana quien figura en id_ganador y no hay sets ni games:
 *   no se inventa ningún marcador, así que no suma ni resta sets/games a nadie.
 *   El resultado trae 'wo' => true.
 */
function resultadoDePartido(array $partido) {

    if (($partido['resultado_tipo'] ?? 'Normal') === 'WO') {

        $idGanador = (int) ($partido['id_ganador'] ?? 0);

        if ($idGanador === (int) $partido['id_entrante_1']) {
            $ganador = 1;
        } elseif ($idGanador === (int) $partido['id_entrante_2']) {
            $ganador = 2;
        } else {
            return ['valido' => false, 'error' => 'wo_invalido'];
        }

        return [
            'valido' => true,
            'wo' => true,
            'ganador' => $ganador,
            'sets_ganados_1' => 0,
            'sets_ganados_2' => 0,
            'games_ganados_1' => 0,
            'games_ganados_2' => 0,
        ];

    }

    return calcularResultadoSets(
        $partido['set1_games_1'], $partido['set1_games_2'],
        $partido['set2_games_1'], $partido['set2_games_2'],
        $partido['set3_games_1'], $partido['set3_games_2']
    );

}


/**
 * Trae los miembros de un grupo (participantes o parejas, ya confirmados)
 * junto con sus partidos de fase de Grupos. $esIndividual define la tabla.
 */
function obtenerMiembrosYPartidosDeGrupo($conexion, $id_grupo, $esIndividual) {

    if ($esIndividual) {

        $stmtMiembros = mysqli_prepare(
            $conexion,
            "SELECT gi.id_grupo_inscripcion AS id_entrante, u.nombre, u.apellido
             FROM grupo_inscripcion gi
             INNER JOIN inscripcion insc ON insc.id_inscripcion = gi.id_inscripcion
             INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
             WHERE gi.id_grupo = ? AND insc.estado = 'Confirmada'
             ORDER BY gi.orden ASC, gi.id_grupo_inscripcion ASC"
        );

    } else {

        $stmtMiembros = mysqli_prepare(
            $conexion,
            "SELECT gp.id_grupo_pareja AS id_entrante,
                    u1.nombre AS nombre1, u1.apellido AS apellido1,
                    u2.nombre AS nombre2, u2.apellido AS apellido2
             FROM grupo_pareja gp
             INNER JOIN pareja p ON p.id_pareja = gp.id_pareja
             INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
             INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
             INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
             INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
             WHERE gp.id_grupo = ?
             ORDER BY gp.orden ASC, gp.id_grupo_pareja ASC"
        );

    }

    mysqli_stmt_bind_param($stmtMiembros, "i", $id_grupo);
    mysqli_stmt_execute($stmtMiembros);
    $miembros = mysqli_fetch_all(mysqli_stmt_get_result($stmtMiembros), MYSQLI_ASSOC);

    $tablaPartidos = $esIndividual ? 'partido_individual' : 'partido_pareja';

    $stmtPartidos = mysqli_prepare(
        $conexion,
        "SELECT id_partido, id_entrante_1, id_entrante_2,
                set1_games_1, set1_games_2, set2_games_1, set2_games_2,
                set3_games_1, set3_games_2, id_ganador, resultado_tipo, estado, orden
         FROM $tablaPartidos
         WHERE id_grupo = ? AND fase = 'Grupos'
         ORDER BY orden ASC, id_partido ASC"
    );
    mysqli_stmt_bind_param($stmtPartidos, "i", $id_grupo);
    mysqli_stmt_execute($stmtPartidos);
    $partidos = mysqli_fetch_all(mysqli_stmt_get_result($stmtPartidos), MYSQLI_ASSOC);

    return ['miembros' => $miembros, 'partidos' => $partidos];

}


/**
 * Calcula PJ/PG/PTS/diferencia de sets/diferencia de games por miembro
 * a partir de la lista de miembros y de partidos (ya jugados o no) de un
 * grupo, y devuelve el listado ordenado por posición.
 */
function calcularStandings(array $miembros, array $partidos) {

    $stats = [];

    foreach ($miembros as $miembro) {
        $stats[(int) $miembro['id_entrante']] = [
            'id_entrante' => (int) $miembro['id_entrante'],
            'pj' => 0,
            'pg' => 0,
            'pp' => 0,
            'pts' => 0,
            'sets_favor' => 0,
            'sets_contra' => 0,
            'games_favor' => 0,
            'games_contra' => 0,
        ];
    }

    foreach ($partidos as $partido) {

        if ($partido['estado'] !== 'Jugado') {
            continue;
        }

        $resultado = resultadoDePartido($partido);

        if (!$resultado['valido']) {
            continue;
        }

        $id1 = (int) $partido['id_entrante_1'];
        $id2 = (int) $partido['id_entrante_2'];

        if (!isset($stats[$id1]) || !isset($stats[$id2])) {
            continue;
        }

        $stats[$id1]['pj']++;
        $stats[$id2]['pj']++;

        $stats[$id1]['sets_favor'] += $resultado['sets_ganados_1'];
        $stats[$id1]['sets_contra'] += $resultado['sets_ganados_2'];
        $stats[$id2]['sets_favor'] += $resultado['sets_ganados_2'];
        $stats[$id2]['sets_contra'] += $resultado['sets_ganados_1'];

        $stats[$id1]['games_favor'] += $resultado['games_ganados_1'];
        $stats[$id1]['games_contra'] += $resultado['games_ganados_2'];
        $stats[$id2]['games_favor'] += $resultado['games_ganados_2'];
        $stats[$id2]['games_contra'] += $resultado['games_ganados_1'];

        $idGanador = ($resultado['ganador'] === 1) ? $id1 : $id2;
        $idPerdedor = ($idGanador === $id1) ? $id2 : $id1;
        $stats[$idGanador]['pg']++;
        $stats[$idGanador]['pts'] += 3;
        $stats[$idPerdedor]['pp']++;

    }

    $ordenOriginal = array_flip(array_keys($stats));

    $listado = array_values($stats);

    // Criterios 1-4: puntos, diferencia de sets, diferencia de games y
    // games ganados. Devuelve el vector de comparación (mayor = mejor).
    $clave = function ($f) {
        return [
            $f['pts'],
            $f['sets_favor'] - $f['sets_contra'],
            $f['games_favor'] - $f['games_contra'],
            $f['games_favor'],
        ];
    };

    usort($listado, function ($a, $b) use ($clave, $ordenOriginal) {
        $cmp = $clave($b) <=> $clave($a);
        return $cmp !== 0 ? $cmp : $ordenOriginal[$a['id_entrante']] <=> $ordenOriginal[$b['id_entrante']];
    });

    // Criterio 5: enfrentamiento directo entre los empatados en 1-4.
    // Se cuentan solo los partidos válidos jugados entre ellos. Si sigue
    // habiendo empate (ej. 3 empatados en ciclo) queda marcado 'empate'.
    $resultadoFinal = [];
    $n = count($listado);
    $i = 0;

    while ($i < $n) {

        $j = $i + 1;
        while ($j < $n && $clave($listado[$j]) === $clave($listado[$i])) {
            $j++;
        }

        $bloque = array_slice($listado, $i, $j - $i);

        if (count($bloque) > 1) {

            $ids = array_column($bloque, 'id_entrante');
            $victoriasDirectas = array_fill_keys($ids, 0);

            foreach ($partidos as $partido) {

                if ($partido['estado'] !== 'Jugado') {
                    continue;
                }

                $id1 = (int) $partido['id_entrante_1'];
                $id2 = (int) $partido['id_entrante_2'];

                if (!in_array($id1, $ids, true) || !in_array($id2, $ids, true)) {
                    continue;
                }

                $r = resultadoDePartido($partido);

                if ($r['valido']) {
                    $victoriasDirectas[$r['ganador'] === 1 ? $id1 : $id2]++;
                }

            }

            usort($bloque, function ($a, $b) use ($victoriasDirectas, $ordenOriginal) {
                $cmp = $victoriasDirectas[$b['id_entrante']] <=> $victoriasDirectas[$a['id_entrante']];
                return $cmp !== 0 ? $cmp : $ordenOriginal[$a['id_entrante']] <=> $ordenOriginal[$b['id_entrante']];
            });

            foreach ($bloque as $k => $fila) {
                $fila['_directo'] = $victoriasDirectas[$fila['id_entrante']];
                $bloque[$k] = $fila;
            }

        }

        foreach ($bloque as $k => $fila) {

            $empate = false;

            if (count($bloque) > 1) {
                foreach ($bloque as $k2 => $otra) {
                    if ($k2 !== $k && $otra['_directo'] === $fila['_directo']) {
                        $empate = true;
                    }
                }
            }

            unset($fila['_directo']);
            $fila['empate'] = $empate;
            $resultadoFinal[] = $fila;

        }

        $i = $j;

    }

    foreach ($resultadoFinal as $k => $fila) {
        $resultadoFinal[$k]['posicion'] = $k + 1;
    }

    return $resultadoFinal;

}
