<?php

include("config/no_cache.php");
include("config/conexion.php");
require_once("funciones/publico_datos.php");
require_once("funciones/publico_componentes.php");


/* =========================================================
   LISTADO PÚBLICO DE TORNEOS (todos los clubes, solo lectura)

   Cualquier visitante puede explorar. Quien ya inició sesión sigue usando su panel.
   Esta página solo hace SELECT: no hay formularios que escriban datos.
   ========================================================= */

if (isset($_SESSION['id_usuario'])) {

    header("Location: paneles.php?seccion=torneos");
    exit();

}

$filtros = publicoFiltrosDesdeGet($_GET);
$clubes = publicoClubesConTorneos($conexion);
$categorias = publicoCategorias($conexion);
$resultado = publicoListarTorneos($conexion, $filtros);

$hayFiltros = ($filtros['q'] !== '' || $filtros['club'] > 0 || $filtros['categoria'] > 0 || $filtros['estado'] !== '');

$e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

/** URL de la misma página con otros filtros / página (siempre construida con http_build_query). */
$urlListado = function (array $cambios = []) use ($filtros) {

    $datos = array_merge([
        'q'         => $filtros['q'],
        'club'      => $filtros['club'] ?: '',
        'categoria' => $filtros['categoria'] ?: '',
        'estado'    => $filtros['estado'],
        'orden'     => $filtros['orden'] === 'antiguos' ? 'antiguos' : '',
        'pagina'    => $filtros['pagina'] > 1 ? $filtros['pagina'] : '',
    ], $cambios);

    $datos = array_filter($datos, fn($v) => $v !== '' && $v !== 0 && $v !== null);

    return 'torneos_publicos.php' . ($datos ? '?' . http_build_query($datos) : '');

};

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Torneos de todos los clubes - The Bull Padel Club</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/publico.css?v=3">

</head>

<body class="pub-body">

    <?php echo renderNavbarPublica('torneos'); ?>

    <main class="pub-contenedor">

        <header class="pub-encabezado">

            <span class="pub-etiqueta">CONSULTA PÚBLICA</span>

            <h1>Torneos de todos los clubes</h1>

            <p>Explorá los torneos, sus grupos y resultados sin necesidad de tener una cuenta.</p>

        </header>


        <form method="get" action="torneos_publicos.php" class="pub-filtros" id="formFiltrosPublicos" role="search">

            <label class="pub-campo pub-campo-buscar">
                <span>Buscar por nombre</span>
                <div class="pub-input-icono">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="<?php echo $e($filtros['q']); ?>" maxlength="60" placeholder="Nombre del torneo">
                </div>
            </label>

            <label class="pub-campo">
                <span>Club</span>
                <select name="club">
                    <option value="0">Todos los clubes</option>
                    <?php foreach ($clubes as $club) { ?>
                        <option value="<?php echo (int) $club['id_club']; ?>" <?php echo $filtros['club'] === (int) $club['id_club'] ? 'selected' : ''; ?>>
                            <?php echo $e($club['nombre']); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>

            <label class="pub-campo">
                <span>Categoría</span>
                <select name="categoria">
                    <option value="0">Todas las categorías</option>
                    <?php foreach ($categorias as $cat) { ?>
                        <option value="<?php echo (int) $cat['id_categoria']; ?>" <?php echo $filtros['categoria'] === (int) $cat['id_categoria'] ? 'selected' : ''; ?>>
                            <?php echo $e($cat['nombre']); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>

            <label class="pub-campo">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <?php foreach (PUBLICO_ESTADOS as $est) { ?>
                        <option value="<?php echo $e($est); ?>" <?php echo $filtros['estado'] === $est ? 'selected' : ''; ?>><?php echo $e($est); ?></option>
                    <?php } ?>
                </select>
            </label>

            <label class="pub-campo">
                <span>Ordenar por fecha de inicio</span>
                <select name="orden">
                    <option value="recientes" <?php echo $filtros['orden'] === 'recientes' ? 'selected' : ''; ?>>Más recientes primero</option>
                    <option value="antiguos" <?php echo $filtros['orden'] === 'antiguos' ? 'selected' : ''; ?>>Más antiguos primero</option>
                </select>
            </label>

            <div class="pub-filtros-botones">
                <button type="submit" class="pub-btn pub-btn-primario"><i class="bi bi-funnel-fill"></i> Filtrar</button>
                <?php if ($hayFiltros || $filtros['orden'] === 'antiguos') { ?>
                    <a href="torneos_publicos.php" class="pub-btn pub-btn-borde">Limpiar</a>
                <?php } ?>
            </div>

        </form>


        <p class="pub-contador" aria-live="polite">
            <?php echo (int) $resultado['total']; ?>
            <?php echo $resultado['total'] === 1 ? 'torneo encontrado' : 'torneos encontrados'; ?>
        </p>


        <?php if (empty($resultado['torneos'])) { ?>

            <div class="pub-vacio">
                <i class="bi bi-trophy"></i>
                <h2><?php echo $hayFiltros ? 'No hay torneos con esos filtros' : 'Todavía no hay torneos publicados'; ?></h2>
                <p><?php echo $hayFiltros ? 'Probá con otra búsqueda o limpiá los filtros.' : 'Cuando los clubes creen torneos, vas a poder verlos acá.'; ?></p>
                <?php if ($hayFiltros) { ?>
                    <a href="torneos_publicos.php" class="pub-btn pub-btn-primario">Ver todos los torneos</a>
                <?php } ?>
            </div>

        <?php } else { ?>

            <div class="pub-grilla">

                <?php foreach ($resultado['torneos'] as $t) {

                    $imagen = trim((string) ($t['imagen'] ?? ''));
                    $fi = date('d/m/Y', strtotime($t['fecha_inicio']));
                    $ff = date('d/m/Y', strtotime($t['fecha_fin']));
                    $hora = date('H:i', strtotime($t['hora_inicio']));
                    $unidad = ($t['tipo'] === 'Individual') ? 'participantes' : 'jugadores';

                ?>

                <article class="pub-card" data-torneo="<?php echo (int) $t['id_torneo']; ?>">

                    <div class="pub-card-imagen">

                        <?php if ($imagen !== '') { ?>
                            <img src="<?php echo $e($imagen); ?>" alt="<?php echo $e($t['nombre']); ?>" loading="lazy">
                        <?php } else { ?>
                            <div class="pub-card-sin-imagen"><i class="bi bi-trophy-fill"></i><span>Sin imagen</span></div>
                        <?php } ?>

                        <span class="pub-estado <?php echo publicoClaseEstado($t['estado']); ?>"><?php echo $e($t['estado']); ?></span>

                    </div>

                    <div class="pub-card-cuerpo">

                        <p class="pub-card-club"><i class="bi bi-buildings-fill"></i> <?php echo $e($t['club']); ?></p>

                        <h2><?php echo $e($t['nombre']); ?></h2>

                        <ul class="pub-datos">
                            <li><i class="bi bi-layers-fill"></i><span>Categoría <?php echo $e($t['categoria']); ?></span></li>
                            <li><i class="bi bi-calendar-event-fill"></i><span><?php echo $fi; ?> – <?php echo $ff; ?></span></li>
                            <li><i class="bi bi-alarm-fill"></i><span>Hora de inicio: <?php echo $hora; ?> hs</span></li>
                            <li><i class="bi bi-geo-alt-fill"></i><span><?php echo $e($t['lugar']); ?></span></li>
                            <li><i class="bi bi-people-fill"></i><span>Modalidad: <?php echo $e(publicoModalidad($t['tipo'])); ?></span></li>
                            <li><i class="bi bi-person-check-fill"></i><span><?php echo (int) $t['confirmados']; ?> / <?php echo (int) $t['max_participantes']; ?> <?php echo $unidad; ?> confirmados</span></li>
                        </ul>

                        <div class="pub-card-acciones">
                            <a href="ver_torneo.php?id=<?php echo (int) $t['id_torneo']; ?>" class="pub-btn pub-btn-primario"><i class="bi bi-eye-fill"></i> Ver</a>
                            <?php if ($t['estado'] === 'Abierto') { echo publicoBotonInscribirme($t['id_torneo'], $t['nombre'], 'pub-btn pub-btn-borde'); } ?>
                        </div>

                    </div>

                </article>

                <?php } ?>

            </div>


            <?php if ($resultado['paginas'] > 1) { ?>

                <nav class="pub-paginacion" aria-label="Páginas de torneos">

                    <?php if ($resultado['pagina'] > 1) { ?>
                        <a href="<?php echo $e($urlListado(['pagina' => $resultado['pagina'] - 1])); ?>" class="pub-btn pub-btn-borde">‹ Anterior</a>
                    <?php } ?>

                    <span>Página <?php echo (int) $resultado['pagina']; ?> de <?php echo (int) $resultado['paginas']; ?></span>

                    <?php if ($resultado['pagina'] < $resultado['paginas']) { ?>
                        <a href="<?php echo $e($urlListado(['pagina' => $resultado['pagina'] + 1])); ?>" class="pub-btn pub-btn-borde">Siguiente ›</a>
                    <?php } ?>

                </nav>

            <?php } ?>

        <?php } ?>

    </main>

    <?php echo renderModalInscripcionPublica(); ?>

    <script src="js/publico.js?v=1"></script>

</body>

</html>
