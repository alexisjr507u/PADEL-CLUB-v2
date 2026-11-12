<?php

require_once __DIR__ . '/ranking_temporada.php';
require_once __DIR__ . '/ranking_vista.php';

/* =========================================================
   TARJETA «TEMPORADA» DEL RANKING DE UN CLUB (solo devuelve HTML)
   Cada club tiene su temporada: se muestra la del club activo, solo si la persona puede gestionarlo
   (administrador: cualquier club; organizador: los suyos). Temporada vigente + botón «Finalizar temporada» (lo valida
   funciones/ranking_cerrar_temporada.php en el servidor) y tres
   botones con contador que despliegan su detalle (js/ranking_temporada.js):
     - Ascensos de la temporada vigente
     - Descensos que habría si se finalizara hoy
     - Resumen de la última temporada finalizada
   ========================================================= */

/** Una fila de ascenso o de descenso. $tipo: 'sube' | 'baja'. */
function rkFilaMovimiento($e, $tipo, $nombre, $de, $a, $puntos, $detalle = '') {

    $sube = ($tipo === 'sube');

    ob_start();
    ?>
    <li class="rk-mov rk-mov-<?php echo $sube ? 'sube' : 'baja'; ?>">
        <span class="rk-mov-icono"><i class="bi <?php echo $sube ? 'bi-arrow-up' : 'bi-arrow-down'; ?>"></i></span>
        <span class="rk-mov-nombre"><?php echo $e($nombre); ?></span>
        <span class="rk-mov-categorias">
            <?php echo $e($de); ?> <i class="bi bi-arrow-right"></i> <strong><?php echo $e($a); ?></strong>
        </span>
        <span class="rk-mov-dato">
            <?php echo rkNumero($puntos); ?> pts<?php echo $detalle !== '' ? ' · ' . $e($detalle) : ''; ?>
        </span>
    </li>
    <?php
    return ob_get_clean();

}


function renderTemporada($conexion, $csrfToken, $codigoAccion = '', $idClub = null) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    $mensajes = [
        'temporada_cerrada' => ['exito', 'Temporada finalizada: los puntos volvieron a 0 y se aplicaron los descensos.'],
        'temporada_ya_cerrada' => ['error', 'Esa temporada ya estaba finalizada. Se muestra la temporada vigente.'],
        'sin_temporada' => ['error', 'Ese club no tiene una temporada abierta.'],
        'sin_permiso' => ['error', 'No tenés permiso para finalizar la temporada de ese club.'],
        'token' => ['error', 'La sesión de seguridad venció. Intentá nuevamente.'],
        'datos_invalidos' => ['error', 'Datos inválidos.'],
        'error_bd' => ['error', 'Ocurrió un error al finalizar la temporada. No se aplicó ningún cambio.'],
    ];

    // Solo se muestra la temporada de un club que esta persona puede gestionar (el rol y los clubes salen de la base).
    $gestiona = $idClub !== null && puedeGestionarClub($conexion, (int) ($_SESSION['id_usuario'] ?? 0), (int) $idClub);
    $temporada = $gestiona ? temporadaAbierta($conexion, (int) $idClub) : null;
    $ascensos = $temporada ? ascensosDeTemporada($conexion, (int) $temporada['id_temporada']) : [];
    $descensos = $temporada ? previsualizarCierreTemporada($conexion, (int) $idClub) : [];
    $ultimo = $gestiona ? ultimoCierreTemporada($conexion, (int) $idClub) : null;
    $nombreClub = '';

    if ($gestiona) {
        $stmtN = mysqli_prepare($conexion, "SELECT nombre FROM club WHERE id_club = ?");
        $idClubInt = (int) $idClub;
        mysqli_stmt_bind_param($stmtN, "i", $idClubInt);
        mysqli_stmt_execute($stmtN);
        $nombreClub = (string) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtN))['nombre'] ?? '');
        mysqli_stmt_close($stmtN);
    }

    ob_start();
    ?>

    <?php if ($codigoAccion !== '' && isset($mensajes[$codigoAccion])) { [$tipo, $texto] = $mensajes[$codigoAccion]; ?>
        <div class="<?php echo $tipo === 'exito' ? 'mensaje-exito' : 'mensaje-error'; ?> mensaje-error-compacto" style="margin-bottom:16px;">
            <i class="bi <?php echo $tipo === 'exito' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'; ?>"></i>
            <?php echo $e($texto); ?>
        </div>
    <?php } ?>

    <?php if ($temporada) { ?>

        <section class="rk rk-temporada" data-rk-temporada>

            <div class="rk-temporada-cabecera">

                <div>
                    <span class="rk-etiqueta">TEMPORADA VIGENTE · <?php echo $e($nombreClub); ?></span>
                    <h2><?php echo $e($temporada['nombre']); ?></h2>
                    <small>Desde el <?php echo $e(date('d/m/Y', strtotime($temporada['fecha_inicio']))); ?></small>
                </div>

                <form action="funciones/ranking_cerrar_temporada.php" method="POST"
                      data-confirmar
                      data-confirmar-tipo="peligro"
                      data-confirmar-icono="bi-flag-fill"
                      data-confirmar-titulo="¿Finalizar la <?php echo $e($temporada['nombre']); ?> de <?php echo $e($nombreClub); ?>?"
                      data-confirmar-texto="Los puntos de todos los jugadores de este club vuelven a 0 y el último del ranking de cada categoría del club desciende una categoría. No afecta a los demás clubes. Esta acción no se puede deshacer."
                      data-confirmar-boton="Sí, finalizar">
                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                    <input type="hidden" name="id_club" value="<?php echo (int) $idClub; ?>">
                    <input type="hidden" name="id_temporada" value="<?php echo (int) $temporada['id_temporada']; ?>">
                    <button type="submit" class="rk-btn rk-btn-peligro"><i class="bi bi-flag-fill"></i> Finalizar temporada</button>
                </form>

            </div>

            <div class="rk-efectos">
                <span class="rk-efecto-titulo">Al finalizar:</span>
                <span class="rk-efecto"><i class="bi bi-arrow-counterclockwise"></i> Los puntos de todos en este club vuelven a 0</span>
                <span class="rk-efecto rk-efecto-baja"><i class="bi bi-arrow-down-circle-fill"></i> El último de cada categoría del club baja una</span>
            </div>

            <div class="rk-botones" role="group" aria-label="Detalle de la temporada">

                <button type="button" class="rk-tab rk-tab-sube" data-rk-panel="ascensos" aria-expanded="false" aria-controls="rk-panel-ascensos">
                    <i class="bi bi-arrow-up-circle-fill rk-tab-icono"></i>
                    <span class="rk-tab-texto">Ascensos</span>
                    <b class="rk-tab-cuenta"><?php echo count($ascensos); ?></b>
                    <i class="bi bi-chevron-down rk-tab-flecha"></i>
                </button>

                <button type="button" class="rk-tab rk-tab-baja" data-rk-panel="descensos" aria-expanded="false" aria-controls="rk-panel-descensos">
                    <i class="bi bi-arrow-down-circle-fill rk-tab-icono"></i>
                    <span class="rk-tab-texto">Descensos de hoy</span>
                    <b class="rk-tab-cuenta"><?php echo count($descensos); ?></b>
                    <i class="bi bi-chevron-down rk-tab-flecha"></i>
                </button>

                <?php if ($ultimo) { ?>
                    <button type="button" class="rk-tab" data-rk-panel="ultima" aria-expanded="false" aria-controls="rk-panel-ultima">
                        <i class="bi bi-clock-history rk-tab-icono"></i>
                        <span class="rk-tab-texto">Última temporada</span>
                        <i class="bi bi-chevron-down rk-tab-flecha"></i>
                    </button>
                <?php } ?>

            </div>

            <div class="rk-paneles" data-rk-paneles>

                <!-- Ascensos de la temporada vigente -->
                <div class="rk-panel" id="rk-panel-ascensos" hidden>
                    <p class="rk-panel-titulo">Ascensos de la <?php echo $e($temporada['nombre']); ?></p>
                    <?php if (empty($ascensos)) { ?>
                        <p class="rk-panel-vacio"><i class="bi bi-info-circle"></i> Todavía no hubo ascensos en esta temporada. Se asciende al llegar a <?php echo rkNumero(RANKING_PUNTOS_ASCENSO); ?> puntos.</p>
                    <?php } else { ?>
                        <ul class="rk-movs">
                            <?php foreach ($ascensos as $a) { echo rkFilaMovimiento($e, 'sube', $a['nombre'], $a['de'], $a['a'], $a['puntos_alcanzados'], (string) $a['torneo']); } ?>
                        </ul>
                    <?php } ?>
                </div>

                <!-- Descensos si se finalizara hoy -->
                <div class="rk-panel" id="rk-panel-descensos" hidden>
                    <p class="rk-panel-titulo">Si finalizás hoy, bajarían de categoría</p>
                    <?php if (empty($descensos)) { ?>
                        <p class="rk-panel-vacio"><i class="bi bi-info-circle"></i> Hoy no bajaría nadie. Solo cuentan quienes jugaron algún torneo en la temporada, y tiene que haber un último distinto del primero.</p>
                    <?php } else { ?>
                        <ul class="rk-movs">
                            <?php foreach ($descensos as $d) { echo rkFilaMovimiento($e, 'baja', $d['nombre'], $d['de_nombre'], $d['a_nombre'], $d['puntos'], 'último de ' . (int) $d['competidores']); } ?>
                        </ul>
                    <?php } ?>
                </div>

                <!-- Última temporada finalizada -->
                <?php if ($ultimo) { ?>
                    <div class="rk-panel" id="rk-panel-ultima" hidden>
                        <p class="rk-panel-titulo">
                            <?php echo $e($ultimo['nombre']); ?> · finalizada el <?php echo $e(date('d/m/Y H:i', strtotime($ultimo['fecha_cierre']))); ?>
                            <?php echo !empty($ultimo['cerro']) ? ' por ' . $e(trim($ultimo['cerro'])) : ''; ?>
                        </p>

                        <p class="rk-subtitulo rk-subtitulo-sube"><i class="bi bi-arrow-up-circle-fill"></i> Ascensos (<?php echo count($ultimo['ascensos']); ?>)</p>
                        <?php if (empty($ultimo['ascensos'])) { ?>
                            <p class="rk-panel-vacio">No hubo ascensos.</p>
                        <?php } else { ?>
                            <ul class="rk-movs">
                                <?php foreach ($ultimo['ascensos'] as $a) { echo rkFilaMovimiento($e, 'sube', $a['nombre'], $a['de'], $a['a'], $a['puntos_alcanzados'], (string) $a['torneo']); } ?>
                            </ul>
                        <?php } ?>

                        <p class="rk-subtitulo rk-subtitulo-baja"><i class="bi bi-arrow-down-circle-fill"></i> Descensos (<?php echo count($ultimo['descensos']); ?>)</p>
                        <?php if (empty($ultimo['descensos'])) { ?>
                            <p class="rk-panel-vacio">No hubo descensos.</p>
                        <?php } else { ?>
                            <ul class="rk-movs">
                                <?php foreach ($ultimo['descensos'] as $d) { echo rkFilaMovimiento($e, 'baja', $d['nombre'], $d['de'], $d['a'], $d['puntos_alcanzados']); } ?>
                            </ul>
                        <?php } ?>
                    </div>
                <?php } ?>

            </div>

        </section>

    <?php } elseif ($gestiona) { ?>

        <section class="rk rk-temporada">
            <div class="rk-vacio">
                <i class="bi bi-calendar-event"></i>
                <strong><?php echo $e($nombreClub); ?></strong> todavía no tiene una temporada abierta: se abre sola al cargar el primer resultado de uno de sus torneos.
            </div>
        </section>

    <?php } ?>

    <?php
    return ob_get_clean();

}
