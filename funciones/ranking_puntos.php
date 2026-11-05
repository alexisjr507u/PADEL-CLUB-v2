<?php

require_once __DIR__ . '/standings.php';

/* =========================================================
   RANKING POR CATEGORÍAS: puntos, ascensos y posiciones

   Puntos de un torneo (se otorgan UNA vez, ver liquidarPuntosTorneo):
   - Fase de grupos, por partido: ganado 2, perdido 1, perdido por W.O. 0.
   - Cruces: UN solo valor según la instancia alcanzada; NO se suman entre
     rondas (campeón 1000, finalista 800, semifinalista 600, cuartos 400,
     octavos 200). Quien no llega a los cruces solo suma los de grupos.
   - Torneo de parejas: cada integrante recibe los puntos completos de su pareja.

   Ranking de una categoría: suma de los puntos de los torneos de ESA categoría
   desde el último ascenso o cambio de categoría del jugador (los puntos de la
   categoría anterior no se trasladan). Con 5000 puntos asciende y empieza en 0.

   No se guarda ni se inventa nada que no salga de resultados reales. Los
   partidos se leen con resultadoDePartido(), que entiende los W.O.
   ========================================================= */

const RANKING_PUNTOS_GRUPO_GANADO = 2;
const RANKING_PUNTOS_GRUPO_PERDIDO = 1;
const RANKING_PUNTOS_GRUPO_WO_PERDIDO = 0;

// Instancia final alcanzada => puntos (no se acumulan).
const RANKING_PUNTOS_CRUCES = [
    'Campeon' => 1000,
    'Final' => 800,
    'Semifinal' => 600,
    'Cuartos' => 400,
    'Octavos' => 200,
];

const RANKING_PUNTOS_ASCENSO = 5000;

const RANKING_NOMBRE_INSTANCIA = [
    'Grupos' => 'Fase de grupos',
    'Octavos' => 'Octavos de final',
    'Cuartos' => 'Cuartos de final',
    'Semifinal' => 'Semifinalista',
    'Final' => 'Finalista',
    'Campeon' => 'Campeón',
];


/** Estadísticas en blanco de un participante/pareja dentro de un torneo. */
function rankingStatsVacias() {

    return [
        'puntos_grupos' => 0,
        'puntos_cruces' => 0,
        'instancia' => 'Grupos',
        'pg' => 0,
        'pp' => 0,
        'wo_perdidos' => 0,
        'sets_favor' => 0,
        'sets_contra' => 0,
        'games_favor' => 0,
        'games_contra' => 0,
    ];

}


/**
 * Calcula, SIN escribir nada, los puntos de cada jugador de un torneo.
 *
 * Devuelve ['ok' => true, 'torneo' => [...], 'jugadores' => [id_usuario => [...]], 'hay_cruces' => bool]
 * o ['ok' => false, 'error' => código, ...] con alguno de:
 *   no_existe, ya_liquidado, sin_partidos, partidos_pendientes, resultado_invalido, falta_final
 */
function calcularPuntosTorneo($conexion, $idTorneo) {

    $stmt = mysqli_prepare($conexion, "SELECT id_torneo, nombre, tipo, id_categoria, estado FROM torneos WHERE id_torneo = ?");
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$torneo) {
        return ['ok' => false, 'error' => 'no_existe'];
    }

    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS n, MAX(fecha_liquidacion) AS fecha FROM ranking_puntos WHERE id_torneo = ?");
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $liquidado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ((int) $liquidado['n'] > 0) {
        return ['ok' => false, 'error' => 'ya_liquidado', 'torneo' => $torneo, 'fecha' => $liquidado['fecha']];
    }

    $esIndividual = ($torneo['tipo'] === 'Individual');

    // Participantes/parejas del torneo (a través de sus grupos) y las personas que los integran.
    if ($esIndividual) {
        $sqlEntrantes = "SELECT gi.id_grupo_inscripcion AS id_entrante, insc.id_usuario AS u1, NULL AS u2
                         FROM grupo_inscripcion gi
                         INNER JOIN grupo g ON g.id_grupo = gi.id_grupo
                         INNER JOIN inscripcion insc ON insc.id_inscripcion = gi.id_inscripcion
                         WHERE g.id_torneo = ?";
        $sqlPartidos = "SELECT p.id_partido, p.fase, p.id_entrante_1, p.id_entrante_2,
                               p.set1_games_1, p.set1_games_2, p.set2_games_1, p.set2_games_2,
                               p.set3_games_1, p.set3_games_2, p.id_ganador, p.resultado_tipo, p.estado
                        FROM partido_individual p
                        INNER JOIN grupo_inscripcion e ON e.id_grupo_inscripcion = p.id_entrante_1
                        INNER JOIN grupo g ON g.id_grupo = e.id_grupo
                        WHERE g.id_torneo = ?
                        ORDER BY p.id_partido ASC";
    } else {
        $sqlEntrantes = "SELECT gp.id_grupo_pareja AS id_entrante, i1.id_usuario AS u1, i2.id_usuario AS u2
                         FROM grupo_pareja gp
                         INNER JOIN grupo g ON g.id_grupo = gp.id_grupo
                         INNER JOIN pareja pa ON pa.id_pareja = gp.id_pareja
                         INNER JOIN inscripcion i1 ON i1.id_inscripcion = pa.id_inscripcion_1
                         INNER JOIN inscripcion i2 ON i2.id_inscripcion = pa.id_inscripcion_2
                         WHERE g.id_torneo = ?";
        $sqlPartidos = "SELECT p.id_partido, p.fase, p.id_entrante_1, p.id_entrante_2,
                               p.set1_games_1, p.set1_games_2, p.set2_games_1, p.set2_games_2,
                               p.set3_games_1, p.set3_games_2, p.id_ganador, p.resultado_tipo, p.estado
                        FROM partido_pareja p
                        INNER JOIN grupo_pareja e ON e.id_grupo_pareja = p.id_entrante_1
                        INNER JOIN grupo g ON g.id_grupo = e.id_grupo
                        WHERE g.id_torneo = ?
                        ORDER BY p.id_partido ASC";
    }

    $stmt = mysqli_prepare($conexion, $sqlEntrantes);
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $entrantes = [];

    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $fila) {
        $usuarios = [(int) $fila['u1']];
        if ($fila['u2'] !== null) {
            $usuarios[] = (int) $fila['u2'];
        }
        $entrantes[(int) $fila['id_entrante']] = rankingStatsVacias() + ['usuarios' => $usuarios];
    }

    $stmt = mysqli_prepare($conexion, $sqlPartidos);
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $partidos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    if (empty($partidos) || empty($entrantes)) {
        return ['ok' => false, 'error' => 'sin_partidos', 'torneo' => $torneo];
    }

    $pendientes = 0;
    $hayCruces = false;
    $finalJugada = false;

    foreach ($partidos as $p) {
        if ($p['fase'] !== 'Grupos') {
            $hayCruces = true;
        }
        if ($p['estado'] !== 'Jugado') {
            $pendientes++;
        }
    }

    if ($pendientes > 0) {
        return ['ok' => false, 'error' => 'partidos_pendientes', 'pendientes' => $pendientes, 'torneo' => $torneo];
    }

    foreach ($partidos as $p) {

        $r = resultadoDePartido($p);
        $id1 = (int) $p['id_entrante_1'];
        $id2 = (int) $p['id_entrante_2'];

        if (!$r['valido'] || !isset($entrantes[$id1]) || !isset($entrantes[$id2])) {
            return ['ok' => false, 'error' => 'resultado_invalido', 'id_partido' => (int) $p['id_partido'], 'torneo' => $torneo];
        }

        $esWo = !empty($r['wo']);
        $idGanador = ($r['ganador'] === 1) ? $id1 : $id2;
        $idPerdedor = ($idGanador === $id1) ? $id2 : $id1;

        // Estadísticas (todos los partidos del torneo; un W.O. no aporta sets ni games).
        $entrantes[$id1]['sets_favor'] += $r['sets_ganados_1'];
        $entrantes[$id1]['sets_contra'] += $r['sets_ganados_2'];
        $entrantes[$id1]['games_favor'] += $r['games_ganados_1'];
        $entrantes[$id1]['games_contra'] += $r['games_ganados_2'];
        $entrantes[$id2]['sets_favor'] += $r['sets_ganados_2'];
        $entrantes[$id2]['sets_contra'] += $r['sets_ganados_1'];
        $entrantes[$id2]['games_favor'] += $r['games_ganados_2'];
        $entrantes[$id2]['games_contra'] += $r['games_ganados_1'];

        $entrantes[$idGanador]['pg']++;
        $entrantes[$idPerdedor]['pp']++;

        if ($esWo) {
            $entrantes[$idPerdedor]['wo_perdidos']++;
        }

        if ($p['fase'] === 'Grupos') {

            $entrantes[$idGanador]['puntos_grupos'] += RANKING_PUNTOS_GRUPO_GANADO;
            $entrantes[$idPerdedor]['puntos_grupos'] += $esWo ? RANKING_PUNTOS_GRUPO_WO_PERDIDO : RANKING_PUNTOS_GRUPO_PERDIDO;

        } else {

            // Cruces: queda UNA sola instancia por participante (la última que pisó), no se suman rondas.
            if ($p['fase'] === 'Final') {
                $finalJugada = true;
                $entrantes[$idGanador]['instancia'] = 'Campeon';
                $entrantes[$idPerdedor]['instancia'] = 'Final';
            } else {
                $entrantes[$idPerdedor]['instancia'] = $p['fase'];
            }

        }

    }

    // Con cruces cargados, el torneo tiene que estar terminado (la final jugada).
    if ($hayCruces && !$finalJugada) {
        return ['ok' => false, 'error' => 'falta_final', 'torneo' => $torneo];
    }

    $jugadores = [];

    foreach ($entrantes as $e) {

        $e['puntos_cruces'] = RANKING_PUNTOS_CRUCES[$e['instancia']] ?? 0;

        foreach ($e['usuarios'] as $idUsuario) {
            $jugadores[$idUsuario] = $e;
        }

    }

    ksort($jugadores);

    // Nombres para mostrar.
    if (!empty($jugadores)) {

        $ids = array_keys($jugadores);
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = mysqli_prepare($conexion, "SELECT id_usuario, nombre, apellido FROM usuario WHERE id_usuario IN ($marcas)");
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($ids)), ...$ids);
        mysqli_stmt_execute($stmt);

        foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $u) {
            $jugadores[(int) $u['id_usuario']]['nombre'] = trim($u['nombre'] . ' ' . $u['apellido']);
        }

    }

    return ['ok' => true, 'torneo' => $torneo, 'jugadores' => $jugadores, 'hay_cruces' => $hayCruces];

}


/** Temporada vigente (la única abierta), o null. */
function temporadaAbierta($conexion) {

    $fila = mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT id_temporada, nombre, fecha_inicio FROM temporada WHERE estado = 'Abierta' LIMIT 1"
    ));

    return $fila ?: null;

}


/** Puntos del jugador en su categoría actual, desde su último ascenso/cambio de categoría (temporada vigente). */
function rankingPuntosDelCiclo($conexion, $idUsuario, $idCategoria) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT COALESCE(SUM(rp.puntos_grupos + rp.puntos_cruces), 0) AS pts
         FROM ranking_puntos rp
         WHERE rp.id_usuario = ? AND rp.id_categoria = ?
           AND rp.id_temporada = (SELECT id_temporada FROM temporada WHERE estado = 'Abierta')
           AND rp.id_ranking_puntos > COALESCE((
                SELECT MAX(h.ultimo_id_ranking_puntos) FROM categoria_historial h
                WHERE h.id_usuario = ? AND h.id_categoria_nueva = ?
           ), 0)"
    );
    mysqli_stmt_bind_param($stmt, "iiii", $idUsuario, $idCategoria, $idUsuario, $idCategoria);
    mysqli_stmt_execute($stmt);

    return (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['pts'];

}


/**
 * Otorga los puntos de un torneo (una sola vez) y aplica los ascensos que correspondan.
 * Todo en UNA transacción: o se guarda todo o nada. Quien llama valida sesión/rol/CSRF.
 *
 * - UNIQUE (id_torneo, id_usuario) en ranking_puntos: el mismo torneo no puede puntuar dos veces.
 * - Cada fila de puntos puede disparar a lo sumo un ascenso (UNIQUE en categoria_historial).
 * - El jugador se bloquea (FOR UPDATE) antes de decidir el ascenso.
 *
 * @return array ['ok' => true, 'jugadores' => n, 'ascensos' => [...]] o ['ok' => false, 'error' => código]
 */
function liquidarPuntosTorneo($conexion, $idTorneo, $idLiquidador, $esAdmin) {

    mysqli_begin_transaction($conexion);

    try {

        // Bloquea el torneo: dos liquidaciones simultáneas se ejecutan una detrás de la otra.
        $stmt = mysqli_prepare(
            $conexion,
            "SELECT t.id_torneo, t.id_categoria, c.id_usuario AS id_organizador
             FROM torneos t
             INNER JOIN club c ON c.id_club = t.id_club
             WHERE t.id_torneo = ?
             FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, "i", $idTorneo);
        mysqli_stmt_execute($stmt);
        $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$torneo) {
            throw new Exception('no_existe');
        }

        if (!$esAdmin && (int) $torneo['id_organizador'] !== (int) $idLiquidador) {
            throw new Exception('sin_permiso');
        }

        $calculo = calcularPuntosTorneo($conexion, $idTorneo);

        if (!$calculo['ok']) {
            throw new Exception($calculo['error']);
        }

        $idCategoriaTorneo = (int) $torneo['id_categoria'];
        $categoriaMaxima = (int) mysqli_fetch_assoc(mysqli_query($conexion, "SELECT MAX(id_categoria) AS m FROM categoria"))['m'];

        $temporada = temporadaAbierta($conexion);

        if (!$temporada) {
            throw new Exception('error_bd');
        }

        $idTemporada = (int) $temporada['id_temporada'];

        $stmtInsert = mysqli_prepare(
            $conexion,
            "INSERT INTO ranking_puntos
                (id_torneo, id_usuario, id_categoria, instancia, puntos_grupos, puntos_cruces,
                 partidos_ganados, partidos_perdidos, partidos_wo_perdidos,
                 sets_favor, sets_contra, games_favor, games_contra, id_liquidador, id_temporada)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmtUsuario = mysqli_prepare($conexion, "SELECT id_categoria FROM usuario WHERE id_usuario = ? FOR UPDATE");

        $ascensos = [];

        foreach ($calculo['jugadores'] as $idUsuario => $j) {

            $idUsuario = (int) $idUsuario;

            mysqli_stmt_bind_param($stmtUsuario, "i", $idUsuario);
            mysqli_stmt_execute($stmtUsuario);
            $usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUsuario));

            if (!$usuario) {
                throw new Exception('error_bd');
            }

            mysqli_stmt_bind_param(
                $stmtInsert,
                "iiisiiiiiiiiiii",
                $idTorneo, $idUsuario, $idCategoriaTorneo, $j['instancia'],
                $j['puntos_grupos'], $j['puntos_cruces'],
                $j['pg'], $j['pp'], $j['wo_perdidos'],
                $j['sets_favor'], $j['sets_contra'], $j['games_favor'], $j['games_contra'],
                $idLiquidador, $idTemporada
            );
            mysqli_stmt_execute($stmtInsert);
            $idRankingPuntos = (int) mysqli_insert_id($conexion);

            // Solo puntúa en el ciclo actual si el jugador sigue en la categoría del torneo.
            if ($usuario['id_categoria'] === null || (int) $usuario['id_categoria'] !== $idCategoriaTorneo) {
                continue;
            }

            $puntosCiclo = rankingPuntosDelCiclo($conexion, $idUsuario, $idCategoriaTorneo);

            if ($puntosCiclo >= RANKING_PUNTOS_ASCENSO && $idCategoriaTorneo < $categoriaMaxima) {

                $nueva = $idCategoriaTorneo + 1;
                $motivo = 'Alcanzó ' . $puntosCiclo . ' puntos (límite ' . RANKING_PUNTOS_ASCENSO . ')';

                $stmtHistorial = mysqli_prepare(
                    $conexion,
                    "INSERT INTO categoria_historial
                        (id_usuario, id_categoria_anterior, id_categoria_nueva, tipo, motivo,
                         id_torneo, id_ranking_puntos, puntos_alcanzados, ultimo_id_ranking_puntos, id_temporada)
                     VALUES (?, ?, ?, 'Ascenso por puntos', ?, ?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param(
                    $stmtHistorial,
                    "iiisiiiii",
                    $idUsuario, $idCategoriaTorneo, $nueva, $motivo,
                    $idTorneo, $idRankingPuntos, $puntosCiclo, $idRankingPuntos, $idTemporada
                );
                mysqli_stmt_execute($stmtHistorial);

                $stmtCambio = mysqli_prepare($conexion, "UPDATE usuario SET id_categoria = ? WHERE id_usuario = ? AND id_categoria = ?");
                mysqli_stmt_bind_param($stmtCambio, "iii", $nueva, $idUsuario, $idCategoriaTorneo);
                mysqli_stmt_execute($stmtCambio);

                if (mysqli_stmt_affected_rows($stmtCambio) !== 1) {
                    throw new Exception('error_bd');
                }

                $ascensos[] = [
                    'id_usuario' => $idUsuario,
                    'nombre' => $j['nombre'] ?? '',
                    'de' => $idCategoriaTorneo,
                    'a' => $nueva,
                    'puntos' => $puntosCiclo,
                ];

            }

        }

        mysqli_commit($conexion);

        return ['ok' => true, 'jugadores' => count($calculo['jugadores']), 'ascensos' => $ascensos];

    } catch (Throwable $e) {

        mysqli_rollback($conexion);

        $codigo = $e->getMessage();
        $conocidos = ['no_existe', 'sin_permiso', 'ya_liquidado', 'sin_partidos', 'partidos_pendientes', 'resultado_invalido', 'falta_final'];

        if ($e instanceof mysqli_sql_exception && (int) $e->getCode() === 1062) {
            return ['ok' => false, 'error' => 'ya_liquidado'];
        }

        if (!in_array($codigo, $conocidos, true)) {
            error_log("Error al liquidar los puntos del torneo $idTorneo: " . $codigo);
            $codigo = 'error_bd';
        }

        return ['ok' => false, 'error' => $codigo];

    }

}


/** Datos de la liquidación de un torneo (null si todavía no se otorgaron puntos). */
function estadoLiquidacionTorneo($conexion, $idTorneo) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT COUNT(*) AS jugadores, MAX(rp.fecha_liquidacion) AS fecha,
                MAX(CONCAT(u.nombre, ' ', u.apellido)) AS liquidador
         FROM ranking_puntos rp
         INNER JOIN usuario u ON u.id_usuario = rp.id_liquidador
         WHERE rp.id_torneo = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ((int) $fila['jugadores'] === 0) {
        return null;
    }

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT u.nombre, u.apellido, ca.nombre AS de, cn.nombre AS a, h.puntos_alcanzados
         FROM categoria_historial h
         INNER JOIN usuario u ON u.id_usuario = h.id_usuario
         INNER JOIN categoria ca ON ca.id_categoria = h.id_categoria_anterior
         INNER JOIN categoria cn ON cn.id_categoria = h.id_categoria_nueva
         WHERE h.id_torneo = ? AND h.tipo = 'Ascenso por puntos'
         ORDER BY h.id_historial ASC"
    );
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);

    $fila['ascensos'] = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    return $fila;

}


/**
 * Enfrentamientos directos entre dos jugadores en torneos ya liquidados:
 * devuelve [victorias de A, victorias de B]. En parejas cuentan los partidos en que
 * estuvieron en lados opuestos.
 */
function rankingEnfrentamientoDirecto($conexion, $idA, $idB) {

    $victoriasA = 0;
    $victoriasB = 0;

    $consultas = [
        // Individual: cada lado es una inscripción.
        "SELECT i1.id_usuario AS lado1, i2.id_usuario AS lado2, p.id_ganador, e1.id_grupo_inscripcion AS ent1
         FROM partido_individual p
         INNER JOIN grupo_inscripcion e1 ON e1.id_grupo_inscripcion = p.id_entrante_1
         INNER JOIN grupo_inscripcion e2 ON e2.id_grupo_inscripcion = p.id_entrante_2
         INNER JOIN inscripcion i1 ON i1.id_inscripcion = e1.id_inscripcion
         INNER JOIN inscripcion i2 ON i2.id_inscripcion = e2.id_inscripcion
         WHERE p.estado = 'Jugado' AND p.id_ganador IS NOT NULL
           AND ((i1.id_usuario = ? AND i2.id_usuario = ?) OR (i1.id_usuario = ? AND i2.id_usuario = ?))
           AND EXISTS (SELECT 1 FROM ranking_puntos r WHERE r.id_temporada = (SELECT id_temporada FROM temporada WHERE estado = 'Abierta') AND r.id_usuario = ? AND r.id_torneo = i1.id_torneo)
           AND EXISTS (SELECT 1 FROM ranking_puntos r WHERE r.id_temporada = (SELECT id_temporada FROM temporada WHERE estado = 'Abierta') AND r.id_usuario = ? AND r.id_torneo = i1.id_torneo)",
        // Parejas: cada lado es una pareja con dos integrantes.
        "SELECT p.id_ganador, p.id_entrante_1 AS ent1,
                (pa1.id_inscripcion_1 IN (SELECT id_inscripcion FROM inscripcion WHERE id_usuario = ?)
                 OR pa1.id_inscripcion_2 IN (SELECT id_inscripcion FROM inscripcion WHERE id_usuario = ?)) AS a_en_lado1,
                pa1.id_torneo AS id_torneo
         FROM partido_pareja p
         INNER JOIN grupo_pareja e1 ON e1.id_grupo_pareja = p.id_entrante_1
         INNER JOIN grupo_pareja e2 ON e2.id_grupo_pareja = p.id_entrante_2
         INNER JOIN pareja pa1 ON pa1.id_pareja = e1.id_pareja
         INNER JOIN pareja pa2 ON pa2.id_pareja = e2.id_pareja
         INNER JOIN inscripcion a1 ON a1.id_inscripcion IN (pa1.id_inscripcion_1, pa1.id_inscripcion_2)
         INNER JOIN inscripcion b2 ON b2.id_inscripcion IN (pa2.id_inscripcion_1, pa2.id_inscripcion_2)
         WHERE p.estado = 'Jugado' AND p.id_ganador IS NOT NULL
           AND a1.id_usuario = ? AND b2.id_usuario = ?
           AND EXISTS (SELECT 1 FROM ranking_puntos r WHERE r.id_temporada = (SELECT id_temporada FROM temporada WHERE estado = 'Abierta') AND r.id_usuario = ? AND r.id_torneo = pa1.id_torneo)
           AND EXISTS (SELECT 1 FROM ranking_puntos r WHERE r.id_temporada = (SELECT id_temporada FROM temporada WHERE estado = 'Abierta') AND r.id_usuario = ? AND r.id_torneo = pa1.id_torneo)",
    ];

    // Individual: A y B en cualquier orden.
    $stmt = mysqli_prepare($conexion, $consultas[0]);
    mysqli_stmt_bind_param($stmt, "iiiiii", $idA, $idB, $idB, $idA, $idA, $idB);
    mysqli_stmt_execute($stmt);

    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $f) {
        // id_ganador es el id del lado 1 o del lado 2 (grupo_inscripcion).
        $ganoLado1 = ((int) $f['id_ganador'] === (int) $f['ent1']);
        $ganador = $ganoLado1 ? (int) $f['lado1'] : (int) $f['lado2'];
        if ($ganador === $idA) { $victoriasA++; } else { $victoriasB++; }
    }

    // Parejas: se consulta con A en un lado y B en el otro, y luego al revés.
    foreach ([[$idA, $idB], [$idB, $idA]] as [$x, $y]) {

        $stmt = mysqli_prepare($conexion, $consultas[1]);
        mysqli_stmt_bind_param($stmt, "iiiiii", $x, $x, $x, $y, $x, $y);
        mysqli_stmt_execute($stmt);

        foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $f) {
            // a_en_lado1 = X (el primero) está en el lado 1.
            $ganoLado1 = ((int) $f['id_ganador'] === (int) $f['ent1']);
            $ganoX = ((int) $f['a_en_lado1'] === 1) ? $ganoLado1 : !$ganoLado1;
            if ($ganoX === ($x === $idA)) { $victoriasA++; } else { $victoriasB++; }
        }

    }

    return [$victoriasA, $victoriasB];

}


/**
 * Ranking de una categoría: todos los jugadores que HOY están en esa categoría
 * (también los que tienen 0 puntos), de mayor a menor.
 *
 * Orden: puntos, partidos ganados, diferencia de sets, diferencia de games, y
 * enfrentamiento directo cuando el empate es entre exactamente dos jugadores.
 * Si sigue igual comparten posición (1, 2, 2, 4).
 *
 * @return array lista de filas con 'posicion', 'empate' y las estadísticas.
 */
function rankingDeCategoria($conexion, $idCategoria) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT u.id_usuario, u.nombre, u.apellido,
                COALESCE(SUM(rp.puntos_grupos + rp.puntos_cruces), 0) AS puntos,
                COALESCE(SUM(rp.puntos_grupos), 0) AS puntos_grupos,
                COALESCE(SUM(rp.puntos_cruces), 0) AS puntos_cruces,
                COUNT(rp.id_ranking_puntos) AS torneos,
                COALESCE(SUM(rp.partidos_ganados), 0) AS pg,
                COALESCE(SUM(rp.partidos_perdidos), 0) AS pp,
                COALESCE(SUM(rp.sets_favor), 0) AS sets_favor,
                COALESCE(SUM(rp.sets_contra), 0) AS sets_contra,
                COALESCE(SUM(rp.games_favor), 0) AS games_favor,
                COALESCE(SUM(rp.games_contra), 0) AS games_contra
         FROM usuario u
         LEFT JOIN ranking_puntos rp
                ON rp.id_usuario = u.id_usuario
                AND rp.id_categoria = u.id_categoria
               AND rp.id_temporada = (SELECT id_temporada FROM temporada WHERE estado = 'Abierta')
               AND rp.id_ranking_puntos > COALESCE((
                    SELECT MAX(h.ultimo_id_ranking_puntos) FROM categoria_historial h
                    WHERE h.id_usuario = u.id_usuario AND h.id_categoria_nueva = u.id_categoria
               ), 0)
         WHERE u.id_rol = 3 AND u.id_categoria = ?
         GROUP BY u.id_usuario, u.nombre, u.apellido"
    );
    mysqli_stmt_bind_param($stmt, "i", $idCategoria);
    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    foreach ($filas as $k => $f) {
        foreach (['puntos', 'puntos_grupos', 'puntos_cruces', 'torneos', 'pg', 'pp', 'sets_favor', 'sets_contra', 'games_favor', 'games_contra', 'id_usuario'] as $campo) {
            $filas[$k][$campo] = (int) $f[$campo];
        }
        $filas[$k]['nombre_completo'] = trim($f['nombre'] . ' ' . $f['apellido']);
        $filas[$k]['dif_sets'] = $filas[$k]['sets_favor'] - $filas[$k]['sets_contra'];
        $filas[$k]['dif_games'] = $filas[$k]['games_favor'] - $filas[$k]['games_contra'];
    }

    $clave = function ($f) {
        return [$f['puntos'], $f['pg'], $f['dif_sets'], $f['dif_games']];
    };

    usort($filas, function ($a, $b) use ($clave) {
        $cmp = $clave($b) <=> $clave($a);
        return $cmp !== 0 ? $cmp : strcasecmp($a['nombre_completo'], $b['nombre_completo']);
    });

    // Enfrentamiento directo: solo cuando el empate (en los 4 criterios) es entre dos jugadores.
    $n = count($filas);
    $i = 0;

    while ($i < $n) {

        $j = $i + 1;
        while ($j < $n && $clave($filas[$j]) === $clave($filas[$i])) {
            $j++;
        }

        if ($j - $i === 2) {

            [$ganaA, $ganaB] = rankingEnfrentamientoDirecto($conexion, $filas[$i]['id_usuario'], $filas[$i + 1]['id_usuario']);

            if ($ganaB > $ganaA) {
                $tmp = $filas[$i];
                $filas[$i] = $filas[$i + 1];
                $filas[$i + 1] = $tmp;
            }

            $filas[$i]['_directo'] = $filas[$i + 1]['_directo'] = [$ganaA, $ganaB];

        }

        $i = $j;

    }

    // Posiciones estilo competencia (1, 2, 2, 4): comparten posición los que siguen empatados.
    $posicion = 0;

    foreach ($filas as $k => $f) {

        $anterior = $k > 0 ? $filas[$k - 1] : null;
        $empataConAnterior = $anterior !== null
            && $clave($anterior) === $clave($f)
            && !(isset($f['_directo']) && $f['_directo'][0] !== $f['_directo'][1]);

        if (!$empataConAnterior) {
            $posicion = $k + 1;
        }

        $filas[$k]['posicion'] = $posicion;
        $filas[$k]['progreso'] = min(100, (int) floor($f['puntos'] / RANKING_PUNTOS_ASCENSO * 100));

    }

    foreach ($filas as $k => $f) {
        $comparteSiguiente = isset($filas[$k + 1]) && $filas[$k + 1]['posicion'] === $f['posicion'];
        $comparteAnterior = $k > 0 && $filas[$k - 1]['posicion'] === $f['posicion'];
        $filas[$k]['empate'] = $comparteSiguiente || $comparteAnterior;
    }

    return $filas;

}


/** Posición y puntos de un jugador en SU categoría actual (null si no tiene categoría confirmada). */
function rankingDeUsuario($conexion, $idUsuario) {

    $stmt = mysqli_prepare($conexion, "SELECT id_categoria FROM usuario WHERE id_usuario = ? AND id_rol = 3");
    mysqli_stmt_bind_param($stmt, "i", $idUsuario);
    mysqli_stmt_execute($stmt);
    $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$u || $u['id_categoria'] === null) {
        return null;
    }

    $ranking = rankingDeCategoria($conexion, (int) $u['id_categoria']);

    foreach ($ranking as $f) {
        if ($f['id_usuario'] === (int) $idUsuario) {
            return $f + ['id_categoria' => (int) $u['id_categoria'], 'total_categoria' => count($ranking)];
        }
    }

    return null;

}
