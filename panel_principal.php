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
   TOP 3 JUGADORES POR CATEGORÍA
   (todavía no hay un sistema de puntos/partidos,
   así que se muestra por categoría como referencia)
   ========================================================= */

$ranking = [];

$sqlRanking = "
    SELECT
        usuario.nombre,
        usuario.apellido,
        categoria.nombre AS categoria
    FROM usuario
    INNER JOIN categoria
        ON usuario.id_categoria = categoria.id_categoria
    WHERE usuario.id_rol = 3
      AND usuario.verificado = 1
    ORDER BY
        categoria.id_categoria DESC,
        usuario.id_usuario ASC
    LIMIT 3
";

$resultadoRanking = mysqli_query($conexion, $sqlRanking);

if ($resultadoRanking) {

    while ($fila = mysqli_fetch_assoc($resultadoRanking)) {
        $ranking[] = $fila;
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
    <link rel="stylesheet" href="css/panel_principal.css">

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
                <a href="#torneos">
                    <i class="bi bi-trophy-fill"></i>
                    Torneos
                </a>
            </li>

            <li>
                <a href="#ranking">
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

                    <a href="login.php" class="btn-landing-secundario">
                        Explorar
                    </a>

                </div>

            </div>

            <aside class="landing-ranking" id="ranking">

                <div class="landing-ranking-header">

                    <div>
                        <span class="etiqueta">CLASIFICACIÓN</span>
                        <h3>Ranking</h3>
                    </div>

                    <a href="login.php">Ver todos</a>

                </div>

                <?php if (count($ranking) > 0) { ?>

                    <?php foreach ($ranking as $indice => $jugador) { ?>

                        <div class="landing-ranking-item">

                            <span class="landing-ranking-puesto">
                                <?php echo $indice + 1; ?>
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
                                </span>

                            </div>

                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <p class="landing-ranking-vacio">
                        Todavía no hay jugadores para mostrar.
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
    <script src="js/padel.js?v=3"></script>
</body>

</html>
