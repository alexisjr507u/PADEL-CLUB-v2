<?php

include("config/no_cache.php");
include("config/conexion.php");
require_once("funciones/standings.php");
require_once("funciones/ranking_torneo.php");
require_once("funciones/publico_datos.php");
require_once("funciones/publico_componentes.php");


/* =========================================================
   SEGURIDAD
   ========================================================= */

// MODO PÚBLICO: un visitante sin sesión puede CONSULTAR el torneo (solo lectura). En ese modo no se
// muestra el menú del panel ni ninguna herramienta del organizador, y ninguna acción de escritura
// está disponible desde esta página. Con sesión, todo funciona como siempre.
$modoPublico = !isset($_SESSION['id_usuario']);

if (!$modoPublico && (
    $_SESSION['id_rol'] != 1 &&
    $_SESSION['id_rol'] != 2 &&
    $_SESSION['id_rol'] != 3
)) {
    header("Location: paneles.php");
    exit();
}


/* =========================================================
   TORNEO
   ========================================================= */

$id_torneo = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id_torneo) {

    header("Location: " . ($modoPublico ? "torneos_publicos.php" : "paneles.php?seccion=torneos&error=torneo_invalido"));
    exit();

}

$id_usuario = $_SESSION['id_usuario'] ?? 0;

$sqlTorneo = "
    SELECT
        torneos.id_torneo,
        torneos.nombre,
        torneos.fecha_inicio,
        torneos.hora_inicio,
        torneos.tipo,
        torneos.estado,
        torneos.max_participantes,
        torneos.lugar,
        categoria.nombre AS categoria,
        club.nombre AS club
    FROM torneos
    INNER JOIN categoria
        ON torneos.id_categoria = categoria.id_categoria
    INNER JOIN club
        ON club.id_club = torneos.id_club
    WHERE torneos.id_torneo = ?
    LIMIT 1
";

$stmtTorneo = mysqli_prepare($conexion, $sqlTorneo);

mysqli_stmt_bind_param(
    $stmtTorneo,
    "i",
    $id_torneo
);

mysqli_stmt_execute($stmtTorneo);

$torneo = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmtTorneo)
);

mysqli_stmt_close($stmtTorneo);

if (!$torneo) {

    header("Location: " . ($modoPublico ? "torneos_publicos.php" : "paneles.php?seccion=torneos&error=sin_permiso"));
    exit();

}


/* =========================================================
   ETIQUETAS DE ESTADO
   ========================================================= */

$etiquetasEstado = [
    'Abierto'     => 'Inscripciones abiertas',
    'Cerrado'     => 'Inscripciones cerradas',
    'En curso'    => 'Torneo en curso',
    'Finalizado'  => 'Torneo finalizado'
];

$estadoTexto = $etiquetasEstado[$torneo['estado']] ?? $torneo['estado'];

$eyebrowTorneo =
    ($torneo['estado'] === 'Finalizado')
        ? 'TORNEO FINALIZADO'
        : 'TORNEO ACTIVO';

$mesesEs = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];

$timestampInicio = strtotime($torneo['fecha_inicio']);

$fechaTorneoFormateada =
    date('d', $timestampInicio) .
    ' de ' .
    $mesesEs[(int) date('n', $timestampInicio)] .
    ' de ' .
    date('Y', $timestampInicio);

$horarioTorneo =
    date('H:i', strtotime($torneo['hora_inicio'])) .
    ' hs';


/* =========================================================
   CONFIGURACIÓN SEGÚN TIPO DE TORNEO
   ========================================================= */

if ($torneo['tipo'] === 'Individual') {

    $textoParticipantes = 'participantes';
    $textoCantidad = 'participantes';
    $textoColumna = 'PARTICIPANTE';

} elseif ($torneo['tipo'] === 'Mixto') {

    $textoParticipantes = 'parejas mixtas';
    $textoCantidad = 'parejas';
    $textoColumna = 'PAREJA MIXTA';

} else {

    $textoParticipantes = 'parejas participantes';
    $textoCantidad = 'parejas';
    $textoColumna = 'PAREJA';

}


/* =========================================================
   PARTICIPANTES / PAREJAS REALES (CONFIRMADOS)
   ========================================================= */

$entradasConfirmadas = [];
$totalPersonasConfirmadas = 0;

if ($torneo['tipo'] === 'Individual') {

    $stmtEntradas = mysqli_prepare(
        $conexion,
        "SELECT u.nombre, u.apellido
         FROM inscripcion insc
         INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
         WHERE insc.id_torneo = ? AND insc.estado = 'Confirmada'
         ORDER BY insc.id_inscripcion ASC"
    );
    mysqli_stmt_bind_param($stmtEntradas, "i", $id_torneo);
    mysqli_stmt_execute($stmtEntradas);
    $resultadoEntradas = mysqli_stmt_get_result($stmtEntradas);

    while ($fila = mysqli_fetch_assoc($resultadoEntradas)) {

        $entradasConfirmadas[] = [
            'nombre' => trim($fila['nombre'] . ' ' . $fila['apellido']),
        ];

    }

    $totalPersonasConfirmadas = count($entradasConfirmadas);

} else {

    // Parejas y Mixto: cada fila de `pareja` ya representa una
    // pareja confirmada (solo se crea cuando ambos lados aceptaron).
    $stmtEntradas = mysqli_prepare(
        $conexion,
        "SELECT u1.nombre AS nombre1, u1.apellido AS apellido1,
                u2.nombre AS nombre2, u2.apellido AS apellido2
         FROM pareja p
         INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
         INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
         INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
         INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
         WHERE p.id_torneo = ?
         ORDER BY p.id_pareja ASC"
    );
    mysqli_stmt_bind_param($stmtEntradas, "i", $id_torneo);
    mysqli_stmt_execute($stmtEntradas);
    $resultadoEntradas = mysqli_stmt_get_result($stmtEntradas);

    while ($fila = mysqli_fetch_assoc($resultadoEntradas)) {

        $entradasConfirmadas[] = [
            'nombres' => [
                trim($fila['nombre1'] . ' ' . $fila['apellido1']),
                trim($fila['nombre2'] . ' ' . $fila['apellido2']),
            ],
            'apellidos' => [
                trim($fila['apellido1']),
                trim($fila['apellido2']),
            ],
        ];

    }

    $totalPersonasConfirmadas = count($entradasConfirmadas) * 2;

}


/* =========================================================
   DISTRIBUCIÓN EN GRUPOS
   Si el organizador/admin ya armó grupos persistentes
   (tablas grupo/grupo_inscripcion/grupo_pareja) se usan esos,
   respetando nombre y orden. Si todavía no hay ninguno, se
   mantiene la propuesta automática calculada en el momento
   (no guardada) como alternativa, igual que antes.
   ========================================================= */

$grupos = [];
$gruposPersistidos = false;

$stmtGruposGuardados = mysqli_prepare(
    $conexion,
    "SELECT id_grupo, nombre FROM grupo WHERE id_torneo = ? ORDER BY orden ASC, id_grupo ASC"
);
mysqli_stmt_bind_param($stmtGruposGuardados, "i", $id_torneo);
mysqli_stmt_execute($stmtGruposGuardados);
$filasGruposGuardados = mysqli_fetch_all(mysqli_stmt_get_result($stmtGruposGuardados), MYSQLI_ASSOC);

if (count($filasGruposGuardados) > 0) {

    $gruposPersistidos = true;
    $letrasGrupos = range('A', 'Z');

    foreach ($filasGruposGuardados as $indice => $filaGrupo) {

        $idGrupoActual = (int) $filaGrupo['id_grupo'];
        $miembrosGrupo = [];
        $esIndividualGrupo = ($torneo['tipo'] === 'Individual');

        $datosGrupoPersistido = obtenerMiembrosYPartidosDeGrupo($conexion, $idGrupoActual, $esIndividualGrupo);

        $miembrosPorIdEntrante = [];
        foreach ($datosGrupoPersistido['miembros'] as $m) {
            $miembrosPorIdEntrante[(int) $m['id_entrante']] = $m;
        }

        $standingsGrupo = calcularStandings($datosGrupoPersistido['miembros'], $datosGrupoPersistido['partidos']);

        foreach ($standingsGrupo as $fila) {

            $m = $miembrosPorIdEntrante[$fila['id_entrante']] ?? null;

            if (!$m) {
                continue;
            }

            if ($esIndividualGrupo) {

                $miembrosGrupo[] = [
                    'nombre' => trim($m['nombre'] . ' ' . $m['apellido']) . (!empty($m['descalificado']) ? ' (descalificado)' : ''),
                    'pj' => $fila['pj'],
                    'pg' => $fila['pg'],
                    'pp' => $fila['pp'],
                    'sets' => $fila['sets_favor'] . '-' . $fila['sets_contra'],
                    'dif' => $fila['games_favor'] - $fila['games_contra'],
                    'pts' => $fila['pts'],
                ];

            } else {

                $miembrosGrupo[] = [
                    'nombres' => [
                        trim($m['nombre1'] . ' ' . $m['apellido1']),
                        trim($m['nombre2'] . ' ' . $m['apellido2']) . (!empty($m['descalificado']) ? ' (descalificado)' : ''),
                    ],
                    'apellidos' => [
                        trim($m['apellido1']),
                        trim($m['apellido2']),
                    ],
                    'pj' => $fila['pj'],
                    'pg' => $fila['pg'],
                    'pp' => $fila['pp'],
                    'sets' => $fila['sets_favor'] . '-' . $fila['sets_contra'],
                    'dif' => $fila['games_favor'] - $fila['games_contra'],
                    'pts' => $fila['pts'],
                ];

            }

        }

        $grupos[] = [
            'letra'  => $letrasGrupos[$indice] ?? ('G' . ($indice + 1)),
            'nombre' => $filaGrupo['nombre'],
        ] + (
            $torneo['tipo'] === 'Individual'
                ? ['participantes' => $miembrosGrupo]
                : ['parejas' => $miembrosGrupo]
        );

    }

} elseif (count($entradasConfirmadas) > 0) {

    $TAMANIO_GRUPO = 4;
    $MINIMO_PARA_AGRUPAR = 5;

    if (count($entradasConfirmadas) < $MINIMO_PARA_AGRUPAR) {

        $grupos[] = [
            'letra'  => '',
            'nombre' => 'Participantes confirmados',
        ] + (
            $torneo['tipo'] === 'Individual'
                ? ['participantes' => $entradasConfirmadas]
                : ['parejas' => $entradasConfirmadas]
        );

    } else {

        $letrasGrupos = range('A', 'Z');

        foreach (array_chunk($entradasConfirmadas, $TAMANIO_GRUPO) as $indice => $bloque) {

            $letra = $letrasGrupos[$indice] ?? ('G' . ($indice + 1));

            $grupos[] = [
                'letra'  => $letra,
                'nombre' => 'Grupo ' . $letra,
            ] + (
                $torneo['tipo'] === 'Individual'
                    ? ['participantes' => $bloque]
                    : ['parejas' => $bloque]
            );

        }

    }

}


/* =========================================================
   RANKING Y CRUCES (SEMIFINALES / FINAL)
   El ranking de jugadores todavía no se pidió, así que se deja
   vacío. Las semis/final sí se calculan a partir de las tablas
   partido_individual/partido_pareja (fase final).
   ========================================================= */

$rankingTorneo = calcularRankingTorneo($conexion, $id_torneo, $torneo['tipo'] === 'Individual');

// Consulta pública: partidos y resultados de la fase de grupos (solo nombres y marcador, nada privado)
$partidosPublicos = $modoPublico
    ? publicoPartidosDeGrupos($conexion, $id_torneo, $torneo['tipo'] === 'Individual')
    : [];

$confirmadosTorneo = 0;

if ($modoPublico || (int) ($_SESSION['id_rol'] ?? 0) === 3) {

    $filaConfirmados = publicoConsulta(
        $conexion,
        "SELECT COUNT(*) AS n FROM inscripcion WHERE id_torneo = ? AND estado = 'Confirmada'",
        'i',
        [(int) $id_torneo]
    );

    $confirmadosTorneo = (int) ($filaConfirmados[0]['n'] ?? 0);

}
$semis = [];
$final = null;
$rondasPrevias = [];   // Octavos / Cuartos (torneos con 4 u 8 grupos)

$esIndividualTorneo = ($torneo['tipo'] === 'Individual');
$tablaPartidosFinal = $esIndividualTorneo ? 'partido_individual' : 'partido_pareja';
$tablaEntrantesFinal = $esIndividualTorneo ? 'grupo_inscripcion' : 'grupo_pareja';
$colEntranteFinal = $esIndividualTorneo ? 'id_grupo_inscripcion' : 'id_grupo_pareja';

$stmtBracket = mysqli_prepare(
    $conexion,
    "SELECT p.id_partido, p.fase, p.orden, p.id_entrante_1, p.id_entrante_2,
            p.set1_games_1, p.set1_games_2, p.set2_games_1, p.set2_games_2,
            p.set3_games_1, p.set3_games_2, p.id_ganador, p.resultado_tipo, p.estado
     FROM $tablaPartidosFinal p
     INNER JOIN $tablaEntrantesFinal e ON e.$colEntranteFinal = p.id_entrante_1
     INNER JOIN grupo g ON g.id_grupo = e.id_grupo
     WHERE g.id_torneo = ? AND p.fase IN ('Octavos', 'Cuartos', 'Semifinal', 'Final')
     ORDER BY FIELD(p.fase, 'Octavos', 'Cuartos', 'Semifinal', 'Final') ASC, p.orden ASC"
);
mysqli_stmt_bind_param($stmtBracket, "i", $id_torneo);
mysqli_stmt_execute($stmtBracket);
$filasBracket = mysqli_fetch_all(mysqli_stmt_get_result($stmtBracket), MYSQLI_ASSOC);

$nombresEntrantesTorneoVT = [];

if (!empty($filasBracket)) {

    $idsEntrantes = [];
    foreach ($filasBracket as $fb) {
        $idsEntrantes[] = (int) $fb['id_entrante_1'];
        $idsEntrantes[] = (int) $fb['id_entrante_2'];
    }
    $idsEntrantes = array_values(array_unique($idsEntrantes));

    $placeholders = implode(',', array_fill(0, count($idsEntrantes), '?'));
    $tiposBind = str_repeat('i', count($idsEntrantes));

    if ($esIndividualTorneo) {
        $sqlNombresEntrantes = "SELECT gi.id_grupo_inscripcion AS id_entrante, u.nombre, u.apellido,
                                        (insc.estado = 'Descalificada') AS descalificado
                                 FROM grupo_inscripcion gi
                                 INNER JOIN inscripcion insc ON insc.id_inscripcion = gi.id_inscripcion
                                 INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
                                 WHERE gi.id_grupo_inscripcion IN ($placeholders)";
    } else {
        $sqlNombresEntrantes = "SELECT gp.id_grupo_pareja AS id_entrante,
                                        u1.nombre AS nombre1, u1.apellido AS apellido1,
                                        u2.nombre AS nombre2, u2.apellido AS apellido2,
                                        (i1.estado = 'Descalificada' OR i2.estado = 'Descalificada') AS descalificado
                                 FROM grupo_pareja gp
                                 INNER JOIN pareja p ON p.id_pareja = gp.id_pareja
                                 INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
                                 INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
                                 INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
                                 INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
                                 WHERE gp.id_grupo_pareja IN ($placeholders)";
    }

    $stmtNombresEntrantes = mysqli_prepare($conexion, $sqlNombresEntrantes);
    mysqli_stmt_bind_param($stmtNombresEntrantes, $tiposBind, ...$idsEntrantes);
    mysqli_stmt_execute($stmtNombresEntrantes);
    $filasNombresEntrantes = mysqli_fetch_all(mysqli_stmt_get_result($stmtNombresEntrantes), MYSQLI_ASSOC);

    foreach ($filasNombresEntrantes as $fn) {
        $nombresEntrantesTorneoVT[(int) $fn['id_entrante']] = ($esIndividualTorneo
            ? trim($fn['nombre'] . ' ' . $fn['apellido'])
            : trim($fn['nombre1'] . ' ' . $fn['apellido1']) . ' / ' . trim($fn['nombre2'] . ' ' . $fn['apellido2']))
            . ((int) $fn['descalificado'] === 1 ? ' (descalificado)' : '');
    }

}

foreach ($filasBracket as $fb) {

    $nombre1Bracket = $nombresEntrantesTorneoVT[(int) $fb['id_entrante_1']] ?? '—';
    $nombre2Bracket = $nombresEntrantesTorneoVT[(int) $fb['id_entrante_2']] ?? '—';
    $jugadoBracket = ($fb['estado'] === 'Jugado');

    $resultadoTextoBracket = '';
    if ($jugadoBracket && ($fb['resultado_tipo'] ?? 'Normal') === 'WO') {
        // Sin sets: el partido se resolvió por incomparecencia.
        $resultadoTextoBracket = 'W.O.';
    } elseif ($jugadoBracket) {
        $setsBracket = [[$fb['set1_games_1'], $fb['set1_games_2']], [$fb['set2_games_1'], $fb['set2_games_2']]];
        if ($fb['set3_games_1'] !== null) {
            $setsBracket[] = [$fb['set3_games_1'], $fb['set3_games_2']];
        }
        $resultadoTextoBracket = implode(' / ', array_map(fn($s) => $s[0] . '-' . $s[1], $setsBracket));
    }

    $filaBracket = [
        'nombre1' => $nombre1Bracket,
        'nombre2' => $nombre2Bracket,
        'jugado' => $jugadoBracket,
        'resultado' => $resultadoTextoBracket,
        'gano1' => $jugadoBracket && (int) $fb['id_ganador'] === (int) $fb['id_entrante_1'],
        'gano2' => $jugadoBracket && (int) $fb['id_ganador'] === (int) $fb['id_entrante_2'],
    ];

    if ($fb['fase'] === 'Semifinal') {
        $semis[] = $filaBracket;
    } elseif ($fb['fase'] === 'Final') {
        $final = $filaBracket;
    } else {
        $rondasPrevias[$fb['fase']][] = $filaBracket;
    }

}


/**
 * Cuadro de eliminación directa simétrico (como en una hoja de torneo):
 * la primera ronda en los bordes, las rondas avanzan hacia el centro donde
 * está la final, con el campeón arriba. La mitad izquierda reúne los primeros
 * partidos de cada ronda y la derecha los últimos. Las rondas que todavía no
 * se generaron se dibujan como "Por definir".
 */
function renderCuadroSimetrico(array $rondasPrevias, array $semis, ?array $final) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    // Niveles desde la primera ronda hasta las semifinales: [titulo => partidos]
    // Con 2 grupos no hay rondas previas: el cuadro empieza en las semifinales (SEMIFINAL · FINAL · SEMIFINAL).
    $primera = !empty($rondasPrevias['Octavos']) ? 'Octavos' : (!empty($rondasPrevias['Cuartos']) ? 'Cuartos' : 'Semifinal');
    $nombresNivel = ['Octavos' => 'OCTAVOS', 'Cuartos' => 'CUARTOS', 'Semifinal' => 'SEMIFINAL'];
    $tamanio = ['Octavos' => 8, 'Cuartos' => 4, 'Semifinal' => 2];
    $secuencia = $primera === 'Octavos' ? ['Octavos', 'Cuartos', 'Semifinal'] : ($primera === 'Cuartos' ? ['Cuartos', 'Semifinal'] : ['Semifinal']);

    $niveles = [];

    foreach ($secuencia as $nombre) {

        $partidos = $nombre === 'Semifinal' ? $semis : ($rondasPrevias[$nombre] ?? []);

        // Completa con "por definir" hasta el tamaño que corresponde a la ronda.
        for ($i = count($partidos); $i < $tamanio[$nombre]; $i++) {
            $partidos[$i] = null;
        }

        $niveles[] = $partidos;

    }

    $tarjeta = function (?array $p, string $clase = '') use ($e) {

        if ($p === null) {
            return '<div class="br-partido br-vacio ' . $clase . '"><div class="br-fila"><span>Por definir</span></div><div class="br-fila"><span>&nbsp;</span></div></div>';
        }

        $fila = function ($nombre, $gano, $marcador) use ($e) {
            return '<div class="br-fila' . ($gano ? ' gano' : '') . '"><span class="br-nombre" title="' . $e($nombre) . '">' . $e($nombre) . '</span><span class="br-marcador">' . $e($marcador) . '</span></div>';
        };

        return '<div class="br-partido' . ($p['jugado'] ? '' : ' br-pendiente') . ' ' . $clase . '">'
            . $fila($p['nombre1'], $p['gano1'], $p['jugado'] ? $p['resultado'] : '')
            . $fila($p['nombre2'], $p['gano2'], '')
            . '</div>';

    };

    // Nodo del árbol: sus dos partidos "hijos" (ronda anterior) y su propio partido.
    $nodo = function (int $nivel, int $indice, bool $espejo) use (&$nodo, $niveles, $tarjeta) {

        $html = '<div class="br-nodo' . ($espejo ? ' br-espejo' : '') . '">';

        if ($nivel > 0) {
            $html .= '<div class="br-hijos">'
                . $nodo($nivel - 1, $indice * 2, $espejo)
                . $nodo($nivel - 1, $indice * 2 + 1, $espejo)
                . '</div>';
        }

        return $html . $tarjeta($niveles[$nivel][$indice]) . '</div>';

    };

    $ultimo = count($niveles) - 1;   // nivel de las semifinales

    $titulos = [];
    foreach ($secuencia as $nombre) {
        $titulos[] = $nombresNivel[$nombre];
    }

    $html = '<div class="vt-card vt-bracket-card br-tarjeta">
        <div class="vt-card-header">
            <div>
                <h3>Cuadro de eliminación directa</h3>
                <p>Clasificados de la fase de grupos</p>
            </div>
        </div>
        <div class="br-scroll">
        <div class="br-cuadro' . (count($secuencia) === 1 ? ' br-corto' : '') . '">';

    // Títulos de cada columna, alineados con el cuadro.
    $html .= '<div class="br-titulos">';
    foreach ($titulos as $t) {
        $html .= '<span class="br-col">' . $t . '</span>';
    }
    $html .= '<span class="br-col br-col-final">FINAL</span>';
    foreach (array_reverse($titulos) as $t) {
        $html .= '<span class="br-col">' . $t . '</span>';
    }
    $html .= '</div>';

    $html .= '<div class="br-arbol">';
    $html .= '<div class="br-lado">' . $nodo($ultimo, 0, false) . '</div>';

    $html .= '<div class="br-centro">';

    if ($final && $final['jugado']) {
        $campeon = $final['gano1'] ? $final['nombre1'] : $final['nombre2'];
        $html .= '<div class="br-campeon"><i class="bi bi-trophy-fill"></i><span>CAMPEÓN</span><strong>' . $e($campeon) . '</strong></div>';
    } else {
        // Todavía no hay campeón: la copa se muestra igual, atenuada y con «Por definir».
        $html .= '<div class="br-campeon br-campeon-pendiente"><i class="bi bi-trophy-fill"></i><span>CAMPEÓN</span><strong>Por definir</strong></div>';
    }

    $html .= $tarjeta($final, 'br-final') . '</div>';

    $html .= '<div class="br-lado br-lado-der">' . $nodo($ultimo, 1, true) . '</div>';
    $html .= '</div></div></div></div>';

    return $html;

}

/**
 * Arma el HTML del cuadro de semifinales/final (o el estado vacío si
 * todavía no se generaron). Se usa tanto en la vista del organizador
 * como en la del participante para no duplicar el markup.
 */
function renderBracketTorneo(array $semis, ?array $final, array $rondasPrevias = []) {

    // Cuadro simétrico («hoja de torneo») para todos los torneos con cruces: con octavos o cuartos (4 u 8 grupos) y también
    // con 2 grupos (solo semifinales y final).
    if (!empty($rondasPrevias['Octavos']) || !empty($rondasPrevias['Cuartos']) || !empty($semis)) {
        return renderCuadroSimetrico($rondasPrevias, $semis, $final);
    }

    if (empty($semis) && empty($rondasPrevias)) {
        return '
            <div class="vt-card vt-estado-vacio">
                <i class="bi bi-diagram-3"></i>
                <p>Todavía no hay cruces generados para este torneo.</p>
            </div>
        ';
    }

    $filaHtml = function (string $nombre, bool $gano, string $score) {
        $clase = $gano ? 'vt-match-fila gano' : 'vt-match-fila';
        return '
            <div class="' . $clase . '">
                <span>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</span>
                <span class="vt-match-score">' . htmlspecialchars($score, ENT_QUOTES, 'UTF-8') . '</span>
            </div>
        ';
    };

    $titulosRonda = ['Octavos' => 'OCTAVOS DE FINAL', 'Cuartos' => 'CUARTOS DE FINAL'];
    $rondasMostradas = array_values(array_filter(['Octavos', 'Cuartos'], fn($r) => !empty($rondasPrevias[$r])));
    $columnas = count($rondasMostradas) + (empty($semis) ? 0 : 1) + (empty($semis) ? 0 : 1);

    $html = '<div class="vt-card vt-bracket-card">
        <div class="vt-card-header">
            <div>
                <h3>' . (empty($rondasMostradas) ? 'Cuadro de semifinales' : 'Cuadro de eliminación directa') . '</h3>
                <p>Clasificados de la fase de grupos</p>
            </div>
        </div>
        <div class="vt-bracket ' . (empty($rondasMostradas) ? 'vt-bracket-2col' : 'vt-bracket-ncol') . '" style="--cols:' . max(1, $columnas) . ';">';

    // Octavos y cuartos (solo con 4 u 8 grupos): una columna por ronda.
    foreach ($rondasMostradas as $nombreRonda) {

        $html .= '<div class="vt-bracket-col ronda"><div class="vt-bracket-col-titulo">' . $titulosRonda[$nombreRonda] . '</div>';

        foreach ($rondasPrevias[$nombreRonda] as $partido) {
            $html .= '<div class="vt-match' . (!$partido['jugado'] ? ' vt-match-pendiente' : '') . '">';
            $html .= $filaHtml($partido['nombre1'], $partido['gano1'], $partido['jugado'] ? $partido['resultado'] : '-');
            $html .= $filaHtml($partido['nombre2'], $partido['gano2'], '');
            $html .= '</div>';
        }

        $html .= '</div>';

    }

    if (!empty($semis)) {
    $html .= '<div class="vt-bracket-col semis">
                <div class="vt-bracket-col-titulo">SEMIFINALES</div>';
    }

    foreach ($semis as $semi) {
        $html .= '<div class="vt-match' . (!$semi['jugado'] ? ' vt-match-pendiente' : '') . '">';
        $html .= $filaHtml($semi['nombre1'], $semi['gano1'], $semi['jugado'] ? $semi['resultado'] : '-');
        $html .= $filaHtml($semi['nombre2'], $semi['gano2'], '');
        $html .= '</div>';
    }

    if (!empty($semis)) {
    $html .= '</div>
            <div class="vt-bracket-col final">
                <div class="vt-bracket-col-titulo">FINAL</div>';
    }

    if (empty($semis)) {

        // Solo hay rondas previas (todavía no se generaron las semifinales).

    } elseif ($final) {

        $html .= '<div class="vt-match' . (!$final['jugado'] ? ' vt-match-pendiente' : '') . '">';
        $html .= $filaHtml($final['nombre1'], $final['gano1'], $final['jugado'] ? $final['resultado'] : '-');
        $html .= $filaHtml($final['nombre2'], $final['gano2'], '');
        $html .= '</div>';

        if ($final['jugado']) {
            $nombreCampeon = $final['gano1'] ? $final['nombre1'] : $final['nombre2'];
            $html .= '<div class="vt-campeon">
                <i class="bi bi-trophy-fill"></i>
                <strong>CAMPEÓN: ' . htmlspecialchars($nombreCampeon, ENT_QUOTES, 'UTF-8') . '</strong>
            </div>';
        }

    } else {
        $html .= '<div class="vt-match vt-match-pendiente"><div class="vt-match-fila"><span>Por definir</span></div></div>';
    }

    if (!empty($semis)) {
        $html .= '</div>';
    }

    $html .= '</div></div>';

    return $html;

}


/**
 * Tabla del ranking final del torneo (todas las parejas/participantes).
 * Se usa en la vista del organizador y en la del participante.
 */
function renderRankingTorneo(array $r, bool $esIndividual) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    if (empty($r['filas'])) {
        return '
            <div class="vt-card vt-estado-vacio">
                <i class="bi bi-bar-chart-line"></i>
                <p>Todavía no hay participantes en grupos para generar el ranking.</p>
            </div>
        ';
    }

    $html = '<div class="vt-card vt-ranking-card">';

    if ($r['provisional']) {
        $html .= '<div class="vt-ranking-aviso vt-ranking-provisional">
            <i class="bi bi-hourglass-split"></i>
            <span><strong>Clasificación provisional.</strong> El torneo sigue en curso: todavía no hay campeón definido.</span>
        </div>';
    } else {
        $html .= '<div class="vt-ranking-aviso vt-ranking-final">
            <i class="bi bi-trophy-fill"></i>
            <span><strong>Clasificación final.</strong> Campeón: ' . $e($r['campeon']) . '</span>
        </div>';
    }

    if (in_array('cruces_desactualizados', $r['avisos'], true)) {
        $html .= '<div class="vt-ranking-aviso vt-ranking-alerta"><i class="bi bi-exclamation-triangle-fill"></i>
            <span>Se modificó un resultado de grupos después de generar las semifinales: los cruces ya creados pueden no coincidir con la tabla actual.</span></div>';
    }

    if (in_array('final_desactualizada', $r['avisos'], true)) {
        $html .= '<div class="vt-ranking-aviso vt-ranking-alerta"><i class="bi bi-exclamation-triangle-fill"></i>
            <span>Se modificó un resultado de semifinales después de generar la final: la final ya creada no coincide con los ganadores actuales.</span></div>';
    }

    $html .= '<table class="vt-tabla-grupo vt-tabla-ranking"><thead><tr>
        <th>#</th>
        <th style="text-align:left;">' . ($esIndividual ? 'Participante' : 'Pareja') . '</th>
        <th>Etapa</th><th>Grupo</th><th>PJ</th><th>PG</th><th>PP</th><th>Sets</th><th>Dif</th><th title="Puntos que este torneo le da a cada jugador en el ranking de su categoría">PTS</th>
    </tr></thead><tbody>';

    foreach ($r['filas'] as $f) {

        $dif = $f['games_favor'] - $f['games_contra'];
        $clase = $f['tier'] <= 1 && !$r['provisional'] ? ' class="vt-ranking-podio"' : '';

        $html .= '<tr' . $clase . '>
            <td>' . (int) $f['puesto'] . ($f['empate'] ? '<span title="Empate en todos los criterios de comparación">=</span>' : '') . '</td>
            <td style="text-align:left;"><strong>' . $e($f['nombre']) . '</strong></td>
            <td>' . $e($f['etapa']) . '</td>
            <td>' . $e($f['grupo']) . ' · ' . (int) $f['pos_grupo'] . '.º</td>
            <td>' . (int) $f['pj'] . '</td>
            <td>' . (int) $f['pg'] . '</td>
            <td>' . (int) $f['pp'] . '</td>
            <td>' . (int) $f['sets_favor'] . '-' . (int) $f['sets_contra'] . '</td>
            <td>' . ($dif > 0 ? '+' : '') . $dif . '</td>
            <td><strong>' . (int) $f['puntos_ranking'] . '</strong></td>
        </tr>';

    }

    $html .= '</tbody></table>
        <p class="vt-nota-datos">Cuentan solo partidos finalizados. Llegar a una ronda posterior pesa más que el rendimiento en grupos; entre eliminados en grupos se ordena por puesto en el grupo y luego por rendimiento promedio por partido. PTS son los puntos que este torneo le da a cada jugador en el ranking de su categoría (fase de grupos + cruces); en torneos de parejas, cada integrante recibe los de su pareja.</p>
    </div>';

    return $html;

}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Ver torneo - <?php echo htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/canchas.css">
    <link rel="stylesheet" href="css/ver_torneo.css?v=17">
    <link rel="stylesheet" href="css/grupos.css?v=27">
    <?php if ($modoPublico) { ?>
    <link rel="stylesheet" href="css/publico.css?v=3">
    <?php } ?>

</head>

<body
    class="panel-body<?php echo $modoPublico ? ' pub-detalle' : ''; ?>"
    data-tipo-torneo="<?php echo htmlspecialchars(
        $torneo['tipo'],
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
>

    <?php if ($modoPublico) { echo renderNavbarPublica('torneos'); } ?>

    <div class="panel vt-panel-oscuro<?php echo $modoPublico ? ' pub-panel' : ''; ?>">

        <?php if (!$modoPublico) { ?>

        <!-- SIDEBAR (igual al panel del organizador) -->

        <aside class="sidebar">

            <div class="logo-panel">

                <img
                    src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                    class="logo-panel-img"
                    alt="Logo The Bull"
                >

                <h2>The Bull</h2>

                <span><?php echo ($_SESSION['id_rol'] == 1) ? 'Administrador' : (($_SESSION['id_rol'] == 3) ? 'Participante' : 'Organizador'); ?></span>

            </div>

            <?php if ($_SESSION['id_rol'] == 3) { ?>

            <!-- Menú del participante (el mismo de su panel) -->
            <ul class="menu">

                <li class="activo">
                    <a href="paneles.php">
                        <i class="bi bi-trophy-fill"></i> Torneos
                    </a>
                </li>

                <li>
                    <a href="paneles.php?seccion=mis_inscripciones">
                        <i class="bi bi-clipboard-check-fill"></i> Mis inscripciones
                    </a>
                </li>

                <li>
                    <a href="paneles.php?seccion=ranking">
                        <i class="bi bi-bar-chart-fill"></i> Ranking
                    </a>
                </li>

                <li>
                    <a href="paneles.php?seccion=notificaciones">
                        <i class="bi bi-bell-fill"></i> Notificaciones
                    </a>
                </li>

                <li>
                    <a href="funciones/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                    </a>
                </li>

            </ul>

            <?php } else { ?>

            <ul class="menu">

                <li>
                    <a href="paneles.php?seccion=inicio">
                        <i class="bi bi-house-door-fill"></i> Panel
                    </a>
                </li>

                <li class="activo">
                    <a href="paneles.php?seccion=torneos">
                        <i class="bi bi-trophy-fill"></i> Torneos
                    </a>
                </li>

                <li>
                    <a href="paneles.php?seccion=canchas">
                        <i class="bi bi-geo-alt-fill"></i> Canchas
                    </a>
                </li>

                <li>
                    <a href="paneles.php?seccion=grupos">
                        <i class="bi bi-diagram-3"></i> Grupos
                    </a>
                </li>

                <li>
                    <a href="paneles.php?seccion=ranking">
                        <i class="bi bi-bar-chart-fill"></i> Ranking
                    </a>
                </li>

                <li>
                    <a href="paneles.php?seccion=notificaciones">
                        <i class="bi bi-bell-fill"></i> Notificaciones
                    </a>
                </li>

                <li>
                    <a href="funciones/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                    </a>
                </li>

            </ul>

            <?php } ?>

        </aside>

        <?php } ?>


        <!-- CONTENIDO -->

        <main class="contenido">
            <?php if ($modoPublico || $_SESSION['id_rol'] == 1 || $_SESSION['id_rol'] == 2) { ?>

    <!-- =========================================
         VISTA DEL ORGANIZADOR
         ========================================= -->


            <!-- ENCABEZADO -->

            <div class="vt-topbar">

                <div>

                    <span class="vt-estado-badge"><?php echo $eyebrowTorneo; ?></span>

                    <h1><?php echo htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></h1>

                    <?php if ($modoPublico) { ?>
                        <p class="pub-club-linea"><i class="bi bi-buildings-fill"></i> <?php echo htmlspecialchars($torneo['club'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php } ?>

                    <p>
                        Campeonato de pádel ·
                        <?php echo htmlspecialchars($torneo['tipo'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                </div>

                <div class="vt-topbar-acciones">

                    <?php if ($modoPublico) { ?>

                        <a href="torneos_publicos.php" class="vt-volver">
                            <i class="bi bi-arrow-left"></i> Volver al listado de torneos
                        </a>

                    <?php } else { ?>

                    <a
                        href="gestionar_grupos.php?id_torneo=<?php echo (int) $id_torneo; ?>"
                        class="vt-volver"
                    >
                        <i class="bi bi-diagram-3"></i> Gestionar grupos
                    </a>

                    <a href="paneles.php?seccion=torneos" class="vt-volver">
                        <i class="bi bi-arrow-left"></i> Volver a torneos
                    </a>

                    <?php } ?>

                </div>

            </div>


            <?php if ($modoPublico) { ?>

                <?php if ($torneo['estado'] === 'Abierto') { ?>

                    <div class="pub-cta">
                        <p><strong>Las inscripciones están abiertas.</strong> Para inscribirte necesitás una cuenta; después volvés acá y confirmás tu inscripción.</p>
                        <?php echo publicoBotonInscribirme($id_torneo, $torneo['nombre']); ?>
                    </div>

                <?php } else { ?>

                    <div class="pub-cta pub-cta-aviso">
                        <p><i class="bi bi-info-circle-fill"></i>
                            <?php echo $torneo['estado'] === 'Cerrado'
                                ? 'Las inscripciones de este torneo están cerradas.'
                                : ($torneo['estado'] === 'En curso'
                                    ? 'Este torneo ya está en curso: las inscripciones están cerradas.'
                                    : 'Este torneo ya finalizó: las inscripciones están cerradas.'); ?>
                        </p>
                    </div>

                <?php } ?>

            <?php } ?>

            <!-- TARJETAS DE INFO -->

            <div class="vt-info-cards">

                <div class="vt-info-card">
                    <div class="vt-info-icono categoria"><i class="bi bi-layers-fill"></i></div>
                    <div>
                        <span>Categoría</span>
                        <strong><?php echo htmlspecialchars($torneo['categoria'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>

                <div class="vt-info-card">
                    <div class="vt-info-icono fecha"><i class="bi bi-calendar-event-fill"></i></div>
                    <div>
                        <span>Fecha de inicio</span>
                        <strong><?php echo $fechaTorneoFormateada; ?></strong>
                    </div>
                </div>

                <div class="vt-info-card">
                    <div class="vt-info-icono horario"><i class="bi bi-alarm-fill"></i></div>
                    <div>
                        <span>Hora de inicio</span>
                        <strong><?php echo $horarioTorneo; ?></strong>
                    </div>
                </div>

                <div class="vt-info-card">
                    <div class="vt-info-icono estado"><i class="bi bi-check-circle-fill"></i></div>
                    <div>
                        <span>Estado</span>
                        <strong><?php echo htmlspecialchars($estadoTexto, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>

            </div>


            <?php if ($modoPublico) { ?>

            <div class="vt-info-cards">

                <div class="vt-info-card">
                    <div class="vt-info-icono categoria"><i class="bi bi-buildings-fill"></i></div>
                    <div>
                        <span>Club</span>
                        <strong><?php echo htmlspecialchars($torneo['club'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>

                <div class="vt-info-card">
                    <div class="vt-info-icono fecha"><i class="bi bi-geo-alt-fill"></i></div>
                    <div>
                        <span>Lugar</span>
                        <strong><?php echo htmlspecialchars($torneo['lugar'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>

                <div class="vt-info-card">
                    <div class="vt-info-icono horario"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <span>Modalidad</span>
                        <strong><?php echo htmlspecialchars(publicoModalidad($torneo['tipo']), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>

                <div class="vt-info-card">
                    <div class="vt-info-icono estado"><i class="bi bi-person-check-fill"></i></div>
                    <div>
                        <span>Confirmados</span>
                        <strong><?php echo (int) $confirmadosTorneo; ?> / <?php echo (int) $torneo['max_participantes']; ?></strong>
                    </div>
                </div>

            </div>

            <?php } ?>

            <!-- FASE DE GRUPOS + RANKING -->

            <nav class="grupos-subnav vt-tabs" id="gruposTabs" role="tablist" aria-label="Secciones del torneo" data-torneo="<?php echo (int) $id_torneo; ?>" data-default="<?php echo $rankingTorneo['provisional'] ? 'fase-de-grupos' : 'ranking-torneo'; ?>">
                <a href="#fase-de-grupos" role="tab" data-tab="fase-de-grupos"><i class="bi bi-people-fill"></i> Fase de grupos</a>
                <a href="#fase-final" role="tab" data-tab="fase-final"><i class="bi bi-trophy-fill"></i> Fase final</a>
                <?php if ($modoPublico) { ?>
                <a href="#partidos-publicos" role="tab" data-tab="partidos-publicos"><i class="bi bi-list-ol"></i> Partidos y resultados</a>
                <?php } ?>
                <a href="#ranking-torneo" role="tab" data-tab="ranking-torneo"><i class="bi bi-bar-chart-fill"></i> Ranking del torneo</a>
            </nav>

            <section id="fase-de-grupos" class="grupos-tab-panel">

            <div class="vt-dos-columnas vt-una-columna">

                <!-- FASE DE GRUPOS -->

                <div>

                    <div class="vt-seccion-titulo">
                        <span class="vt-eyebrow">COMPETENCIA</span>
                        <h2>Fase de grupos</h2>
                        <p>
                            <?php echo $gruposPersistidos
                                ? 'Distribución configurada por la organización'
                                : 'Distribución propuesta a partir de las inscripciones confirmadas'; ?>
                        </p>
                    </div>

                    <?php if (empty($grupos)) { ?>

                        <div class="vt-card vt-estado-vacio">
                            <i class="bi bi-people"></i>
                            <p>Todavía no hay participantes confirmados para este torneo.</p>
                        </div>

                    <?php } else { ?>

                    <div class="vt-card">

                        <div class="vt-card-header">

                            <div class="vt-grupo-nombre">

                                <div class="vt-grupo-letra" id="vtGrupoLetra">
                                    <?php echo htmlspecialchars($grupos[0]['letra'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>

                                <div>
                                    <strong id="vtGrupoNombre"><?php echo htmlspecialchars($grupos[0]['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span id="vtGrupoCantidad">
                                        <?php echo count($grupos[0]['parejas'] ?? $grupos[0]['participantes'] ?? []); ?>
                                        <?php echo htmlspecialchars($textoCantidad, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>

                            </div>

                            <div class="vt-grupo-nav">

                                <button type="button" id="vtGrupoAnterior" disabled>‹</button>
                                <button type="button" id="vtGrupoSiguiente" <?php echo count($grupos) <= 1 ? 'disabled' : ''; ?>>›</button>

                            </div>

                        </div>

                        <table class="vt-tabla-grupo" data-grupos='<?php echo htmlspecialchars(json_encode($grupos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>'>

                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th style="text-align:left;"><?php echo htmlspecialchars($textoColumna, ENT_QUOTES, 'UTF-8'); ?></th>
                                    <th>PJ</th>
                                    <th>PG</th>
                                    <th>PP</th>
                                    <th>Sets</th>
                                    <th>PTS</th>
                                </tr>
                            </thead>

                            <tbody id="vtGrupoTabla"></tbody>

                        </table>

                        <p class="vt-nota-datos">
                            <?php if ($gruposPersistidos) { ?>
                                * Distribución guardada por la organización. Las posiciones se calculan solo con los partidos finalizados (victoria = 3 pts).
                            <?php } else { ?>
                                * Distribución calculada con las <?php echo (int) count($entradasConfirmadas); ?>
                                <?php echo htmlspecialchars($textoCantidad, ENT_QUOTES, 'UTF-8'); ?> confirmadas actuales.
                                No es una fase guardada de forma permanente: puede cambiar si se confirman más inscripciones.
                                Las columnas PJ/PG/PTS están en cero porque todavía no hay partidos cargados.
                            <?php } ?>
                        </p>

                    </div>

                    <?php } ?>

                </div>


</div>


            </section>

<section id="fase-final" class="grupos-tab-panel">

<!-- CRUCES DEL TORNEO -->

            <div class="vt-seccion-titulo">
                <span class="vt-eyebrow">COMPETENCIA</span>
                <h2>Cruces del torneo</h2>
                <p>Llaves eliminatorias del campeonato</p>
            </div>

            <?php echo renderBracketTorneo($semis, $final, $rondasPrevias); ?>

</section>

<?php if ($modoPublico) { ?>
<section id="partidos-publicos" class="grupos-tab-panel">

    <div class="vt-seccion-titulo">
        <span class="vt-eyebrow">COMPETENCIA</span>
        <h2>Partidos y resultados</h2>
        <p>Fase de grupos · la fase final está en la pestaña «Fase final»</p>
    </div>

    <?php
    $hayPartidosPublicos = false;
    foreach ($partidosPublicos as $grupoPub) { if (!empty($grupoPub['partidos'])) { $hayPartidosPublicos = true; } }
    ?>

    <?php if (!$hayPartidosPublicos) { ?>

        <div class="vt-card vt-estado-vacio">
            <i class="bi bi-list-ol"></i>
            <p>Todavía no hay partidos cargados para este torneo.</p>
        </div>

    <?php } else { foreach ($partidosPublicos as $grupoPub) { if (empty($grupoPub['partidos'])) { continue; } ?>

        <div class="vt-card pub-partidos-grupo">

            <h3><?php echo htmlspecialchars($grupoPub['nombre'], ENT_QUOTES, 'UTF-8'); ?></h3>

            <?php foreach ($grupoPub['partidos'] as $partidoPub) { ?>
                <div class="pub-partido">
                    <span class="<?php echo $partidoPub['gano1'] ? 'pub-gano' : ''; ?>"><?php echo htmlspecialchars($partidoPub['n1'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="pub-marcador">
                        <?php if ($partidoPub['jugado']) { echo htmlspecialchars($partidoPub['resultado'], ENT_QUOTES, 'UTF-8'); } else { ?><span class="pub-pendiente">Pendiente</span><?php } ?>
                    </span>
                    <span class="pub-lado-2 <?php echo $partidoPub['gano2'] ? 'pub-gano' : ''; ?>"><?php echo htmlspecialchars($partidoPub['n2'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php } ?>

        </div>

    <?php } } ?>

    <p class="pub-nota">Resultados en games por set. Solo se muestran partidos reales registrados por la organización.</p>

</section>
<?php } ?>

<section id="ranking-torneo" class="grupos-tab-panel">

<!-- RANKING DEL TORNEO -->
<div class="vt-ranking-bloque">
<div class="vt-seccion-titulo">
                        <span class="vt-eyebrow">CLASIFICACIÓN</span>
                        <h2>Ranking del torneo</h2>
                        <p>Posición final de todos los participantes del torneo</p>
                    </div>

                    <?php echo renderRankingTorneo($rankingTorneo, $torneo['tipo'] === 'Individual'); ?>
</div>
</section>

<?php } elseif ($_SESSION['id_rol'] == 3) { ?>

    <!-- =========================================
     VISTA PARTICIPANTE
     ========================================= -->

<div class="vt-topbar">

    <div>

        <span class="vt-estado-badge">
            <?php echo htmlspecialchars(
                $estadoTexto,
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </span>

        <h1>
            <?php echo htmlspecialchars(
                $torneo['nombre'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </h1>

        <p>
            Campeonato de pádel ·
            <?php echo htmlspecialchars(
                $torneo['tipo'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </p>

    </div>


    <div class="vt-topbar-acciones">

    <?php if ($torneo['estado'] === 'Abierto') { ?>
        <a
            href="paneles.php?seccion=torneos&amp;inscribirme=<?php echo (int) $id_torneo; ?>"
            class="vt-volver"
        >
            <i class="bi bi-pencil-square"></i>
            Inscribirme
        </a>
    <?php } ?>

    <a
        href="paneles.php?seccion=torneos"
        class="vt-volver"
    >
        <i class="bi bi-arrow-left"></i>
        Volver a torneos
    </a>

    </div>

</div>


<!-- =========================================
     INFORMACIÓN DEL TORNEO
     ========================================= -->

<div class="vt-info-cards">

    <div class="vt-info-card">

        <div class="vt-info-icono categoria">
            <i class="bi bi-layers-fill"></i>
        </div>

        <div>
            <span>Categoría</span>

            <strong>
                <?php echo htmlspecialchars(
                    $torneo['categoria'],
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </strong>
        </div>

    </div>


    <div class="vt-info-card">

        <div class="vt-info-icono fecha">
            <i class="bi bi-calendar-event-fill"></i>
        </div>

        <div>
            <span>Fecha de inicio</span>

            <strong>
                <?php echo htmlspecialchars(
                    $fechaTorneoFormateada,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </strong>
        </div>

    </div>


    <div class="vt-info-card">

        <div class="vt-info-icono horario">
            <i class="bi bi-alarm-fill"></i>
        </div>

        <div>
            <span>Hora de inicio</span>

            <strong>
                <?php echo htmlspecialchars(
                    $horarioTorneo,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </strong>
        </div>

    </div>


    <div class="vt-info-card">

        <div class="vt-info-icono estado">
            <i class="bi bi-check-circle-fill"></i>
        </div>

        <div>
            <span>Estado</span>

            <strong>
                <?php echo htmlspecialchars(
                    $estadoTexto,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </strong>
        </div>

    </div>

</div>
<!-- =========================================
     FASE DE GRUPOS + RANKING
     ========================================= -->

<nav class="grupos-subnav vt-tabs" id="gruposTabs" role="tablist" aria-label="Secciones del torneo" data-torneo="<?php echo (int) $id_torneo; ?>" data-default="<?php echo $rankingTorneo['provisional'] ? 'fase-de-grupos' : 'ranking-torneo'; ?>">
                <a href="#fase-de-grupos" role="tab" data-tab="fase-de-grupos"><i class="bi bi-people-fill"></i> Fase de grupos</a>
                <a href="#fase-final" role="tab" data-tab="fase-final"><i class="bi bi-trophy-fill"></i> Fase final</a>
                <a href="#ranking-torneo" role="tab" data-tab="ranking-torneo"><i class="bi bi-bar-chart-fill"></i> Ranking del torneo</a>
            </nav>

            <section id="fase-de-grupos" class="grupos-tab-panel">

            <div class="vt-dos-columnas vt-una-columna">

    <!-- FASE DE GRUPOS -->
    <div>

        <div class="vt-seccion-titulo">
            <span class="vt-eyebrow">COMPETENCIA</span>
            <h2>Fase de grupos</h2>
            <p>
                <?php echo $gruposPersistidos
                    ? 'Distribución configurada por la organización'
                    : 'Distribución propuesta a partir de las inscripciones confirmadas'; ?>
            </p>
        </div>

        <?php if (empty($grupos)) { ?>

            <div class="vt-card vt-estado-vacio">
                <i class="bi bi-people"></i>
                <p>Todavía no hay participantes confirmados para este torneo.</p>
            </div>

        <?php } else { ?>

        <div class="vt-card">

            <div class="vt-card-header">

                <div class="vt-grupo-nombre">

                    <div class="vt-grupo-letra">
                        <?php echo htmlspecialchars($grupos[0]['letra'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>

                    <div>
                        <strong>
                            <?php echo htmlspecialchars($grupos[0]['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </strong>

                        <span>
                            <?php echo count($grupos[0]['parejas'] ?? $grupos[0]['participantes'] ?? []); ?>
                            <?php echo htmlspecialchars($textoCantidad, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                </div>

                <div class="vt-grupo-nav">
                    <button type="button" id="vtGrupoAnterior" disabled>‹</button>
                    <button type="button" id="vtGrupoSiguiente" <?php echo count($grupos) <= 1 ? 'disabled' : ''; ?>>›</button>
                </div>

            </div>

            <table
                class="vt-tabla-grupo"
                data-grupos='<?php echo htmlspecialchars(
                    json_encode($grupos, JSON_UNESCAPED_UNICODE),
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>'
            >

                <thead>
                    <tr>
                        <th>#</th>
                        <th style="text-align:left;"><?php echo htmlspecialchars($textoColumna, ENT_QUOTES, 'UTF-8'); ?></th>
                        <th>PJ</th>
                        <th>PG</th>
                                    <th>PP</th>
                                    <th>Sets</th>
                        <th>PTS</th>
                    </tr>
                </thead>

                <tbody id="vtGrupoTabla"></tbody>

            </table>

            <p class="vt-nota-datos">
                <?php if ($gruposPersistidos) { ?>
                    * Distribución guardada por la organización.
                <?php } else { ?>
                    * Distribución calculada con las <?php echo (int) count($entradasConfirmadas); ?>
                    <?php echo htmlspecialchars($textoCantidad, ENT_QUOTES, 'UTF-8'); ?> confirmadas actuales.
                    No es una fase guardada de forma permanente.
                <?php } ?>
            </p>

        </div>

        <?php } ?>

    </div>


</div>


</section>

<section id="fase-final" class="grupos-tab-panel">

<!-- CRUCES DEL TORNEO -->

<div class="vt-seccion-titulo">
    <span class="vt-eyebrow">COMPETENCIA</span>
    <h2>Cruces del torneo</h2>
    <p>Llaves eliminatorias del campeonato</p>
</div>

<?php echo renderBracketTorneo($semis, $final, $rondasPrevias); ?>

</section>

<section id="ranking-torneo" class="grupos-tab-panel">

<!-- RANKING DEL TORNEO -->
<div class="vt-ranking-bloque">
<div class="vt-seccion-titulo">
            <span class="vt-eyebrow">CLASIFICACIÓN</span>
            <h2>Ranking del torneo</h2>
            <p>Posición final de todos los participantes del torneo</p>
        </div>

        <?php echo renderRankingTorneo($rankingTorneo, $torneo['tipo'] === 'Individual'); ?>
</div>
</section>

<?php } ?>
        </main>
        


    </div>
    <?php if ($modoPublico) { echo renderModalInscripcionPublica(); } ?>
    <script src="js/padel.js?v=17"></script>
    <script src="js/pestanas.js?v=1"></script>
    <?php if ($modoPublico) { ?>
    <script src="js/publico.js?v=1"></script>
    <?php } ?>
</body>

</html>
