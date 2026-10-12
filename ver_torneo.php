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
    $_SESSION['id_rol'] != 1 &&
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

        if ($torneo['tipo'] === 'Individual') {

            $stmtMiembros = mysqli_prepare(
                $conexion,
                "SELECT u.nombre, u.apellido
                 FROM grupo_inscripcion gi
                 INNER JOIN inscripcion insc ON insc.id_inscripcion = gi.id_inscripcion
                 INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
                 WHERE gi.id_grupo = ? AND insc.estado = 'Confirmada'
                 ORDER BY gi.orden ASC, gi.id_grupo_inscripcion ASC"
            );
            mysqli_stmt_bind_param($stmtMiembros, "i", $idGrupoActual);
            mysqli_stmt_execute($stmtMiembros);
            $filasMiembros = mysqli_fetch_all(mysqli_stmt_get_result($stmtMiembros), MYSQLI_ASSOC);

            foreach ($filasMiembros as $m) {
                $miembrosGrupo[] = ['nombre' => trim($m['nombre'] . ' ' . $m['apellido'])];
            }

        } else {

            $stmtMiembros = mysqli_prepare(
                $conexion,
                "SELECT u1.nombre AS nombre1, u1.apellido AS apellido1,
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
            mysqli_stmt_bind_param($stmtMiembros, "i", $idGrupoActual);
            mysqli_stmt_execute($stmtMiembros);
            $filasMiembros = mysqli_fetch_all(mysqli_stmt_get_result($stmtMiembros), MYSQLI_ASSOC);

            foreach ($filasMiembros as $m) {
                $miembrosGrupo[] = [
                    'nombres' => [
                        trim($m['nombre1'] . ' ' . $m['apellido1']),
                        trim($m['nombre2'] . ' ' . $m['apellido2']),
                    ],
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
   RANKING Y CRUCES
   Todavía no existe ninguna tabla de resultados/partidos en
   el proyecto, así que no hay datos reales para mostrar acá.
   No se inventan puntos ni cruces.
   ========================================================= */

$ranking = [];
$cuartos = [];
$semis = [];
$final = null;

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

                <span><?php echo ($_SESSION['id_rol'] == 1) ? 'Administrador' : 'Organizador'; ?></span>

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
            <?php if ($_SESSION['id_rol'] == 1 || $_SESSION['id_rol'] == 2) { ?>

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

                <div class="vt-topbar-acciones">

                    <a
                        href="gestionar_grupos.php?id_torneo=<?php echo (int) $id_torneo; ?>"
                        class="vt-volver"
                    >
                        <i class="bi bi-diagram-3"></i> Gestionar grupos
                    </a>

                    <a href="paneles.php?seccion=torneos" class="vt-volver">
                        <i class="bi bi-arrow-left"></i> Volver a torneos
                    </a>

                </div>

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
                                    <th>PTS</th>
                                </tr>
                            </thead>

                            <tbody id="vtGrupoTabla"></tbody>

                        </table>

                        <p class="vt-nota-datos">
                            <?php if ($gruposPersistidos) { ?>
                                * Distribución guardada por la organización. Las columnas PJ/PG/PTS
                                están en cero porque todavía no hay partidos cargados.
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


                <!-- RANKING DEL TORNEO -->

                <div>

                    <div class="vt-seccion-titulo">
                        <span class="vt-eyebrow">CLASIFICACIÓN</span>
                        <h2>Ranking del torneo</h2>
                        <p>Jugadores con mayor cantidad de puntos</p>
                    </div>

                    <div class="vt-card vt-estado-vacio">
                        <i class="bi bi-bar-chart-line"></i>
                        <p>Todavía no hay resultados cargados para generar el ranking.</p>
                    </div>

                </div>

            </div>


            <!-- CRUCES DEL TORNEO -->

            <div class="vt-seccion-titulo">
                <span class="vt-eyebrow">COMPETENCIA</span>
                <h2>Cruces del torneo</h2>
                <p>Llaves eliminatorias del campeonato</p>
            </div>

            <div class="vt-card vt-estado-vacio">
                <i class="bi bi-diagram-3"></i>
                <p>Todavía no hay cruces generados para este torneo.</p>
            </div>

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


    <!-- RANKING DEL TORNEO -->

    <div>

        <div class="vt-seccion-titulo">
            <span class="vt-eyebrow">CLASIFICACIÓN</span>
            <h2>Ranking del torneo</h2>
            <p>Jugadores con mayor cantidad de puntos</p>
        </div>

        <div class="vt-card vt-estado-vacio">
            <i class="bi bi-bar-chart-line"></i>
            <p>Todavía no hay resultados cargados para generar el ranking.</p>
        </div>

    </div>

</div>

<?php } ?>
        </main>
        


    </div>
    <script src="js/padel.js?v=6"></script>
</body>

</html>
