<?php

require_once __DIR__ . '/standings.php';
require_once __DIR__ . '/actividad.php';
require_once __DIR__ . '/club_categoria.php';
require_once __DIR__ . '/temporada_club.php';

/* =========================================================
   RANKING POR CATEGORÍAS: puntos, ascensos y posiciones

   Puntos de un torneo (AUTOMÁTICOS: se suman con cada resultado cargado, ver sincronizarPuntosTorneo):
   - Fase de grupos, por partido: ganado 2, perdido 1, perdido por W.O. 0.
   - Cruces: UN solo valor según la instancia alcanzada; NO se suman entre
     rondas (campeón 1000, finalista 800, semifinalista 600, cuartos 400,
     octavos 200). Quien no llega a los cruces solo suma los de grupos.
   - Torneo de parejas: cada integrante recibe los puntos completos de su pareja.

   TODO ES POR CLUB: los puntos de un torneo cuentan SOLO en el club de ese torneo. La categoría del jugador es la de su
   membresía en ese club (usuario_club.id_categoria) y la temporada es la temporada abierta de ese club. Nunca se suma
   ni se compara nada de un club con otro.

   Ranking de una categoría de un club: suma de los puntos de los torneos de ESA categoría en ESE club, en la temporada
   abierta del club, desde el último ascenso o cambio de categoría del jugador en ese club (los puntos de la categoría
   anterior no se trasladan). Con 5000 puntos asciende (solo en ese club) y empieza en 0.

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
        'en_cruces' => false,      // figura en algún partido de cruces (jugado o pendiente)
        'perdio_cruce' => false,   // ya perdió un partido de cruces
    ];

}


/**
 * Calcula, SIN escribir nada, los puntos de cada jugador de un torneo a partir de los partidos YA JUGADOS.
 *
 * Los puntos son automáticos: cada resultado cargado suma en el acto. Un partido sin jugar no da nada:
 * llegar a octavos (o a cualquier ronda) no suma hasta que el partido se define. En los cruces, la
 * instancia (y sus puntos) la fija el partido que el jugador PIERDE, o el campeonato al ganar la final.
 *
 * Devuelve ['ok' => true, 'torneo' => [...], 'jugadores' => [id_usuario => [...]], 'hay_cruces' => bool,
 *           'completo' => bool, 'pendientes' => n, 'invalidos' => n]
 * o ['ok' => false, 'error' => código] con: no_existe, sin_partidos.
 *
 * 'completo' = todos los partidos jugados y, si hay cruces, la final jugada (o, sin cruces posibles,
 * grupos terminados). Solo entonces se evalúan los ascensos de categoría.
 * Los descalificados no figuran en 'jugadores' (no reciben puntos de este torneo).
 */
function calcularPuntosTorneo($conexion, $idTorneo) {

    $stmt = mysqli_prepare($conexion, "SELECT id_torneo, nombre, tipo, id_categoria, estado FROM torneos WHERE id_torneo = ?");
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$torneo) {
        return ['ok' => false, 'error' => 'no_existe'];
    }

    $esIndividual = ($torneo['tipo'] === 'Individual');

    // Participantes/parejas del torneo (a través de sus grupos) y las personas que los integran.
    if ($esIndividual) {
        $sqlEntrantes = "SELECT gi.id_grupo_inscripcion AS id_entrante, insc.id_usuario AS u1, NULL AS u2,
                                (insc.estado = 'Descalificada') AS descalificado
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
        $sqlEntrantes = "SELECT gp.id_grupo_pareja AS id_entrante, i1.id_usuario AS u1, i2.id_usuario AS u2,
                                (i1.estado = 'Descalificada' OR i2.estado = 'Descalificada') AS descalificado
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
        $entrantes[(int) $fila['id_entrante']] = rankingStatsVacias() + [
            'usuarios' => $usuarios,
            'descalificado' => ((int) $fila['descalificado'] === 1),
        ];
    }

    $stmt = mysqli_prepare($conexion, $sqlPartidos);
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $partidos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    if (empty($partidos) || empty($entrantes)) {
        return ['ok' => false, 'error' => 'sin_partidos', 'torneo' => $torneo];
    }

    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM grupo WHERE id_torneo = ?");
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $cantidadGrupos = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];

    $pendientes = 0;
    $invalidos = 0;
    $hayCruces = false;
    $finalJugada = false;

    foreach ($partidos as $p) {

        if ($p['fase'] !== 'Grupos') {

            $hayCruces = true;

            foreach ([(int) $p['id_entrante_1'], (int) $p['id_entrante_2']] as $idEnCruce) {
                if (isset($entrantes[$idEnCruce])) {
                    $entrantes[$idEnCruce]['en_cruces'] = true;
                }
            }

        }

        // Un partido sin jugar no da puntos a nadie.
        if ($p['estado'] !== 'Jugado') {
            $pendientes++;
            continue;
        }

        $r = resultadoDePartido($p);
        $id1 = (int) $p['id_entrante_1'];
        $id2 = (int) $p['id_entrante_2'];

        // Un resultado guardado que no es válido no suma (igual que en las tablas de posiciones).
        if (!$r['valido'] || !isset($entrantes[$id1]) || !isset($entrantes[$id2])) {
            $invalidos++;
            $pendientes++;
            continue;
        }

        $esWo = !empty($r['wo']);
        $idGanador = ($r['ganador'] === 1) ? $id1 : $id2;
        $idPerdedor = ($idGanador === $id1) ? $id2 : $id1;

        // Estadísticas (todos los partidos jugados; un W.O. no aporta sets ni games).
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

            // Cruces: queda UNA sola instancia por participante (la de su última derrota, o el título),
            // no se suman rondas. Quien gana una ronda no suma nada hasta que se define su siguiente partido.
            if ($p['fase'] === 'Final') {
                $finalJugada = true;
                $entrantes[$idGanador]['instancia'] = 'Campeon';
                $entrantes[$idPerdedor]['instancia'] = 'Final';
            } else {
                $entrantes[$idPerdedor]['instancia'] = $p['fase'];
            }

            $entrantes[$idPerdedor]['perdio_cruce'] = true;

        }

    }

    $completo = ($pendientes === 0)
        && ($hayCruces ? $finalJugada : !in_array($cantidadGrupos, [2, 4, 8], true));

    $jugadores = [];
    $porEntrante = [];   // id de participante/pareja en el torneo => sus puntos (para mostrarlos igual en todas las pantallas)

    foreach ($entrantes as $idEntrante => $e) {

        // Un descalificado no recibe puntos de este torneo, hasta donde haya llegado. Sus partidos
        // ya jugados cuentan igual para sus rivales (más arriba), pero él no genera fila de ranking.
        if ($e['descalificado']) {
            $porEntrante[$idEntrante] = ['puntos_grupos' => 0, 'puntos_cruces' => 0, 'puntos' => 0, 'descalificado' => true];
            continue;
        }

        $e['puntos_cruces'] = RANKING_PUNTOS_CRUCES[$e['instancia']] ?? 0;
        $e['partidos'] = $e['pg'] + $e['pp'];

        $porEntrante[$idEntrante] = [
            'puntos_grupos' => $e['puntos_grupos'],
            'puntos_cruces' => $e['puntos_cruces'],
            'puntos' => $e['puntos_grupos'] + $e['puntos_cruces'],
            'descalificado' => false,
        ];

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

    return [
        'ok' => true,
        'torneo' => $torneo,
        'jugadores' => $jugadores,
        'entrantes' => $porEntrante,
        'hay_cruces' => $hayCruces,
        'completo' => $completo,
        'pendientes' => $pendientes,
        'invalidos' => $invalidos,
    ];

}


/**
 * Puntos del jugador EN ESE CLUB, en su categoría actual de ese club, desde su último ascenso/cambio de categoría
 * en ese club (temporada abierta del club). Los puntos y los cambios de otros clubes no cuentan.
 */
function rankingPuntosDelCiclo($conexion, $idUsuario, $idClub, $idCategoria) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT COALESCE(SUM(rp.puntos_grupos + rp.puntos_cruces), 0) AS pts
         FROM ranking_puntos rp
         WHERE rp.id_usuario = ? AND rp.id_club = ? AND rp.id_categoria = ? AND rp.anulado = 0
           AND rp.id_temporada = (SELECT id_temporada FROM temporada WHERE id_club = ? AND estado = 'Abierta')
           AND rp.id_ranking_puntos > COALESCE((
                SELECT MAX(h.ultimo_id_ranking_puntos) FROM categoria_historial h
                WHERE h.id_usuario = ? AND h.id_club = ? AND h.id_categoria_nueva = ?
           ), 0)"
    );
    mysqli_stmt_bind_param($stmt, "iiiiiii", $idUsuario, $idClub, $idCategoria, $idClub, $idUsuario, $idClub, $idCategoria);
    mysqli_stmt_execute($stmt);

    return (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['pts'];

}


/**
 * Ascenso de categoría EN EL CLUB DEL TORNEO: si el jugador (que sigue en la categoría del torneo dentro de ese club) llegó
 * al límite de puntos de ese club, sube de categoría SOLO en ese club y empieza en 0 (el excedente se descarta).
 * No se modifica ninguna otra membresía ni usuario.id_categoria. El cambio queda en categoria_historial con su club.
 * Quien llama ya tiene la transacción abierta. Devuelve los datos del ascenso o null si no corresponde.
 */
function aplicarAscensoSiCorresponde($conexion, $idUsuario, $idCategoriaTorneo, $idTorneo, $idRankingPuntos, $idTemporada, $categoriaMaxima, $nombre = '') {

    $torneo = ccFila($conexion, "SELECT id_club FROM torneos WHERE id_torneo = ?", 'i', [(int) $idTorneo]);

    if (!$torneo) {
        return null;
    }

    $idClub = (int) $torneo['id_club'];

    // La membresía del jugador en ESE club se bloquea antes de decidir el ascenso.
    $membresia = membresiaEnClub($conexion, $idUsuario, $idClub, true);

    // Sin membresía en el club del torneo no hay categoría en ese club y, por lo tanto, no hay ascenso posible.
    if (!$membresia) {
        return null;
    }

    // Solo puntúa en el ciclo actual si el jugador sigue en la categoría del torneo dentro de este club.
    if ($membresia['id_categoria'] === null || (int) $membresia['id_categoria'] !== $idCategoriaTorneo) {
        return null;
    }

    $puntosCiclo = rankingPuntosDelCiclo($conexion, $idUsuario, $idClub, $idCategoriaTorneo);

    if ($puntosCiclo < RANKING_PUNTOS_ASCENSO || $idCategoriaTorneo >= $categoriaMaxima) {
        return null;
    }

    $nueva = $idCategoriaTorneo + 1;
    $motivo = 'Alcanzó ' . $puntosCiclo . ' puntos (límite ' . RANKING_PUNTOS_ASCENSO . ')';

    $stmtHistorial = mysqli_prepare(
        $conexion,
        "INSERT INTO categoria_historial
            (id_usuario, id_categoria_anterior, id_categoria_nueva, tipo, motivo,
             id_torneo, id_ranking_puntos, puntos_alcanzados, ultimo_id_ranking_puntos, id_temporada, id_club)
         VALUES (?, ?, ?, 'Ascenso por puntos', ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmtHistorial,
        "iiisiiiiii",
        $idUsuario, $idCategoriaTorneo, $nueva, $motivo,
        $idTorneo, $idRankingPuntos, $puntosCiclo, $idRankingPuntos, $idTemporada, $idClub
    );
    mysqli_stmt_execute($stmtHistorial);

    // La categoría vigente del jugador en este club (única fuente de verdad): solo esa fila cambia.
    $stmtCambio = mysqli_prepare($conexion, "UPDATE usuario_club SET id_categoria = ? WHERE id_usuario = ? AND id_club = ? AND id_categoria = ?");
    mysqli_stmt_bind_param($stmtCambio, "iiii", $nueva, $idUsuario, $idClub, $idCategoriaTorneo);
    mysqli_stmt_execute($stmtCambio);

    if (mysqli_stmt_affected_rows($stmtCambio) !== 1) {
        throw new Exception('error_bd');
    }

    actividadCategoriaAutomatica($conexion, $idUsuario, $idCategoriaTorneo, $nueva, 'alcanzó ' . $puntosCiclo . ' puntos', $idTorneo, $idClub);

    return [
        'id_usuario' => $idUsuario,
        'id_club' => $idClub,
        'nombre' => $nombre,
        'de' => $idCategoriaTorneo,
        'a' => $nueva,
        'puntos' => $puntosCiclo,
    ];

}

/**
 * Pone al día, a partir de los partidos jugados, los puntos que el torneo le da a cada jugador.
 * Se llama cada vez que se define un partido (resultado cargado, W.O., descalificación): no hay
 * un botón para «otorgar» puntos.
 *
 * - Una fila por jugador y torneo (UNIQUE id_torneo+id_usuario); se crea con su primer partido jugado
 *   y se actualiza con cada resultado. Es idempotente: recalcular da siempre lo mismo (sin duplicar).
 * - La fila conserva la temporada y la categoría con que nació; las filas anuladas (descalificados)
 *   no se tocan.
 * - Los ascensos de categoría se evalúan SOLO cuando el torneo está completo (final jugada), igual que
 *   antes se evaluaban al otorgar los puntos.
 *
 * Debe llamarse DENTRO de una transacción abierta por quien llama, para que el resultado y los puntos
 * se guarden juntos o no se guarde nada. Lanza una excepción si algo falla.
 *
 * @return array ['jugadores' => filas con puntos, 'ascensos' => [...], 'completo' => bool]
 */
function sincronizarPuntosTorneo($conexion, $idTorneo, $idActor) {

    $idTorneo = (int) $idTorneo;
    $idActor = (int) $idActor;

    // Bloquea el torneo: dos resultados simultáneos se procesan uno detrás de otro.
    $stmt = mysqli_prepare($conexion, "SELECT id_categoria, id_club FROM torneos WHERE id_torneo = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$torneo) {
        throw new Exception('no_existe');
    }

    $calculo = calcularPuntosTorneo($conexion, $idTorneo);

    if (!$calculo['ok']) {
        // Sin partidos todavía: no hay nada que sumar.
        return ['jugadores' => 0, 'ascensos' => [], 'completo' => false];
    }

    // Los puntos del torneo van a la temporada abierta de SU club (si el club todavía no tiene ninguna, se abre la primera).
    $temporada = temporadaAbiertaOCrear($conexion, (int) $torneo['id_club']);

    if (!$temporada) {
        throw new Exception('error_bd');
    }

    $idTemporada = (int) $temporada['id_temporada'];
    $idCategoriaTorneo = (int) $torneo['id_categoria'];

    $stmtBuscar = mysqli_prepare(
        $conexion,
        "SELECT id_ranking_puntos, anulado, instancia, puntos_grupos, puntos_cruces,
                partidos_ganados, partidos_perdidos, partidos_wo_perdidos,
                sets_favor, sets_contra, games_favor, games_contra
         FROM ranking_puntos WHERE id_torneo = ? AND id_usuario = ? FOR UPDATE"
    );

    $stmtActualizar = mysqli_prepare(
        $conexion,
        "UPDATE ranking_puntos
         SET instancia = ?, puntos_grupos = ?, puntos_cruces = ?,
             partidos_ganados = ?, partidos_perdidos = ?, partidos_wo_perdidos = ?,
             sets_favor = ?, sets_contra = ?, games_favor = ?, games_contra = ?,
             id_liquidador = ?, fecha_liquidacion = NOW()
         WHERE id_ranking_puntos = ? AND anulado = 0"
    );

    $stmtInsertar = mysqli_prepare(
        $conexion,
        "INSERT INTO ranking_puntos
            (id_torneo, id_usuario, id_categoria, instancia, puntos_grupos, puntos_cruces,
             partidos_ganados, partidos_perdidos, partidos_wo_perdidos,
             sets_favor, sets_contra, games_favor, games_contra, id_liquidador, id_temporada)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $filas = [];

    foreach ($calculo['jugadores'] as $idUsuario => $j) {

        // Quien todavía no jugó ningún partido no tiene puntos ni fila.
        if ($j['partidos'] === 0) {
            continue;
        }

        $idUsuario = (int) $idUsuario;

        mysqli_stmt_bind_param($stmtBuscar, "ii", $idTorneo, $idUsuario);
        mysqli_stmt_execute($stmtBuscar);
        $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtBuscar));

        if ($existente) {

            if ((int) $existente['anulado'] === 1) {
                continue;
            }

            $idFila = (int) $existente['id_ranking_puntos'];

            // Solo se escribe si algo cambió: así no se pisan la fecha ni quién cargó el último cambio de filas que siguen igual.
            $cambio = $existente['instancia'] !== $j['instancia']
                || (int) $existente['puntos_grupos'] !== $j['puntos_grupos']
                || (int) $existente['puntos_cruces'] !== $j['puntos_cruces']
                || (int) $existente['partidos_ganados'] !== $j['pg']
                || (int) $existente['partidos_perdidos'] !== $j['pp']
                || (int) $existente['partidos_wo_perdidos'] !== $j['wo_perdidos']
                || (int) $existente['sets_favor'] !== $j['sets_favor']
                || (int) $existente['sets_contra'] !== $j['sets_contra']
                || (int) $existente['games_favor'] !== $j['games_favor']
                || (int) $existente['games_contra'] !== $j['games_contra'];

            if ($cambio) {

                mysqli_stmt_bind_param(
                    $stmtActualizar,
                    "siiiiiiiiiii",
                    $j['instancia'], $j['puntos_grupos'], $j['puntos_cruces'],
                    $j['pg'], $j['pp'], $j['wo_perdidos'],
                    $j['sets_favor'], $j['sets_contra'], $j['games_favor'], $j['games_contra'],
                    $idActor, $idFila
                );
                mysqli_stmt_execute($stmtActualizar);

            }

        } else {

            mysqli_stmt_bind_param(
                $stmtInsertar,
                "iiisiiiiiiiiiii",
                $idTorneo, $idUsuario, $idCategoriaTorneo, $j['instancia'],
                $j['puntos_grupos'], $j['puntos_cruces'],
                $j['pg'], $j['pp'], $j['wo_perdidos'],
                $j['sets_favor'], $j['sets_contra'], $j['games_favor'], $j['games_contra'],
                $idActor, $idTemporada
            );
            mysqli_stmt_execute($stmtInsertar);
            $idFila = (int) mysqli_insert_id($conexion);

        }

        $filas[$idUsuario] = $idFila;

    }

    // Ascensos: solo con el torneo terminado, para que un jugador no cambie de categoría a mitad de torneo.
    $ascensos = [];

    if ($calculo['completo']) {

        $categoriaMaxima = (int) mysqli_fetch_assoc(mysqli_query($conexion, "SELECT MAX(id_categoria) AS m FROM categoria"))['m'];

        foreach ($filas as $idUsuario => $idFila) {

            $ascenso = aplicarAscensoSiCorresponde(
                $conexion, $idUsuario, $idCategoriaTorneo, $idTorneo, $idFila, $idTemporada,
                $categoriaMaxima, $calculo['jugadores'][$idUsuario]['nombre'] ?? ''
            );

            if ($ascenso) {
                $ascensos[] = $ascenso;
            }

        }

    }

    return ['jugadores' => count($filas), 'ascensos' => $ascensos, 'completo' => $calculo['completo']];

}


/** ¿Este torneo ya provocó ascensos de categoría? Entonces sus resultados quedan fijos (no se pueden reabrir). */
function torneoConAscensos($conexion, $idTorneo) {

    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM categoria_historial WHERE id_torneo = ? AND tipo = 'Ascenso por puntos'");
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);

    return (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'] > 0;

}


/** Ascensos que provocó este torneo (para mostrarlos en la pantalla de puntos). */
function ascensosDeTorneo($conexion, $idTorneo) {

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

    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

}


/**
 * Enfrentamientos directos entre dos jugadores en torneos con puntos vigentes de ambos:
 * devuelve [victorias de A, victorias de B]. En parejas cuentan los partidos en que
 * estuvieron en lados opuestos.
 */
function rankingEnfrentamientoDirecto($conexion, $idA, $idB, $idClub) {

    $victoriasA = 0;
    $victoriasB = 0;

    // Solo cuentan los torneos del club y de su temporada abierta ($idClub es un entero, no viene del navegador).
    $idClub = (int) $idClub;
    $vigente = "r.anulado = 0 AND r.id_club = $idClub AND r.id_temporada = (SELECT id_temporada FROM temporada WHERE id_club = $idClub AND estado = 'Abierta')";

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
           AND EXISTS (SELECT 1 FROM ranking_puntos r WHERE " . $vigente . " AND r.id_usuario = ? AND r.id_torneo = i1.id_torneo)
           AND EXISTS (SELECT 1 FROM ranking_puntos r WHERE " . $vigente . " AND r.id_usuario = ? AND r.id_torneo = i1.id_torneo)",
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
           AND EXISTS (SELECT 1 FROM ranking_puntos r WHERE " . $vigente . " AND r.id_usuario = ? AND r.id_torneo = pa1.id_torneo)
           AND EXISTS (SELECT 1 FROM ranking_puntos r WHERE " . $vigente . " AND r.id_usuario = ? AND r.id_torneo = pa1.id_torneo)",
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
 * Ranking de una categoría DE UN CLUB: todos los jugadores que HOY están en esa categoría dentro de ese club
 * (membresía usuario_club; también los que tienen 0 puntos), de mayor a menor, con los puntos ganados en ese club.
 *
 * Orden: puntos, partidos ganados, diferencia de sets, diferencia de games, y
 * enfrentamiento directo cuando el empate es entre exactamente dos jugadores.
 * Si sigue igual comparten posición (1, 2, 2, 4).
 *
 * @return array lista de filas con 'posicion', 'empate' y las estadísticas.
 */
function rankingDeCategoria($conexion, $idClub, $idCategoria) {

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
         FROM usuario_club uc
         INNER JOIN usuario u ON u.id_usuario = uc.id_usuario
         LEFT JOIN ranking_puntos rp
                ON rp.id_usuario = u.id_usuario
                AND rp.id_club = uc.id_club
                AND rp.anulado = 0
                AND rp.id_categoria = uc.id_categoria
               AND rp.id_temporada = (SELECT id_temporada FROM temporada WHERE id_club = uc.id_club AND estado = 'Abierta')
               AND rp.id_ranking_puntos > COALESCE((
                    SELECT MAX(h.ultimo_id_ranking_puntos) FROM categoria_historial h
                    WHERE h.id_usuario = u.id_usuario AND h.id_club = uc.id_club AND h.id_categoria_nueva = uc.id_categoria
               ), 0)
         WHERE u.id_rol = 3 AND uc.id_club = ? AND uc.id_categoria = ?
         GROUP BY u.id_usuario, u.nombre, u.apellido"
    );
    mysqli_stmt_bind_param($stmt, "ii", $idClub, $idCategoria);
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

            [$ganaA, $ganaB] = rankingEnfrentamientoDirecto($conexion, $filas[$i]['id_usuario'], $filas[$i + 1]['id_usuario'], $idClub);

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


/** Posición y puntos de un jugador en SU categoría actual DE ESE CLUB (null si no pertenece al club o no tiene categoría allí). */
function rankingDeUsuario($conexion, $idUsuario, $idClub) {

    $m = membresiaEnClub($conexion, $idUsuario, $idClub);

    if (!$m || $m['id_categoria'] === null) {
        return null;
    }

    $ranking = rankingDeCategoria($conexion, (int) $idClub, (int) $m['id_categoria']);

    foreach ($ranking as $f) {
        if ($f['id_usuario'] === (int) $idUsuario) {
            return $f + ['id_categoria' => (int) $m['id_categoria'], 'total_categoria' => count($ranking)];
        }
    }

    return null;

}