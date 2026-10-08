<?php

include("config/no_cache.php");
include("config/conexion.php");

session_regenerate_id(true);


// ==========================================
// VERIFICAR SESIÓN
// ==========================================

if (!isset($_SESSION['id_usuario'])) {

    header("Location: login.php");
    exit();

}


// ==========================================
// EVITAR CACHÉ
// ==========================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


// ==========================================
// SECCIÓN DEL PANEL
// ==========================================

$seccion = $_GET['seccion'] ?? 'inicio';
/* =========================================================
   CATEGORÍA DEL PARTICIPANTE
   ========================================================= */

$categoriaParticipante = 'Sin categoría asignada';

if ($_SESSION['id_rol'] == 3) {

    $idUsuarioParticipante = intval($_SESSION['id_usuario']);

    $sqlCategoriaParticipante = "
        SELECT categoria.nombre
        FROM usuario
        LEFT JOIN categoria
            ON usuario.id_categoria = categoria.id_categoria
        WHERE usuario.id_usuario = ?
        LIMIT 1
    ";

    $stmtCategoriaParticipante = mysqli_prepare(
        $conexion,
        $sqlCategoriaParticipante
    );

    if ($stmtCategoriaParticipante) {

        mysqli_stmt_bind_param(
            $stmtCategoriaParticipante,
            "i",
            $idUsuarioParticipante
        );

        mysqli_stmt_execute(
            $stmtCategoriaParticipante
        );

        $resultadoCategoriaParticipante =
            mysqli_stmt_get_result(
                $stmtCategoriaParticipante
            );

        $datosCategoriaParticipante =
            mysqli_fetch_assoc(
                $resultadoCategoriaParticipante
            );

        if (
            $datosCategoriaParticipante &&
            !empty($datosCategoriaParticipante['nombre'])
        ) {

            $categoriaParticipante =
                $datosCategoriaParticipante['nombre'];

        }

        mysqli_stmt_close(
            $stmtCategoriaParticipante
        );
    }
}


// ==========================================
// CANTIDAD DE USUARIOS
// ==========================================

$sqlUsuarios = "SELECT COUNT(*) AS total FROM usuario";

$resultadoUsuarios = mysqli_query(
    $conexion,
    $sqlUsuarios
);

$filaUsuarios = mysqli_fetch_assoc(
    $resultadoUsuarios
);

$totalUsuarios = $filaUsuarios["total"];


// ==========================================
// CANTIDAD DE SOLICITUDES PENDIENTES
// ==========================================

$sqlSolicitudes = "
    SELECT COUNT(*) AS total
    FROM solicitud_organizador
    WHERE estado_solicitud = 'Pendiente'
";

$resultadoSolicitudes = mysqli_query(
    $conexion,
    $sqlSolicitudes
);

$filaSolicitudes = mysqli_fetch_assoc(
    $resultadoSolicitudes
);

$totalSolicitudes = $filaSolicitudes["total"];

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Paneles</title>

    <link rel="stylesheet"
          href="css/style.css"> 
          <link rel="stylesheet" href="css/canchas.css">

</head>


<body class="panel-body">


<?php if ($_SESSION['id_rol'] == 1) { ?>


<!-- ========================================= -->
<!-- PANEL ADMINISTRADOR -->
<!-- ========================================= -->

<div class="panel">


    <!-- ===================================== -->
    <!-- SIDEBAR -->
    <!-- ===================================== -->

    <aside class="sidebar">


        <div class="logo-panel">

            <img
                src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                class="logo-panel-img"
            >

            <h2>The Bull</h2>

            <span>Administrador</span>

        </div>


        <ul class="menu">

    <!-- TORNEOS -->

    <li class="activo">

        <a href="paneles.php">

            <svg viewBox="0 0 24 24" fill="none">

                <path
                    d="M8 21h8"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M12 17v4"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M6 17h12"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M7 13h10"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M8 9h8"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M10 5h4"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

            </svg>

            <span>Torneos</span>

        </a>

    </li>


    <!-- MIS INSCRIPCIONES -->

    <li>

        <a href="paneles.php?seccion=mis_inscripciones">

            <svg viewBox="0 0 24 24" fill="none">

                <rect
                    x="4"
                    y="3"
                    width="16"
                    height="18"
                    rx="2"
                    stroke="currentColor"
                    stroke-width="1.8"
                />

                <path
                    d="M8 8h8"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M8 12h8"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M8 16h5"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

            </svg>

            <span>Mis inscripciones</span>

        </a>

    </li>


    <!-- RANKING -->

    <li>

        <a href="paneles.php?seccion=ranking">

            <svg viewBox="0 0 24 24" fill="none">

                <path
                    d="M5 20V10"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M12 20V5"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M19 20v-7"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

            </svg>

            <span>Ranking</span>

        </a>

    </li>


    <!-- NOTIFICACIONES -->

    <li>

        <a href="paneles.php?seccion=notificaciones">

            <svg viewBox="0 0 24 24" fill="none">

                <path
                    d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <path
                    d="M10 21h4"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

            </svg>

            <span>Notificaciones</span>

        </a>

    </li>


    <!-- CERRAR SESIÓN -->

    <li>

        <a href="funciones/logout.php">

            <svg viewBox="0 0 24 24" fill="none">

                <path
                    d="M10 17l5-5-5-5"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <path
                    d="M15 12H3"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

                <path
                    d="M21 3v18"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />

            </svg>

            <span>Cerrar sesión</span>

        </a>

    </li>

</ul>


    </aside>


    <!-- ===================================== -->
    <!-- CONTENIDO -->
    <!-- ===================================== -->

    <main class="contenido solicitudes-pagina">


    <?php if ($seccion == 'inicio') { ?>


        <!-- ================================= -->
        <!-- INICIO -->
        <!-- ================================= -->

        <header class="topbar">

            <div>

                <h1>Panel</h1>

                <p>

                    Bienvenido,

                    <strong>

                        <?php echo htmlspecialchars(
                            $_SESSION['nombre']
                        ); ?>

                    </strong>

                </p>

            </div>

        </header>


        <!-- TARJETAS -->

        <section class="cards">


            <div class="card">

                <h3>Usuarios</h3>

                <span>

                    <?php echo $totalUsuarios; ?>

                </span>

            </div>


            <div class="card">

                <h3>Solicitudes</h3>

                <span>

                    <?php echo $totalSolicitudes; ?>

                </span>

            </div>


            <div class="card">

                <h3>Torneos</h3>

                <span>0</span>

            </div>


            <div class="card">

                <h3>Inscripciones</h3>

                <span>0</span>

            </div>


        </section>


        <!-- TORNEOS -->

        <section class="panel-inferior">


            <div class="tabla">

                <h2>Torneos recientes</h2>


                <table>

                    <tr>

                        <th>Nombre</th>

                        <th>Categoría</th>

                        <th>Estado</th>

                    </tr>


                    <tr>

                        <td colspan="3">

                            No hay torneos registrados.

                        </td>

                    </tr>

                </table>


            </div>


        </section>


    <?php } ?>

        <?php if ($seccion == 'solicitudes') { ?>

        <header class="topbar">
            <div>
                <h1>Solicitudes de Organizadores</h1>
                <p>Revisá los registros pendientes antes de aceptarlos.</p>
            </div>
        </header>

        <?php
        $sql = "
            SELECT *
            FROM solicitud_organizador
            WHERE estado_solicitud = 'Pendiente'
            ORDER BY fecha_solicitud DESC
        ";

        $resultado = mysqli_query($conexion, $sql);
        ?>

        <?php if (!$resultado || mysqli_num_rows($resultado) == 0) { ?>

            <div class="solicitudes-vacio">
                <div class="solicitudes-vacio-icono">📋</div>
                <h2>No hay solicitudes pendientes</h2>
                <p>Actualmente no hay solicitudes de organizadores para revisar.</p>
            </div>

        <?php } else { ?>

            <div class="lista-solicitudes">

                <?php
                $numeroSolicitud = 0;
                while ($solicitud = mysqli_fetch_assoc($resultado)) {
                    $numeroSolicitud++;
                    $idSolicitud = intval($solicitud['id_solicitud']);

                    $sqlCanchas = "
                        SELECT *
                        FROM solicitud_cancha
                        WHERE id_solicitud = $idSolicitud
                        ORDER BY id_solicitud_cancha ASC
                    ";

                    $resultadoCanchas = mysqli_query($conexion, $sqlCanchas);
                ?>

                    <article class="solicitud-card <?php echo ($numeroSolicitud === 1) ? 'abierto' : ''; ?>">

                        <!-- CABECERA DE LA TARJETA -->
                        <div class="solicitud-card-cabecera solicitud-card-toggle"
                             data-solicitud-toggle="<?php echo $idSolicitud; ?>"
                             role="button"
                             tabindex="0"
                             aria-expanded="<?php echo ($numeroSolicitud === 1) ? 'true' : 'false'; ?>">

                            <div class="solicitud-usuario">
                                <div class="solicitud-avatar">👤</div>

                                <div>
                                    <h2>
                                        <?php echo htmlspecialchars($solicitud['nombre'] . ' ' . $solicitud['apellido']); ?>
                                    </h2>

                                    <p>
                                        <?php echo htmlspecialchars($solicitud['nombre_club']); ?>
                                        <span>•</span>
                                        🎾 <?php echo htmlspecialchars($solicitud['cantidad_canchas']); ?> canchas
                                    </p>
                                </div>
                            </div>

                            <div class="solicitud-cabecera-derecha">
                                <span class="estado-pendiente">Pendiente</span>
                                <span class="solicitud-flecha"></span>
                            </div>
                        </div>

                        <!-- CONTENIDO DESPLEGABLE -->
                        <div class="solicitud-card-body">

                            <!-- DATOS IMPORTANTES -->
                            <div class="solicitud-datos">

                                <div class="solicitud-dato">
                                    <span>📍</span>
                                    <div>
                                        <small>Dirección</small>
                                        <strong><?php echo htmlspecialchars($solicitud['direccion']); ?></strong>
                                    </div>
                                </div>

                                <div class="solicitud-dato">
                                    <span>✉️</span>
                                    <div>
                                        <small>Correo</small>
                                        <strong><?php echo htmlspecialchars($solicitud['email']); ?></strong>
                                    </div>
                                </div>

                                <div class="solicitud-dato">
                                    <span>📅</span>
                                    <div>
                                        <small>Fecha de solicitud</small>
                                        <strong><?php echo htmlspecialchars($solicitud['fecha_solicitud']); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- INFORMACIÓN DEL ORGANIZADOR -->
                            <section class="solicitud-detalle-seccion">
                                <h3>👤 Información del Organizador</h3>

                                <div class="solicitud-detalle-grid">
                                    <div>
                                        <span>Nombre</span>
                                        <strong><?php echo htmlspecialchars($solicitud['nombre']); ?></strong>
                                    </div>

                                    <div>
                                        <span>Apellido</span>
                                        <strong><?php echo htmlspecialchars($solicitud['apellido']); ?></strong>
                                    </div>

                                    <div>
                                        <span>Fecha de nacimiento</span>
                                        <strong><?php echo htmlspecialchars($solicitud['fecha_nacimiento']); ?></strong>
                                    </div>

                                    <div>
                                        <span>Teléfono</span>
                                        <strong><?php echo htmlspecialchars($solicitud['telefono']); ?></strong>
                                    </div>

                                    <div class="detalle-completo">
                                        <span>Correo electrónico</span>
                                        <strong><?php echo htmlspecialchars($solicitud['email']); ?></strong>
                                    </div>
                                </div>
                            </section>

                            <!-- INFORMACIÓN DEL CLUB -->
                            <section class="solicitud-detalle-seccion">
                                <h3>🏢 Información del Club</h3>

                                <div class="solicitud-detalle-grid">
                                    <div>
                                        <span>Nombre del club</span>
                                        <strong><?php echo htmlspecialchars($solicitud['nombre_club']); ?></strong>
                                    </div>

                                    <div>
                                        <span>Dirección</span>
                                        <strong><?php echo htmlspecialchars($solicitud['direccion']); ?></strong>
                                    </div>

                                    <div>
                                        <span>Teléfono</span>
                                        <strong><?php echo htmlspecialchars($solicitud['telefono_club']); ?></strong>
                                    </div>

                                            <div>
                                               <span>Horario de mañana</span>
                                               <strong>
                                                   <?php
                                                   if (!empty($solicitud['horario_manana'])) {
                                                       echo htmlspecialchars(
                                                           $solicitud['horario_manana']
                                                       );
                                                   } else {
                                                       echo "No especificado";
                                                   }
                                                   ?>
                                               </strong>
                                            </div>
                                            
                                            <div>
                                                <span>Horario de tarde</span>
                                                <strong>
                                                    <?php
                                                    if (!empty($solicitud['horario_tarde'])) {
                                                        echo htmlspecialchars(
                                                            $solicitud['horario_tarde']
                                                        );
                                                    } else {
                                                        echo "No especificado";
                                                    }
                                                    ?>
                                                </strong>
                                            </div>

                                    <div>
                                        <span>Cantidad de canchas</span>
                                        <strong><?php echo htmlspecialchars($solicitud['cantidad_canchas']); ?></strong>
                                    </div>
                                </div>
                            </section>

                            <!-- INFORMACIÓN DE LAS CANCHAS -->
                            <section class="solicitud-detalle-seccion">
                                <h3>🎾 Información de las Canchas</h3>

                                <?php if ($resultadoCanchas && mysqli_num_rows($resultadoCanchas) > 0) { ?>
                                    <div class="solicitud-canchas-grid">

                                        <?php
                                        $numeroCancha = 0;
                                        while ($cancha = mysqli_fetch_assoc($resultadoCanchas)) {
                                            $numeroCancha++;
                                        ?>

                                            <div class="solicitud-cancha-card">
                                                <div class="solicitud-cancha-titulo">
                                                    <strong>🎾 Cancha <?php echo $numeroCancha; ?></strong>

                                                    <span class="cancha-estado <?php echo ($cancha['estado'] == 'Disponible') ? 'disponible' : 'ocupada'; ?>">
                                                        <?php echo htmlspecialchars($cancha['estado']); ?>
                                                    </span>
                                                </div>

                                                <div class="solicitud-cancha-imagen">
                                                    <?php if (!empty($cancha['imagen'])) { ?>
                                                        <img src="<?php echo htmlspecialchars($cancha['imagen']); ?>" alt="Imagen de <?php echo htmlspecialchars($cancha['nombre']); ?>">
                                                    <?php } else { ?>
                                                        <div class="solicitud-sin-imagen">🏟️<span>Sin imagen</span></div>
                                                    <?php } ?>
                                                </div>

                                                <div class="solicitud-cancha-nombre">
                                                    <small>Nombre</small>
                                                    <strong><?php echo htmlspecialchars($cancha['nombre']); ?></strong>
                                                </div>
                                            </div>

                                        <?php } ?>

                                    </div>
                                <?php } else { ?>
                                    <div class="sin-canchas">No hay información de canchas.</div>
                                <?php } ?>
                            </section>

                            <!-- BOTONES -->
                            <div class="solicitud-acciones">

                                <form action="funciones/aceptar_solicitud.php" method="GET">
                                    <input type="hidden" name="id_solicitud" value="<?php echo $idSolicitud; ?>">
                                    <button type="submit" class="btn-aceptar-solicitud">✓ Aceptar</button>
                                </form>

                                <form action="funciones/rechazar_solicitud.php" method="GET">
                                    <input type="hidden" name="id_solicitud" value="<?php echo $idSolicitud; ?>">
                                    <button type="submit" class="btn-rechazar-solicitud">✕ Rechazar</button>
                                </form>

                            </div>

                            <div class="solicitud-numero">
                                Solicitud #<?php echo $idSolicitud; ?>
                            </div>

                        </div>
                    </article>

                <?php } ?>

            </div>

        <?php } ?>
<?php } ?>

    <?php if ($seccion == 'usuarios') { ?>


        <!-- ================================= -->
        <!-- USUARIOS -->
        <!-- ================================= -->

        <header class="topbar">

            <h1>Usuarios</h1>

            <p>

                Administración de usuarios registrados.

            </p>

        </header>


        <div class="tabla">

            <h2>Usuarios</h2>

            <p>

                Esta sección la vamos a desarrollar después.

            </p>

        </div>


    <?php } ?>


    <?php if ($seccion == 'torneos') { ?>


        <!-- ================================= -->
        <!-- TORNEOS -->
        <!-- ================================= -->

        <header class="topbar">

            <h1>Torneos</h1>

            <p>

                Administración de torneos.

            </p>

        </header>


    <?php } ?>

  
    <?php if ($seccion == 'canchas') { ?>

 
    <!-- ========================================= -->
    <!-- VISTA CANCHAS - ORGANIZADOR -->
    <!-- ========================================= -->

?>
    <div class="canchas-pagina">

        <!-- ENCABEZADO -->
        <div class="canchas-header">

            <div class="canchas-titulo">

                <div class="canchas-icono">
                    🏟
                </div>

                <div>
                    <h1>Canchas</h1>

                    <p>
                        Administración de las canchas de tu club.
                    </p>
                </div>

            </div>

        </div>


        <!-- ========================================= -->
        <!-- ESTADÍSTICAS -->
        <!-- ========================================= -->

        <?php

        $id_usuario = $_SESSION['id_usuario'];

    $sqlEstadisticas = "
    SELECT
        COUNT(cancha.id_cancha) AS total,
        SUM(cancha.estado = 'Disponible') AS disponibles,
        SUM(cancha.estado = 'No disponible') AS no_disponibles
    FROM cancha
    INNER JOIN club
        ON cancha.id_club = club.id_club
    WHERE club.id_usuario = ?
";
        $stmtEstadisticas = mysqli_prepare(
            $conexion,
            $sqlEstadisticas
        );

        mysqli_stmt_bind_param(
            $stmtEstadisticas,
            "i",
            $id_usuario
        );

        mysqli_stmt_execute(
            $stmtEstadisticas
        );

        $resultadoEstadisticas =
            mysqli_stmt_get_result(
                $stmtEstadisticas
            );

        $estadisticas =
            mysqli_fetch_assoc(
                $resultadoEstadisticas
            );

        $totalCanchas =
            intval($estadisticas['total'] ?? 0);

        $canchasDisponibles =
            intval($estadisticas['disponibles'] ?? 0);

        $canchasNoDisponibles =
            intval($estadisticas['no_disponibles'] ?? 0);

        ?>

        <div class="canchas-estadisticas">

            <!-- TOTAL -->
            <div class="estadistica-cancha">

                <div class="estadistica-icono azul">
                    🏟
                </div>

                <div>
                    <span>Total de canchas</span>

                    <strong>
                        <?php echo $totalCanchas; ?>
                    </strong>

                    <small>
                        Registradas
                    </small>
                </div>

            </div>


            <!-- DISPONIBLES -->
            <div class="estadistica-cancha">

                <div class="estadistica-icono verde">
                    ✓
                </div>

                <div>
                    <span>Disponibles</span>

                    <strong>
                        <?php echo $canchasDisponibles; ?>
                    </strong>

                    <small>
                        Canchas libres
                    </small>
                </div>

            </div>


            <!-- NO DISPONIBLES -->
            <div class="estadistica-cancha">

                <div class="estadistica-icono rojo">
                    ×
                </div>

                <div>
                    <span>No disponibles</span>

                    <strong>
                        <?php echo $canchasNoDisponibles; ?>
                    </strong>

                    <small>
                        No disponibles
                    </small>
                </div>

            </div>


        </div>

                <div>
                    <span>Ocupadas</span>

                    <strong>
                        <?php echo $canchasOcupadas; ?>
                    </strong>

                    <small>
                        En uso
                    </small>
                </div>

            </div>


            <!-- MANTENIMIENTO -->
            <div class="estadistica-cancha">

                <div class="estadistica-icono rojo">
                    🔧
                </div>

                <div>
                    <span>Mantenimiento</span>

                    <strong>
    <?php echo $canchasMantenimiento; ?>
                    </strong>

                    <small>
                        En mantenimiento
                    </small>
                </div>

            </div>

        </div>


        <!-- ========================================= -->
        <!-- MIS CANCHAS -->
        <!-- ========================================= -->

        <div class="mis-canchas-panel">

            <div class="mis-canchas-header">

                <div>

                    <h2>
                        🏟 Mis canchas
                    </h2>

                </div>


                <button
                    type="button"
                    class="btn-agregar-cancha"
                >
                    ＋ Agregar cancha
                </button>

            </div>


            <!-- BUSCADOR Y FILTROS -->

            <div class="canchas-filtros">

                <div class="buscador-cancha">

                    <span>⌕</span>

                    <input
                        type="text"
                        id="buscarCancha"
                        placeholder="Buscar cancha..."
                    >

                </div>


                <select
    id="filtroEstado"
    class="filtro-estado"
>
    <option value="todos">
        Estado: Todos
    </option>

    <option value="Disponible">
        Disponible
    </option>

    <option value="Ocupada">
        Ocupada
    </option>

    <option value="Mantenimiento">
        Mantenimiento
    </option>
</select>


                <div class="vista-botones">

                    <button
                        type="button"
                        class="vista-activa"
                    >
                        ▦
                    </button>

                    <button
                        type="button"
                    >
                        ☷
                    </button>

                </div>

            </div>


            <!-- ========================================= -->
            <!-- LISTADO DE CANCHAS -->
            <!-- ========================================= -->

            <div class="mis-canchas">

                <?php

                $sqlCanchas = "
                    SELECT
                        cancha.id_cancha,
                        cancha.nombre,
                        cancha.estado,
                        cancha.imagen
                    FROM cancha
                    INNER JOIN club
                        ON cancha.id_club = club.id_club
                    WHERE club.id_usuario = ?
                    ORDER BY cancha.id_cancha ASC
                ";

                $stmtCanchas =
                    mysqli_prepare(
                        $conexion,
                        $sqlCanchas
                    );

                mysqli_stmt_bind_param(
                    $stmtCanchas,
                    "i",
                    $id_usuario
                );

                mysqli_stmt_execute(
                    $stmtCanchas
                );

                $resultadoCanchas =
                    mysqli_stmt_get_result(
                        $stmtCanchas
                    );

                ?>


                <?php if (mysqli_num_rows($resultadoCanchas) > 0) { ?>

                    <?php while ($cancha = mysqli_fetch_assoc($resultadoCanchas)) { ?>

                        <article
                            class="cancha-card"
                            data-nombre="<?php echo htmlspecialchars($cancha['nombre']); ?>"
                            data-estado="<?php echo htmlspecialchars($cancha['estado']); ?>"
                        >

                            <!-- IMAGEN -->
                            <div class="cancha-card-imagen">

                                <?php if (!empty($cancha['imagen'])) { ?>

                                    <img
                                        src="<?php echo htmlspecialchars($cancha['imagen']); ?>"
                                        alt="<?php echo htmlspecialchars($cancha['nombre']); ?>"
                                    >

                                <?php } else { ?>

                                    <div class="cancha-sin-imagen">
                                        🏟
                                    </div>

                                <?php } ?>


                                <!-- ESTADO SOBRE LA IMAGEN -->

                                <span
                                    class="estado-cancha <?php echo str_replace(' ', '-', strtolower($cancha['estado'])); ?>"
                                >

                                    <span class="cancha-estado"></span>

                                    <?php echo htmlspecialchars($cancha['estado']); ?>

                                </span>

                            </div>


                            <!-- INFORMACIÓN -->

                            <div class="cancha-card-contenido">

                                <h3>
                                    <?php echo htmlspecialchars($cancha['nombre']); ?>
                                </h3>

                                <p class="cancha-ubicacion">
                                    📍 Cancha principal
                                </p>


                                <!-- BOTONES -->

                                <div class="cancha-acciones">

                                    <button
                                        type="button"
                                        class="btn-cancha detalles"
                                    >
                                        👁
                                        <span>Ver detalles</span>
                                    </button>


                                    <button
                                        type="button"
                                        class="btn-cancha estado"
                                    >
                                        ⟳
                                        <span>Cambiar estado</span>
                                    </button>


                                        <button
                                            type="button"
                                            class="btn-cancha editar"
                                            data-id="<?php echo $cancha['id_cancha']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($cancha['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-imagen="<?php echo htmlspecialchars($cancha['imagen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            ✎
                                            <span>Editar</span>
                                        </button>

                                </div>

                            </div>

                        </article>

                    <?php } ?>

                <?php } else { ?>

                    <div class="canchas-vacio">

                        <div>
                            🏟
                        </div>

                        <h3>
                            No hay canchas registradas
                        </h3>

                        <p>
                            Cuando agregues una cancha aparecerá aquí.
                        </p>

                    </div>

                <?php } ?>

            </div>

        </div>

    </div>


    <?php mysqli_stmt_close($stmtEstadisticas); ?>
    <?php mysqli_stmt_close($stmtCanchas); ?>

<?php } ?>

    <?php if ($seccion == 'ranking') { ?>


        <!-- ================================= -->
        <!-- RANKING -->
        <!-- ================================= -->

        <header class="topbar">

            <h1>Ranking</h1>

            <p>

                Ranking de jugadores.

            </p>

        </header>


    <?php } ?>


    <?php if ($seccion == 'notificaciones') { ?>


        <!-- ================================= -->
        <!-- NOTIFICACIONES -->
        <!-- ================================= -->

        <header class="topbar">

            <h1>Notificaciones</h1>

            <p>

                Notificaciones del sistema.

            </p>

        </header>


    <?php } ?>


    </main>


</div>


<?php } ?>


<?php if ($_SESSION['id_rol'] == 2) { ?>

    <!-- ========================================= -->
    <!-- PANEL ORGANIZADOR -->
    <!-- ========================================= -->

    <div class="panel">

        <!-- SIDEBAR -->

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


                <!-- PANEL -->

                <li class="<?php echo ($seccion == 'inicio') ? 'activo' : ''; ?>">

                    <a href="paneles.php?seccion=inicio">

                        🏠 Panel

                    </a>

                </li>


                <!-- TORNEOS -->

                <li class="<?php echo ($seccion == 'torneos') ? 'activo' : ''; ?>">

                    <a href="paneles.php?seccion=torneos">

                        🏆 Torneos

                    </a>

                </li>


                <!-- CANCHAS -->

                <li class="<?php echo ($seccion == 'canchas') ? 'activo' : ''; ?>">

                    <a href="paneles.php?seccion=canchas">

                        🏟 Canchas

                    </a>

                </li>


                <!-- RANKING -->

                <li class="<?php echo ($seccion == 'ranking') ? 'activo' : ''; ?>">

                    <a href="paneles.php?seccion=ranking">

                        📊 Ranking

                    </a>

                </li>


                <!-- NOTIFICACIONES -->

                <li class="<?php echo ($seccion == 'notificaciones') ? 'activo' : ''; ?>">

                    <a href="paneles.php?seccion=notificaciones">

                        📢 Notificaciones

                    </a>

                </li>


                <!-- CERRAR SESIÓN -->

                <li>

                    <a href="funciones/logout.php">

                        🚪 Cerrar sesión

                    </a>

                </li>


            </ul>

        </aside>


        <!-- CONTENIDO -->

        <main class="contenido <?php echo ($seccion == 'canchas') ? 'contenido-canchas' : ''; ?>">

            <?php if ($seccion == 'inicio') { ?>

                <header class="topbar">

                    <div>

                        <h1>Panel del Organizador</h1>

                        <p>

                            Bienvenido,

                            <strong>

                                <?php echo htmlspecialchars(
                                    $_SESSION['nombre']
                                ); ?>

                            </strong>

                        </p>

                    </div>

                </header>


                <section class="cards">

                    <div class="card">

                        <h3>Mis torneos</h3>

                        <span>0</span>

                    </div>


                    <div class="card">

                        <h3>Mis canchas</h3>

                        <span>0</span>

                    </div>


                    <div class="card">

                        <h3>Inscripciones</h3>

                        <span>0</span>

                    </div>

                </section>

            <?php } ?>


           <?php if ($seccion == 'torneos') { ?>
           <?php

/* =========================================================
   DATOS DE LOS TORNEOS
   ========================================================= */

$id_usuario = $_SESSION['id_usuario'];


/* =========================================================
   BUSCAR CLUB DEL ORGANIZADOR
   ========================================================= */

$sqlClubTorneos = "
    SELECT id_club
    FROM club
    WHERE id_usuario = ?
    LIMIT 1
";

$stmtClubTorneos = mysqli_prepare(
    $conexion,
    $sqlClubTorneos
);

$id_club_torneos = 0;

if ($stmtClubTorneos) {

    mysqli_stmt_bind_param(
        $stmtClubTorneos,
        "i",
        $id_usuario
    );

    mysqli_stmt_execute(
        $stmtClubTorneos
    );

    $resultadoClubTorneos =
        mysqli_stmt_get_result(
            $stmtClubTorneos
        );

    $clubTorneos =
        mysqli_fetch_assoc(
            $resultadoClubTorneos
        );

    mysqli_stmt_close(
        $stmtClubTorneos
    );

    if ($clubTorneos) {

        $id_club_torneos =
            intval($clubTorneos['id_club']);

    }

}


/* =========================================================
   OBTENER TORNEOS
   ========================================================= */

$torneos = [];

if ($id_club_torneos > 0) {

    $sqlTorneos = "
        SELECT
            torneos.id_torneo,
            torneos.nombre,
            torneos.imagen,
            torneos.fecha_inicio,
            torneos.hora_inicio,
            torneos.fecha_fin,
            torneos.hora_fin,
            torneos.lugar,
            torneos.tipo,
            torneos.max_participantes,
            torneos.estado,
            categoria.nombre AS categoria
        FROM torneos
        INNER JOIN categoria
            ON torneos.id_categoria =
               categoria.id_categoria
        WHERE torneos.id_club = ?
        ORDER BY torneos.fecha_inicio DESC
    ";

    $stmtTorneos = mysqli_prepare(
        $conexion,
        $sqlTorneos
    );

    if ($stmtTorneos) {

        mysqli_stmt_bind_param(
            $stmtTorneos,
            "i",
            $id_club_torneos
        );

        mysqli_stmt_execute(
            $stmtTorneos
        );

        $resultadoTorneos =
            mysqli_stmt_get_result(
                $stmtTorneos
            );

        while (
            $torneo =
            mysqli_fetch_assoc(
                $resultadoTorneos
            )
        ) {

            $torneos[] = $torneo;

        }

        mysqli_stmt_close(
            $stmtTorneos
        );

    }

}


/* =========================================================
   ESTADÍSTICAS
   ========================================================= */

$totalTorneos = count($torneos);

$torneosAbiertos = 0;
$torneosEnCurso = 0;
$torneosFinalizados = 0;

foreach ($torneos as $torneo) {

    if ($torneo['estado'] === 'Abierto') {

        $torneosAbiertos++;

    }

    if ($torneo['estado'] === 'En curso') {

        $torneosEnCurso++;

    }

    if ($torneo['estado'] === 'Finalizado') {

        $torneosFinalizados++;

    }

}

?>

    <!-- ========================================= -->
    <!-- VISTA TORNEOS - ORGANIZADOR -->
    <!-- ========================================= -->

    <div class="torneos-pagina">

        <!-- ENCABEZADO -->
        <div class="torneos-header">

            <div class="torneos-titulo">

                <div class="torneos-icono">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M7 4H17V8C17 11.3137 14.7614 14 12 14C9.23858 14 7 11.3137 7 8V4Z"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linejoin="round"
                        />

                        <path
                            d="M7 6H4C4 10.4183 6.23858 13 9.5 13"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                        <path
                            d="M17 6H20C20 10.4183 17.7614 13 14.5 13"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                        <path
                            d="M12 14V19"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                        <path
                            d="M8 21H16"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                    </svg>

                </div>

                <div>
                    <h1>Mis torneos</h1>

                    <p>
                        Administración de los torneos de tu club.
                    </p>
                </div>

            </div>

            <button
                type="button"
                class="btn-crear-torneo"
                id="btnAbrirCrearTorneo"
            >

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M12 5V19"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                    />

                    <path
                        d="M5 12H19"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                    />
                </svg>

                Crear torneo

            </button>

        </div>


        <!-- ========================================= -->
        <!-- ESTADÍSTICAS -->
        <!-- ========================================= -->

        <div class="torneos-estadisticas">

            <!-- TOTAL -->
            <div class="estadistica-torneo">

                <div class="estadistica-torneo-icono azul">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M6 3H18V7C18 10.3137 15.3137 13 12 13C8.68629 13 6 10.3137 6 7V3Z"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linejoin="round"
                        />

                        <path
                            d="M6 5H3C3 9.41828 5.23858 12 8.5 12"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                        <path
                            d="M18 5H21C21 9.41828 18.7614 12 15.5 12"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                        <path
                            d="M12 13V18"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                        <path
                            d="M8 21H16"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />

                    </svg>

                </div>

                <div>

                    <span>Total de torneos</span>

                    <strong>
    <?php echo $totalTorneos; ?>
</strong>

                    <small>
                        Registrados
                    </small>

                </div>

            </div>


            <!-- ABIERTOS -->
            <div class="estadistica-torneo">

                <div class="estadistica-torneo-icono verde">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M5 12L10 17L19 7"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>

                </div>

                <div>

                    <span>Abiertos</span>

                        <strong>
    <?php echo $torneosAbiertos; ?>
</strong>

                    <small>
                        Inscripciones abiertas
                    </small>

                </div>

            </div>


            <!-- EN CURSO -->
            <div class="estadistica-torneo">

                <div class="estadistica-torneo-icono amarillo">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <circle
                            cx="12"
                            cy="12"
                            r="8"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />

                        <path
                            d="M12 8V12L15 14"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>

                </div>

                <div>

                    <span>En curso</span>

                    <strong>
    <?php echo $torneosEnCurso; ?>
</strong>

                    <small>
                        Torneos activos
                    </small>

                </div>

            </div>


            <!-- FINALIZADOS -->
            <div class="estadistica-torneo">

                <div class="estadistica-torneo-icono gris">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M5 12L10 17L19 7"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>

                </div>

                <div>

                    <span>Finalizados</span>

                    <strong>
    <?php echo $torneosFinalizados; ?>
</strong>

                    <small>
                        Torneos terminados
                    </small>

                </div>

            </div>

        </div>


        <!-- ========================================= -->
        <!-- LISTADO DE TORNEOS -->
        <!-- ========================================= -->

        <div class="mis-torneos-panel">

            <!-- CABECERA -->
            <div class="mis-torneos-header">

                <div>

                    <h2>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M6 3H18V7C18 10.3137 15.3137 13 12 13C8.68629 13 6 10.3137 6 7V3Z"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linejoin="round"
                            />

                            <path
                                d="M6 5H3C3 9.41828 5.23858 12 8.5 12"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                            <path
                                d="M18 5H21C21 9.41828 18.7614 12 15.5 12"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                            <path
                                d="M12 13V18"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />
                        </svg>

                        Mis torneos
                    </h2>

                </div>

            </div>


            <!-- BUSCADOR Y FILTROS -->
            <div class="torneos-filtros">

                <div class="torneo-buscador">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <circle
                            cx="11"
                            cy="11"
                            r="6.5"
                            stroke="currentColor"
                            stroke-width="1.8"
                        />

                        <path
                            d="M16 16L21 21"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />
                    </svg>

                    <input
                        type="text"
                        id="buscarTorneo"
                        placeholder="Buscar torneo..."
                        autocomplete="off"
                    >

                </div>


                <select
                    id="filtroEstadoTorneo"
                    class="filtro-estado-torneo"
                >

                    <option value="todos">
                        Estado: Todos
                    </option>

                    <option value="Abierto">
                        Abierto
                    </option>

                    <option value="Cerrado">
                        Cerrado
                    </option>

                    <option value="En curso">
                        En curso
                    </option>

                    <option value="Finalizado">
                        Finalizado
                    </option>

                </select>

            </div>


            <!-- ========================================= -->
            <!-- CONTENEDOR DE TORNEOS -->
            <!-- ========================================= -->

            <!-- =========================================================
     GRID DE TORNEOS
     ========================================================= -->

<div
    class="torneos-grid"
    id="listaTorneos"
>

    <?php if (!empty($torneos)) { ?>

        <?php foreach ($torneos as $torneo) { ?>

            <?php
                $estado = $torneo['estado'];

                $claseEstado = '';

                if ($estado === 'Abierto') {
                    $claseEstado = 'abierto';
                } elseif ($estado === 'Cerrado') {
                    $claseEstado = 'cerrado';
                } elseif ($estado === 'En curso') {
                    $claseEstado = 'en-curso';
                } elseif ($estado === 'Finalizado') {
                    $claseEstado = 'finalizado';
                }


                /* -----------------------------------------
                   FORMATEAR FECHAS
                   ----------------------------------------- */

                $fechaInicio = date(
                    'd/m/Y',
                    strtotime($torneo['fecha_inicio'])
                );

                $fechaFin = date(
                    'd/m/Y',
                    strtotime($torneo['fecha_fin'])
                );
                $horaInicio = date(
    'H:i',
    strtotime($torneo['hora_inicio'])
);

$horaFin = date(
    'H:i',
    strtotime($torneo['hora_fin'])
);


                /* -----------------------------------------
                   IMAGEN
                   ----------------------------------------- */

                $imagenTorneo = $torneo['imagen'] ?? '';


                /* -----------------------------------------
                   DATOS SEGUROS
                   ----------------------------------------- */

                $nombreTorneo = htmlspecialchars(
                    $torneo['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                $lugarTorneo = htmlspecialchars(
                    $torneo['lugar'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                $categoriaTorneo = htmlspecialchars(
                    $torneo['categoria'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                $tipoTorneo = htmlspecialchars(
                    $torneo['tipo'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                $estadoTorneo = htmlspecialchars(
                    $estado,
                    ENT_QUOTES,
                    'UTF-8'
                );
            ?>


            <article
                class="torneo-card"
                data-nombre="<?php echo $nombreTorneo; ?>"
                data-estado="<?php echo $estadoTorneo; ?>"
            >


                <!-- =====================================
                     IMAGEN
                     ===================================== -->

                <div class="torneo-card-imagen">

                    <?php if (!empty($imagenTorneo)) { ?>

                        <img
                            src="<?php echo htmlspecialchars(
                                $imagenTorneo,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            alt="<?php echo $nombreTorneo; ?>"
                        >

                    <?php } else { ?>

                        <div class="torneo-card-sin-imagen">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >

                                <path
                                    d="M6 3H18V7C18 10.3137 15.3137 13 12 13C8.68629 13 6 10.3137 6 7V3Z"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linejoin="round"
                                />

                                <path
                                    d="M6 5H3C3 9.41828 5.23858 12 8.5 12"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M18 5H21C21 9.41828 18.7614 12 15.5 12"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M12 13V18"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M8 21H16"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />

                            </svg>

                            <span>
                                Sin imagen
                            </span>

                        </div>

                    <?php } ?>


                    <!-- ESTADO -->

                    <div
                        class="torneo-estado <?php echo $claseEstado; ?>"
                    >

                        <span class="torneo-estado-punto"></span>

                        <?php echo $estadoTorneo; ?>

                    </div>

                </div>


                <!-- =====================================
                     INFORMACIÓN
                     ===================================== -->

                <div class="torneo-card-contenido">


                    <!-- NOMBRE -->

                    <h3>
                        <?php echo $nombreTorneo; ?>
                    </h3>


                    <!-- FECHA -->

                    <div class="torneo-dato">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >

                            <rect
                                x="3"
                                y="5"
                                width="18"
                                height="16"
                                rx="2"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />

                            <path
                                d="M16 3V7"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                            <path
                                d="M8 3V7"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                            <path
                                d="M3 10H21"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />

                        </svg>

                        <span>
    <?php echo $fechaInicio; ?>
    -
    <?php echo $fechaFin; ?>

    <span class="torneo-horario">
        <?php echo $horaInicio; ?>
        -
        <?php echo $horaFin; ?>
    </span>
</span>

                    </div>


                    <!-- LUGAR -->

                    <div class="torneo-dato">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >

                            <path
                                d="M12 21C16 16.5 19 13.5 19 9.5C19 5.91015 15.866 3 12 3C8.13401 3 5 5.91015 5 9.5C5 13.5 8 16.5 12 21Z"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />

                            <circle
                                cx="12"
                                cy="9.5"
                                r="2.5"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />

                        </svg>

                        <span>
                            <?php echo $lugarTorneo; ?>
                        </span>

                    </div>


                    <!-- CATEGORÍA -->

                    <div class="torneo-dato">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >

                            <path
                                d="M6 3H18V7C18 10.3137 15.3137 13 12 13C8.68629 13 6 10.3137 6 7V3Z"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linejoin="round"
                            />

                            <path
                                d="M12 13V18"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                            <path
                                d="M8 21H16"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                        </svg>

                        <span>
                            <?php echo $categoriaTorneo; ?> categoría
                        </span>

                    </div>


                    <!-- TIPO -->

                    <div class="torneo-dato">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >

                            <circle
                                cx="9"
                                cy="8"
                                r="3"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />

                            <circle
                                cx="17"
                                cy="9"
                                r="2.5"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />

                            <path
                                d="M3.5 20C3.5 16.6863 5.96243 14 9 14C12.0376 14 14.5 16.6863 14.5 20"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                            <path
                                d="M14.5 14.5C15.25 14.18 16.08 14 17 14C19.4853 14 21.5 15.79 21.5 18.5"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                        </svg>

                        <span>
                            <?php echo $tipoTorneo; ?>
                        </span>

                    </div>


                    <!-- PARTICIPANTES -->

                    <div class="torneo-dato">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >

                            <circle
                                cx="9"
                                cy="8"
                                r="3"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />

                            <path
                                d="M3.5 20C3.5 16.6863 5.96243 14 9 14C12.0376 14 14.5 16.6863 14.5 20"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                            <path
                                d="M16 11C18.2091 11 20 12.7909 20 15"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />

                        </svg>

                        <span>
                            0 /
                            <?php echo intval(
                                $torneo['max_participantes']
                            ); ?>
                            participantes
                        </span>

                    </div>


                    <!-- =================================
                         ACCIONES
                         ================================= -->

                    <div class="torneo-card-acciones">

                        <button
                            type="button"
                            class="btn-torneo ver"
                            data-id="<?php echo intval(
                                $torneo['id_torneo']
                            ); ?>"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >

                                <path
                                    d="M2.5 12C4.8 7.5 8 5 12 5C16 5 19.2 7.5 21.5 12C19.2 16.5 16 19 12 19C8 19 4.8 16.5 2.5 12Z"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linejoin="round"
                                />

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="2.5"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                />

                            </svg>

                            <span>
                                Ver
                            </span>

                        </button>


                        <button
                            type="button"
                            class="btn-torneo editar"
                            data-id="<?php echo intval(
                                $torneo['id_torneo']
                            ); ?>"
                            data-nombre="<?php echo $nombreTorneo; ?>"
                            data-fecha-inicio="<?php echo htmlspecialchars(
                                $torneo['fecha_inicio'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            data-hora-inicio="<?php echo htmlspecialchars(
                                $horaInicio,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            data-fecha-fin="<?php echo htmlspecialchars(
                                $torneo['fecha_fin'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            data-hora-fin="<?php echo htmlspecialchars(
                                $horaFin,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            data-categoria="<?php echo $categoriaTorneo; ?>"
                            data-tipo="<?php echo $tipoTorneo; ?>"
                            data-max-participantes="<?php echo intval(
                                $torneo['max_participantes']
                            ); ?>"
                            data-estado="<?php echo $estadoTorneo; ?>"
                            data-imagen="<?php echo htmlspecialchars(
                                $imagenTorneo,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >

                                <path
                                    d="M4 20H8L19 9C20.1046 7.89543 20.1046 6.10457 19 5C17.8954 3.89543 16.1046 3.89543 15 5L4 16V20Z"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linejoin="round"
                                />

                                <path
                                    d="M13 7L17 11"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />

                            </svg>

                            <span>
                                Editar
                            </span>

                        </button>


                        <button
                            type="button"
                            class="btn-torneo eliminar"
                            data-id="<?php echo intval(
                                $torneo['id_torneo']
                            ); ?>"
                            data-nombre="<?php echo $nombreTorneo; ?>"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >

                                <path
                                    d="M5 7H19"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M9 7V5H15V7"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M7 7L8 20H16L17 7"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linejoin="round"
                                />

                                <path
                                    d="M10 11V17"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M14 11V17"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />

                            </svg>

                            <span>
                                Eliminar
                            </span>

                        </button>

                    </div>

                </div>

            </article>

        <?php } ?>

    <?php } else { ?>


        <!-- =====================================
             SIN TORNEOS
             ===================================== -->

        <div class="torneos-vacio">

            <div class="torneos-vacio-icono">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >

                    <path
                        d="M6 3H18V7C18 10.3137 15.3137 13 12 13C8.68629 13 6 10.3137 6 7V3Z"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linejoin="round"
                    />

                    <path
                        d="M6 5H3C3 9.41828 5.23858 12 8.5 12"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M18 5H21C21 9.41828 18.7614 12 15.5 12"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M12 13V18"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M8 21H16"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                </svg>

            </div>

            <h3>
                No hay torneos registrados
            </h3>

            <p>
                Cuando crees un torneo aparecerá aquí.
            </p>

        </div>


    <?php } ?>

</div>

            

        </div>

    </div>
<!-- =========================================================
     MODAL CREAR TORNEO
     ========================================================= -->

<div class="modal-torneo" id="modalCrearTorneo">

    <div class="modal-torneo-contenido">

        <!-- CERRAR -->

        <button
            type="button"
            class="modal-torneo-cerrar"
            id="cerrarCrearTorneo"
        >
            ×
        </button>


        <!-- ENCABEZADO -->

        <div class="modal-torneo-header">

            <div class="modal-torneo-icono">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >

                    <path
                        d="M6 3H18V7C18 10.3137 15.3137 13 12 13C8.68629 13 6 10.3137 6 7V3Z"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linejoin="round"
                    />

                    <path
                        d="M6 5H3C3 9.41828 5.23858 12 8.5 12"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M18 5H21C21 9.41828 18.7614 12 15.5 12"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M12 13V18"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M8 21H16"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                </svg>

            </div>

            <div>

                <h2 id="modalTorneoTitulo">Crear torneo</h2>

                <p id="modalTorneoSubtitulo">
                    Completá los datos del nuevo torneo.
                </p>

            </div>

        </div>


        <!-- FORMULARIO -->

        <form
            action="funciones/crear_torneo.php"
            method="POST"
            enctype="multipart/form-data"
            id="formCrearTorneo"
        >
        <input
            type="hidden"
            id="idTorneoEditar"
            name="id_torneo"
            value=""
        >

        <!-- NOMBRE DEL TORNEO -->

<div class="campo-torneo">
    <label for="nombreTorneo">
        Nombre del torneo
    </label>

    <input
        type="text"
        id="nombreTorneo"
        name="nombre"
        maxlength="100"
        placeholder="Ej. Copa The Bull"
        required
    >
</div>


            <!-- FILA 1 -->

           <!-- =========================================
     FECHA Y HORA
     ========================================= -->

<div class="form-torneo-fila">

    <div class="campo-torneo">

        <label for="fechaInicioTorneo">
            Fecha de inicio
        </label>

        <input
            type="date"
            id="fechaInicioTorneo"
            name="fecha_inicio"
            required
        >

    </div>


    <div class="campo-torneo">

        <label for="horaInicioTorneo">
            Hora de inicio
        </label>

        <input
            type="time"
            id="horaInicioTorneo"
            name="hora_inicio"
            required
        >

    </div>

</div>


<div class="form-torneo-fila">

    <div class="campo-torneo">

        <label for="fechaFinTorneo">
            Fecha de finalización
        </label>

        <input
            type="date"
            id="fechaFinTorneo"
            name="fecha_fin"
            required
        >

    </div>


    <div class="campo-torneo">

        <label for="horaFinTorneo">
            Hora de finalización
        </label>

        <input
            type="time"
            id="horaFinTorneo"
            name="hora_fin"
            required
        >

    </div>

</div>  

            <!-- IMAGEN -->

            <div class="campo-torneo">

                <label for="imagenTorneo">
                    Imagen del torneo
                </label>

                <div
                    class="subir-imagen-torneo"
                    id="zonaImagenTorneo"
                >

                    <div class="subir-imagen-torneo-icono">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >

                            <rect
                                x="3"
                                y="4"
                                width="18"
                                height="16"
                                rx="2"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />

                            <circle
                                cx="8.5"
                                cy="9"
                                r="1.5"
                                stroke="currentColor"
                                stroke-width="1.6"
                            />

                            <path
                                d="M3 17L8 12L12 16L15 13L21 19"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                        </svg>

                    </div>

                    <strong>
                        Seleccionar imagen
                    </strong>

                    <span>
                        JPG, PNG o WEBP · Máximo 5 MB
                    </span>

                    <input
                        type="file"
                        id="imagenTorneo"
                        name="imagen"
                        accept="image/jpeg,image/png,image/webp"
                    >

                </div>

                <div
                    class="preview-torneo"
                    id="previewTorneo"
                ></div>

            </div>


            


            <!-- CATEGORÍA / TIPO -->

            <div class="form-torneo-fila">

                <div class="campo-torneo">

                    <label for="categoriaTorneo">
                        Categoría
                    </label>

                    <select
                        id="categoriaTorneo"
                        name="categoria"
                        required
                    >

                        <option value="">
                            Seleccionar categoría
                        </option>

                        <option value="Principiante">
                            Principiante
                        </option>

                        <option value="7ta">
                            7ta
                        </option>

                        <option value="6ta">
                            6ta
                        </option>

                        <option value="5ta">
                            5ta
                        </option>

                        <option value="4ta">
                            4ta
                        </option>

                        <option value="3ra">
                            3ra
                        </option>

                        <option value="2da">
                            2da
                        </option>

                        <option value="1ra">
                            1ra
                        </option>

                    </select>

                </div>


                <div class="campo-torneo">

                    <label for="tipoTorneo">
                        Tipo de torneo
                    </label>

                    <select
                        id="tipoTorneo"
                        name="tipo"
                        required
                    >

                        <option value="">
                            Seleccionar tipo
                        </option>

                        <option value="Individual">
                            Individual
                        </option>

                        <option value="Parejas">
                            Parejas
                        </option>

                        <option value="Mixto">
                            Mixto
                        </option>

                    </select>

                </div>

            </div>


            <!-- PARTICIPANTES / ESTADO -->

            <div class="form-torneo-fila">

                <div class="campo-torneo">

                    <label for="maxParticipantes">
                        Máximo de participantes
                    </label>

                    <input
                        type="number"
                        id="maxParticipantes"
                        name="max_participantes"
                        min="2"
                        max="1000"
                        placeholder="Ej. 32"
                        required
                    >

                </div>


                <div class="campo-torneo">

                    <label for="estadoTorneo">
                        Estado
                    </label>

                    <select
                        id="estadoTorneo"
                        name="estado"
                        required
                    >

                        <option value="Abierto">
                            Abierto
                        </option>

                        <option value="Cerrado">
                            Cerrado
                        </option>

                        <option value="En curso">
                            En curso
                        </option>

                        <option value="Finalizado">
                            Finalizado
                        </option>

                    </select>

                </div>

            </div>


            <!-- BOTONES -->

            <div class="modal-torneo-acciones">

                <button
                    type="button"
                    class="btn-cancelar-torneo"
                    id="cancelarCrearTorneo"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-guardar-torneo"
                >

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >

                        <path
                            d="M5 12L10 17L19 7"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                    </svg>

                    <span id="textoBotonGuardarTorneo">Crear torneo</span>

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     MODAL ELIMINAR TORNEO
     ========================================================= -->

<div class="modal-eliminar-cancha" id="modalEliminarTorneo">

    <div class="modal-eliminar-contenido">

        <!-- CERRAR -->
        <button
            type="button"
            class="modal-eliminar-cerrar"
            id="cerrarEliminarTorneo"
        >
            ×
        </button>

        <!-- ICONO -->
        <div class="modal-eliminar-icono">
            🗑
        </div>

        <!-- TITULO -->
        <h2>
            ¿Eliminar <span>torneo?</span>
        </h2>

        <p class="modal-eliminar-texto">
            ¿Estás seguro de que querés eliminar el torneo
            <strong id="nombreTorneoEliminar"></strong>?
        </p>

        <p class="modal-eliminar-aviso">
            Esta acción no se puede deshacer.
        </p>

        <!-- ADVERTENCIA -->
        <div class="modal-eliminar-advertencia">

            <div class="modal-eliminar-advertencia-icono">
                !
            </div>

            <p>
                Se eliminará el torneo y toda la información
                asociada a él.
            </p>

        </div>

        <!-- FORMULARIO -->
        <form
            action="funciones/eliminar_torneo.php"
            method="POST"
            id="formEliminarTorneo"
        >

            <input
                type="hidden"
                name="id_torneo"
                id="idTorneoEliminar"
            >

            <div class="modal-eliminar-acciones">

                <button
                    type="button"
                    class="btn-modal-cancelar"
                    id="cancelarEliminarTorneo"
                >
                    <span>×</span>
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-modal-eliminar"
                >
                    <span>🗑</span>
                    Sí, eliminar
                </button>

            </div>

        </form>

    </div>

</div>
<?php } ?>


    <?php if ($seccion == 'canchas') { ?>

    <!-- ========================================= -->
    <!-- VISTA CANCHAS - ORGANIZADOR -->
    <!-- ========================================= -->
<?php if (
    isset($_GET['error']) &&
    $_GET['error'] === 'cancha_repetida'
) { ?>

<div class="modal-error-cancha" id="modalErrorCancha">

    <div class="modal-error-cancha-contenido">

        <button
            type="button"
            class="modal-error-cancha-cerrar"
            id="cerrarModalErrorCancha"
        >
            ×
        </button>

        <div class="modal-error-cancha-icono">
            !
        </div>

        <h2>
            Nombre de cancha en uso
        </h2>

        <p>
            Ya existe una cancha con ese nombre.
            <br>
            Por favor, ingresá un nombre diferente.
        </p>

        <button
            type="button"
            class="modal-error-cancha-aceptar"
            id="aceptarModalErrorCancha"
        >
            Aceptar
        </button>

    </div>

</div>

<?php } ?>

<div class="canchas-pagina">

        <!-- ENCABEZADO -->
        <div class="canchas-header">

            <div class="canchas-titulo">

                <div class="canchas-icono">
                    🏟
                </div>

                <div>
                    <h1>Canchas</h1>

                    <p>
                        Administración de las canchas de tu club.
                    </p>
                </div>

            </div>

        </div>


        <!-- ========================================= -->
        <!-- ESTADÍSTICAS -->
        <!-- ========================================= -->

        <?php

        $id_usuario = $_SESSION['id_usuario'];

      $sqlEstadisticas = "
    SELECT
        COUNT(cancha.id_cancha) AS total,
        SUM(cancha.estado = 'Disponible') AS disponibles,
        SUM(cancha.estado = 'No disponible') AS no_disponibles
    FROM cancha
    INNER JOIN club
        ON cancha.id_club = club.id_club
    WHERE club.id_usuario = ?
";
        $stmtEstadisticas = mysqli_prepare(
            $conexion,
            $sqlEstadisticas
        );

        mysqli_stmt_bind_param(
            $stmtEstadisticas,
            "i",
            $id_usuario
        );

        mysqli_stmt_execute(
            $stmtEstadisticas
        );

        $resultadoEstadisticas =
            mysqli_stmt_get_result(
                $stmtEstadisticas
            );

        $estadisticas =
            mysqli_fetch_assoc(
                $resultadoEstadisticas
            );

        $totalCanchas =
            intval($estadisticas['total'] ?? 0);

      $canchasDisponibles =
    intval($estadisticas['disponibles'] ?? 0);

$canchasNoDisponibles =
    intval($estadisticas['no_disponibles'] ?? 0);

        ?>

        <div class="canchas-estadisticas">

            <!-- TOTAL -->
            <div class="estadistica-cancha">

                <div class="estadistica-icono azul">
                    🏟
                </div>

                <div>
                    <span>Total de canchas</span>

                    <strong>
                        <?php echo $totalCanchas; ?>
                    </strong>

                    <small>
                        Registradas
                    </small>
                </div>

            </div>


            <!-- DISPONIBLES -->
            <div class="estadistica-cancha">

                <div class="estadistica-icono verde">
                    ✓
                </div>

                <div>
                    <span>Disponibles</span>

                    <strong>
                        <?php echo $canchasDisponibles; ?>
                    </strong>

                    <small>
                        Canchas libres
                    </small>
                </div>

            </div>


           <!-- NO DISPONIBLES -->
<div class="estadistica-cancha">
    <div class="estadistica-icono rojo">
        !
    </div>

    <div>
        <span>No disponibles</span>

        <strong>
            <?php echo $canchasNoDisponibles; ?>
        </strong>

        <small>
            No disponibles
        </small>
    </div>
</div>

        </div>


        <!-- ========================================= -->
        <!-- MIS CANCHAS -->
        <!-- ========================================= -->

        <div class="mis-canchas-panel">

            <div class="mis-canchas-header">

                <div>

                    <h2>
                        🏟 Mis canchas
                    </h2>

                </div>


                <button
                    type="button"
                    class="btn-agregar-cancha"
                    id="btnAbrirAgregarCancha"
                >
                    ＋ Agregar cancha
                </button>

            </div>
            <!-- ==========================================
     MODAL AGREGAR CANCHA
========================================== -->

<div class="modal-cancha" id="modalAgregarCancha">

    <div class="modal-cancha-contenido">

        <button
            type="button"
            class="modal-cancha-cerrar"
            id="cerrarAgregarCancha"
        >
            ×
        </button>

        <div class="modal-cancha-header">

            <div class="modal-cancha-icono">
                🏟
            </div>

            <div>
                <h2>Agregar cancha</h2>

                <p>
                    Registrá una nueva cancha para tu club.
                </p>
            </div>

        </div>


        <form
            action="funciones/agregar_cancha.php"
            method="POST"
            enctype="multipart/form-data"
            id="formAgregarCancha"
        >

            <!-- NOMBRE -->

            <div class="campo-cancha">

                <label for="nombre_cancha">
                    Nombre de la cancha
                </label>

                <input
                    type="text"
                    id="nombre_cancha"
                    name="nombre_cancha"
                    placeholder="Ej: Cancha 2"
                    maxlength="50"
                    required
                >

            </div>


            <!-- IMAGEN -->

            <div class="campo-cancha">

                <label for="imagen_cancha">
                    Imagen de la cancha
                </label>

                <div
                    class="subir-imagen-cancha"
                    id="zonaImagenCancha"
                >

                    <div class="subir-imagen-icono">
                        📷
                    </div>

                    <strong>
                        Seleccionar imagen
                    </strong>

                    <span>
                        JPG, PNG o WEBP · Máximo 5 MB
                    </span>

                    <input
                        type="file"
                        id="imagen_cancha"
                        name="imagen_cancha"
                        accept="image/jpeg,image/png,image/webp"
                        required
                    >

                </div>

                <div
                    class="preview-cancha"
                    id="previewCancha"
                ></div>

            </div>


            <!-- INFORMACIÓN -->

            <div class="aviso-cancha">

                <span>✓</span>

                <p>
                    La cancha se registrará automáticamente
                    como <strong>Disponible</strong>.
                </p>

            </div>


            <!-- BOTONES -->

            <div class="modal-cancha-acciones">

                <button
                    type="button"
                    class="btn-cancelar-cancha"
                    id="cancelarAgregarCancha"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-guardar-cancha"
                >
                    ✓ Guardar cancha
                </button>

            </div>

        </form>

    </div>

</div>


            <!-- BUSCADOR Y FILTROS -->
             <!-- ==========================================
     MODAL CAMBIAR ESTADO
========================================== -->

<div class="modal-cancha" id="modalCambiarEstado">

    <div class="modal-cancha-contenido">

        <button
            type="button"
            class="modal-cancha-cerrar"
            id="cerrarCambiarEstado"
        >
            ×
        </button>

        <div class="modal-cancha-header">

            <div class="modal-cancha-icono">
                ⟳
            </div>

            <div>
                <h2>Cambiar estado</h2>

                <p>
                    Modificá el estado de la cancha.
                </p>
            </div>

        </div>


        <form
            action="funciones/cambiar_estado_cancha.php"
            method="POST"
            id="formCambiarEstado"
        >

            <!-- ID DE LA CANCHA -->

            <input
                type="hidden"
                name="id_cancha"
                id="estadoIdCancha"
            >


            <!-- NOMBRE -->

            <div class="campo-cancha">

                <label>
                    Cancha
                </label>

                <input
                    type="text"
                    id="estadoNombreCancha"
                    readonly
                >

            </div>


            <!-- ESTADO -->

            <div class="campo-cancha">

                <label for="nuevoEstadoCancha">
                    Nuevo estado
                </label>

                                     <select
                                            name="estado"
                                            id="nuevoEstadoCancha"
                                            required
                                        >
                                            <option value="Disponible">
                                                Disponible
                                            </option>

                                            <option value="No disponible">
                                                No disponible
                                            </option>
                                        </select>

            </div>

            <!-- BOTONES -->

            <div class="modal-cancha-acciones">

                <button
                    type="button"
                    class="btn-cancelar-cancha"
                    id="cancelarCambiarEstado"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-guardar-cancha"
                >
                    ✓ Guardar cambios
                </button>

            </div>

        </form>

    </div>

</div>
<!-- ==========================================
     MODAL EDITAR CANCHA
========================================== -->

<div class="modal-cancha" id="modalEditarCancha">

    <div class="modal-cancha-contenido">

        <button
            type="button"
            class="modal-cancha-cerrar"
            id="cerrarEditarCancha"
        >
            ×
        </button>

        <div class="modal-cancha-header">

            <div class="modal-cancha-icono">
                ✎
            </div>

            <div>
                <h2>Editar cancha</h2>

                <p>
                    Modificá los datos de la cancha.
                </p>
            </div>

        </div>


        <form
            action="funciones/editar_cancha.php"
            method="POST"
            enctype="multipart/form-data"
            id="formEditarCancha"
        >

            <!-- ID DE LA CANCHA -->

            <input
                type="hidden"
                name="id_cancha"
                id="editarIdCancha"
            >


            <!-- NOMBRE -->

            <div class="campo-cancha">

                <label for="editarNombreCancha">
                    Nombre de la cancha
                </label>

                <input
                    type="text"
                    id="editarNombreCancha"
                    name="nombre_cancha"
                    maxlength="50"
                    required
                >

            </div>


            <!-- IMAGEN -->

            <div class="campo-cancha">

                <label for="editarImagenCancha">
                    Imagen de la cancha
                </label>

                <div
                    class="subir-imagen-cancha"
                    id="zonaEditarImagenCancha"
                >

                    <div class="subir-imagen-icono">
                        📷
                    </div>

                    <strong>
                        Cambiar imagen
                    </strong>

                    <span>
                        JPG, PNG o WEBP · Máximo 5 MB
                    </span>

                    <input
                        type="file"
                        id="editarImagenCancha"
                        name="imagen_cancha"
                        accept="image/jpeg,image/png,image/webp"
                    >

                </div>


                <!-- IMAGEN ACTUAL / PREVISUALIZACIÓN -->

                <div
                    class="preview-cancha"
                    id="previewEditarCancha"
                ></div>

            </div>


            <!-- BOTONES -->

            <div class="modal-cancha-acciones">

                <button
                    type="button"
                    class="btn-cancelar-cancha"
                    id="cancelarEditarCancha"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-guardar-cancha"
                >
                    ✓ Guardar cambios
                </button>

            </div>

        </form>

    </div>

</div>
            <div class="canchas-filtros">

                <div class="buscador-cancha">

                    <span>⌕</span>

                    <input
                        type="text"
                        id="buscarCancha"
                        placeholder="Buscar cancha..."
                    >

                </div>


                        <select
    id="filtroEstado"
    class="filtro-estado"
>
    
       <option value="todos">
    Estado: Todos
</option>

<option value="Disponible">
    Disponible
</option>

<option value="No disponible">
    No disponible
</option>
</select>


                <div class="vista-botones">

                    <button
                        type="button"
                        class="vista-activa"
                    >
                        ▦
                    </button>

                    <button
                        type="button"
                    >
                        ☷
                    </button>

                </div>

            </div>


            <!-- ========================================= -->
            <!-- LISTADO DE CANCHAS -->
            <!-- ========================================= -->

           <!-- ========================================= -->
<!-- MODAL ELIMINAR CANCHA -->
<!-- ========================================= -->

<div class="modal-eliminar-cancha" id="modalEliminarCancha">

    <div class="modal-eliminar-contenido">

        <!-- CERRAR -->
        <button
            type="button"
            class="modal-eliminar-cerrar"
            id="cerrarEliminarCancha"
        >
            ×
        </button>

        <!-- ICONO -->
        <div class="modal-eliminar-icono">
            🗑
        </div>

        <!-- TITULO -->
        <h2>
            ¿Eliminar <span>cancha?</span>
        </h2>

        <p class="modal-eliminar-texto">
            ¿Estás seguro de que querés eliminar la cancha
            <strong id="nombreCanchaEliminar"></strong>?
        </p>

        <p class="modal-eliminar-aviso">
            Esta acción no se puede deshacer.
        </p>

        <!-- ADVERTENCIA -->
        <div class="modal-eliminar-advertencia">

            <div class="modal-eliminar-advertencia-icono">
                !
            </div>

            <p>
                Se eliminará la cancha y toda la información
                asociada a ella.
            </p>

        </div>

        <!-- FORMULARIO -->
        <form
            action="funciones/eliminar_cancha.php"
            method="POST"
            id="formEliminarCancha"
        >

            <input
                type="hidden"
                name="id_cancha"
                id="idCanchaEliminar"
            >

            <div class="modal-eliminar-acciones">

                <button
                    type="button"
                    class="btn-modal-cancelar"
                    id="cancelarEliminarCancha"
                >
                    <span>×</span>
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-modal-eliminar"
                >
                    <span>🗑</span>
                    Sí, eliminar
                </button>

            </div>

        </form>

    </div>

</div>
            <div class="mis-canchas">

                <?php

                $sqlCanchas = "
                    SELECT
                        cancha.id_cancha,
                        cancha.nombre,
                        cancha.estado,
                        cancha.imagen
                    FROM cancha
                    INNER JOIN club
                        ON cancha.id_club = club.id_club
                    WHERE club.id_usuario = ?
                    ORDER BY cancha.id_cancha ASC
                ";

                $stmtCanchas =
                    mysqli_prepare(
                        $conexion,
                        $sqlCanchas
                    );

                mysqli_stmt_bind_param(
                    $stmtCanchas,
                    "i",
                    $id_usuario
                );

                mysqli_stmt_execute(
                    $stmtCanchas
                );

                $resultadoCanchas =
                    mysqli_stmt_get_result(
                        $stmtCanchas
                    );

                ?>


                <?php if (mysqli_num_rows($resultadoCanchas) > 0) { ?>

                    <?php while ($cancha = mysqli_fetch_assoc($resultadoCanchas)) { ?>

                        <article
                            class="cancha-card"
                            data-nombre="<?php echo htmlspecialchars($cancha['nombre']); ?>"
                            data-estado="<?php echo htmlspecialchars($cancha['estado']); ?>"
                        >

                            <!-- IMAGEN -->
                            <div class="cancha-card-imagen">

                                <?php if (!empty($cancha['imagen'])) { ?>

                                    <img
                                        src="<?php echo htmlspecialchars($cancha['imagen']); ?>"
                                        alt="<?php echo htmlspecialchars($cancha['nombre']); ?>"
                                    >

                                <?php } else { ?>

                                    <div class="cancha-sin-imagen">
                                        🏟
                                    </div>

                                <?php } ?>


                                <!-- ESTADO SOBRE LA IMAGEN -->

                                <span
                                   class="estado-cancha <?php echo str_replace(' ', '-', strtolower($cancha['estado'])); ?>"
                                >

                                    <span class="cancha-estado"></span>

                                    <?php echo htmlspecialchars($cancha['estado']); ?>

                                </span>

                            </div>


                            <!-- INFORMACIÓN -->

                            <div class="cancha-card-contenido">

                                <h3>
                                    <?php echo htmlspecialchars($cancha['nombre']); ?>
                                </h3>

                                <p class="cancha-ubicacion">
                                    📍 Cancha principal
                                </p>


                                <!-- BOTONES -->

                                <div class="cancha-acciones">


                                        <button
                                                type="button"
                                                class="btn-cancha estado"
                                                data-id="<?php echo $cancha['id_cancha']; ?>"
                                                data-nombre="<?php echo htmlspecialchars($cancha['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-estado="<?php echo htmlspecialchars($cancha['estado'], ENT_QUOTES, 'UTF-8'); ?>"
                                            >
                                                ⟳
                                                <span>Cambiar estado</span>
                                                </button>


                                        <button
                                            type="button"
                                            class="btn-cancha editar"
                                            data-id="<?php echo $cancha['id_cancha']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($cancha['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-imagen="<?php echo htmlspecialchars($cancha['imagen'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            ✎
                                            <span>Editar</span>
                                        </button>
                                         <button
                                            type="button"
                                            class="btn-cancha eliminar"
                                            data-id="<?php echo $cancha['id_cancha']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($cancha['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            🗑
                                            <span>Eliminar</span>
                                        </button>

                                </div>

                            </div>

                        </article>

                    <?php } ?>

                <?php } else { ?>

                    <div class="canchas-vacio">

                        <div>
                            🏟
                        </div>

                        <h3>
                            No hay canchas registradas
                        </h3>

                        <p>
                            Cuando agregues una cancha aparecerá aquí.
                        </p>

                    </div>

                <?php } ?>

            </div>

        </div>

    </div>


    <?php mysqli_stmt_close($stmtEstadisticas); ?>
    <?php mysqli_stmt_close($stmtCanchas); ?>

<?php } ?>

            <?php if ($seccion == 'ranking') { ?>

                <header class="topbar">

                    <div>

                        <h1>Ranking</h1>

                        <p>
                            Ranking de jugadores.
                        </p>

                    </div>

                </header>

            <?php } ?>


            <?php if ($seccion == 'notificaciones') { ?>

                <header class="topbar">

                    <div>

                        <h1>Notificaciones</h1>

                        <p>
                            Notificaciones del sistema.
                        </p>

                    </div>

                </header>

            <?php } ?>

        </main>

    </div>

<?php } ?>

<?php if ($_SESSION['id_rol'] == 3) { ?>

    <!-- ========================================= -->
    <!-- PARTICIPANTE -->
    <!-- ========================================= -->

    <div class="panel">

        <!-- SIDEBAR -->

        <aside class="sidebar">

            <div class="logo-panel">

                <img
                    src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                    class="logo-panel-img"
                    alt="Logo The Bull"
                >

                <h2>The Bull</h2>

                <span>Participante</span>

            </div>


            <ul class="menu">

                <!-- TORNEOS -->

                <li class="activo">

                    <a href="paneles.php">

                        🏆 Torneos

                    </a>

                </li>


                <!-- MIS INSCRIPCIONES -->

                <li>

                    <a href="paneles.php?seccion=mis_inscripciones">

                        📋 Mis inscripciones

                    </a>

                </li>


                <!-- RANKING -->

                <li>

                    <a href="paneles.php?seccion=ranking">

                        📊 Ranking

                    </a>

                </li>


                <!-- NOTIFICACIONES -->

                <li>

                    <a href="paneles.php?seccion=notificaciones">

                        📢 Notificaciones

                    </a>

                </li>


                <!-- CERRAR SESIÓN -->

                <li>

                    <a href="funciones/logout.php">

                        🚪 Cerrar sesión

                    </a>

                </li>

            </ul>

        </aside>


        <!-- ========================================= -->
        <!-- CONTENIDO -->
        <!-- ========================================= -->

        <main class="contenido contenido-participante">

            <!-- BARRA SUPERIOR -->

            <div class="barra-superior-participante">
<div class="categoria-participante-barra">

    <span class="categoria-barra-label">
        Mi categoría
    </span>

   <span class="categoria-barra-valor">

    <?php echo htmlspecialchars(
        $categoriaParticipante,
        ENT_QUOTES,
        'UTF-8'
    ); ?>

</span>

</div>
                <button
                    type="button"
                    class="btn-menu-movil"
                    id="btnMenuMovilParticipante"
                    aria-label="Abrir menú"
                >
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>

                <div class="usuario-chip-participante">

                    <div class="usuario-chip-avatar">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M4 20.5c1.5-3.5 4.8-5.5 8-5.5s6.5 2 8 5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>

                    <div class="usuario-chip-datos">
                        <strong><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>Participante</span>
                    </div>

                    <svg class="usuario-chip-flecha" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>

                </div>

            </div>


            <!-- ENCABEZADO + FILTROS -->

            <div class="cabecera-torneos-participante">

                <div>

                    <h1>Torneos disponibles</h1>

                    <p>
                        Explorá todos los torneos y sumate al que más te guste.
                    </p>

                </div>


                <!-- ========================================= -->
                <!-- FILTROS -->
                <!-- ========================================= -->

                <div class="torneos-participante-filtros">

                    <div class="torneo-busqueda">

                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                            <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>

                        <input
                            type="text"
                            id="buscarTorneoParticipante"
                            placeholder="Buscar torneos..."
                        >

                    </div>


                    <select id="filtroTorneoParticipante">

                        <option value="todos">
                            Todos los estados
                        </option>

                        <option value="Abierto">
                            Abiertos
                        </option>

                        <option value="Cerrado">
                            Cerrados
                        </option>

                        <option value="En curso">
                            En curso
                        </option>

                        <option value="Finalizado">
                            Finalizados
                        </option>

                    </select>

                </div>

            </div>


            <!-- ========================================= -->
            <!-- TORNEOS -->
            <!-- ========================================= -->

            <section class="torneos-participante">

                <?php

                $sqlTorneosParticipante = "
                    SELECT
                        torneos.id_torneo,
                        torneos.nombre,
                        torneos.imagen,
                        torneos.fecha_inicio,
                        torneos.hora_inicio,
                        torneos.hora_fin,
                        torneos.lugar,
                        torneos.tipo,
                        torneos.max_participantes,
                        torneos.estado,
                        categoria.nombre AS categoria
                    FROM torneos

                    INNER JOIN categoria
                        ON torneos.id_categoria = categoria.id_categoria

                    ORDER BY torneos.fecha_inicio ASC
                ";


                $resultadoTorneosParticipante = mysqli_query(
                    $conexion,
                    $sqlTorneosParticipante
                );


                if (
                    $resultadoTorneosParticipante &&
                    mysqli_num_rows($resultadoTorneosParticipante) > 0
                ) {

                    while (
                        $torneo = mysqli_fetch_assoc(
                            $resultadoTorneosParticipante
                        )
                    ) {

                        $imagenTorneo = !empty($torneo['imagen'])
                            ? $torneo['imagen']
                            : 'img/fondo-solicitudes.png';


                        $fechaTorneo = date(
                            'd/m/Y',
                            strtotime($torneo['fecha_inicio'])
                        );


                        $horaInicio = !empty($torneo['hora_inicio'])
                            ? substr($torneo['hora_inicio'], 0, 5)
                            : '--:--';


                        $horaFin = !empty($torneo['hora_fin'])
                            ? substr($torneo['hora_fin'], 0, 5)
                            : '--:--';


                        $estadoClase = strtolower(
                            str_replace(
                                ' ',
                                '-',
                                $torneo['estado']
                            )
                        );

                ?>

                    <article
                        class="torneo-card-participante"
                        data-nombre="<?php echo htmlspecialchars(
                            strtolower($torneo['nombre'])
                        ); ?>"
                        data-estado="<?php echo htmlspecialchars(
                            $torneo['estado']
                        ); ?>"
                    >

                        <!-- IMAGEN -->

                        <div class="torneo-card-imagen">

                            <img
                                src="<?php echo htmlspecialchars(
                                    $imagenTorneo
                                ); ?>"
                                alt="Imagen del torneo"
                            >

                        </div>


                        <!-- INFORMACIÓN -->

                        <div class="torneo-card-info">

                            <span
                                class="torneo-estado-participante <?php echo $estadoClase; ?>"
                            >

                                <?php echo htmlspecialchars(
                                    strtoupper($torneo['estado'])
                                ); ?>

                            </span>


                            <h2>

                                <?php echo htmlspecialchars(
                                    $torneo['nombre']
                                ); ?>

                            </h2>


                            <span class="torneo-categoria-participante">

                                <?php echo htmlspecialchars(
                                    $torneo['categoria']
                                ); ?>

                            </span>


                            <div class="torneo-dato">

                                <span class="icono-dato">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="2"/>
                                        <path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </span>

                                <span>
                                    <?php echo $fechaTorneo; ?>
                                </span>

                            </div>


                            <div class="torneo-dato">

                                <span class="icono-dato">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
                                        <path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>

                                <span>
                                    <?php echo $horaInicio; ?>
                                    a
                                    <?php echo $horaFin; ?>
                                </span>

                            </div>


                            <div class="torneo-dato">

                                <span class="icono-dato">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="2"/>
                                        <path d="M3 20c0-3 2.7-5 6-5s6 2 6 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M15.5 4.3a3 3 0 0 1 0 5.8M20 20c0-2.5-2-4.5-4.5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </span>

                                <span>
                                    <?php echo htmlspecialchars(
                                        $torneo['tipo']
                                    ); ?>
                                </span>

                            </div>


                            <div class="torneo-dato">

                                <span class="icono-dato">
                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        <circle cx="7" cy="7" r="3.2" stroke="currentColor" stroke-width="2"/>
                                        <path d="M15.5 3.5a3.2 3.2 0 0 1 0 6.4M22 20v-2c0-1.8-1.3-3.3-3-3.8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </span>

                                <span>
                                    0 /
                                    <?php echo intval(
                                        $torneo['max_participantes']
                                    ); ?>
                                    participantes
                                </span>

                            </div>


                            <?php if (!empty($torneo['lugar'])) { ?>

                                <div class="torneo-dato torneo-lugar">

                                    <span class="icono-dato">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 21s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <circle cx="12" cy="9" r="2.3" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </span>

                                    <span>
                                        <?php echo htmlspecialchars(
                                            $torneo['lugar']
                                        ); ?>
                                    </span>

                                </div>

                            <?php } ?>


                            <!-- BOTONES -->

                            <div class="torneo-acciones-participante">

                                <a
    href="ver_torneo.php?id=<?php echo intval($torneo['id_torneo']); ?>"
    class="btn-torneo-participante btn-ver-participante"
>
    Ver torneo
</a>

                                <?php if ($torneo['estado'] === 'Abierto') { ?>

                                    <button
                                        type="button"
                                        class="btn-torneo-participante btn-inscribirse-participante"
                                        data-id="<?php echo intval(
                                            $torneo['id_torneo']
                                        ); ?>"
                                        data-nombre="<?php echo htmlspecialchars(
                                            $torneo['nombre'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <circle cx="9" cy="7" r="3.2" stroke="currentColor" stroke-width="2"/>
                                            <path d="M19 8v6M22 11h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                        Inscribirse
                                    </button>

                                <?php } else { ?>

                                    <button
                                        type="button"
                                        class="btn-torneo-participante btn-inscribirse-deshabilitado"
                                        disabled
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <circle cx="9" cy="7" r="3.2" stroke="currentColor" stroke-width="2"/>
                                            <path d="M19 8v6M22 11h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                        Inscribirse
                                    </button>

                                <?php } ?>

                            </div>

                        </div>

                    </article>

                <?php

                    }

                } else {

                ?>

                    <div class="torneos-participante-vacio">

                        <h3>
                            No hay torneos disponibles
                        </h3>

                        <p>
                            Cuando haya nuevos torneos aparecerán aquí.
                        </p>

                    </div>

                <?php } ?>

            </section>

        </main>

    </div>

<?php } ?>


<script src="js/padel.js?v=2"></script>

<!-- =========================================================
     MODAL MENSAJE DE ÉXITO
     ========================================================= -->

<div class="modal-mensaje-exito" id="modalMensajeExito">

    <div class="modal-mensaje-exito-contenido">

        <!-- BOTÓN CERRAR -->
        <button
            type="button"
            class="modal-mensaje-exito-cerrar"
            id="cerrarMensajeExito"
        >
            ×
        </button>


        <!-- ÍCONO -->
        <div class="modal-mensaje-exito-icono">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M5 12.5L9.5 17L19 7"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>

        </div>


        <!-- TÍTULO -->
        <h2 id="tituloMensajeExito">
            ¡Cambios guardados!
        </h2>


        <!-- MENSAJE -->
        <p id="textoMensajeExito">
            Los cambios del torneo se guardaron correctamente.
        </p>


        <!-- BOTÓN -->
        <button
            type="button"
            class="modal-mensaje-exito-aceptar"
            id="aceptarMensajeExito"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M5 12.5L9.5 17L19 7"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>

            Aceptar

        </button>

    </div>

</div>
</body>

</html>