<?php

require_once __DIR__ . '/ranking_puntos.php';

/* =========================================================
   VISTAS DEL RANKING (solo devuelven HTML; no escriben nada)
   - renderRankingCategorias(): selector de categoría + tabla de posiciones.
   - renderPuntosTorneo(): puntos de un torneo (vista previa / otorgados),
     con el botón para otorgarlos (lo valida funciones/ranking_liquidar.php).
   ========================================================= */

function rkNumero($n) {
    return number_format((int) $n, 0, ',', '.');
}


/**
 * Ranking por categoría con selector.
 *
 * @param string   $urlBase           ej. 'paneles.php?seccion=ranking' (se le agrega &categoria=N)
 * @param int|null $categoriaPedida   categoría elegida (null = la primera con puntos, o con jugadores)
 * @param int|null $idUsuarioResaltar fila a destacar (el propio participante)
 */
function renderRankingCategorias($conexion, $urlBase, $categoriaPedida = null, $idUsuarioResaltar = null) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    $categorias = mysqli_fetch_all(mysqli_query(
        $conexion,
        "SELECT c.id_categoria, c.nombre,
                (SELECT COUNT(*) FROM usuario u WHERE u.id_rol = 3 AND u.id_categoria = c.id_categoria) AS jugadores,
                (SELECT COUNT(*) FROM ranking_puntos r WHERE r.id_categoria = c.id_categoria) AS con_puntos
         FROM categoria c ORDER BY c.id_categoria ASC"
    ), MYSQLI_ASSOC);

    $ids = array_map('intval', array_column($categorias, 'id_categoria'));
    $seleccionada = in_array((int) $categoriaPedida, $ids, true) ? (int) $categoriaPedida : null;

    if ($seleccionada === null) {

        foreach ($categorias as $c) {
            if ((int) $c['con_puntos'] > 0 && (int) $c['jugadores'] > 0) { $seleccionada = (int) $c['id_categoria']; break; }
        }

        if ($seleccionada === null) {
            foreach ($categorias as $c) {
                if ((int) $c['jugadores'] > 0) { $seleccionada = (int) $c['id_categoria']; break; }
            }
        }

        if ($seleccionada === null) {
            $seleccionada = $ids[0] ?? 1;
        }

    }

    $nombreCategoria = '';
    foreach ($categorias as $c) {
        if ((int) $c['id_categoria'] === $seleccionada) { $nombreCategoria = $c['nombre']; }
    }

    $ranking = rankingDeCategoria($conexion, $seleccionada);
    $separador = (strpos($urlBase, '?') === false) ? '?' : '&';

    ob_start();
    ?>

    <div class="rk">

        <nav class="rk-categorias" aria-label="Categorías">
            <?php foreach ($categorias as $c) { $activa = ((int) $c['id_categoria'] === $seleccionada); ?>
                <a href="<?php echo $e($urlBase . $separador . 'categoria=' . (int) $c['id_categoria']); ?>"
                   class="rk-chip <?php echo $activa ? 'activo' : ''; ?>"
                   <?php echo $activa ? 'aria-current="page"' : ''; ?>>
                    <?php echo $e($c['nombre']); ?>
                    <small><?php echo (int) $c['jugadores']; ?></small>
                </a>
            <?php } ?>
        </nav>

        <div class="rk-resumen">
            <strong><?php echo $e($nombreCategoria); ?></strong>
            <span><?php echo count($ranking); ?> <?php echo count($ranking) === 1 ? 'jugador' : 'jugadores'; ?></span>
            <span>Se asciende al llegar a <?php echo rkNumero(RANKING_PUNTOS_ASCENSO); ?> puntos</span>
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

    </div>

    <?php
    return ob_get_clean();

}


/** Puntos de un torneo: vista previa (aún sin otorgar) o puntos ya otorgados. */
function renderPuntosTorneo($conexion, $idTorneo, $csrfToken) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT t.nombre, t.id_categoria, cat.nombre AS categoria
         FROM torneos t INNER JOIN categoria cat ON cat.id_categoria = t.id_categoria
         WHERE t.id_torneo = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $liquidacion = estadoLiquidacionTorneo($conexion, $idTorneo);
    $filas = [];
    $problema = '';

    if ($liquidacion) {

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT rp.*, u.nombre, u.apellido, u.id_categoria AS categoria_actual
             FROM ranking_puntos rp INNER JOIN usuario u ON u.id_usuario = rp.id_usuario
             WHERE rp.id_torneo = ?"
        );
        mysqli_stmt_bind_param($stmt, "i", $idTorneo);
        mysqli_stmt_execute($stmt);

        foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $r) {
            $filas[] = [
                'nombre' => trim($r['nombre'] . ' ' . $r['apellido']),
                'instancia' => $r['instancia'],
                'puntos_grupos' => (int) $r['puntos_grupos'],
                'puntos_cruces' => (int) $r['puntos_cruces'],
                'pg' => (int) $r['partidos_ganados'],
                'pp' => (int) $r['partidos_perdidos'],
                'cuenta' => ((int) $r['categoria_actual'] === (int) $torneo['id_categoria']),
            ];
        }

    } else {

        $calculo = calcularPuntosTorneo($conexion, $idTorneo);

        if ($calculo['ok']) {

            $ids = array_keys($calculo['jugadores']);
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $stmt = mysqli_prepare($conexion, "SELECT id_usuario, id_categoria FROM usuario WHERE id_usuario IN ($marcas)");
            mysqli_stmt_bind_param($stmt, str_repeat('i', count($ids)), ...$ids);
            mysqli_stmt_execute($stmt);
            $categoriasActuales = [];
            foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $u) {
                $categoriasActuales[(int) $u['id_usuario']] = $u['id_categoria'] === null ? null : (int) $u['id_categoria'];
            }

            foreach ($calculo['jugadores'] as $idU => $j) {
                $filas[] = [
                    'nombre' => $j['nombre'] ?? '',
                    'instancia' => $j['instancia'],
                    'puntos_grupos' => $j['puntos_grupos'],
                    'puntos_cruces' => $j['puntos_cruces'],
                    'pg' => $j['pg'],
                    'pp' => $j['pp'],
                    'cuenta' => (($categoriasActuales[$idU] ?? null) === (int) $torneo['id_categoria']),
                ];
            }

        } else {

            $problemas = [
                'sin_partidos' => 'Todavía no hay partidos generados en este torneo.',
                'partidos_pendientes' => 'Faltan cargar resultados: ' . ($calculo['pendientes'] ?? '') . ' partido(s) todavía sin jugar.',
                'falta_final' => 'El cuadro de cruces todavía no terminó: falta jugar la final.',
                'resultado_invalido' => 'Hay un partido con un resultado que no es válido. Revisalo antes de otorgar los puntos.',
                'no_existe' => 'No se encontró el torneo.',
            ];
            $problema = $problemas[$calculo['error']] ?? 'No se pueden calcular los puntos todavía.';

        }

    }

    usort($filas, function ($a, $b) {
        $ta = $a['puntos_grupos'] + $a['puntos_cruces'];
        $tb = $b['puntos_grupos'] + $b['puntos_cruces'];
        return $tb <=> $ta ?: strcasecmp($a['nombre'], $b['nombre']);
    });

    $fueraDeCategoria = count(array_filter($filas, fn($f) => !$f['cuenta']));

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

        <?php if ($liquidacion) { ?>

            <div class="rk-estado rk-estado-ok">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <strong>Puntos otorgados</strong>
                    <span>El <?php echo $e(date('d/m/Y H:i', strtotime($liquidacion['fecha']))); ?> por <?php echo $e(trim($liquidacion['liquidador'])); ?>.
                        Quedan fijos: ya no se pueden modificar los resultados de este torneo.</span>
                </div>
            </div>

            <?php if (!empty($liquidacion['ascensos'])) { ?>
                <div class="rk-ascensos">
                    <strong><i class="bi bi-arrow-up-circle-fill"></i> Ascensos por este torneo</strong>
                    <ul>
                        <?php foreach ($liquidacion['ascensos'] as $a) { ?>
                            <li><?php echo $e(trim($a['nombre'] . ' ' . $a['apellido'])); ?>: <?php echo $e($a['de']); ?> → <strong><?php echo $e($a['a']); ?></strong> (<?php echo rkNumero($a['puntos_alcanzados']); ?> puntos)</li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>

        <?php } elseif ($problema !== '') { ?>

            <div class="rk-estado rk-estado-espera">
                <i class="bi bi-hourglass-split"></i>
                <div>
                    <strong>Todavía no se pueden otorgar los puntos</strong>
                    <span><?php echo $e($problema); ?></span>
                </div>
            </div>

        <?php } else { ?>

            <div class="rk-estado rk-estado-listo">
                <i class="bi bi-eye-fill"></i>
                <div>
                    <strong>Vista previa: todavía no se otorgaron</strong>
                    <span>Revisá los puntos. Al otorgarlos se suman al ranking de <?php echo $e($torneo['categoria']); ?>, se aplican los ascensos que correspondan y los resultados del torneo quedan fijos.</span>
                </div>
                <form action="funciones/ranking_liquidar.php" method="POST" class="rk-form-otorgar"
                      onsubmit="return confirm('¿Otorgar los puntos de este torneo al ranking? No se puede deshacer.');">
                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                    <input type="hidden" name="id_torneo" value="<?php echo (int) $idTorneo; ?>">
                    <button type="submit" class="rk-btn"><i class="bi bi-award-fill"></i> Otorgar puntos</button>
                </form>
            </div>

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
                            <th>Instancia alcanzada</th>
                            <th class="rk-num">Grupos</th>
                            <th class="rk-num">Cruces</th>
                            <th class="rk-num">Total</th>
                            <th class="rk-num">PG</th>
                            <th class="rk-num">PP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $f) { ?>
                            <tr>
                                <td class="rk-jugador">
                                    <span class="rk-nombre"><?php echo $e($f['nombre']); ?></span>
                                    <?php if (!$f['cuenta']) { ?><i class="bi bi-slash-circle rk-fuera" title="No está en la categoría del torneo"></i><?php } ?>
                                </td>
                                <td><?php echo $e(RANKING_NOMBRE_INSTANCIA[$f['instancia']] ?? $f['instancia']); ?></td>
                                <td class="rk-num"><?php echo $f['puntos_grupos']; ?></td>
                                <td class="rk-num"><?php echo rkNumero($f['puntos_cruces']); ?></td>
                                <td class="rk-num"><strong><?php echo rkNumero($f['puntos_grupos'] + $f['puntos_cruces']); ?></strong></td>
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
