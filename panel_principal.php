<?php

include("config/no_cache.php");
include("config/conexion.php");


/* =========================================================
   SI YA HAY UNA SESIÓN INICIADA
   VA DIRECTO A SU PANEL (NO VE LA LANDING)
   ========================================================= */

if (isset($_SESSION['id_usuario'])) {

    header("Location: paneles.php");
    exit();

}


/* =========================================================
   TOP 3 DE UN CLUB
   Cada club tiene su propio ranking: el top 3 sale de los puntos REALES ganados en torneos de ese club (nunca de otros
   clubes ni de la categoría de registro). El club se elige con ?club=ID entre los clubes registrados (público).
   Con un solo club se elige solo; con varios hay que elegir uno: no se elige ninguno por el visitante.
   ========================================================= */

require_once("funciones/ranking_vista.php");

$clubesLanding = rankingClubesRegistrados($conexion);
$clubLanding = rkElegirClub(array_column($clubesLanding, 'id_club'), rkClubDeGet());
$nombreClubLanding = '';
$ranking = [];

if ($clubLanding !== null) {

    $datosClub = rankingDeClubPublico($conexion, $clubLanding, 'historico');

    if ($datosClub !== null) {

        $nombreClubLanding = $datosClub['club']['nombre'];

        // Los tres primeros con puntos (los empatados comparten posición; las filas ya vienen ordenadas).
        $ranking = array_slice(array_values(array_filter($datosClub['filas'], fn($f) => $f['puntos'] > 0)), 0, 3);

    }

}
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <title>The Bull Padel Club</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/panel_principal.css?v=2">

</head>

<body class="landing-body">


    <!-- ========================================= -->
    <!-- NAVBAR -->
    <!-- ========================================= -->

    <nav class="landing-navbar">

        <div class="landing-marca">

            <img
                src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                alt="Logo The Bull"
            >

            <span>THE BULL</span>

        </div>

        <ul class="landing-menu">

            <li>
                <a href="panel_principal.php" class="activo">
                    <i class="bi bi-house-door-fill"></i>
                    Inicio
                </a>
            </li>

            <li>
                <a href="torneos_publicos.php">
                    <i class="bi bi-trophy-fill"></i>
                    Torneos
                </a>
            </li>

            <li>
                <a href="ranking_publico.php">
                    <i class="bi bi-bar-chart-fill"></i>
                    Ranking
                </a>
            </li>

        </ul>

        <a href="login.php" class="btn-ingresar">
            <i class="bi bi-box-arrow-in-right"></i>
            Iniciar sesión
        </a>

    </nav>


    <!-- ========================================= -->
    <!-- HERO -->
    <!-- ========================================= -->

    <header class="landing-hero" id="torneos">

        <div class="landing-hero-slides">

            <div
                class="landing-hero-slide activo"
                style="background-image: url('img/fondo-solicitudes.png');"
            ></div>

            <div
                class="landing-hero-slide"
                style="background-image: url('https://images.unsplash.com/photo-1767128890954-20ce481be8f0?fm=jpg&amp;q=80&amp;w=1600&amp;auto=format&amp;fit=crop');"
            ></div>

            <div
                class="landing-hero-slide"
                style="background-image: url('https://images.unsplash.com/photo-1658723826297-fe4d1b1e6600?fm=jpg&amp;q=80&amp;w=1600&amp;auto=format&amp;fit=crop');"
            ></div>

            <div
                class="landing-hero-slide"
                style="background-image: url('https://images.unsplash.com/photo-1709587825415-814c2d7cfce7?fm=jpg&amp;q=80&amp;w=1600&amp;auto=format&amp;fit=crop');"
            ></div>

        </div>

        <div class="landing-bienvenida">

            <span class="etiqueta">PANEL PRINCIPAL</span>

            <h1>Bienvenido a <b>The Bull</b></h1>

            <p>Disfrutá de los torneos y viví el pádel como nunca antes.</p>

        </div>

        <div class="landing-hero-contenido">

            <div class="landing-hero-texto">

                <span class="etiqueta">THE BULL PADEL CLUB</span>

                <h2>Viví el pádel<br>como nunca antes.</h2>

                <p>
                    Encontrá nuevos desafíos, participá en torneos y
                    competí con jugadores de toda la comunidad.
                </p>

                <div class="landing-hero-botones">

                    <a href="#ranking" class="btn-landing-primario">
                        Conocer más
                        <i class="bi bi-arrow-right"></i>
                    </a>

                    <a href="torneos_publicos.php" class="btn-landing-secundario">
                        Explorar torneos
                    </a>

                </div>

            </div>

            <aside class="landing-ranking" id="ranking">

                <div class="landing-ranking-header">

                    <div>
                        <span class="etiqueta">CLASIFICACIÓN</span>
                        <h3>Ranking</h3>
                    </div>

                    <a href="<?php echo $clubLanding !== null ? 'ranking_publico.php?club=' . (int) $clubLanding : 'ranking_publico.php'; ?>">Ver todos</a>

                </div>

                <?php if (!empty($clubesLanding)) { ?>
                    <nav class="landing-clubes" aria-label="Elegir club">
                        <?php foreach ($clubesLanding as $clubL) { $activoL = ($clubLanding === (int) $clubL['id_club']); ?>
                            <a href="panel_principal.php?club=<?php echo (int) $clubL['id_club']; ?>#ranking"
                               class="landing-club <?php echo $activoL ? 'activo' : ''; ?>"
                               <?php echo $activoL ? 'aria-current="page"' : ''; ?>>
                                <?php echo htmlspecialchars($clubL['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php } ?>
                    </nav>
                <?php } ?>

                <?php if (count($ranking) > 0) { ?>

                    <?php foreach ($ranking as $indice => $jugador) { ?>

                        <div class="landing-ranking-item">

                            <span class="landing-ranking-puesto">
                                <?php echo (int) $jugador['posicion']; ?>
                            </span>

                            <div class="landing-ranking-avatar">
                                <?php
                                    echo strtoupper(
                                        mb_substr($jugador['nombre'], 0, 1) .
                                        mb_substr($jugador['apellido'], 0, 1)
                                    );
                                ?>
                            </div>

                            <div class="landing-ranking-datos">

                                <strong>
                                    <?php
                                        echo htmlspecialchars(
                                            $jugador['nombre'] . ' ' . $jugador['apellido'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>
                                </strong>

                                <span>
                                    Categoría
                                    <?php
                                        echo htmlspecialchars(
                                            $jugador['categoria'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>
                                    · <?php echo number_format((int) $jugador['puntos'], 0, ',', '.'); ?> pts
                                </span>

                            </div>

                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <p class="landing-ranking-vacio">
                        <?php if (empty($clubesLanding)) { ?>
                            Todavía no hay clubes registrados.
                        <?php } elseif ($clubLanding === null) { ?>
                            Elegí un club para ver su top 3.
                        <?php } else { ?>
                            Todavía no hay resultados en <?php echo htmlspecialchars($nombreClubLanding, ENT_QUOTES, 'UTF-8'); ?>.
                        <?php } ?>
                    </p>

                <?php } ?>

            </aside>

        </div>

        <div class="landing-hero-dots">

            <button type="button" class="activo" data-slide="0"></button>
            <button type="button" data-slide="1"></button>
            <button type="button" data-slide="2"></button>
            <button type="button" data-slide="3"></button>

        </div>

    </header>
    <script src="js/padel.js?v=17"></script>
</body>

</html>
