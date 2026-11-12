<?php

include("config/no_cache.php");
include("config/conexion.php");
require_once("funciones/ranking_vista.php");
require_once("funciones/publico_componentes.php");


/* =========================================================
   RANKING PÚBLICO POR CLUB (solo lectura, sin cuenta)

   Cualquier visitante puede ver el ranking de cada club registrado. Quien ya inició sesión usa el
   Ranking de su panel. Esta página solo hace SELECT: no hay formularios ni acciones de escritura,
   y solo muestra nombre, categoría y resultados (nunca correos, teléfonos ni datos personales).
   ========================================================= */

if (isset($_SESSION['id_usuario'])) {

    header("Location: paneles.php?seccion=ranking");
    exit();

}

$e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

$clubes = rankingClubesRegistrados($conexion);
$filtro = rkFiltroDeGet();

// Club pedido (?club=ID). Con un solo club se elige solo; con varios hay que elegir uno: no se elige ninguno por el visitante.
// Un club que no existe se ignora (se vuelve a pedir que elija).
$clubActivo = rkElegirClub(array_column($clubes, 'id_club'), rkClubDeGet());

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ranking de los clubes - The Bull Padel Club</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/publico.css?v=3">
    <link rel="stylesheet" href="css/ranking.css?v=10">

</head>

<body class="pub-body">

    <?php echo renderNavbarPublica('ranking'); ?>

    <main class="pub-contenedor">

        <header class="pub-encabezado">

            <span class="pub-etiqueta">CONSULTA PÚBLICA</span>

            <h1>Ranking de los clubes</h1>

            <p>Mirá la clasificación de cada club según los puntos ganados en sus torneos, sin necesidad de tener una cuenta.</p>

        </header>

        <div class="rk">

            <?php if (empty($clubes)) { ?>

                <div class="rk-vacio"><i class="bi bi-buildings"></i> Todavía no hay clubes registrados.</div>

            <?php } else { ?>

                <?php echo rkSelectorClub($clubes, $clubActivo, 'ranking_publico.php', '?', 'filtro=' . $filtro, $e); ?>

                <?php if ($clubActivo === null) { echo rkAvisoElegirClub(); } else { echo renderCuerpoRankingClub($conexion, 'ranking_publico.php', '?', $clubActivo, $filtro, null, $e, rkTemporadaDeGet()); } ?>

            <?php } ?>

        </div>

    </main>

    <script src="js/ranking_general.js?v=3"></script>

</body>

</html>
