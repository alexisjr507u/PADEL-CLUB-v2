<?php

include("config/no_cache.php");
include("config/conexion.php");
require_once("funciones/csrf.php");
require_once("funciones/estado_grupos.php");
require_once("funciones/standings.php");
require_once("funciones/ranking_vista.php");


/* =========================================================
   SEGURIDAD: solo organizador (de su propio torneo) o admin
   ========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['id_rol'] != 1 && $_SESSION['id_rol'] != 2) {
    header("Location: paneles.php");
    exit;
}

$id_torneo = filter_input(INPUT_GET, 'id_torneo', FILTER_VALIDATE_INT);

if (!$id_torneo) {
    header("Location: paneles.php?seccion=torneos");
    exit;
}

$csrf_token = generar_csrf_token();

$esAdmin = ($_SESSION['id_rol'] == 1);


/* =========================================================
   TORNEO + VERIFICAR PERTENENCIA (si es organizador)
   ========================================================= */

$sqlTorneo = "
    SELECT torneos.id_torneo, torneos.nombre, torneos.tipo, torneos.estado,
           torneos.imagen, torneos.fecha_inicio, torneos.fecha_fin,
           categoria.nombre AS categoria, club.nombre AS nombre_club, club.id_usuario AS id_organizador
    FROM torneos
    INNER JOIN categoria ON categoria.id_categoria = torneos.id_categoria
    INNER JOIN club ON club.id_club = torneos.id_club
    WHERE torneos.id_torneo = ?
    LIMIT 1
";

$stmtTorneo = mysqli_prepare($conexion, $sqlTorneo);
mysqli_stmt_bind_param($stmtTorneo, "i", $id_torneo);
mysqli_stmt_execute($stmtTorneo);
$torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTorneo));

if (!$torneo) {
    header("Location: paneles.php?seccion=torneos");
    exit;
}

if (!$esAdmin && (int) $torneo['id_organizador'] !== (int) $_SESSION['id_usuario']) {
    header("Location: paneles.php?seccion=torneos&error=sin_permiso");
    exit;
}

$esIndividual = ($torneo['tipo'] === 'Individual');


/* =========================================================
   GRUPOS EXISTENTES + SUS INTEGRANTES
   ========================================================= */

$stmtGrupos = mysqli_prepare(
    $conexion,
    "SELECT id_grupo, nombre, orden FROM grupo WHERE id_torneo = ? ORDER BY orden ASC, id_grupo ASC"
);
mysqli_stmt_bind_param($stmtGrupos, "i", $id_torneo);
mysqli_stmt_execute($stmtGrupos);
$grupos = mysqli_fetch_all(mysqli_stmt_get_result($stmtGrupos), MYSQLI_ASSOC);

foreach ($grupos as &$grupo) {

    if ($esIndividual) {

        $stmtMiembros = mysqli_prepare(
            $conexion,
            "SELECT insc.id_inscripcion, u.nombre, u.apellido, gi.orden
             FROM grupo_inscripcion gi
             INNER JOIN inscripcion insc ON insc.id_inscripcion = gi.id_inscripcion
             INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
             WHERE gi.id_grupo = ? AND insc.estado = 'Confirmada'
             ORDER BY gi.orden ASC, gi.id_grupo_inscripcion ASC"
        );
        mysqli_stmt_bind_param($stmtMiembros, "i", $grupo['id_grupo']);
        mysqli_stmt_execute($stmtMiembros);
        $grupo['miembros'] = mysqli_fetch_all(mysqli_stmt_get_result($stmtMiembros), MYSQLI_ASSOC);

    } else {

        $stmtMiembros = mysqli_prepare(
            $conexion,
            "SELECT p.id_pareja,
                    u1.nombre AS nombre1, u1.apellido AS apellido1,
                    u2.nombre AS nombre2, u2.apellido AS apellido2,
                    gp.orden
             FROM grupo_pareja gp
             INNER JOIN pareja p ON p.id_pareja = gp.id_pareja
             INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
             INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
             INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
             INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
             WHERE gp.id_grupo = ?
             ORDER BY gp.orden ASC, gp.id_grupo_pareja ASC"
        );
        mysqli_stmt_bind_param($stmtMiembros, "i", $grupo['id_grupo']);
        mysqli_stmt_execute($stmtMiembros);
        $grupo['miembros'] = mysqli_fetch_all(mysqli_stmt_get_result($stmtMiembros), MYSQLI_ASSOC);

    }

    $datosPartidosGrupo = obtenerMiembrosYPartidosDeGrupo($conexion, $grupo['id_grupo'], $esIndividual);
    $grupo['standings'] = calcularStandings($datosPartidosGrupo['miembros'], $datosPartidosGrupo['partidos']);
    $grupo['partidos'] = $datosPartidosGrupo['partidos'];

    $grupo['nombres_entrantes'] = [];
    foreach ($datosPartidosGrupo['miembros'] as $m) {
        $idEnt = (int) $m['id_entrante'];
        $grupo['nombres_entrantes'][$idEnt] = ($esIndividual
            ? trim($m['nombre'] . ' ' . $m['apellido'])
            : trim($m['nombre1'] . ' ' . $m['apellido1']) . ' / ' . trim($m['nombre2'] . ' ' . $m['apellido2']))
            . (!empty($m['descalificado']) ? ' (descalificado)' : '');
    }

    $grupo['partidos_pendientes'] = 0;
    foreach ($grupo['partidos'] as $p) {
        if ($p['estado'] !== 'Jugado') {
            $grupo['partidos_pendientes']++;
        }
    }

}
unset($grupo);


/* =========================================================
   COLORES DE ÍCONO POR GRUPO + LISTADO GLOBAL DE PARTIDOS
   ========================================================= */

// Un solo acento (azul), para no romper la identidad visual del resto
// del sitio con colores que no se usan en ninguna otra pantalla.
$coloresGrupos = ['azul'];

$partidosGlobal = [];

foreach ($grupos as $indiceColor => $g) {
    foreach ($g['partidos'] as $p) {
        $p['grupo_nombre'] = $g['nombre'];
        $p['grupo_color'] = $coloresGrupos[$indiceColor % count($coloresGrupos)];
        $p['nombres_entrantes'] = $g['nombres_entrantes'];
        $partidosGlobal[] = $p;
    }
}

$hayAlgunPartido = !empty($partidosGlobal);

/* =========================================================
   QUÉ SE PUEDE HACER SEGÚN EL ESTADO DEL TORNEO (los endpoints lo vuelven a comprobar en el servidor;
   ver funciones/estado_grupos.php)
   - Abierto / Cerrado: todo, como siempre.
   - En curso con partidos de grupos ya generados: sin crear grupos, sin cambiar la distribución y sin
     regenerar partidos; sí resultados, fases siguientes y renombrar grupos.
   - Finalizado: solo consulta.
   ========================================================= */

$estadoTorneoPagina = $torneo['estado'];
$esFinalizado = ($estadoTorneoPagina === 'Finalizado');
$antesDeEmpezar = in_array($estadoTorneoPagina, ESTADOS_ANTES_DE_EMPEZAR, true);
$estructuraBloqueada = $esFinalizado || ($estadoTorneoPagina === 'En curso' && $hayAlgunPartido);
$puedeEstructura = !$estructuraBloqueada;
$puedeRenombrar = !$esFinalizado;
$puedeRondas = !$esFinalizado;

$algunGrupoSinPartidos = false;
foreach ($grupos as $grupoRevisado) {
    if (empty($grupoRevisado['partidos'])) {
        $algunGrupoSinPartidos = true;
    }
}

// «Generar partidos»: antes de empezar siempre; En curso solo para grupos que todavía no tienen partidos.
$puedeGenerarPartidos = !$esFinalizado && !empty($grupos) && ($antesDeEmpezar || $algunGrupoSinPartidos);



/* =========================================================
   FASE FINAL (SEMIFINALES/FINAL) — solo si hay exactamente 2 grupos
   ========================================================= */

$nombresEntrantesTorneo = [];
foreach ($grupos as $g) {
    $nombresEntrantesTorneo += $g['nombres_entrantes'];
}

// Con 2 grupos: semifinales y final. Con 4 u 8 grupos: cuadro completo (cuartos u octavos
// hasta la final). Otras cantidades no tienen cruces automáticos.
$modoCuadro = in_array(count($grupos), [4, 8], true);
$faseFinalDisponible = (count($grupos) === 2) || $modoCuadro;
$partidosFaseFinal = [];

if ($faseFinalDisponible) {

    $tablaPartidosFF = $esIndividual ? 'partido_individual' : 'partido_pareja';
    $tablaEntrantesFF = $esIndividual ? 'grupo_inscripcion' : 'grupo_pareja';
    $colEntranteFF = $esIndividual ? 'id_grupo_inscripcion' : 'id_grupo_pareja';

    $stmtFaseFinal = mysqli_prepare(
        $conexion,
        "SELECT p.id_partido, p.fase, p.orden, p.id_entrante_1, p.id_entrante_2,
                p.set1_games_1, p.set1_games_2, p.set2_games_1, p.set2_games_2,
                p.set3_games_1, p.set3_games_2, p.id_ganador, p.resultado_tipo, p.estado
         FROM $tablaPartidosFF p
         INNER JOIN $tablaEntrantesFF e ON e.$colEntranteFF = p.id_entrante_1
         INNER JOIN grupo g ON g.id_grupo = e.id_grupo
         WHERE g.id_torneo = ? AND p.fase IN ('Octavos', 'Cuartos', 'Semifinal', 'Final')
         ORDER BY FIELD(p.fase, 'Octavos', 'Cuartos', 'Semifinal', 'Final') ASC, p.orden ASC"
    );
    mysqli_stmt_bind_param($stmtFaseFinal, "i", $id_torneo);
    mysqli_stmt_execute($stmtFaseFinal);
    $partidosFaseFinal = mysqli_fetch_all(mysqli_stmt_get_result($stmtFaseFinal), MYSQLI_ASSOC);

}

$semisTotal = 0;
$semisJugadas = 0;
$finalGenerada = false;

foreach ($partidosFaseFinal as $pf) {
    if ($pf['fase'] === 'Semifinal') {
        $semisTotal++;
        if ($pf['estado'] === 'Jugado') {
            $semisJugadas++;
        }
    } elseif ($pf['fase'] === 'Final') {
        $finalGenerada = true;
    }
}

$semifinalesGeneradas = $semisTotal > 0;
$semifinalesCompletas = ($semisTotal === 2 && $semisJugadas === 2);

// Cuadro (4 u 8 grupos): estado de cada ronda y cuál es la próxima a generar.
$rondasCuadro = [];
foreach ($partidosFaseFinal as $pf) {
    $rondasCuadro[$pf['fase']][] = $pf;
}

$ordenRondasCuadro = ['Octavos', 'Cuartos', 'Semifinal', 'Final'];
$etiquetasRonda = ['Octavos' => 'Octavos de final', 'Cuartos' => 'Cuartos de final', 'Semifinal' => 'Semifinales', 'Final' => 'Final'];
$etiquetasBoton = ['Octavos' => 'Generar octavos de final', 'Cuartos' => 'Generar cuartos de final', 'Semifinal' => 'Generar semifinales', 'Final' => 'Generar final'];

$proximaRonda = null;
$rondaPendienteMensaje = '';

if ($modoCuadro) {

    $primera = count($grupos) === 8 ? 'Octavos' : 'Cuartos';
    $ultimaGenerada = null;

    foreach ($ordenRondasCuadro as $nombreRonda) {
        if (!empty($rondasCuadro[$nombreRonda])) {
            $ultimaGenerada = $nombreRonda;
        }
    }

    if ($ultimaGenerada === null) {
        $proximaRonda = $primera;
    } elseif ($ultimaGenerada !== 'Final') {
        $proximaRonda = $ordenRondasCuadro[array_search($ultimaGenerada, $ordenRondasCuadro, true) + 1];
    }

    $campeonCuadro = '';

    if (!empty($rondasCuadro['Final']) && $rondasCuadro['Final'][0]['estado'] === 'Jugado') {
        $campeonCuadro = $nombresEntrantesTorneo[(int) $rondasCuadro['Final'][0]['id_ganador']] ?? '';
    }

}

$gruposCompletosParaSemis = $faseFinalDisponible;

foreach ($grupos as $g) {
    if (count($g['miembros']) < 2 || $g['partidos_pendientes'] > 0 || empty($g['partidos'])) {
        $gruposCompletosParaSemis = false;
    }
}


/* =========================================================
   CONFIRMADOS SIN ASIGNAR A NINGÚN GRUPO
   ========================================================= */

$sinAsignar = [];

if ($esIndividual) {

    $stmtSinAsignar = mysqli_prepare(
        $conexion,
        "SELECT insc.id_inscripcion, u.nombre, u.apellido
         FROM inscripcion insc
         INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
         LEFT JOIN grupo_inscripcion gi ON gi.id_inscripcion = insc.id_inscripcion
         WHERE insc.id_torneo = ? AND insc.estado = 'Confirmada' AND gi.id_grupo_inscripcion IS NULL
         ORDER BY insc.id_inscripcion ASC"
    );

} else {

    $stmtSinAsignar = mysqli_prepare(
        $conexion,
        "SELECT p.id_pareja,
                u1.nombre AS nombre1, u1.apellido AS apellido1,
                u2.nombre AS nombre2, u2.apellido AS apellido2
         FROM pareja p
         INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
         INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
         INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
         INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
         LEFT JOIN grupo_pareja gp ON gp.id_pareja = p.id_pareja
         WHERE p.id_torneo = ? AND gp.id_grupo_pareja IS NULL
         ORDER BY p.id_pareja ASC"
    );

}

mysqli_stmt_bind_param($stmtSinAsignar, "i", $id_torneo);
mysqli_stmt_execute($stmtSinAsignar);
$sinAsignar = mysqli_fetch_all(mysqli_stmt_get_result($stmtSinAsignar), MYSQLI_ASSOC);


$mensajesAccion = [
    'grupo_creado'        => ['tipo' => 'exito', 'texto' => 'El grupo se creó correctamente.'],
    'grupo_renombrado'    => ['tipo' => 'exito', 'texto' => 'El nombre del grupo se actualizó.'],
    'grupo_reordenado'    => ['tipo' => 'exito', 'texto' => 'El orden de los grupos se actualizó.'],
    'grupo_eliminado'     => ['tipo' => 'exito', 'texto' => 'El grupo se eliminó. Los participantes y parejas del torneo no se vieron afectados.'],
    'asignado'            => ['tipo' => 'exito', 'texto' => 'Se asignó correctamente al grupo.'],
    'movido'              => ['tipo' => 'exito', 'texto' => 'Se movió correctamente de grupo.'],
    'quitado'             => ['tipo' => 'exito', 'texto' => 'Se quitó del grupo. Su inscripción al torneo no se vio afectada.'],
    'token'               => ['tipo' => 'error', 'texto' => 'Tu sesión expiró, volvé a intentarlo.'],
    'sin_permiso'         => ['tipo' => 'error', 'texto' => 'No tenés permiso sobre ese torneo.'],
    'no_existe'           => ['tipo' => 'error', 'texto' => 'Ese torneo no existe.'],
    'grupo_no_existe'     => ['tipo' => 'error', 'texto' => 'Ese grupo no existe en este torneo.'],
    'datos_invalidos'     => ['tipo' => 'error', 'texto' => 'Los datos enviados no son válidos.'],
    'torneo_liquidado'    => ['tipo' => 'error', 'texto' => 'Este torneo ya provocó ascensos de categoría: sus resultados quedaron fijos.'],
    'modalidad_incompatible' => ['tipo' => 'error', 'texto' => 'No se pueden mezclar inscripciones individuales con parejas.'],
    'elemento_no_disponible' => ['tipo' => 'error', 'texto' => 'Ese participante o pareja no está confirmado en este torneo.'],
    'elemento_no_asignado'   => ['tipo' => 'error', 'texto' => 'Ese participante o pareja no está asignado a ningún grupo de este torneo.'],
    'ya_asignado'         => ['tipo' => 'error', 'texto' => 'Ya está asignado a un grupo. Quitalo primero si querés moverlo.'],
    'tiene_partidos'      => ['tipo' => 'error', 'texto' => 'No se puede quitar/eliminar: ese grupo ya tiene partidos con resultados cargados.'],
    'error_bd'            => ['tipo' => 'error', 'texto' => 'Ocurrió un error al guardar. Intentá nuevamente.'],
    'partidos_generados'  => ['tipo' => 'exito', 'texto' => 'Se generaron los partidos del grupo (todos contra todos).'],
    'partidos_con_resultados' => ['tipo' => 'error', 'texto' => 'No se puede regenerar: ya hay resultados cargados en ese grupo.'],
    'miembros_insuficientes'  => ['tipo' => 'error', 'texto' => 'El grupo necesita al menos 2 participantes o parejas para generar partidos.'],
    'resultado_cargado'   => ['tipo' => 'exito', 'texto' => 'El resultado se cargó correctamente y los puntos del ranking ya se sumaron.'],
    'resultado_wo_cargado' => ['tipo' => 'exito', 'texto' => 'Se registró el W.O.: el partido quedó para quien se presentó, sin sets. Los puntos del ranking ya se sumaron.'],
    'resultado_invalido'  => ['tipo' => 'error', 'texto' => 'El resultado cargado no es válido. Revisá los sets.'],
    'faltan_sets'         => ['tipo' => 'error', 'texto' => 'Completá los games del set 1 y del set 2.'],
    'set_incompleto'      => ['tipo' => 'error', 'texto' => 'El set 3 tiene que tener los games de las dos parejas.'],
    'set_invalido'        => ['tipo' => 'error', 'texto' => 'Hay un set con un marcador imposible. Un set termina 6-0 a 6-4, 7-5 o 7-6.'],
    'falta_tercer_set'    => ['tipo' => 'error', 'texto' => 'Los sets quedaron 1 a 1: falta cargar el set 3.'],
    'set_sobrante'        => ['tipo' => 'error', 'texto' => 'El partido ya se definió en 2 sets: el set 3 no corresponde.'],
    'resultado_inconcluso' => ['tipo' => 'error', 'texto' => 'Ese resultado no define un ganador.'],
    'partido_no_existe'   => ['tipo' => 'error', 'texto' => 'Ese partido no existe en este torneo.'],
    'requiere_dos_grupos' => ['tipo' => 'error', 'texto' => 'El cruce de semifinales solo está disponible con exactamente 2 grupos.'],
    'grupos_incompletos'  => ['tipo' => 'error', 'texto' => 'Todavía faltan resultados por cargar en los grupos.'],
    'semifinales_ya_generadas' => ['tipo' => 'error', 'texto' => 'Las semifinales ya fueron generadas.'],
    'semifinales_generadas'    => ['tipo' => 'exito', 'texto' => 'Se generaron las semifinales.'],
    'requiere_semifinales'     => ['tipo' => 'error', 'texto' => 'Primero tenés que generar las semifinales.'],
    'semifinales_incompletas'  => ['tipo' => 'error', 'texto' => 'Todavía faltan resultados de las semifinales.'],
    'final_ya_generada'        => ['tipo' => 'error', 'texto' => 'La final ya fue generada.'],
    'final_generada'          => ['tipo' => 'exito', 'texto' => 'Se generó la final del torneo.'],
    'ronda_generada'         => ['tipo' => 'exito', 'texto' => 'Se generó la nueva ronda del cuadro.'],
    'ronda_incompleta'       => ['tipo' => 'error', 'texto' => 'Todavía faltan resultados en la ronda actual del cuadro.'],
    'ronda_ya_generada'      => ['tipo' => 'error', 'texto' => 'Esa ronda ya fue generada.'],
    'cuadro_completo'        => ['tipo' => 'error', 'texto' => 'El cuadro ya llegó a la final.'],
    'cuadro_no_soportado'    => ['tipo' => 'error', 'texto' => 'Los cruces automáticos están disponibles con 2, 4 u 8 grupos.'],
    'torneo_finalizado_grupos' => ['tipo' => 'error', 'texto' => 'El torneo ya finalizó: queda solo para consulta. No se pueden crear ni modificar grupos, partidos ni resultados.'],
    'grupos_ya_generados'    => ['tipo' => 'error', 'texto' => 'El torneo ya está en curso y sus grupos y partidos ya fueron generados: no se pueden crear grupos ni cambiar la distribución.'],
    'partidos_ya_generados'  => ['tipo' => 'error', 'texto' => 'Los partidos de fase de grupos ya fueron generados y el torneo está en curso: no se vuelven a generar.'],
];


/* =========================================================
   FORMATO DE FECHAS PARA LA PORTADA
   ========================================================= */

$mesesCortos = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

$fechaRangoTorneo = '';

if (!empty($torneo['fecha_inicio']) && !empty($torneo['fecha_fin'])) {

    $fechaInicioTs = strtotime($torneo['fecha_inicio']);
    $fechaFinTs = strtotime($torneo['fecha_fin']);

    $mesInicio = $mesesCortos[(int) date('n', $fechaInicioTs) - 1];
    $mesFin = $mesesCortos[(int) date('n', $fechaFinTs) - 1];

    if (date('Y-m', $fechaInicioTs) === date('Y-m', $fechaFinTs)) {
        $fechaRangoTorneo = date('d', $fechaInicioTs) . ' - ' . date('d', $fechaFinTs) . ' ' . $mesInicio . ' ' . date('Y', $fechaFinTs);
    } else {
        $fechaRangoTorneo = date('d', $fechaInicioTs) . ' ' . $mesInicio . ' - ' . date('d', $fechaFinTs) . ' ' . $mesFin . ' ' . date('Y', $fechaFinTs);
    }

}


/* =========================================================
   AVISO DE RESULTADO: cuando se guarda un partido, el mensaje
   se muestra dentro de ese mismo marcador (la página vuelve
   posicionada ahí), no arriba de todo.
   ========================================================= */

$avisoPartido = null;
$accionActual = $_GET['accion'] ?? '';
$idPartidoAviso = filter_input(INPUT_GET, 'partido', FILTER_VALIDATE_INT);

if ($idPartidoAviso && isset($mensajesAccion[$accionActual])) {
    $avisoPartido = ['partido' => $idPartidoAviso] + $mensajesAccion[$accionActual];
}


/**
 * Marcador de un partido con los games de cada set editables en el
 * lugar. Las casillas están en orden de columna (set por set) para que
 * el Tab vaya 6 → 4 → siguiente set, como se anota un resultado.
 */
function renderMarcador(array $partido, string $nombre1, string $nombre2, string $etiqueta, bool $esIndividual, string $csrfToken, int $idTorneo, ?array $aviso) {

    // Torneo finalizado: el marcador se ve pero no se puede editar.
    global $esFinalizado;
    $soloLectura = !empty($esFinalizado);

    $id = (int) $partido['id_partido'];
    $jugado = ($partido['estado'] === 'Jugado');
    $idGanador = $jugado ? (int) $partido['id_ganador'] : 0;
    $gano1 = $jugado && $idGanador === (int) $partido['id_entrante_1'];
    $gano2 = $jugado && $idGanador === (int) $partido['id_entrante_2'];
    $esWo = $jugado && (($partido['resultado_tipo'] ?? 'Normal') === 'WO');
    // En un W.O. pierde quien no ganó (value = lado que no se presentó).
    $woPerdedor = $esWo ? ($gano1 ? '2' : '1') : '';

    $e = function ($valor) {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    };
    $games = function ($campo) use ($partido) {
        return $partido[$campo] === null ? '' : (int) $partido[$campo];
    };
    ?>
    <form
        action="funciones/grupos_cargar_resultado.php"
        method="POST"
        class="marcador <?php echo $jugado ? 'marcador-jugado' : ''; ?> <?php echo $esWo ? 'marcador-es-wo' : ''; ?>"
        id="partido-<?php echo $id; ?>"
        <?php echo $soloLectura ? 'onsubmit="return false;"' : 'data-marcador'; ?>
        novalidate
    >
        <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
        <input type="hidden" name="id_torneo" value="<?php echo $idTorneo; ?>">
        <input type="hidden" name="tipo" value="<?php echo $esIndividual ? 'inscripcion' : 'pareja'; ?>">
        <input type="hidden" name="id_partido" value="<?php echo $id; ?>">

        <div class="marcador-cabecera">
            <span class="marcador-etiqueta"><?php echo $e($etiqueta); ?></span>
            <span class="marcador-estado <?php echo $jugado ? 'es-jugado' : 'es-pendiente'; ?>">
                <?php echo $jugado ? 'Finalizado' : 'Pendiente'; ?><?php echo $esWo ? ' · W.O.' : ''; ?>
            </span>
        </div>

        <div class="marcador-cuerpo">

            <div class="marcador-grilla">

                <span class="marcador-esquina"></span>

                <span class="marcador-nombre <?php echo $gano1 ? 'gano' : ''; ?>">
                    <?php if ($gano1) { ?><i class="bi bi-trophy-fill"></i><?php } ?>
                    <?php echo $e($nombre1); ?>
                </span>

                <span class="marcador-nombre <?php echo $gano2 ? 'gano' : ''; ?>">
                    <?php if ($gano2) { ?><i class="bi bi-trophy-fill"></i><?php } ?>
                    <?php echo $e($nombre2); ?>
                </span>

                <?php for ($set = 1; $set <= 3; $set++) { ?>

                    <span class="marcador-set-titulo">Set <?php echo $set; ?></span>

                    <?php foreach ([1 => $nombre1, 2 => $nombre2] as $lado => $nombreLado) { ?>
                        <input
                            type="number"
                            name="set<?php echo $set; ?>_<?php echo $lado; ?>"
                            value="<?php echo $games("set{$set}_games_{$lado}"); ?>"
                            min="0"
                            max="7"
                            inputmode="numeric"
                            class="marcador-input"
                            <?php echo $soloLectura ? 'readonly tabindex="-1"' : ''; ?>
                            <?php echo $set === 3 ? 'data-set3 placeholder="–"' : ''; ?>
                            aria-label="Set <?php echo $set; ?>, games de <?php echo $e($nombreLado); ?>"
                        >
                    <?php } ?>

                <?php } ?>

            </div>

            <?php if (!$soloLectura) { ?>
            <button type="submit" class="marcador-guardar">
                <i class="bi bi-check2"></i>
                <span><?php echo $jugado ? 'Actualizar' : 'Guardar'; ?></span>
            </button>
            <?php } ?>

        </div>

        <div class="marcador-wo">
            <label for="wo-<?php echo $id; ?>">Resultado</label>
            <select name="wo" id="wo-<?php echo $id; ?>" class="marcador-wo-select" <?php echo $soloLectura ? 'disabled' : ''; ?>>
                <option value="">Se jugó (cargar sets)</option>
                <option value="1" <?php echo $woPerdedor === '1' ? 'selected' : ''; ?>>W.O.: no se presentó <?php echo $e($nombre1); ?></option>
                <option value="2" <?php echo $woPerdedor === '2' ? 'selected' : ''; ?>>W.O.: no se presentó <?php echo $e($nombre2); ?></option>
            </select>
        </div>

        <p
            class="marcador-aviso <?php echo ($aviso && $aviso['partido'] === $id) ? 'marcador-aviso-' . $aviso['tipo'] : ''; ?>"
            data-marcador-aviso
            <?php echo ($aviso && $aviso['partido'] === $id) ? '' : 'hidden'; ?>
        >
            <?php echo ($aviso && $aviso['partido'] === $id) ? $e($aviso['texto']) : ''; ?>
        </p>

    </form>
    <?php

}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Grupos - <?php echo htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/ver_torneo.css?v=15">
    <link rel="stylesheet" href="css/grupos.css?v=29">
    <link rel="stylesheet" href="css/ranking.css?v=8">
    <link rel="stylesheet" href="css/confirmar.css?v=1">

</head>

<body class="panel-body">

    <div class="panel vt-panel-oscuro">

        <!-- SIDEBAR (igual al panel del organizador) -->

        <aside class="sidebar">

            <div class="logo-panel">

                <img
                    src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                    class="logo-panel-img"
                    alt="Logo The Bull"
                >

                <h2>The Bull</h2>

                <span><?php echo ($_SESSION['id_rol'] == 1) ? 'Administrador' : 'Organizador'; ?></span>

            </div>

            <ul class="menu">

                <li>
                    <a href="paneles.php?seccion=inicio">
                        <i class="bi bi-house-door-fill"></i> Panel
                    </a>
                </li>

                <li>
                    <a href="paneles.php?seccion=torneos">
                        <i class="bi bi-trophy-fill"></i> Torneos
                    </a>
                </li>

                <li>
                    <a href="paneles.php?seccion=canchas">
                        <i class="bi bi-geo-alt-fill"></i> Canchas
                    </a>
                </li>

                <li class="activo">
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

        </aside>

        <main class="contenido" style="width:100%; padding: 32px;">

            <div class="grupos-cabecera">

            <a href="paneles.php?seccion=torneos" class="vt-volver">
                <i class="bi bi-arrow-left"></i> Volver a torneos
            </a>

            <div class="grupos-hero">

                <div class="grupos-hero-contenido">

                    <span class="vt-estado-badge"><?php echo htmlspecialchars($torneo['estado'], ENT_QUOTES, 'UTF-8'); ?></span>

                    <h1>Grupos de <?php echo htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></h1>

                    <p>
                        <?php echo htmlspecialchars($torneo['tipo'], ENT_QUOTES, 'UTF-8'); ?> ·
                        <?php echo htmlspecialchars($torneo['categoria'], ENT_QUOTES, 'UTF-8'); ?> ·
                        <?php echo htmlspecialchars($torneo['nombre_club'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                </div>

                <div class="grupos-hero-info">

                    <div class="grupos-hero-info-icono">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>

                    <strong class="grupos-hero-fecha"><?php echo htmlspecialchars($fechaRangoTorneo, ENT_QUOTES, 'UTF-8'); ?></strong>

                    <a href="ver_torneo.php?id=<?php echo (int) $id_torneo; ?>" class="grupos-hero-boton">
                        Ver torneo <i class="bi bi-box-arrow-up-right"></i>
                    </a>

                </div>

            </div>

            <?php
                $etiquetaMiembro = $esIndividual ? 'participante' : 'pareja';
                $etiquetaMiembroPlural = $esIndividual ? 'participantes' : 'parejas';
            ?>


            <!-- ========================================= -->
            <!-- NAVEGACIÓN ENTRE SECCIONES -->
            <!-- ========================================= -->

            <nav class="grupos-subnav" id="gruposTabs" role="tablist" aria-label="Secciones de la página" data-torneo="<?php echo (int) $id_torneo; ?>">
                <a href="#posiciones" role="tab" data-tab="posiciones"><i class="bi bi-bar-chart-fill"></i> Posiciones y resultados</a>
                <?php if ($faseFinalDisponible) { ?>
                    <a href="#fase-final" role="tab" data-tab="fase-final"><i class="bi bi-trophy-fill"></i> Fase final</a>
                <?php } ?>
                <a href="#ranking-puntos" role="tab" data-tab="ranking-puntos"><i class="bi bi-award-fill"></i> Puntos del ranking</a>
                <a href="#administrar-grupos" role="tab" data-tab="administrar-grupos"><i class="bi bi-people-fill"></i> Administrar grupos</a>
            </nav>

            </div>

            <?php if (!$avisoPartido && isset($_GET['accion']) && isset($mensajesAccion[$_GET['accion']])) {
                $m = $mensajesAccion[$_GET['accion']];
                $icono = $m['tipo'] === 'exito' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
                $clase = $m['tipo'] === 'exito' ? 'mensaje-exito' : 'mensaje-error';
            ?>
                <div class="<?php echo $clase; ?> mensaje-error-compacto" style="margin-top:16px;">
                    <i class="bi <?php echo $icono; ?>"></i> <?php echo htmlspecialchars($m['texto']); ?>
                </div>
            <?php } ?>



            <?php if ($esFinalizado) { ?>
                <div class="rk-estado rk-estado-espera" style="margin-top:16px;">
                    <i class="bi bi-lock-fill"></i>
                    <div>
                        <strong>Torneo finalizado: solo consulta</strong>
                        <span>Los grupos, las posiciones y los resultados quedan como historial. Ya no se pueden crear ni modificar grupos, generar partidos ni cargar resultados.</span>
                    </div>
                </div>
            <?php } elseif ($estadoTorneoPagina === 'En curso' && $hayAlgunPartido) { ?>
                <div class="rk-estado rk-estado-espera" style="margin-top:16px;">
                    <i class="bi bi-lock-fill"></i>
                    <div>
                        <strong>Torneo en curso: grupos y partidos ya generados</strong>
                        <span>No se pueden crear grupos, cambiar la distribución ni volver a generar los partidos de grupos. Podés cargar resultados y generar las fases siguientes.</span>
                    </div>
                </div>
            <?php } ?>

            <!-- ========================================= -->
            <!-- 1. POSICIONES Y RESULTADOS -->
            <!-- ========================================= -->

            <section id="posiciones" class="grupos-bloque grupos-tab-panel">

                <div class="grupos-seccion-header">

                    <div class="vt-seccion-titulo" style="margin:0;">
                        <span class="vt-eyebrow">COMPETENCIA</span>
                        <h2>Posiciones y resultados</h2>
                        <p>Cargá los games de cada set directamente en el partido; la tabla se actualiza al guardar.</p>
                    </div>

                    <?php if ($puedeGenerarPartidos) { ?>
                        <form action="funciones/grupos_generar_partidos_torneo.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                            <button
                                type="submit"
                                class="btn-torneo editar grupos-btn-fixture"
                                title="Arma todos los cruces de cada grupo. Los grupos que ya tienen resultados cargados no se tocan."
                            >
                                <i class="bi bi-shuffle"></i>
                                <span><?php echo ($hayAlgunPartido && $antesDeEmpezar) ? 'Regenerar partidos' : 'Generar partidos'; ?></span>
                            </button>
                        </form>
                    <?php } ?>

                </div>

                <?php if (empty($grupos)) { ?>

                    <div class="vt-card vt-estado-vacio">
                        <i class="bi bi-diagram-3"></i>
                        <p>Todavía no hay grupos. <a href="#administrar-grupos">Creá el primero</a> para empezar a cargar resultados.</p>
                    </div>

                <?php } else { ?>

                    <!-- Sin JavaScript se muestran todos los grupos uno debajo del otro. -->
                    <noscript><style>.grupos-panel[hidden] { display: block; }</style></noscript>

                    <?php if (count($grupos) > 1) { ?>
                        <div class="grupos-selector" role="tablist" aria-label="Grupos">
                            <?php foreach ($grupos as $indiceChip => $grupoChip) {

                                $totalChip = count($grupoChip['partidos']);
                                $jugadosChip = $totalChip - $grupoChip['partidos_pendientes'];
                                $completoChip = $totalChip > 0 && $jugadosChip === $totalChip;
                            ?>
                                <button type="button" class="grupos-chip<?php echo $completoChip ? ' es-completo' : ''; ?>" data-grupo-chip="<?php echo (int) $indiceChip; ?>" role="tab"
                                        title="<?php echo $jugadosChip; ?> de <?php echo $totalChip; ?> partidos con resultado">
                                    <span><?php echo htmlspecialchars($grupoChip['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <i class="bi <?php echo $completoChip ? 'bi-check-circle-fill' : 'bi-circle'; ?>"></i>
                                </button>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="grupos-paneles grupos-carrusel" id="gruposCarrusel" data-torneo="<?php echo (int) $id_torneo; ?>">

                        <?php foreach ($grupos as $indiceGrupo => $grupo) {

                            $totalPartidos = count($grupo['partidos']);

                            // Letra de la insignia: la inicial del nombre sin el prefijo "Grupo".
                            $nombreSinPrefijo = trim(preg_replace('/^grupo\s+/iu', '', $grupo['nombre']));
                            $letraGrupo = preg_match('/[\p{L}\d]/u', $nombreSinPrefijo, $mLetra) ? strtoupper($mLetra[0]) : '#';
                            $jugados = $totalPartidos - $grupo['partidos_pendientes'];
                            $porcentaje = $totalPartidos > 0 ? round($jugados * 100 / $totalPartidos) : 0;
                        ?>

                            <article class="grupos-panel" data-indice="<?php echo (int) $indiceGrupo; ?>"<?php echo $indiceGrupo > 0 ? ' hidden' : ''; ?>>

                                <header class="grupos-panel-header">

                                    <div class="grupos-panel-titulo">
                                        <span class="grupos-letra" aria-hidden="true"><?php echo htmlspecialchars($letraGrupo, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <h3><?php echo htmlspecialchars(preg_match('/^grupo\b/iu', $grupo['nombre']) ? $grupo['nombre'] : 'Grupo ' . $grupo['nombre'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <span class="grupos-pill">
                                            <?php echo count($grupo['miembros']); ?>
                                            <?php echo count($grupo['miembros']) === 1 ? $etiquetaMiembro : $etiquetaMiembroPlural; ?>
                                        </span>
                                    </div>

                                    <?php if ($totalPartidos > 0) { ?>
                                        <div class="grupos-progreso" title="<?php echo $jugados; ?> de <?php echo $totalPartidos; ?> partidos con resultado">
                                            <span><?php echo $jugados; ?>/<?php echo $totalPartidos; ?> partidos jugados</span>
                                            <div class="grupos-progreso-barra">
                                                <div style="width: <?php echo (int) $porcentaje; ?>%;"></div>
                                            </div>
                                        </div>
                                    <?php } ?>

                                    <?php if (count($grupos) > 1) { ?>
                                        <div class="grupos-nav-carrusel">
                                            <button type="button" data-grupo-dir="-1" aria-label="Grupo anterior"><i class="bi bi-arrow-left"></i></button>
                                            <button type="button" data-grupo-dir="1" aria-label="Grupo siguiente"><i class="bi bi-arrow-right"></i></button>
                                        </div>
                                    <?php } ?>

                                </header>

                                <div class="grupos-panel-cuerpo">

                                    <!-- Tabla de posiciones -->
                                    <div class="grupos-panel-tabla">

                                        <?php if (empty($grupo['standings'])) { ?>

                                            <div class="grupos-posiciones-vacio">
                                                <i class="bi bi-bar-chart"></i>
                                                <span>Agregá <?php echo $etiquetaMiembroPlural; ?> al grupo en <a href="#administrar-grupos">Administrar grupos</a>.</span>
                                            </div>

                                        <?php } else { ?>

                                            <div class="grupos-tabla-scroll">
                                                <table class="grupos-tabla-standings">
                                                    <thead>
                                                        <tr>
                                                            <th class="col-pos">#</th>
                                                            <th class="col-nombre"><?php echo ucfirst($etiquetaMiembro); ?></th>
                                                            <th class="col-num" title="Partidos jugados">PJ</th>
                                                            <th class="col-num" title="Partidos ganados">PG</th>
                                                            <th class="col-num col-extra" title="Partidos perdidos">PP</th>
                                                            <th class="col-sets col-extra" title="Sets ganados - sets perdidos">Sets</th>
                                                            <th class="col-num col-pts" title="Puntos (3 por victoria)">PTS</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($grupo['standings'] as $posicion => $fila) {

                                                        ?>
                                                            <tr class="<?php echo $posicion < CLASIFICAN_POR_GRUPO ? 'grupos-standings-clasifica' : ''; ?>">
                                                                <td class="col-pos"><span class="grupos-posicion"><?php echo $posicion + 1; ?></span><?php if (!empty($fila['empate'])) { ?><span title="Empate sin resolver en todos los criterios" style="margin-left:4px;color:var(--gr-alerta);">=</span><?php } ?></td>
                                                                <td class="col-nombre">
                                                                    <span class="grupos-avatar" aria-hidden="true"><?php echo htmlspecialchars($letraGrupo, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                    <span class="grupos-nombre-texto"><?php echo htmlspecialchars($grupo['nombres_entrantes'][$fila['id_entrante']] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                                                </td>
                                                                <td class="col-num"><?php echo (int) $fila['pj']; ?></td>
                                                                <td class="col-num"><?php echo (int) $fila['pg']; ?></td>
                                                                <td class="col-num col-extra"><?php echo (int) $fila['pj'] - (int) $fila['pg']; ?></td>
                                                                <td class="col-sets col-extra"><?php echo (int) $fila['sets_favor']; ?>-<?php echo (int) $fila['sets_contra']; ?></td>
                                                                <td class="col-num col-pts"><?php echo (int) $fila['pts']; ?></td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <p class="grupos-nota-card">
                                                <span class="grupos-leyenda-clasifica"></span>
                                                Clasifican los <?php echo CLASIFICAN_POR_GRUPO; ?> primeros · Victoria 3 pts, derrota 0 · Desempate: dif. de sets, dif. de games, games ganados y enfrentamiento directo
                                            </p>

                                        <?php } ?>

                                    </div>

                                    <!-- Partidos del grupo -->
                                    <div class="grupos-panel-partidos">

                                        <h4 class="grupos-seccion-titulo">Partidos</h4>

                                        <?php if (count($grupo['miembros']) < 2) { ?>

                                            <p class="grupos-tarjeta-vacio">Con al menos 2 <?php echo $etiquetaMiembroPlural; ?> en el grupo se pueden generar sus partidos.</p>

                                        <?php } elseif (empty($grupo['partidos'])) { ?>

                                            <p class="grupos-tarjeta-vacio">Todavía no hay partidos. Usá <strong>Generar partidos</strong> (arriba) para armar el todos contra todos.</p>

                                        <?php } else { ?>

                                            <div class="grupos-marcadores">
                                                <?php foreach ($grupo['partidos'] as $numeroPartido => $partido) {
                                                    renderMarcador(
                                                        $partido,
                                                        $grupo['nombres_entrantes'][(int) $partido['id_entrante_1']] ?? '—',
                                                        $grupo['nombres_entrantes'][(int) $partido['id_entrante_2']] ?? '—',
                                                        'Partido ' . ($numeroPartido + 1),
                                                        $esIndividual,
                                                        $csrf_token,
                                                        (int) $id_torneo,
                                                        $avisoPartido
                                                    );
                                                } ?>
                                            </div>

                                        <?php } ?>

                                    </div>

                                </div>

                            </article>

                        <?php } ?>

                    </div>

                <?php } ?>

            </section>


            <!-- ========================================= -->
            <!-- 2. FASE FINAL (SEMIFINALES / FINAL) -->
            <!-- ========================================= -->

            <?php if ($faseFinalDisponible) { ?>

                <section id="fase-final" class="grupos-bloque grupos-tab-panel">

                    <div class="vt-seccion-titulo">
                        <span class="vt-eyebrow">PLAYOFFS</span>
                        <h2>Fase final</h2>
                        <?php if ($modoCuadro) { ?>
                            <p>
                                Clasifican los 2 primeros de cada grupo. Cruces por pares de grupos
                                (<?php echo htmlspecialchars($grupos[0]['nombre'], ENT_QUOTES, 'UTF-8'); ?>-<?php echo htmlspecialchars($grupos[1]['nombre'], ENT_QUOTES, 'UTF-8'); ?>:
                                1° de uno vs 2° del otro y viceversa); después los ganadores avanzan en el orden del cuadro hasta la final.
                            </p>
                        <?php } else { ?>
                        <p>
                            1° de <?php echo htmlspecialchars($grupos[0]['nombre'], ENT_QUOTES, 'UTF-8'); ?> vs 2° de <?php echo htmlspecialchars($grupos[1]['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            · 1° de <?php echo htmlspecialchars($grupos[1]['nombre'], ENT_QUOTES, 'UTF-8'); ?> vs 2° de <?php echo htmlspecialchars($grupos[0]['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            · los ganadores juegan la final.
                        </p>
                        <?php } ?>
                    </div>

                    <div class="vt-card grupos-fase-final">

                        <?php if ($modoCuadro) { ?>

                            <?php
                            // Pestañas por ronda: se muestra una ronda por vez (la última generada al entrar).
                            $rondasGeneradas = [];
                            foreach ($ordenRondasCuadro as $nombreRonda) {
                                if (!empty($rondasCuadro[$nombreRonda])) {
                                    $rondasGeneradas[] = $nombreRonda;
                                }
                            }
                            $rondaActivaInicial = end($rondasGeneradas);
                            ?>

                            <?php if (!empty($rondasGeneradas)) { ?>

                                <noscript><style>.grupos-ronda-panel[hidden] { display: block; }</style></noscript>

                                <div class="grupos-selector grupos-subtabs" id="rondasTabs" role="tablist" aria-label="Rondas del cuadro" data-torneo="<?php echo (int) $id_torneo; ?>" data-rondas="<?php echo count($rondasGeneradas); ?>" data-inicial="<?php echo htmlspecialchars($rondaActivaInicial, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php foreach ($rondasGeneradas as $nombreRonda) {

                                        $totalRonda = count($rondasCuadro[$nombreRonda]);
                                        $jugadosRonda = 0;
                                        foreach ($rondasCuadro[$nombreRonda] as $pr) {
                                            if ($pr['estado'] === 'Jugado') {
                                                $jugadosRonda++;
                                            }
                                        }
                                        $completaRonda = $jugadosRonda === $totalRonda;
                                    ?>
                                        <button type="button" class="grupos-chip<?php echo $completaRonda ? ' es-completo' : ''; ?>" data-ronda-tab="<?php echo htmlspecialchars($nombreRonda, ENT_QUOTES, 'UTF-8'); ?>" role="tab"
                                                title="<?php echo $jugadosRonda; ?> de <?php echo $totalRonda; ?> partidos con resultado">
                                            <span><?php echo htmlspecialchars($etiquetasRonda[$nombreRonda], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <i class="bi <?php echo $completaRonda ? 'bi-check-circle-fill' : 'bi-circle'; ?>"></i>
                                        </button>
                                    <?php } ?>
                                </div>

                                <div class="grupos-rondas" id="gruposRondas">

                                    <?php foreach ($rondasGeneradas as $nombreRonda) { ?>

                                        <div class="grupos-ronda-panel" data-ronda-panel="<?php echo htmlspecialchars($nombreRonda, ENT_QUOTES, 'UTF-8'); ?>" role="tabpanel"<?php echo $nombreRonda !== $rondaActivaInicial ? ' hidden' : ''; ?>>

                                            <div class="grupos-marcadores grupos-marcadores-final">

                                                <?php foreach ($rondasCuadro[$nombreRonda] as $partido) {

                                                    $etiquetaPartido = $nombreRonda === 'Final'
                                                        ? 'Final'
                                                        : ($nombreRonda === 'Semifinal' ? 'Semifinal ' : ($nombreRonda === 'Cuartos' ? 'Cuartos ' : 'Octavos ')) . (int) $partido['orden'];

                                                    renderMarcador(
                                                        $partido,
                                                        $nombresEntrantesTorneo[(int) $partido['id_entrante_1']] ?? '—',
                                                        $nombresEntrantesTorneo[(int) $partido['id_entrante_2']] ?? '—',
                                                        $etiquetaPartido,
                                                        $esIndividual,
                                                        $csrf_token,
                                                        (int) $id_torneo,
                                                        $avisoPartido
                                                    );
                                                } ?>

                                            </div>

                                        </div>

                                    <?php } ?>

                                </div>

                            <?php } ?>
                            <?php if ($campeonCuadro !== '') { ?>

                                <div class="vt-campeon" style="margin-top:16px;">
                                    <i class="bi bi-trophy-fill"></i>
                                    <strong>CAMPEÓN: <?php echo htmlspecialchars($campeonCuadro, ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>

                            <?php } elseif ($proximaRonda !== null) {

                                // La ronda se habilita cuando la anterior (o los grupos) está completa.
                                $anteriorCompleta = true;
                                $textoEspera = '';

                                if (empty($rondasCuadro)) {
                                    $anteriorCompleta = $gruposCompletosParaSemis;
                                    $textoEspera = 'La primera ronda se habilita cuando cada grupo tenga al menos 2 integrantes y todos sus partidos con resultado.';
                                } else {
                                    foreach ($rondasCuadro as $partidosRonda) {
                                        foreach ($partidosRonda as $pr) {
                                            if ($pr['estado'] !== 'Jugado') {
                                                $anteriorCompleta = false;
                                            }
                                        }
                                    }
                                    $textoEspera = 'La siguiente ronda se habilita cuando todos los partidos de la ronda actual tengan resultado.';
                                }
                            ?>

                                <?php if ($anteriorCompleta) { ?>

                                    <?php if ($puedeRondas) { ?>
                                    <form action="funciones/grupos_generar_ronda.php" method="POST" style="margin-top:16px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                        <button type="submit" class="btn-torneo editar grupos-btn-fixture">
                                            <i class="bi bi-diagram-3"></i>
                                            <span><?php echo htmlspecialchars($etiquetasBoton[$proximaRonda], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </button>
                                    </form>
                                    <?php } else { ?>
                                        <p class="grupos-tarjeta-vacio" style="margin-top:16px;">Torneo finalizado: ya no se generan rondas.</p>
                                    <?php } ?>

                                <?php } else { ?>

                                    <p class="grupos-tarjeta-vacio" style="margin-top:16px;"><?php echo htmlspecialchars($textoEspera, ENT_QUOTES, 'UTF-8'); ?></p>

                                <?php } ?>

                            <?php } ?>

                        <?php } elseif (!$semifinalesGeneradas) { ?>

                            <?php if ($gruposCompletosParaSemis) { ?>

                                <?php if ($puedeRondas) { ?>
                                <form action="funciones/grupos_generar_semifinales.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                    <button type="submit" class="btn-torneo editar grupos-btn-fixture">
                                        <i class="bi bi-diagram-3"></i>
                                        <span>Generar semifinales</span>
                                    </button>
                                </form>
                                <?php } else { ?>
                                    <p class="grupos-tarjeta-vacio">Torneo finalizado: ya no se generan rondas.</p>
                                <?php } ?>

                            <?php } else { ?>

                                <p class="grupos-tarjeta-vacio">
                                    Las semifinales se habilitan cuando cada grupo tenga al menos 2 integrantes y todos sus partidos con resultado.
                                </p>

                            <?php } ?>

                        <?php } else { ?>

                            <div class="grupos-marcadores grupos-marcadores-final">

                                <?php
                                $nombreCampeon = '';

                                foreach ($partidosFaseFinal as $partido) {

                                    $etiquetaFase = $partido['fase'] === 'Final'
                                        ? 'Final'
                                        : 'Semifinal ' . (int) $partido['orden'];

                                    if ($partido['fase'] === 'Final' && $partido['estado'] === 'Jugado') {
                                        $nombreCampeon = $nombresEntrantesTorneo[(int) $partido['id_ganador']] ?? '';
                                    }

                                    renderMarcador(
                                        $partido,
                                        $nombresEntrantesTorneo[(int) $partido['id_entrante_1']] ?? '—',
                                        $nombresEntrantesTorneo[(int) $partido['id_entrante_2']] ?? '—',
                                        $etiquetaFase,
                                        $esIndividual,
                                        $csrf_token,
                                        (int) $id_torneo,
                                        $avisoPartido
                                    );
                                }
                                ?>

                            </div>

                            <?php if (!$finalGenerada) { ?>

                                <?php if ($semifinalesCompletas) { ?>

                                    <?php if ($puedeRondas) { ?>
                                    <form action="funciones/grupos_generar_final.php" method="POST" style="margin-top:16px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                        <button type="submit" class="btn-torneo editar grupos-btn-fixture">
                                            <i class="bi bi-trophy-fill"></i>
                                            <span>Generar final</span>
                                        </button>
                                    </form>
                                    <?php } else { ?>
                                        <p class="grupos-tarjeta-vacio" style="margin-top:16px;">Torneo finalizado: ya no se generan rondas.</p>
                                    <?php } ?>

                                <?php } else { ?>

                                    <p class="grupos-tarjeta-vacio" style="margin-top:16px;">La final se habilita cuando las dos semifinales tengan resultado.</p>

                                <?php } ?>

                            <?php } elseif ($nombreCampeon !== '') { ?>

                                <div class="vt-campeon" style="margin-top:16px;">
                                    <i class="bi bi-trophy-fill"></i>
                                    <strong>CAMPEÓN: <?php echo htmlspecialchars($nombreCampeon, ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>

                            <?php } ?>

                        <?php } ?>

                    </div>

                </section>

            <?php } ?>


            <!-- ========================================= -->
            <!-- 3. ADMINISTRAR GRUPOS -->
            <!-- ========================================= -->

            <section id="ranking-puntos" class="grupos-bloque grupos-tab-panel">

                <div class="vt-seccion-titulo">
                    <span class="vt-eyebrow">RANKING</span>
                    <h2>Puntos del ranking</h2>
                    <p>Puntos que este torneo le da a cada jugador en el ranking de <?php echo htmlspecialchars($torneo['categoria'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                </div>

                <?php echo renderPuntosTorneo($conexion, (int) $id_torneo, $csrf_token); ?>

            </section>

            <section id="administrar-grupos" class="grupos-bloque grupos-tab-panel">

                <div class="grupos-seccion-header">

                    <div class="vt-seccion-titulo" style="margin:0;">
                        <span class="vt-eyebrow">ORGANIZACIÓN</span>
                        <h2>Administrar grupos</h2>
                        <p><?php echo $puedeEstructura
                            ? 'Creá grupos y asigná, mové o quitá ' . $etiquetaMiembroPlural . '.'
                            : ($esFinalizado
                                ? 'Los grupos quedaron como historial del torneo: solo se pueden consultar.'
                                : 'Los grupos y sus partidos ya fueron generados: la distribución quedó fija mientras el torneo está en curso.'); ?></p>
                    </div>

                    <?php if (!empty($grupos)) { ?>
                        <div class="grupos-buscador">
                            <i class="bi bi-search"></i>
                            <input type="text" id="buscarGrupo" placeholder="Buscar grupo..." aria-label="Buscar grupo">
                        </div>
                    <?php } ?>

                </div>

                <?php if (!$puedeEstructura) { ?>

                    <div class="vt-card grupos-crear-card">
                        <p class="grupos-tarjeta-vacio" style="margin:0;">
                            <i class="bi bi-lock-fill"></i>
                            <?php echo $esFinalizado
                                ? 'Torneo finalizado: no se pueden crear ni modificar grupos.'
                                : 'Torneo en curso con los grupos y partidos ya generados: no se pueden crear grupos ni cambiar la distribución.'; ?>
                        </p>
                    </div>

                <?php } else { ?>

                <div class="vt-card grupos-crear-card">

                    <form
                        action="funciones/grupos_crear.php"
                        method="POST"
                        class="grupos-form-crear"
                    >
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">

                        <input
                            type="text"
                            name="nombre"
                            placeholder="Nombre del nuevo grupo (ej: Grupo C)"
                            maxlength="50"
                            required
                            aria-label="Nombre del nuevo grupo"
                        >

                        <button type="submit" class="btn-torneo editar">
                            <i class="bi bi-plus-lg"></i>
                            <span>Crear grupo</span>
                        </button>
                    </form>

                </div>

                <?php } ?>

                <?php if (!empty($grupos)) { ?>

                    <div class="grupos-grid">

                        <?php foreach ($grupos as $indiceGrupo => $grupo) {

                            $grupoVacio = empty($grupo['miembros']);
                        ?>

                            <article
                                class="grupos-tarjeta <?php echo $grupoVacio ? 'grupos-tarjeta-vacia' : ''; ?>"
                                data-nombre-grupo="<?php echo htmlspecialchars(mb_strtolower($grupo['nombre']), ENT_QUOTES, 'UTF-8'); ?>"
                            >

                                <header class="grupos-tarjeta-header">

                                    <div class="grupos-tarjeta-titulo">

                                        <?php if (!$puedeRenombrar) { ?>
                                            <strong class="grupos-nombre-estatico"><?php echo htmlspecialchars($grupo['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php } else { ?>
                                        <form action="funciones/grupos_renombrar.php" method="POST" class="grupos-form-renombrar">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                            <input type="hidden" name="id_grupo" value="<?php echo (int) $grupo['id_grupo']; ?>">

                                            <input
                                                type="text"
                                                name="nombre"
                                                id="nombreGrupo<?php echo (int) $grupo['id_grupo']; ?>"
                                                value="<?php echo htmlspecialchars($grupo['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                size="<?php echo max(4, mb_strlen($grupo['nombre']) + 1); ?>"
                                                maxlength="50"
                                                required
                                                class="grupos-nombre-input"
                                                aria-label="Nombre del grupo"
                                            >

                                            <button type="submit" title="Guardar nombre" class="grupos-nombre-guardar">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <?php } ?>

                                        <span class="grupos-pill">
                                            <?php echo count($grupo['miembros']); ?>
                                            <?php echo count($grupo['miembros']) === 1 ? $etiquetaMiembro : $etiquetaMiembroPlural; ?>
                                        </span>

                                    </div>

                                    <div class="grupos-tarjeta-acciones">

                                        <?php if ($puedeEstructura) { ?>
                                        <form action="funciones/grupos_reordenar.php" method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                            <input type="hidden" name="id_grupo" value="<?php echo (int) $grupo['id_grupo']; ?>">
                                            <input type="hidden" name="direccion" value="subir">
                                            <button type="submit" class="grupos-icono-btn" title="Mover grupo arriba" <?php echo $indiceGrupo === 0 ? 'disabled' : ''; ?>>
                                                <i class="bi bi-arrow-up"></i>
                                            </button>
                                        </form>

                                        <form action="funciones/grupos_reordenar.php" method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                            <input type="hidden" name="id_grupo" value="<?php echo (int) $grupo['id_grupo']; ?>">
                                            <input type="hidden" name="direccion" value="bajar">
                                            <button type="submit" class="grupos-icono-btn" title="Mover grupo abajo" <?php echo $indiceGrupo === count($grupos) - 1 ? 'disabled' : ''; ?>>
                                                <i class="bi bi-arrow-down"></i>
                                            </button>
                                        </form>
                                        <?php } ?>

                                        <?php if ($puedeRenombrar) { ?>
                                        <button
                                            type="button"
                                            class="grupos-icono-btn grupos-btn-editar-nombre"
                                            data-focus-nombre="nombreGrupo<?php echo (int) $grupo['id_grupo']; ?>"
                                            title="Renombrar grupo"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php } ?>

                                        <?php if ($puedeEstructura) { ?>
                                        <button
                                            type="button"
                                            class="grupos-icono-btn grupos-icono-btn-peligro btn-eliminar-grupo"
                                            data-id="<?php echo (int) $grupo['id_grupo']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($grupo['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                            title="Eliminar grupo"
                                        >
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                        <?php } ?>

                                    </div>

                                </header>

                                <section class="grupos-seccion">

                                    <?php if ($grupoVacio) { ?>

                                        <div class="grupos-tarjeta-vacio-estado">
                                            <i class="bi bi-people"></i>
                                            <strong>Aún no hay <?php echo $etiquetaMiembroPlural; ?> en este grupo</strong>
                                            <p>Agregá <?php echo $esIndividual ? 'los' : 'las'; ?> <?php echo $etiquetaMiembroPlural; ?> que van a jugar en este grupo.</p>
                                        </div>

                                    <?php } else { ?>

                                        <ul class="grupos-miembros">

                                            <?php foreach ($grupo['miembros'] as $indiceMiembro => $miembro) {

                                                $idElemento = $esIndividual ? (int) $miembro['id_inscripcion'] : (int) $miembro['id_pareja'];
                                                $nombreMiembro = $esIndividual
                                                    ? trim($miembro['nombre'] . ' ' . $miembro['apellido'])
                                                    : trim($miembro['nombre1'] . ' ' . $miembro['apellido1']) . ' / ' . trim($miembro['nombre2'] . ' ' . $miembro['apellido2']);
                                                $idMoverMiembro = 'mover' . $grupo['id_grupo'] . '_' . $idElemento;
                                            ?>

                                                <li class="grupos-miembro-item">

                                                    <div class="grupos-miembro">

                                                        <span class="grupos-miembro-numero"><?php echo $indiceMiembro + 1; ?></span>

                                                        <span class="grupos-miembro-nombre"><?php echo htmlspecialchars($nombreMiembro, ENT_QUOTES, 'UTF-8'); ?></span>

                                                        <div class="grupos-miembro-acciones">

                                                            <?php if (count($grupos) > 1 && $puedeEstructura) { ?>
                                                                <button
                                                                    type="button"
                                                                    class="grupos-icono-btn grupos-icono-btn-sm grupos-miembro-mover"
                                                                    data-toggle-agregar="<?php echo $idMoverMiembro; ?>"
                                                                    title="Mover a otro grupo"
                                                                >
                                                                    <i class="bi bi-arrow-left-right"></i>
                                                                </button>
                                                            <?php } ?>

                                                            <?php if ($puedeEstructura) { ?>
                                                            <form action="funciones/grupos_quitar.php" method="POST">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                                <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                                                <input type="hidden" name="tipo" value="<?php echo $esIndividual ? 'inscripcion' : 'pareja'; ?>">
                                                                <input type="hidden" name="id_elemento" value="<?php echo $idElemento; ?>">

                                                                <button type="submit" class="grupos-icono-btn grupos-icono-btn-sm grupos-icono-btn-peligro" title="Quitar del grupo">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
                                                            </form>
                                                            <?php } ?>

                                                        </div>

                                                    </div>

                                                    <?php if (count($grupos) > 1 && $puedeEstructura) { ?>

                                                        <form
                                                            action="funciones/grupos_mover.php"
                                                            method="POST"
                                                            class="grupos-form-agregar-inline"
                                                            id="<?php echo $idMoverMiembro; ?>"
                                                            hidden
                                                        >
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                            <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                                            <input type="hidden" name="tipo" value="<?php echo $esIndividual ? 'inscripcion' : 'pareja'; ?>">
                                                            <input type="hidden" name="id_elemento" value="<?php echo $idElemento; ?>">

                                                            <select name="id_grupo_destino" required aria-label="Grupo de destino">
                                                                <option value="">Mover a...</option>
                                                                <?php foreach ($grupos as $otroGrupo) {
                                                                    if ((int) $otroGrupo['id_grupo'] === (int) $grupo['id_grupo']) {
                                                                        continue;
                                                                    }
                                                                ?>
                                                                    <option value="<?php echo (int) $otroGrupo['id_grupo']; ?>">
                                                                        <?php echo htmlspecialchars($otroGrupo['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>

                                                            <button type="submit" class="grupos-btn-confirmar">Mover</button>
                                                        </form>

                                                    <?php } ?>

                                                </li>

                                            <?php } ?>

                                        </ul>

                                    <?php } ?>

                                    <?php if (!empty($sinAsignar) && $puedeEstructura) { ?>

                                        <button
                                            type="button"
                                            class="grupos-btn-agregar <?php echo $grupoVacio ? 'grupos-btn-agregar-destacado' : ''; ?>"
                                            data-toggle-agregar="agregarPareja<?php echo (int) $grupo['id_grupo']; ?>"
                                        >
                                            <i class="bi bi-plus-lg"></i> Agregar <?php echo $etiquetaMiembro; ?>
                                        </button>

                                        <form
                                            action="funciones/grupos_asignar.php"
                                            method="POST"
                                            class="grupos-form-agregar-inline"
                                            id="agregarPareja<?php echo (int) $grupo['id_grupo']; ?>"
                                            hidden
                                        >
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                            <input type="hidden" name="tipo" value="<?php echo $esIndividual ? 'inscripcion' : 'pareja'; ?>">
                                            <input type="hidden" name="id_grupo" value="<?php echo (int) $grupo['id_grupo']; ?>">

                                            <select name="id_elemento" required aria-label="<?php echo ucfirst($etiquetaMiembro); ?> a agregar">
                                                <option value="">Elegí <?php echo $esIndividual ? 'un participante' : 'una pareja'; ?>...</option>
                                                <?php foreach ($sinAsignar as $elemento) {

                                                    $idElementoSinAsignar = $esIndividual ? (int) $elemento['id_inscripcion'] : (int) $elemento['id_pareja'];
                                                    $nombreElementoSinAsignar = $esIndividual
                                                        ? trim($elemento['nombre'] . ' ' . $elemento['apellido'])
                                                        : trim($elemento['nombre1'] . ' ' . $elemento['apellido1']) . ' / ' . trim($elemento['nombre2'] . ' ' . $elemento['apellido2']);
                                                ?>
                                                    <option value="<?php echo $idElementoSinAsignar; ?>">
                                                        <?php echo htmlspecialchars($nombreElementoSinAsignar, ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>

                                            <button type="submit" class="grupos-btn-confirmar">Agregar</button>
                                        </form>

                                    <?php } elseif ($grupoVacio && $puedeEstructura) { ?>

                                        <p class="grupos-tarjeta-vacio">No hay <?php echo $etiquetaMiembroPlural; ?> <?php echo $esIndividual ? 'confirmados' : 'confirmadas'; ?> sin asignar.</p>

                                    <?php } ?>

                                </section>

                            </article>

                        <?php } ?>

                    </div>

                <?php } ?>

            </section>

        </main>

    </div>


    <!-- MODAL ELIMINAR GRUPO -->

    <div class="modal-mensaje-exito" id="modalEliminarGrupo">

        <div class="modal-mensaje-exito-contenido">

            <button type="button" class="modal-mensaje-exito-cerrar" id="cerrarEliminarGrupo">×</button>

            <div class="modal-mensaje-exito-icono">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:32px;"></i>
            </div>

            <h2>¿Eliminar grupo?</h2>

            <p id="textoEliminarGrupo">¿Seguro que querés eliminar este grupo?</p>

            <form
                action="funciones/grupos_eliminar.php"
                method="POST"
                id="formEliminarGrupo"
            >

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                <input type="hidden" name="id_grupo" id="idGrupoEliminar" value="">

                <div class="modal-inscripcion-botones">

                    <button type="button" class="btn-cancelar-inscripcion" id="noEliminarGrupo">
                        No, volver
                    </button>

                    <button type="submit" class="btn-confirmar-inscripcion btn-confirmar-cancelacion">
                        Sí, eliminar
                    </button>

                </div>

            </form>

        </div>

    </div>


    <script src="js/pestanas.js?v=1"></script>
    <script src="js/confirmar.js?v=1"></script>
    <script src="js/partidos_carrusel.js?v=1"></script>
    <script src="js/grupos_carrusel.js?v=2"></script>
    <script src="js/rondas_tabs.js?v=1"></script>

    <script>

        document.addEventListener("DOMContentLoaded", function () {

            var botonesEliminar = document.querySelectorAll(".btn-eliminar-grupo");

            var modal = document.getElementById("modalEliminarGrupo");
            var texto = document.getElementById("textoEliminarGrupo");
            var idOculto = document.getElementById("idGrupoEliminar");
            var cerrar = document.getElementById("cerrarEliminarGrupo");
            var noEliminar = document.getElementById("noEliminarGrupo");

            function cerrarModalEliminar() {
                modal.classList.remove("mostrar");
                document.documentElement.style.overflow = "";
                document.body.style.overflow = "";
            }

            botonesEliminar.forEach(function (boton) {

                boton.addEventListener("click", function () {

                    var nombre = boton.dataset.nombre || "este grupo";

                    texto.textContent = "¿Seguro que querés eliminar \"" + nombre + "\"? Los participantes y parejas del torneo no se verán afectados.";

                    idOculto.value = boton.dataset.id || "";

                    modal.classList.add("mostrar");

                    document.documentElement.style.overflow = "hidden";
                    document.body.style.overflow = "hidden";

                });

            });

            if (cerrar) {
                cerrar.addEventListener("click", cerrarModalEliminar);
            }

            if (noEliminar) {
                noEliminar.addEventListener("click", cerrarModalEliminar);
            }


            // Marcadores de partidos: el set 3 sólo se habilita si los sets
            // quedaron 1 a 1, y se valida antes de enviar con la misma regla
            // que el servidor (que igual vuelve a validar).
            function esSetValido(a, b) {
                var mayor = Math.max(a, b);
                var menor = Math.min(a, b);
                return (mayor === 6 && menor <= 4) || (mayor === 7 && (menor === 5 || menor === 6));
            }

            document.querySelectorAll("[data-marcador]").forEach(function (form) {

                var aviso = form.querySelector("[data-marcador-aviso]");
                var selectWo = form.querySelector('[name="wo"]');

                function esWo() {
                    return !!selectWo && selectWo.value !== "";
                }

                // Con W.O. no hay sets: se deshabilitan (no se envían) y no se validan.
                function aplicarWo() {
                    var wo = esWo();

                    form.classList.toggle("marcador-es-wo", wo);

                    form.querySelectorAll(".marcador-input").forEach(function (input) {
                        input.disabled = wo;
                    });

                    if (!wo) {
                        actualizarSet3();
                    }
                }

                function campo(set, lado) {
                    return form.querySelector('[name="set' + set + '_' + lado + '"]');
                }

                function leerSet(set) {
                    var a = campo(set, 1).value;
                    var b = campo(set, 2).value;
                    return (a === "" || b === "") ? null : [parseInt(a, 10), parseInt(b, 10)];
                }

                function mostrarAviso(texto) {
                    aviso.textContent = texto;
                    aviso.className = "marcador-aviso marcador-aviso-error";
                    aviso.hidden = false;
                }

                function actualizarSet3() {
                    var s1 = leerSet(1);
                    var s2 = leerSet(2);
                    var definido = !!(s1 && s2 && s1[0] !== s1[1] && s2[0] !== s2[1] && ((s1[0] > s1[1]) === (s2[0] > s2[1])));

                    [campo(3, 1), campo(3, 2)].forEach(function (input) {
                        input.disabled = definido;
                        if (definido) {
                            input.value = "";
                        }
                    });

                    form.classList.toggle("marcador-sin-set3", definido);
                }

                function validar() {
                    if (esWo()) {
                        return "";
                    }

                    var s1 = leerSet(1);
                    var s2 = leerSet(2);

                    if (!s1 || !s2) {
                        return "Completá los games del set 1 y del set 2.";
                    }

                    if (!esSetValido(s1[0], s1[1]) || !esSetValido(s2[0], s2[1])) {
                        return "Hay un set con un marcador imposible. Un set termina 6-0 a 6-4, 7-5 o 7-6.";
                    }

                    if ((s1[0] > s1[1]) !== (s2[0] > s2[1])) {
                        var s3 = leerSet(3);
                        if (!s3) {
                            return "Los sets quedaron 1 a 1: falta cargar el set 3.";
                        }
                        if (!esSetValido(s3[0], s3[1])) {
                            return "El set 3 tiene un marcador imposible. Un set termina 6-0 a 6-4, 7-5 o 7-6.";
                        }
                    }

                    return "";
                }

                form.addEventListener("input", function () {
                    aplicarWo();
                    if (aviso.classList.contains("marcador-aviso-error")) {
                        aviso.hidden = true;
                    }
                });

                form.addEventListener("submit", function (evento) {
                    var error = validar();
                    if (error) {
                        evento.preventDefault();
                        mostrarAviso(error);
                    }
                });

                aplicarWo();

            });


            // Buscador de grupos por nombre.
            var buscadorGrupo = document.getElementById("buscarGrupo");

            if (buscadorGrupo) {

                var tarjetasGrupo = document.querySelectorAll(".grupos-tarjeta[data-nombre-grupo]");

                buscadorGrupo.addEventListener("input", function () {

                    var consulta = buscadorGrupo.value.trim().toLowerCase();

                    tarjetasGrupo.forEach(function (tarjeta) {

                        var coincide = tarjeta.dataset.nombreGrupo.indexOf(consulta) !== -1;

                        tarjeta.style.display = coincide ? "" : "none";

                    });

                });

            }


            // Botón "+ Agregar pareja/participante": despliega el selector inline.
            var botonesAgregar = document.querySelectorAll("[data-toggle-agregar]");

            botonesAgregar.forEach(function (boton) {

                var formulario = document.getElementById(boton.dataset.toggleAgregar);

                if (!formulario) {
                    return;
                }

                var textoOriginal = boton.innerHTML;
                var cambiarTexto = boton.classList.contains("grupos-btn-agregar");

                boton.addEventListener("click", function () {

                    var abierto = !formulario.hidden;

                    formulario.hidden = abierto;

                    boton.classList.toggle("grupos-toggle-activo", !abierto);

                    if (cambiarTexto) {
                        boton.innerHTML = abierto
                            ? textoOriginal
                            : '<i class="bi bi-x-lg"></i> Cancelar';
                    }

                });

            });


            // El campo del nombre se ensancha con el texto para que parezca un título.
            document.querySelectorAll(".grupos-nombre-input").forEach(function (input) {
                input.addEventListener("input", function () {
                    input.size = Math.max(4, input.value.length + 1);
                });
            });


            // Botón "editar nombre": enfoca y selecciona el input del nombre del grupo.
            var botonesEditarNombre = document.querySelectorAll("[data-focus-nombre]");

            botonesEditarNombre.forEach(function (boton) {

                boton.addEventListener("click", function () {

                    var input = document.getElementById(boton.dataset.focusNombre);

                    if (input) {
                        input.focus();
                        input.select();
                    }

                });

            });

        });

    </script>

    <script src="js/padel.js?v=17"></script>

</body>

</html>
