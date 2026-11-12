<?php

require_once __DIR__ . '/standings.php';
require_once __DIR__ . '/ranking_puntos.php';

/* =========================================================
   RANKING FINAL DEL TORNEO
   Una posición general para TODOS los participantes/parejas del
   torneo. Llegar más lejos siempre pesa más que el rendimiento en
   rondas anteriores:

     1. Campeón / 2. Subcampeón (solo si la final tiene resultado válido)
     3-4. Semifinalistas eliminados
     Eliminados en grupos: primero por puesto dentro de su grupo
     (los 3.º antes que los 4.º, etc.) y, dentro del mismo puesto,
     por rendimiento PROMEDIO por partido (puntos, dif. de sets,
     dif. de games y games ganados), para no perjudicar a grupos con
     distinta cantidad de partidos.

   Solo cuentan partidos 'Jugado' con sets válidos. Es una función de
   lectura: el ranking se recalcula en cada visita, así que cualquier
   alta o cambio de resultado lo actualiza sin pasos adicionales.
   ========================================================= */


function nombreDeMiembro(array $m, $esIndividual) {

    if ($esIndividual) {
        return trim($m['nombre'] . ' ' . $m['apellido']);
    }

    return trim($m['nombre1'] . ' ' . $m['apellido1']) . ' / ' . trim($m['nombre2'] . ' ' . $m['apellido2']);

}


/** Vector de comparación por partido jugado (mayor = mejor). */
function promedioRendimiento(array $s) {

    $pj = max(1, (int) $s['pj']);

    return [
        $s['pts'] / $pj,
        ($s['sets_favor'] - $s['sets_contra']) / $pj,
        ($s['games_favor'] - $s['games_contra']) / $pj,
        $s['games_favor'] / $pj,
    ];

}


function calcularRankingTorneo($conexion, $id_torneo, $esIndividual) {

    $stmtGrupos = mysqli_prepare(
        $conexion,
        "SELECT id_grupo, nombre FROM grupo WHERE id_torneo = ? ORDER BY orden ASC, id_grupo ASC"
    );
    mysqli_stmt_bind_param($stmtGrupos, "i", $id_torneo);
    mysqli_stmt_execute($stmtGrupos);
    $grupos = mysqli_fetch_all(mysqli_stmt_get_result($stmtGrupos), MYSQLI_ASSOC);

    $entrantes = [];
    $gruposCompletos = [];
    $topPorGrupo = [];

    foreach ($grupos as $g) {

        $datos = obtenerMiembrosYPartidosDeGrupo($conexion, (int) $g['id_grupo'], $esIndividual);
        $standings = calcularStandings($datos['miembros'], $datos['partidos']);

        $nombres = [];
        foreach ($datos['miembros'] as $m) {
            $nombres[(int) $m['id_entrante']] = nombreDeMiembro($m, $esIndividual)
                . (!empty($m['descalificado']) ? ' (descalificado)' : '');
        }

        $completo = count($datos['miembros']) >= 2 && !empty($datos['partidos']);
        foreach ($datos['partidos'] as $p) {
            if ($p['estado'] !== 'Jugado') {
                $completo = false;
            }
        }
        $gruposCompletos[] = $completo;
        $topPorGrupo[] = clasificadosDeTabla($standings);

        foreach ($standings as $fila) {

            $id = (int) $fila['id_entrante'];

            $entrantes[$id] = [
                'id_entrante' => $id,
                'nombre' => $nombres[$id] ?? '—',
                'grupo' => $g['nombre'],
                'pos_grupo' => (int) $fila['posicion'],
                'grupo_stats' => $fila,
                'total' => $fila,
            ];

        }

    }

    // ---- Fase final (solo existe con exactamente 2 grupos) ----

    $semis = [];
    $final = null;
    $rondas = ['Octavos' => [], 'Cuartos' => [], 'Semifinal' => [], 'Final' => []];

    if (!empty($entrantes)) {

        $tablaPartidos = $esIndividual ? 'partido_individual' : 'partido_pareja';
        $tablaEntrantes = $esIndividual ? 'grupo_inscripcion' : 'grupo_pareja';
        $colEntrante = $esIndividual ? 'id_grupo_inscripcion' : 'id_grupo_pareja';

        $stmtBracket = mysqli_prepare(
            $conexion,
            "SELECT p.fase, p.orden, p.id_entrante_1, p.id_entrante_2, p.estado,
                    p.set1_games_1, p.set1_games_2, p.set2_games_1, p.set2_games_2,
                    p.set3_games_1, p.set3_games_2, p.id_ganador, p.resultado_tipo
             FROM $tablaPartidos p
             INNER JOIN $tablaEntrantes e ON e.$colEntrante = p.id_entrante_1
             INNER JOIN grupo g ON g.id_grupo = e.id_grupo
             WHERE g.id_torneo = ? AND p.fase IN ('Octavos', 'Cuartos', 'Semifinal', 'Final')
             ORDER BY FIELD(p.fase, 'Octavos', 'Cuartos', 'Semifinal', 'Final') ASC, p.orden ASC"
        );
        mysqli_stmt_bind_param($stmtBracket, "i", $id_torneo);
        mysqli_stmt_execute($stmtBracket);

        foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmtBracket), MYSQLI_ASSOC) as $p) {

            $id1 = (int) $p['id_entrante_1'];
            $id2 = (int) $p['id_entrante_2'];

            if (!isset($entrantes[$id1]) || !isset($entrantes[$id2])) {
                continue;
            }

            $r = ['valido' => false];

            if ($p['estado'] === 'Jugado') {
                $r = resultadoDePartido($p);
            }

            $partidoFinal = [
                'id1' => $id1,
                'id2' => $id2,
                'valido' => $r['valido'],
                'ganador' => $r['valido'] ? ($r['ganador'] === 1 ? $id1 : $id2) : null,
                'perdedor' => $r['valido'] ? ($r['ganador'] === 1 ? $id2 : $id1) : null,
            ];

            // El rendimiento total del torneo incluye las rondas finales.
            if ($r['valido']) {

                $campos = [
                    $id1 => ['sets_ganados_1', 'sets_ganados_2', 'games_ganados_1', 'games_ganados_2'],
                    $id2 => ['sets_ganados_2', 'sets_ganados_1', 'games_ganados_2', 'games_ganados_1'],
                ];

                foreach ($campos as $id => $c) {
                    $entrantes[$id]['total']['pj']++;
                    $entrantes[$id]['total']['sets_favor'] += $r[$c[0]];
                    $entrantes[$id]['total']['sets_contra'] += $r[$c[1]];
                    $entrantes[$id]['total']['games_favor'] += $r[$c[2]];
                    $entrantes[$id]['total']['games_contra'] += $r[$c[3]];
                }

                $entrantes[$partidoFinal['ganador']]['total']['pg']++;
                $entrantes[$partidoFinal['ganador']]['total']['pts'] += 3;
                $entrantes[$partidoFinal['perdedor']]['total']['pp']++;

            }

            $rondas[$p['fase']][] = $partidoFinal;

            if ($p['fase'] === 'Semifinal') {
                $semis[] = $partidoFinal;
            } elseif ($p['fase'] === 'Final') {
                $final = $partidoFinal;
            }

        }

    }

    // ---- Etapa alcanzada por cada uno (menor = mejor) ----
    // Llegar más lejos siempre pesa más. Quien ganó una ronda ya cuenta como
    // clasificado a la siguiente aunque esa ronda todavía no se haya generado.

    $etapas = [
        0 => 'Campeón',
        1 => 'Subcampeón',
        2 => 'Finalista',
        3 => 'Semifinalista',
        4 => 'Semifinalista eliminado',
        5 => 'Cuartos de final',
        6 => 'Eliminado en cuartos de final',
        7 => 'Octavos de final',
        8 => 'Eliminado en octavos de final',
        9 => 'Eliminado en fase de grupos',
        10 => 'Fase de grupos en curso',
    ];

    $hayCuadro = !empty($rondas['Octavos']) || !empty($rondas['Cuartos']) || !empty($rondas['Semifinal']) || !empty($rondas['Final']);

    $campeonId = null;
    $provisional = true;

    // Posición de cada entrante en una ronda: null (no juega), 'pendiente', 'gano' o 'perdio'.
    $enRonda = function (array $partidos, $id) {

        foreach ($partidos as $p) {

            if ($p['id1'] !== $id && $p['id2'] !== $id) {
                continue;
            }

            if (!$p['valido']) {
                return 'pendiente';
            }

            return $p['ganador'] === $id ? 'gano' : 'perdio';

        }

        return null;

    };

    foreach ($entrantes as $id => $e) {

        if (!$hayCuadro) {
            $tier = 10;
        } else {

            $oct = $enRonda($rondas['Octavos'], $id);
            $cua = $enRonda($rondas['Cuartos'], $id);
            $sem = $enRonda($rondas['Semifinal'], $id);
            $fin = $enRonda($rondas['Final'], $id);

            if ($fin !== null) {
                $tier = $fin === 'gano' ? 0 : ($fin === 'perdio' ? 1 : 2);
            } elseif ($sem !== null) {
                $tier = $sem === 'perdio' ? 4 : 3;
            } elseif ($cua !== null) {
                $tier = $cua === 'perdio' ? 6 : ($cua === 'gano' ? 3 : 5);
            } elseif ($oct !== null) {
                $tier = $oct === 'perdio' ? 8 : ($oct === 'gano' ? 5 : 7);
            } else {
                $tier = 9;
            }

        }

        $entrantes[$id]['tier'] = $tier;

    }
    if ($final !== null && $final['valido']) {
        $campeonId = $final['ganador'];
        $provisional = false;
    }

    // ---- Orden general ----

    $lista = array_values($entrantes);

    $clave = function ($e) {
        if ($e['tier'] === 10) {
            return promedioRendimiento($e['grupo_stats']);
        }
        if ($e['tier'] === 9) {
            return array_merge([-$e['pos_grupo']], promedioRendimiento($e['grupo_stats']));
        }
        return promedioRendimiento($e['total']);
    };

    usort($lista, function ($a, $b) use ($clave) {

        if ($a['tier'] !== $b['tier']) {
            return $a['tier'] <=> $b['tier'];
        }

        $cmp = $clave($b) <=> $clave($a);

        return $cmp !== 0 ? $cmp : $a['id_entrante'] <=> $b['id_entrante'];

    });

    // Puntos DEFINITIVOS que el torneo da en el ranking: la misma función que usa «Puntos del ranking» (no hay una segunda fórmula).
    $calculoPuntos = calcularPuntosTorneo($conexion, $id_torneo);
    $puntosPorEntrante = !empty($calculoPuntos['ok']) ? $calculoPuntos['entrantes'] : [];

    $filas = [];

    foreach ($lista as $k => $e) {

        $anterior = $lista[$k - 1] ?? null;
        $empate = $anterior !== null
            && $anterior['tier'] === $e['tier']
            && $e['tier'] >= 3
            && $clave($anterior) === $clave($e);

        $filas[] = [
            'puesto' => $k + 1,
            'id_entrante' => $e['id_entrante'],
            'nombre' => $e['nombre'],
            'grupo' => $e['grupo'],
            'pos_grupo' => $e['pos_grupo'],
            'etapa' => $etapas[$e['tier']],
            'tier' => $e['tier'],
            'pj' => $e['total']['pj'],
            'pg' => $e['total']['pg'],
            'pp' => $e['total']['pp'],
            'pts' => $e['total']['pts'],   // puntos de clasificación por partido (3 por victoria): solo para ordenar
            'puntos_ranking' => (int) ($puntosPorEntrante[$e['id_entrante']]['puntos'] ?? 0),
            'sets_favor' => $e['total']['sets_favor'],
            'sets_contra' => $e['total']['sets_contra'],
            'games_favor' => $e['total']['games_favor'],
            'games_contra' => $e['total']['games_contra'],
            'empate' => $empate,
        ];

    }

    // ---- Avisos de consistencia ----
    // Si se corrige un resultado después de generar los cruces, las
    // semifinales/final ya creadas pueden no coincidir con la tabla.

    $avisos = [];

    if (count($grupos) === 2 && !empty($semis) && !in_array(false, $gruposCompletos, true)) {

        $esperadas = [
            [$topPorGrupo[0][0] ?? 0, $topPorGrupo[1][1] ?? 0],
            [$topPorGrupo[1][0] ?? 0, $topPorGrupo[0][1] ?? 0],
        ];
        $reales = array_map(fn($s) => [$s['id1'], $s['id2']], $semis);

        if ($esperadas !== $reales) {
            $avisos[] = 'cruces_desactualizados';
        }

    }

    if ($final !== null && count($semis) === 2 && $semis[0]['valido'] && $semis[1]['valido']) {

        if ([$final['id1'], $final['id2']] !== [$semis[0]['ganador'], $semis[1]['ganador']]) {
            $avisos[] = 'final_desactualizada';
        }

    }

    return [
        'filas' => $filas,
        'provisional' => $provisional,
        'campeon' => $campeonId !== null ? $entrantes[$campeonId]['nombre'] : null,
        'hay_fase_final' => $hayCuadro,
        'avisos' => $avisos,
    ];

}
