<?php

include("config/no_cache.php");
include("config/conexion.php");

session_regenerate_id(true);


/* =========================================================
   SEGURIDAD
   ========================================================= */

if (!isset($_SESSION['id_usuario'])) {

    header("Location: login.php");
    exit();

}



  if (
    $_SESSION['id_rol'] != 2 &&
    $_SESSION['id_rol'] != 3
) {
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

    header("Location: paneles.php?seccion=torneos&error=torneo_invalido");
    exit();

}

$id_usuario = $_SESSION['id_usuario'];

$sqlTorneo = "
    SELECT
        torneos.id_torneo,
        torneos.nombre,
        torneos.fecha_inicio,
        torneos.hora_inicio,
        torneos.hora_fin,
        torneos.tipo,
        torneos.estado,
        torneos.max_participantes,
        categoria.nombre AS categoria
    FROM torneos
    INNER JOIN categoria
        ON torneos.id_categoria = categoria.id_categoria
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

    header("Location: paneles.php?seccion=torneos&error=sin_permiso");
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
    ' - ' .
    date('H:i', strtotime($torneo['hora_fin'])) .
    ' hs';


/* =========================================================
   DATOS DE EJEMPLO
   (fase de grupos, ranking y cruces todavía no tienen
   una fuente real: faltan las tablas de inscripciones,
   parejas y resultados de partidos. Se muestran de ejemplo
   para tener la vista lista en cuanto se carguen datos reales)
   ========================================================= */
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
$grupos = [
    [
        'letra'   => 'A',
        'nombre'  => 'Grupo A',
        'parejas' => [
            ['nombres' => ['Martina Gómez', 'Sofía Ruiz'],     'pj' => 3, 'pg' => 3, 'pts' => 9],
            ['nombres' => ['Camila Torres', 'Valentina Paz'],  'pj' => 3, 'pg' => 2, 'pts' => 7],
            ['nombres' => ['Agustina López', 'Julieta Sosa'],  'pj' => 3, 'pg' => 1, 'pts' => 5],
            ['nombres' => ['Micaela Ortiz', 'Luciana Vega'],   'pj' => 3, 'pg' => 0, 'pts' => 3],
        ],
    ],
    [
        'letra'   => 'B',
        'nombre'  => 'Grupo B',
        'parejas' => [
            ['nombres' => ['Julián Pérez', 'Tomás Díaz'],       'pj' => 3, 'pg' => 3, 'pts' => 9],
            ['nombres' => ['Ramiro Sánchez', 'Diego Ruiz'],     'pj' => 3, 'pg' => 2, 'pts' => 7],
            ['nombres' => ['Lucas Martínez', 'Federico Paz'],   'pj' => 3, 'pg' => 1, 'pts' => 5],
            ['nombres' => ['Claudio Núñez', 'Mario Torres'],    'pj' => 3, 'pg' => 0, 'pts' => 3],
        ],
    ],
];

$ranking = [
    ['nombre' => 'Matías Gómez',      'puntos' => 240],
    ['nombre' => 'Lucas Díaz',        'puntos' => 185],
    ['nombre' => 'Franco Ruiz',       'puntos' => 160],
    ['nombre' => 'Agustín González',  'puntos' => 145],
    ['nombre' => 'Martín Romero',     'puntos' => 130],
];

$cuartos = [
    ['a' => ['nombre' => 'Julián Pérez / Tomás Díaz',      'score' => 6, 'gano' => true],  'b' => ['nombre' => 'Ramiro Sánchez / Diego Ruiz',   'score' => 4, 'gano' => false]],
    ['a' => ['nombre' => 'Lucas Martínez / Federico Paz',  'score' => 6, 'gano' => true],  'b' => ['nombre' => 'Claudio Núñez / Mario Torres',  'score' => 3, 'gano' => false]],
    ['a' => ['nombre' => 'Franco Ruiz / Agustín López',    'score' => 4, 'gano' => false], 'b' => ['nombre' => 'Martín Romero / Nicolás Díaz',  'score' => 6, 'gano' => true]],
    ['a' => ['nombre' => 'Mateo Silva / Juan Cruz',        'score' => 6, 'gano' => true],  'b' => ['nombre' => 'Gonzalo Pérez / Alan Torres',   'score' => 2, 'gano' => false]],
];

$semis = [
    ['a' => ['nombre' => 'Julián Pérez / Tomás Díaz',     'score' => 6, 'gano' => true],  'b' => ['nombre' => 'Lucas Martínez / Federico Paz', 'score' => 4, 'gano' => false]],
    ['a' => ['nombre' => 'Martín Romero / Nicolás Díaz',  'score' => 3, 'gano' => false], 'b' => ['nombre' => 'Mateo Silva / Juan Cruz',       'score' => 6, 'gano' => true]],
];

$final = [
    'a' => 'Julián Pérez / Tomás Díaz',
    'b' => 'Mateo Silva / Juan Cruz',
];

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
    <link rel="stylesheet" href="css/ver_torneo.css">

</head>

<body
    class="panel-body"
    data-tipo-torneo="<?php echo htmlspecialchars(
        $torneo['tipo'],
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
>

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

                <span>Organizador</span>

            </div>

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


        <!-- CONTENIDO -->

        <main class="contenido">
            <?php if ($_SESSION['id_rol'] == 2) { ?>

    <!-- =========================================
         VISTA DEL ORGANIZADOR
         ========================================= -->


            <!-- ENCABEZADO -->

            <div class="vt-topbar">

                <div>

                    <span class="vt-estado-badge"><?php echo $eyebrowTorneo; ?></span>

                    <h1><?php echo htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></h1>

                    <p>
                        Campeonato de pádel ·
                        <?php echo htmlspecialchars($torneo['tipo'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                </div>

                <a href="paneles.php?seccion=torneos" class="vt-volver">
                    <i class="bi bi-arrow-left"></i> Volver a torneos
                </a>

            </div>


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
                        <span>Horarios</span>
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


            <!-- FASE DE GRUPOS + RANKING -->

            <div class="vt-dos-columnas">

                <!-- FASE DE GRUPOS -->

                <div>

                    <div class="vt-seccion-titulo">
                        <span class="vt-eyebrow">COMPETENCIA</span>
                        <h2>Fase de grupos</h2>
                        <p>Clasificación de las parejas participantes</p>
                    </div>

                    <div class="vt-card">

                        <div class="vt-card-header">

                            <div class="vt-grupo-nombre">

                                <div class="vt-grupo-letra" id="vtGrupoLetra">
                                    <?php echo $grupos[0]['letra']; ?>
                                </div>

                                <div>
                                    <strong id="vtGrupoNombre"><?php echo $grupos[0]['nombre']; ?></strong>
                                    <span id="vtGrupoCantidad">
                                        <?php echo count($grupos[0]['parejas']); ?> parejas
                                    </span>
                                </div>

                            </div>

                            <div class="vt-grupo-nav">

                                <button type="button" id="vtGrupoAnterior" disabled>‹</button>
                                <button type="button" id="vtGrupoSiguiente">›</button>

                            </div>

                        </div>

                        <table class="vt-tabla-grupo" data-grupos='<?php echo htmlspecialchars(json_encode($grupos, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>'>

                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th style="text-align:left;">PAREJA</th>
                                    <th>PJ</th>
                                    <th>PG</th>
                                    <th>PTS</th>
                                </tr>
                            </thead>

                            <tbody id="vtGrupoTabla"></tbody>

                        </table>

                    </div>

                </div>


                <!-- RANKING DEL TORNEO -->

                <div>

                    <div class="vt-seccion-titulo">
                        <span class="vt-eyebrow">CLASIFICACIÓN</span>
                        <h2>Ranking del torneo</h2>
                        <p>Jugadores con mayor cantidad de puntos</p>
                    </div>

                    <div class="vt-card">

                        <?php foreach ($ranking as $indice => $jugador) { ?>

                            <div class="vt-ranking-item">

                                <span class="vt-ranking-puesto"><?php echo $indice + 1; ?></span>

                                <div class="vt-avatar">
                                    <?php
                                        $partesNombre = explode(' ', $jugador['nombre']);
                                        echo strtoupper(
                                            mb_substr($partesNombre[0], 0, 1) .
                                            mb_substr(end($partesNombre), 0, 1)
                                        );
                                    ?>
                                </div>

                                <div class="vt-ranking-datos">
                                    <strong><?php echo htmlspecialchars($jugador['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span><?php echo $jugador['puntos']; ?> puntos</span>
                                </div>

                                <?php if ($indice === 0) { ?>
                                    <i class="bi bi-award-fill vt-corona"></i>
                                <?php } ?>

                            </div>

                        <?php } ?>

                    </div>

                </div>

            </div>


            <!-- CRUCES DEL TORNEO -->

            <div class="vt-seccion-titulo">
                <span class="vt-eyebrow">COMPETENCIA</span>
                <h2>Cruces del torneo</h2>
                <p>Llaves eliminatorias del campeonato</p>
            </div>

            <div class="vt-card vt-bracket-card">

                <div class="vt-card-header">

                    <div>
                        <h3>Cuadro eliminatorio</h3>
                        <p>Fase final del torneo</p>
                    </div>

                    <span class="vt-badge-pill"><i class="bi bi-trophy-fill"></i> Eliminatorias</span>

                </div>

                <div class="vt-bracket">

                    <!-- CUARTOS DE FINAL -->

                    <div class="vt-bracket-col">

                        <div class="vt-bracket-col-titulo">CUARTOS DE FINAL</div>

                        <?php foreach ($cuartos as $partido) { ?>

                            <div class="vt-match">

                                <div class="vt-match-fila <?php echo $partido['a']['gano'] ? 'gano' : ''; ?>">
                                    <span><?php echo htmlspecialchars($partido['a']['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="vt-match-score"><?php echo $partido['a']['score']; ?></span>
                                </div>

                                <div class="vt-match-fila <?php echo $partido['b']['gano'] ? 'gano' : ''; ?>">
                                    <span><?php echo htmlspecialchars($partido['b']['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="vt-match-score"><?php echo $partido['b']['score']; ?></span>
                                </div>

                            </div>

                        <?php } ?>

                    </div>


                    <!-- SEMIFINALES -->

                    <div class="vt-bracket-col semis">

                        <div class="vt-bracket-col-titulo">SEMIFINALES</div>

                        <?php foreach ($semis as $partido) { ?>

                            <div class="vt-match">

                                <div class="vt-match-fila <?php echo $partido['a']['gano'] ? 'gano' : ''; ?>">
                                    <span><?php echo htmlspecialchars($partido['a']['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="vt-match-score"><?php echo $partido['a']['score']; ?></span>
                                </div>

                                <div class="vt-match-fila <?php echo $partido['b']['gano'] ? 'gano' : ''; ?>">
                                    <span><?php echo htmlspecialchars($partido['b']['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="vt-match-score"><?php echo $partido['b']['score']; ?></span>
                                </div>

                            </div>

                        <?php } ?>

                    </div>


                    <!-- FINAL -->

                    <div class="vt-bracket-col final">

                        <div class="vt-bracket-col-titulo">FINAL</div>

                        <div class="vt-match vt-match-pendiente">

                            <div class="vt-match-fila">
                                <span><?php echo htmlspecialchars($final['a'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="vt-match-score">–</span>
                            </div>

                            <div class="vt-match-fila">
                                <span><?php echo htmlspecialchars($final['b'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="vt-match-score">–</span>
                            </div>

                        </div>

                        <div class="vt-campeon">
                            <i class="bi bi-trophy-fill"></i>
                            <strong>CAMPEÓN</strong>
                        </div>

                    </div>

                </div>

            </div>

            <p class="vt-nota-datos">
                * La fase de grupos, el ranking del torneo y los cruces todavía muestran datos
                de ejemplo: falta cargar las inscripciones y los resultados reales de los partidos.
            </p>
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


    <a
        href="paneles.php?seccion=torneos"
        class="vt-volver"
    >
        <i class="bi bi-arrow-left"></i>
        Volver a torneos
    </a>

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
            <span>Horarios</span>

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

<div class="vt-dos-columnas">

    <!-- FASE DE GRUPOS -->
    <div>

        <div class="vt-seccion-titulo">
            <span class="vt-eyebrow">COMPETENCIA</span>
            <h2>Fase de grupos</h2>
            <p>Clasificación de las parejas participantes</p>
        </div>

        <div class="vt-card">

            <div class="vt-card-header">

                <div class="vt-grupo-nombre">

                    <div class="vt-grupo-letra">
                        <?php echo $grupos[0]['letra']; ?>
                    </div>

                    <div>
                        <strong>
                            <?php echo $grupos[0]['nombre']; ?>
                        </strong>

                        <span>
                            <?php echo count($grupos[0]['parejas']); ?> parejas
                        </span>
                    </div>

                </div>

                <div class="vt-grupo-nav">
                    <button type="button" id="vtGrupoAnterior" disabled>‹</button>
                    <button type="button" id="vtGrupoSiguiente">›</button>
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
                        <th style="text-align:left;">PAREJA</th>
                        <th>PJ</th>
                        <th>PG</th>
                        <th>PTS</th>
                    </tr>
                </thead>

                <tbody id="vtGrupoTabla"></tbody>

            </table>

        </div>

    </div>


    <!-- RANKING DEL TORNEO -->

    <div>

        <div class="vt-seccion-titulo">
            <span class="vt-eyebrow">CLASIFICACIÓN</span>
            <h2>Ranking del torneo</h2>
            <p>Jugadores con mayor cantidad de puntos</p>
        </div>

        <div class="vt-card">

            <?php foreach ($ranking as $indice => $jugador) { ?>

                <div class="vt-ranking-item">

                    <span class="vt-ranking-puesto">
                        <?php echo $indice + 1; ?>
                    </span>

                    <div class="vt-avatar">

                        <?php

                        $partesNombre = explode(
                            ' ',
                            $jugador['nombre']
                        );

                        echo strtoupper(
                            mb_substr($partesNombre[0], 0, 1) .
                            mb_substr(end($partesNombre), 0, 1)
                        );

                        ?>

                    </div>

                    <div class="vt-ranking-datos">

                        <strong>
                            <?php echo htmlspecialchars(
                                $jugador['nombre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </strong>

                        <span>
                            <?php echo $jugador['puntos']; ?> puntos
                        </span>

                    </div>

                    <?php if ($indice === 0) { ?>

                        <i class="bi bi-award-fill vt-corona"></i>

                    <?php } ?>

                </div>

            <?php } ?>

        </div>

    </div>

</div>

<?php } ?>
        </main>
        


    </div>
    <script src="js/padel.js"></script>
</body>

</html>
