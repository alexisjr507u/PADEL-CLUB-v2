<?php

require_once __DIR__ . '/ranking_puntos.php';
require_once __DIR__ . '/ranking_general.php';
require_once __DIR__ . '/ranking_club.php';

/* =========================================================
   VISTAS DEL RANKING (solo devuelven HTML; no escriben nada)
   - renderRankingCategorias(): selector de categoría + tabla de posiciones.
   - renderPuntosTorneo(): puntos de un torneo, en vivo (se suman solos con cada resultado).
   ========================================================= */

function rkNumero($n) {
    return number_format((int) $n, 0, ',', '.');
}


/** Categoría pedida por la URL: 'general', un id de categoría, o null (= General). */
function rkCategoriaDeGet() {

    $valor = $_GET['categoria'] ?? null;

    if ($valor === 'general') {
        return 'general';
    }

    $id = filter_var($valor, FILTER_VALIDATE_INT);

    return $id === false ? null : $id;

}


/** Club pedido por la URL (?club=ID): un entero positivo o null. La existencia se confirma contra la base. */
function rkClubDeGet() {

    $id = filter_var($_GET['club'] ?? null, FILTER_VALIDATE_INT);

    return ($id !== false && $id > 0) ? (int) $id : null;

}


/** Temporada pedida por la URL (?temporada=ID): un entero positivo o null. Que pertenezca al club se confirma contra la base. */
function rkTemporadaDeGet() {

    $id = filter_var($_GET['temporada'] ?? null, FILTER_VALIDATE_INT);

    return ($id !== false && $id > 0) ? (int) $id : null;

}


/**
 * Club activo de una lista de clubes permitidos: el pedido si está en la lista; si la persona solo tiene UN club, ese;
 * si tiene varios y no eligió ninguno (o pidió uno que no puede ver), null: tiene que elegir. Nunca se elige uno por ella.
 */
function rkElegirClub(array $idsPermitidos, $pedido) {

    $ids = array_map('intval', $idsPermitidos);

    if ($pedido !== null && in_array((int) $pedido, $ids, true)) {
        return (int) $pedido;
    }

    return count($ids) === 1 ? $ids[0] : null;

}


/**
 * Club en cuyo ranking del panel se está mirando (administrador: cualquiera; organizador: los suyos; participante: los que
 * integra). El permiso se calcula en el servidor contra la base; el selector de la pantalla es solo comodidad.
 * null = hay que elegir (o no tiene ninguno). Cada club tiene su propio ranking: nunca se mezclan puntos de dos clubes.
 */
function rkClubContexto($conexion) {

    return rkElegirClub(array_column(rankingClubesVisibles($conexion), 'id_club'), rkClubDeGet());

}


/**
 * Selector de club: enlaces con el estilo de las demás pestañas (funcionan sin JavaScript). Muestra TODOS los clubes que la
 * persona puede consultar y marca el activo. $paramsExtra (ej. 'categoria=general&filtro=historico') se conserva al cambiar
 * de club; la temporada no, porque cada club tiene las suyas.
 */
function rkSelectorClub(array $clubes, $clubActivo, $urlBase, $separador, $paramsExtra, $e) {

    if (empty($clubes)) {
        return '';
    }

    ob_start();
    ?>
    <nav class="rk-categorias rk-clubes" aria-label="Elegir club">
        <span class="rk-clubes-titulo"><i class="bi bi-buildings-fill"></i> Club</span>
        <?php foreach ($clubes as $club) { $activo = ($clubActivo !== null && (int) $clubActivo === (int) $club['id_club']); ?>
            <a href="<?php echo $e($urlBase . $separador . 'club=' . (int) $club['id_club'] . ($paramsExtra !== '' ? '&' . $paramsExtra : '')); ?>"
               class="rk-chip rk-chip-club <?php echo $activo ? 'activo' : ''; ?>"
               <?php echo $activo ? 'aria-current="page"' : ''; ?>>
                <?php echo $e($club['nombre']); ?>
                <?php if (isset($club['jugadores'])) { ?><small title="Jugadores con resultados en este club"><?php echo (int) $club['jugadores']; ?></small><?php } ?>
            </a>
        <?php } ?>
    </nav>
    <?php
    return ob_get_clean();

}


/** Aviso cuando todavía no se eligió un club (no se muestra ningún dato de ranking). */
function rkAvisoElegirClub() {

    return '<div class="rk-vacio rk-elegir-club"><i class="bi bi-buildings"></i> Elegí un club para ver su ranking.</div>';

}


/**
 * Selector de temporada del club activo: la actual y las anteriores. Es un formulario GET (con un botón «Ver» que el JavaScript
 * oculta porque el cambio se envía solo). $fijos = parámetros que se conservan (club, categoría, filtro).
 */
function rkSelectorTemporada($conexion, $idClub, $temporadaActiva, $urlBase, array $fijos, $e) {

    $temporadas = temporadasDeClub($conexion, (int) $idClub);

    if (empty($temporadas)) {
        return '';
    }

    $partes = parse_url($urlBase);
    $accion = $partes['path'] ?? $urlBase;
    parse_str($partes['query'] ?? '', $deLaUrl);

    ob_start();
    ?>
    <form method="get" action="<?php echo $e($accion); ?>" class="rk-selector-temporada">
        <?php foreach ($deLaUrl + $fijos as $k => $v) { ?>
            <input type="hidden" name="<?php echo $e($k); ?>" value="<?php echo $e($v); ?>">
        <?php } ?>
        <label for="rk-temporada-<?php echo (int) $idClub; ?>"><i class="bi bi-calendar-event"></i> Temporada</label>
        <select name="temporada" id="rk-temporada-<?php echo (int) $idClub; ?>" data-rk-autoenviar>
            <?php foreach ($temporadas as $t) { $elegida = ($temporadaActiva && (int) $temporadaActiva['id_temporada'] === (int) $t['id_temporada']); ?>
                <option value="<?php echo (int) $t['id_temporada']; ?>" <?php echo $elegida ? 'selected' : ''; ?>>
                    <?php echo $e($t['nombre']); ?><?php echo $t['estado'] === 'Abierta' ? ' · actual' : ' · cerrada el ' . date('d/m/Y', strtotime($t['fecha_cierre'])); ?>
                </option>
            <?php } ?>
        </select>
        <button type="submit" class="rk-btn rk-btn-suave" data-rk-sin-js>Ver</button>
    </form>
    <?php
    return ob_get_clean();

}

/** Filtro del ranking general pedido por la URL ('historico' por defecto). */
function rkFiltroDeGet() {

    return rankingGeneralFiltro($_GET['filtro'] ?? 'historico');

}


/**
 * Cuerpo de la pestaña «General»: filtro Histórico / Temporada actual, tabla con todos los jugadores
 * (se ven los 10 primeros y el resto se despliega con «Ver más», js/ranking_general.js) y, si se
 * pasa un usuario, un resumen con su posición.
 */
function renderCuerpoRankingGeneral($conexion, $urlBase, $separador, $idClub, $filtro, $idUsuarioResaltar, $e, $idTemporada = null) {

    $datos = rankingGeneral($conexion, $idClub, $filtro, $idTemporada);
    $filas = $datos['filas'];
    $filtro = $datos['filtro'];
    $temporada = $datos['temporada'];
    $total = count($filas);
    $esTemporada = ($filtro === 'temporada');
    $nombreTemporada = $temporada ? $temporada['nombre'] : 'Temporada actual';

    $mia = null;
    $miCategoria = null;

    if ($idUsuarioResaltar !== null) {
        foreach ($filas as $f) {
            if ($f['id_usuario'] === (int) $idUsuarioResaltar) { $mia = $f; break; }
        }
        $miCategoria = rankingDeUsuario($conexion, (int) $idUsuarioResaltar, (int) $idClub);
    }

    $urlFiltro = fn($valor) => $urlBase . $separador . 'club=' . (int) $idClub . '&categoria=general&filtro=' . $valor;

    ob_start();
    ?>

    <div class="rk-general" data-rk-general data-rk-inicial="10" data-rk-paso="10">

        <?php if ($mia) { ?>
            <section class="rk-mi-posicion" aria-label="Tu posición">
                <div>
                    <span>General · histórico</span>
                    <strong>#<?php echo (int) $mia['pos_historico']; ?></strong>
                    <small><?php echo rkNumero($mia['puntos_totales']); ?> puntos totales</small>
                </div>
                <div>
                    <span>General · <?php echo $e($nombreTemporada); ?></span>
                    <strong>#<?php echo (int) $mia['pos_temporada']; ?></strong>
                    <small><?php echo rkNumero($mia['puntos_temporada']); ?> puntos de temporada</small>
                </div>
                <div>
                    <span>Mi categoría · <?php echo $e($mia['categoria']); ?></span>
                    <?php if ($miCategoria) { ?>
                        <strong>#<?php echo (int) $miCategoria['posicion']; ?></strong>
                        <small>de <?php echo (int) $miCategoria['total_categoria']; ?> jugadores</small>
                    <?php } else { ?>
                        <strong>—</strong>
                        <small>Sin categoría confirmada</small>
                    <?php } ?>
                </div>
                <button type="button" class="rk-btn rk-btn-suave" data-rk-ir-propia><i class="bi bi-crosshair"></i> Ver mi fila</button>
            </section>
        <?php } ?>

        <div class="rk-barra-filtros">

            <div class="rk-filtros" role="group" aria-label="Filtro del ranking general">
                <a href="<?php echo $e($urlFiltro('historico')); ?>" class="rk-filtro <?php echo !$esTemporada ? 'activo' : ''; ?>" <?php echo !$esTemporada ? 'aria-current="true"' : ''; ?>>
                    <i class="bi bi-infinity"></i> Histórico
                </a>
                <a href="<?php echo $e($urlFiltro('temporada')); ?>" class="rk-filtro <?php echo $esTemporada ? 'activo' : ''; ?>" <?php echo $esTemporada ? 'aria-current="true"' : ''; ?>>
                    <i class="bi bi-calendar-event"></i> Temporada
                </a>
            </div>

            <?php if ($esTemporada) { echo rkSelectorTemporada($conexion, $idClub, $temporada, $urlBase, ['club' => (int) $idClub, 'categoria' => 'general', 'filtro' => 'temporada'], $e); } ?>

            <div class="rk-resumen">
                <strong>General</strong>
                <span><?php echo $total; ?> <?php echo $total === 1 ? 'jugador' : 'jugadores'; ?></span>
                <span><?php echo $esTemporada ? 'Ordenado por los puntos de la ' . $e($nombreTemporada) . ($temporada && $temporada['estado'] === 'Cerrada' ? ' (cerrada)' : '') : 'Ordenado por los puntos totales de toda la trayectoria en este club'; ?></span>
            </div>

        </div>

        <?php if (empty($filas)) { ?>

            <div class="rk-vacio"><i class="bi bi-people"></i> Todavía no hay jugadores registrados.</div>

        <?php } else { ?>

            <div class="rk-tabla-scroll">
                <table class="rk-tabla rk-tabla-general">
                    <thead>
                        <tr>
                            <th class="rk-col-pos">#</th>
                            <th>Jugador</th>
                            <th class="rk-col-cat">Categoría actual</th>
                            <th class="rk-num rk-col-temporada <?php echo $esTemporada ? 'rk-activa' : ''; ?>">Puntos de temporada</th>
                            <th class="rk-num rk-col-total <?php echo !$esTemporada ? 'rk-activa' : ''; ?>">Puntos totales</th>
                            <th class="rk-num rk-oculto-chico" title="Campeonatos ganados">Camp.</th>
                            <th class="rk-num rk-oculto-chico" title="Partidos ganados">PG</th>
                            <th class="rk-num rk-oculto-chico" title="Diferencia de sets">Dif. sets</th>
                            <th class="rk-num rk-oculto-chico" title="Diferencia de games">Dif. games</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $f) {
                            $propia = ($idUsuarioResaltar !== null && (int) $idUsuarioResaltar === $f['id_usuario']);
                            $clasePos = ($f['posicion'] <= 3 && $f['puntos'] > 0) ? ' rk-podio-' . $f['posicion'] : '';
                        ?>
                            <tr data-rk-fila <?php echo $propia ? 'data-rk-propia' : ''; ?> class="<?php echo $propia ? 'rk-propia' : ''; ?><?php echo $clasePos; ?>">
                                <td class="rk-col-pos">
                                    <span class="rk-pos"><?php echo $f['posicion']; ?></span>
                                    <?php if ($f['empate']) { ?><i class="bi bi-distribute-vertical rk-empate" title="Empatado: comparte posición"></i><?php } ?>
                                </td>
                                <td class="rk-jugador">
                                    <span class="rk-avatar"><?php echo $e(mb_strtoupper(mb_substr($f['nombre'], 0, 1) . mb_substr($f['apellido'], 0, 1))); ?></span>
                                    <span class="rk-nombre">
                                        <?php echo $e($f['nombre_completo']); ?><?php echo $propia ? ' <em>(vos)</em>' : ''; ?>
                                        <small class="rk-cat-movil"><?php echo $e($f['categoria']); ?></small>
                                    </span>
                                </td>
                                <td class="rk-col-cat"><span class="rk-etiqueta-cat"><?php echo $e($f['categoria']); ?></span></td>
                                <td class="rk-num rk-col-temporada <?php echo $esTemporada ? 'rk-activa' : ''; ?>"><?php echo rkNumero($f['puntos_temporada']); ?></td>
                                <td class="rk-num rk-col-total <?php echo !$esTemporada ? 'rk-activa' : ''; ?>"><?php echo rkNumero($f['puntos_totales']); ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo $f['campeonatos']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo $f['pg']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo ($f['dif_sets'] > 0 ? '+' : '') . $f['dif_sets']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo ($f['dif_games'] > 0 ? '+' : '') . $f['dif_games']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="rk-mas" data-rk-mas hidden>
                <span class="rk-mas-contador" data-rk-contador aria-live="polite"></span>
                <div class="rk-mas-botones">
                    <button type="button" class="rk-btn rk-btn-suave" data-rk-ver-menos hidden><i class="bi bi-chevron-up"></i> Ver menos</button>
                    <button type="button" class="rk-btn" data-rk-ver-mas><i class="bi bi-chevron-down"></i> Ver más</button>
                </div>
            </div>

            <p class="rk-nota">
                Todos los jugadores suman los mismos puntos por el mismo resultado, en cualquier categoría. Los puntos totales nunca se reinician;
                los de temporada empiezan de cero con cada temporada. La categoría es la actual de cada jugador.
                Desempate: puntos, campeonatos ganados, partidos ganados, diferencia de sets y diferencia de games; si sigue igual, comparten posición.
            </p>

        <?php } ?>

    </div>

    <?php
    return ob_get_clean();

}

/**
 * Cuerpo del ranking de UN club: filtro Histórico / Temporada actual, resumen del club y tabla de jugadores
 * con los puntos que ganaron en torneos de ese club. Solo lectura.
 */
function renderCuerpoRankingClub($conexion, $urlBase, $separador, $idClub, $filtro, $idUsuarioResaltar, $e, $idTemporada = null) {

    $datos = rankingDeClubPublico($conexion, $idClub, $filtro, $idTemporada);

    if ($datos === null) {
        return '<div class="rk-vacio"><i class="bi bi-buildings"></i> Ese club no existe.</div>';
    }

    $filas = $datos['filas'];
    $esTemporada = ($datos['filtro'] === 'temporada');
    $nombreTemporada = $datos['temporada'] ? $datos['temporada']['nombre'] : 'Temporada actual';
    $total = count($filas);
    $urlFiltro = fn($valor) => $urlBase . $separador . 'club=' . (int) $idClub . '&filtro=' . $valor;

    ob_start();
    ?>

    <div class="rk-general rk-club" data-rk-general data-rk-inicial="10" data-rk-paso="10">

        <div class="rk-barra-filtros">

            <div class="rk-filtros" role="group" aria-label="Filtro del ranking del club">
                <a href="<?php echo $e($urlFiltro('historico')); ?>" class="rk-filtro <?php echo !$esTemporada ? 'activo' : ''; ?>" <?php echo !$esTemporada ? 'aria-current="true"' : ''; ?>>
                    <i class="bi bi-infinity"></i> Histórico
                </a>
                <a href="<?php echo $e($urlFiltro('temporada')); ?>" class="rk-filtro <?php echo $esTemporada ? 'activo' : ''; ?>" <?php echo $esTemporada ? 'aria-current="true"' : ''; ?>>
                    <i class="bi bi-calendar-event"></i> Temporada
                </a>
            </div>

            <?php if ($esTemporada) { echo rkSelectorTemporada($conexion, $idClub, $datos['temporada'], $urlBase, ['club' => (int) $idClub, 'filtro' => 'temporada'], $e); } ?>

            <div class="rk-resumen">
                <strong><i class="bi bi-buildings-fill"></i> <?php echo $e($datos['club']['nombre']); ?></strong>
                <span><?php echo $total; ?> <?php echo $total === 1 ? 'jugador' : 'jugadores'; ?></span>
                <span><?php echo (int) $datos['torneos']; ?> <?php echo $datos['torneos'] === 1 ? 'torneo' : 'torneos'; ?> (<?php echo (int) $datos['finalizados']; ?> <?php echo $datos['finalizados'] === 1 ? 'finalizado' : 'finalizados'; ?>)</span>
                <span><?php echo $esTemporada ? 'Puntos ganados en este club durante la ' . $e($nombreTemporada) : 'Puntos ganados en este club en toda su trayectoria'; ?></span>
            </div>

        </div>

        <?php if (empty($filas)) { ?>

            <div class="rk-vacio">
                <i class="bi bi-trophy"></i>
                <?php if ($esTemporada && !$datos['temporada']) { ?>
                    No hay una temporada vigente en este momento.
                <?php } elseif ($datos['torneos'] === 0) { ?>
                    Este club todavía no organizó torneos.
                <?php } else { ?>
                    Todavía no hay resultados cargados en los torneos de este club<?php echo $esTemporada ? ' en la ' . $e($nombreTemporada) : ''; ?>.
                <?php } ?>
            </div>

        <?php } else { ?>

            <div class="rk-tabla-scroll">
                <table class="rk-tabla rk-tabla-general">
                    <thead>
                        <tr>
                            <th class="rk-col-pos">#</th>
                            <th>Jugador</th>
                            <th class="rk-col-cat">Categoría actual</th>
                            <th class="rk-num rk-activa">Puntos en el club</th>
                            <th class="rk-num" title="Torneos del club en los que sumó puntos">Torneos</th>
                            <th class="rk-num rk-oculto-chico" title="Campeonatos ganados en el club">Camp.</th>
                            <th class="rk-num rk-oculto-chico" title="Partidos ganados">PG</th>
                            <th class="rk-num rk-oculto-chico" title="Partidos perdidos">PP</th>
                            <th class="rk-num rk-oculto-chico" title="Diferencia de sets">Dif. sets</th>
                            <th class="rk-num rk-oculto-chico" title="Diferencia de games">Dif. games</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $f) {
                            $propia = ($idUsuarioResaltar !== null && (int) $idUsuarioResaltar === $f['id_usuario']);
                            $clasePos = ($f['posicion'] <= 3 && $f['puntos'] > 0) ? ' rk-podio-' . $f['posicion'] : '';
                        ?>
                            <tr data-rk-fila <?php echo $propia ? 'data-rk-propia' : ''; ?> class="<?php echo $propia ? 'rk-propia' : ''; ?><?php echo $clasePos; ?>">
                                <td class="rk-col-pos">
                                    <span class="rk-pos"><?php echo $f['posicion']; ?></span>
                                    <?php if ($f['empate']) { ?><i class="bi bi-distribute-vertical rk-empate" title="Empatado: comparte posición"></i><?php } ?>
                                </td>
                                <td class="rk-jugador">
                                    <span class="rk-avatar"><?php echo $e(mb_strtoupper(mb_substr($f['nombre'], 0, 1) . mb_substr($f['apellido'], 0, 1))); ?></span>
                                    <span class="rk-nombre">
                                        <?php echo $e($f['nombre_completo']); ?><?php echo $propia ? ' <em>(vos)</em>' : ''; ?>
                                        <small class="rk-cat-movil"><?php echo $e($f['categoria']); ?></small>
                                    </span>
                                </td>
                                <td class="rk-col-cat"><span class="rk-etiqueta-cat"><?php echo $e($f['categoria']); ?></span></td>
                                <td class="rk-num rk-activa"><strong><?php echo rkNumero($f['puntos']); ?></strong></td>
                                <td class="rk-num"><?php echo $f['torneos']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo $f['campeonatos']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo $f['pg']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo $f['pp']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo ($f['dif_sets'] > 0 ? '+' : '') . $f['dif_sets']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo ($f['dif_games'] > 0 ? '+' : '') . $f['dif_games']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="rk-mas" data-rk-mas hidden>
                <span class="rk-mas-contador" data-rk-contador aria-live="polite"></span>
                <div class="rk-mas-botones">
                    <button type="button" class="rk-btn rk-btn-suave" data-rk-ver-menos hidden><i class="bi bi-chevron-up"></i> Ver menos</button>
                    <button type="button" class="rk-btn" data-rk-ver-mas><i class="bi bi-chevron-down"></i> Ver más</button>
                </div>
            </div>

            <p class="rk-nota">
                Solo cuentan los puntos ganados en torneos de este club; los de otros clubes no se suman acá y los descalificados no puntúan.
                Es una vista de consulta: no cambia el ranking general ni el de cada categoría.
                Desempate: puntos, campeonatos ganados, partidos ganados, diferencia de sets y diferencia de games; si sigue igual, comparten posición.
            </p>

        <?php } ?>

    </div>

    <?php
    return ob_get_clean();

}


/**
 * Ranking por categoría con selector, SIEMPRE dentro de un club (cada club tiene su propio ranking).
 * El club sale de ?club= y solo puede ser uno de los que la persona puede ver (se valida en el servidor). Con un solo club se
 * elige solo; con varios hay que elegir uno (no se elige ninguno por la persona).
 *
 * @param string   $urlBase           ej. 'paneles.php?seccion=ranking' (se le agrega &categoria=N)
 * @param int|string|null $categoriaPedida  id de categoría, 'general' o null (= General, la pestaña inicial)
 * @param int|null $idUsuarioResaltar fila a destacar (el propio participante)
 * @param string   $filtro            filtro del ranking general: 'historico' | 'temporada'
 */
function renderRankingCategorias($conexion, $urlBase, $categoriaPedida = null, $idUsuarioResaltar = null, $filtro = 'historico') {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    // Clubes que esta persona puede ver (administrador: todos; organizador: los suyos; participante: los que integra) y club
    // activo. Un club inexistente o ajeno se ignora: no se muestra ningún dato hasta elegir uno permitido.
    $clubes = rankingClubesVisibles($conexion);
    $clubActivo = rkClubContexto($conexion);
    $separador = (strpos($urlBase, '?') === false) ? '?' : '&';

    if (empty($clubes)) {
        return '<div class="rk"><div class="rk-vacio"><i class="bi bi-buildings"></i> Todavía no pertenecés a ningún club, por eso no hay ranking para mostrar. Unite a un club desde Torneos.</div></div>';
    }

    if ($clubActivo === null) {
        $pestana = rkCategoriaDeGet();
        return '<div class="rk">' . rkSelectorClub($clubes, null, $urlBase, $separador, 'categoria=' . ($pestana === null ? 'general' : $pestana) . '&filtro=' . rankingGeneralFiltro($filtro), $e) . rkAvisoElegirClub() . '</div>';
    }

    $stmtCat = mysqli_prepare(
        $conexion,
        "SELECT c.id_categoria, c.nombre,
                (SELECT COUNT(*) FROM usuario_club uc INNER JOIN usuario u ON u.id_usuario = uc.id_usuario
                  WHERE u.id_rol = 3 AND uc.id_club = ? AND uc.id_categoria = c.id_categoria) AS jugadores
         FROM categoria c ORDER BY c.id_categoria ASC"
    );
    mysqli_stmt_bind_param($stmtCat, "i", $clubActivo);
    mysqli_stmt_execute($stmtCat);
    $categorias = mysqli_fetch_all(mysqli_stmt_get_result($stmtCat), MYSQLI_ASSOC);
    mysqli_stmt_close($stmtCat);

    $ids = array_map('intval', array_column($categorias, 'id_categoria'));

    // General es la pestaña inicial; una categoría inexistente también vuelve a General.
    $esGeneral = ($categoriaPedida === null || $categoriaPedida === 'general');
    $seleccionada = (!$esGeneral && in_array((int) $categoriaPedida, $ids, true)) ? (int) $categoriaPedida : null;

    if ($seleccionada === null) {
        $esGeneral = true;
    }

    $stmtTot = mysqli_prepare($conexion, "SELECT COUNT(*) FROM usuario_club uc INNER JOIN usuario u ON u.id_usuario = uc.id_usuario WHERE u.id_rol = 3 AND uc.id_club = ?");
    mysqli_stmt_bind_param($stmtTot, "i", $clubActivo);
    mysqli_stmt_execute($stmtTot);
    $totalJugadores = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmtTot))[0];
    mysqli_stmt_close($stmtTot);

    $nombreCategoria = '';
    foreach ($categorias as $c) {
        if ((int) $c['id_categoria'] === $seleccionada) { $nombreCategoria = $c['nombre']; }
    }

    $ranking = $esGeneral ? [] : rankingDeCategoria($conexion, $clubActivo, $seleccionada);
    $qClub = 'club=' . $clubActivo;

    ob_start();
    ?>

    <div class="rk">

        <?php echo rkSelectorClub($clubes, $clubActivo, $urlBase, $separador, 'categoria=' . ($esGeneral ? 'general' : $seleccionada) . '&filtro=' . rankingGeneralFiltro($filtro), $e); ?>

        <nav class="rk-categorias" aria-label="Categorías">
            <a href="<?php echo $e($urlBase . $separador . $qClub . '&categoria=general'); ?>"
               class="rk-chip rk-chip-general <?php echo $esGeneral ? 'activo' : ''; ?>"
               <?php echo $esGeneral ? 'aria-current="page"' : ''; ?>>
                <i class="bi bi-globe2"></i> General
                <small><?php echo $totalJugadores; ?></small>
            </a>
            <?php foreach ($categorias as $c) { $activa = ((int) $c['id_categoria'] === $seleccionada); ?>
                <a href="<?php echo $e($urlBase . $separador . $qClub . '&categoria=' . (int) $c['id_categoria']); ?>"
                   class="rk-chip <?php echo $activa ? 'activo' : ''; ?>"
                   <?php echo $activa ? 'aria-current="page"' : ''; ?>>
                    <?php echo $e($c['nombre']); ?>
                    <small><?php echo (int) $c['jugadores']; ?></small>
                </a>
            <?php } ?>
        </nav>

        <?php if ($esGeneral) { echo renderCuerpoRankingGeneral($conexion, $urlBase, $separador, $clubActivo, $filtro, $idUsuarioResaltar, $e, rkTemporadaDeGet()); } else { ?>

        <div class="rk-resumen">
            <strong><?php echo $e($nombreCategoria); ?></strong>
            <span><?php echo count($ranking); ?> <?php echo count($ranking) === 1 ? 'jugador' : 'jugadores'; ?></span>
            <span>Se asciende en este club al llegar a <?php echo rkNumero(RANKING_PUNTOS_ASCENSO); ?> puntos ganados en él</span>
        </div>

        <?php if (empty($ranking)) { ?>

            <div class="rk-vacio"><i class="bi bi-people"></i> Todavía no hay jugadores en esta categoría.</div>

        <?php } else { ?>

            <div class="rk-tabla-scroll">
                <table class="rk-tabla">
                    <thead>
                        <tr>
                            <th class="rk-col-pos">#</th>
                            <th>Jugador</th>
                            <th>Categoría</th>
                            <th class="rk-num">Puntos</th>
                            <th class="rk-col-progreso">Progreso a <?php echo rkNumero(RANKING_PUNTOS_ASCENSO); ?></th>
                            <th class="rk-num" title="Torneos disputados">Torneos</th>
                            <th class="rk-num" title="Partidos ganados">PG</th>
                            <th class="rk-num" title="Partidos perdidos">PP</th>
                            <th class="rk-num rk-oculto-chico" title="Diferencia de sets">Dif. sets</th>
                            <th class="rk-num rk-oculto-chico" title="Diferencia de games">Dif. games</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ranking as $f) {
                            $propia = ($idUsuarioResaltar !== null && (int) $idUsuarioResaltar === $f['id_usuario']);
                            $clasePos = $f['posicion'] <= 3 && $f['puntos'] > 0 ? ' rk-podio-' . $f['posicion'] : '';
                        ?>
                            <tr class="<?php echo $propia ? 'rk-propia' : ''; ?><?php echo $clasePos; ?>">
                                <td class="rk-col-pos">
                                    <span class="rk-pos"><?php echo $f['posicion']; ?></span>
                                    <?php if ($f['empate']) { ?><i class="bi bi-distribute-vertical rk-empate" title="Empatado: comparte posición"></i><?php } ?>
                                </td>
                                <td class="rk-jugador">
                                    <span class="rk-avatar"><?php echo $e(mb_strtoupper(mb_substr($f['nombre'], 0, 1) . mb_substr($f['apellido'], 0, 1))); ?></span>
                                    <span class="rk-nombre"><?php echo $e($f['nombre_completo']); ?><?php echo $propia ? ' <em>(vos)</em>' : ''; ?></span>
                                </td>
                                <td><?php echo $e($nombreCategoria); ?></td>
                                <td class="rk-num"><strong><?php echo rkNumero($f['puntos']); ?></strong></td>
                                <td class="rk-col-progreso">
                                    <div class="rk-barra" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo RANKING_PUNTOS_ASCENSO; ?>" aria-valuenow="<?php echo min($f['puntos'], RANKING_PUNTOS_ASCENSO); ?>">
                                        <i style="width: <?php echo $f['progreso']; ?>%"></i>
                                    </div>
                                    <small><?php echo rkNumero($f['puntos']); ?> / <?php echo rkNumero(RANKING_PUNTOS_ASCENSO); ?></small>
                                </td>
                                <td class="rk-num"><?php echo $f['torneos']; ?></td>
                                <td class="rk-num"><?php echo $f['pg']; ?></td>
                                <td class="rk-num"><?php echo $f['pp']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo ($f['dif_sets'] > 0 ? '+' : '') . $f['dif_sets']; ?></td>
                                <td class="rk-num rk-oculto-chico"><?php echo ($f['dif_games'] > 0 ? '+' : '') . $f['dif_games']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <p class="rk-nota">
                Orden: puntos, partidos ganados, diferencia de sets, diferencia de games y enfrentamiento directo.
                Si sigue el empate, comparten posición. Los puntos cuentan desde el último ascenso o cambio de categoría.
            </p>

        <?php } ?>

        <?php } // fin de la vista de categoría ?>

    </div>

    <?php
    return ob_get_clean();

}


/** Etiqueta de la situación de un jugador en el torneo (para la tabla de puntos). */
function rkEtapaDeJugador(array $j, $hayCruces) {

    if ($j['partidos'] === 0 && !$j['en_cruces']) {
        return 'Todavía sin jugar';
    }

    if ($j['instancia'] === 'Campeon') {
        return RANKING_NOMBRE_INSTANCIA['Campeon'];
    }

    if ($j['perdio_cruce']) {
        return RANKING_NOMBRE_INSTANCIA[$j['instancia']] ?? $j['instancia'];
    }

    if ($j['en_cruces']) {
        return 'Sigue en carrera';
    }

    return $hayCruces ? 'Fase de grupos (no clasificó)' : 'Fase de grupos';

}


/** Puntos de un torneo, en vivo: se suman solos con cada resultado cargado (no hay botón para otorgarlos). */
function renderPuntosTorneo($conexion, $idTorneo, $csrfToken = '') {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT t.nombre, t.id_categoria, t.id_club, cat.nombre AS categoria
         FROM torneos t INNER JOIN categoria cat ON cat.id_categoria = t.id_categoria
         WHERE t.id_torneo = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $calculo = calcularPuntosTorneo($conexion, $idTorneo);
    $filas = [];
    $hayCruces = false;
    $completo = false;
    $pendientes = 0;
    $invalidos = 0;

    if ($calculo['ok']) {

        $hayCruces = $calculo['hay_cruces'];
        $completo = $calculo['completo'];
        $pendientes = $calculo['pendientes'];
        $invalidos = $calculo['invalidos'];

        $ids = array_keys($calculo['jugadores']);

        $categoriasActuales = [];

        if (!empty($ids)) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            // La categoría que cuenta es la del jugador en el club de ESTE torneo.
            $stmt = mysqli_prepare($conexion, "SELECT id_usuario, id_categoria FROM usuario_club WHERE id_club = ? AND id_usuario IN ($marcas)");
            mysqli_stmt_bind_param($stmt, 'i' . str_repeat('i', count($ids)), $torneo['id_club'], ...$ids);
            mysqli_stmt_execute($stmt);
            foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $u) {
                $categoriasActuales[(int) $u['id_usuario']] = $u['id_categoria'] === null ? null : (int) $u['id_categoria'];
            }
        }

        foreach ($calculo['jugadores'] as $idU => $j) {
            $filas[] = [
                'nombre' => $j['nombre'] ?? '',
                'etapa' => rkEtapaDeJugador($j, $hayCruces),
                'puntos_grupos' => $j['puntos_grupos'],
                'puntos_cruces' => $j['puntos_cruces'],
                'pg' => $j['pg'],
                'pp' => $j['pp'],
                'cuenta' => (($categoriasActuales[$idU] ?? null) === (int) $torneo['id_categoria']),
                'anulado' => false,
            ];
        }

    }

    // Descalificados: figuran con 0 (y con lo que se les anuló, si ya tenían puntos).
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT CONCAT(u.nombre, ' ', u.apellido) AS nombre,
                COALESCE(SUM(rp.puntos_grupos + rp.puntos_cruces), 0) AS anulados,
                COALESCE(SUM(rp.partidos_ganados), 0) AS pg, COALESCE(SUM(rp.partidos_perdidos), 0) AS pp
         FROM inscripcion i
         INNER JOIN usuario u ON u.id_usuario = i.id_usuario
         LEFT JOIN ranking_puntos rp ON rp.id_torneo = i.id_torneo AND rp.id_usuario = i.id_usuario AND rp.anulado = 1
         WHERE i.id_torneo = ? AND i.estado = 'Descalificada'
         GROUP BY i.id_inscripcion, u.nombre, u.apellido
         ORDER BY u.apellido, u.nombre"
    );
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);

    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $d) {
        $filas[] = [
            'nombre' => $d['nombre'],
            'etapa' => 'Descalificado',
            'puntos_grupos' => 0,
            'puntos_cruces' => 0,
            'anulados' => (int) $d['anulados'],
            'pg' => (int) $d['pg'],
            'pp' => (int) $d['pp'],
            'cuenta' => true,
            'anulado' => true,
        ];
    }

    // Los descalificados van al final y no suman nada (sus puntos están anulados).
    usort($filas, function ($a, $b) {
        if ($a['anulado'] !== $b['anulado']) {
            return $a['anulado'] <=> $b['anulado'];
        }
        $ta = $a['puntos_grupos'] + $a['puntos_cruces'];
        $tb = $b['puntos_grupos'] + $b['puntos_cruces'];
        return $tb <=> $ta ?: strcasecmp($a['nombre'], $b['nombre']);
    });

    $fueraDeCategoria = count(array_filter($filas, fn($f) => !$f['cuenta'] && !$f['anulado']));
    $ascensos = $calculo['ok'] ? ascensosDeTorneo($conexion, $idTorneo) : [];

    ob_start();
    ?>

    <div class="rk rk-torneo">

        <div class="rk-reglas">
            <div>
                <strong>Fase de grupos</strong>
                <span>Partido ganado <b>2</b> · perdido <b>1</b> · perdido por W.O. <b>0</b></span>
            </div>
            <div>
                <strong>Cruces (una sola instancia, no se suman)</strong>
                <span>Campeón <b>1000</b> · Finalista <b>800</b> · Semifinalista <b>600</b> · Cuartos <b>400</b> · Octavos <b>200</b></span>
            </div>
        </div>

        <?php if (!$calculo['ok']) { ?>

            <div class="rk-estado rk-estado-espera">
                <i class="bi bi-hourglass-split"></i>
                <div>
                    <strong>Todavía no hay partidos jugados</strong>
                    <span>Los puntos se van sumando solos al ranking de <?php echo $e($torneo['categoria']); ?> a medida que se cargan los resultados.</span>
                </div>
            </div>

        <?php } elseif ($completo) { ?>

            <div class="rk-estado rk-estado-ok">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <strong>Torneo completo: puntos definitivos</strong>
                    <span>Todos los resultados están cargados y los puntos ya figuran en el ranking de <?php echo $e($torneo['categoria']); ?>.
                        <?php echo empty($ascensos) ? 'Se puede corregir un resultado mientras el torneo no haya provocado ascensos de categoría; los puntos se recalculan solos.' : 'Como hubo ascensos de categoría, los resultados quedaron fijos.'; ?></span>
                </div>
            </div>

            <?php if (!empty($ascensos)) { ?>
                <div class="rk-ascensos">
                    <strong><i class="bi bi-arrow-up-circle-fill"></i> Ascensos por este torneo</strong>
                    <ul>
                        <?php foreach ($ascensos as $a) { ?>
                            <li><?php echo $e(trim($a['nombre'] . ' ' . $a['apellido'])); ?>: <?php echo $e($a['de']); ?> → <strong><?php echo $e($a['a']); ?></strong> (<?php echo rkNumero($a['puntos_alcanzados']); ?> puntos)</li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>

        <?php } else { ?>

            <div class="rk-estado rk-estado-listo">
                <i class="bi bi-lightning-charge-fill"></i>
                <div>
                    <strong>Torneo en curso: los puntos se suman solos</strong>
                    <span>Cada resultado que cargás suma en el acto al ranking de <?php echo $e($torneo['categoria']); ?>.
                        Llegar a una ronda no da puntos: se suman cuando se define el partido.
                        Faltan <?php echo (int) $pendientes; ?> partido<?php echo $pendientes === 1 ? '' : 's'; ?> por jugar o cargar.
                        Los ascensos de categoría se aplican cuando termina el torneo.</span>
                </div>
            </div>

        <?php } ?>

        <?php if ($invalidos > 0) { ?>
            <p class="rk-aviso">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Hay <?php echo (int) $invalidos; ?> partido<?php echo $invalidos === 1 ? '' : 's'; ?> con un resultado que no es válido: no suma puntos hasta que se corrija.
            </p>
        <?php } ?>

        <?php if ($fueraDeCategoria > 0) { ?>
            <p class="rk-aviso">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo $fueraDeCategoria; ?> <?php echo $fueraDeCategoria === 1 ? 'jugador no está' : 'jugadores no están'; ?> hoy en la categoría
                del torneo (<?php echo $e($torneo['categoria']); ?>). Sus puntos quedan registrados pero no suman en su categoría actual.
                Se marcan con <i class="bi bi-slash-circle"></i>.
            </p>
        <?php } ?>

        <?php if (!empty($filas)) { ?>
            <div class="rk-tabla-scroll rk-tabla-torneo">
                <table class="rk-tabla">
                    <thead>
                        <tr>
                            <th>Jugador</th>
                            <th>Situación</th>
                            <th class="rk-num">Grupos</th>
                            <th class="rk-num">Cruces</th>
                            <th class="rk-num">Total</th>
                            <th class="rk-num">PG</th>
                            <th class="rk-num">PP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $f) { ?>
                            <?php $anulado = $f['anulado']; ?>
                            <tr<?php echo $anulado ? ' class="rk-descalificado"' : ''; ?>>
                                <td class="rk-jugador">
                                    <span class="rk-nombre"><?php echo $e($f['nombre']); ?></span>
                                    <?php if ($anulado) { ?><span class="rk-etiqueta-desc" title="Descalificado: sus puntos de este torneo no suman en ningún ranking">Descalificado</span><?php } ?>
                                    <?php if (!$anulado && !$f['cuenta']) { ?><i class="bi bi-slash-circle rk-fuera" title="No está en la categoría del torneo"></i><?php } ?>
                                </td>
                                <td><?php echo $anulado ? 'Descalificado' : $e($f['etapa']); ?></td>
                                <?php if ($anulado) { ?>
                                <td class="rk-num" colspan="3"><strong>0</strong><?php if ($f['anulados'] > 0) { ?> <span class="rk-anulado-nota">(anulados: <?php echo rkNumero($f['anulados']); ?>)</span><?php } ?></td>
                                <?php } else { ?>
                                <td class="rk-num"><?php echo $f['puntos_grupos']; ?></td>
                                <td class="rk-num"><?php echo rkNumero($f['puntos_cruces']); ?></td>
                                <td class="rk-num"><strong><?php echo rkNumero($f['puntos_grupos'] + $f['puntos_cruces']); ?></strong></td>
                                <?php } ?>
                                <td class="rk-num"><?php echo $f['pg']; ?></td>
                                <td class="rk-num"><?php echo $f['pp']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

    </div>

    <?php
    return ob_get_clean();

}
