<?php

require_once __DIR__ . '/ranking_temporada.php';
require_once __DIR__ . '/ranking_vista.php';

/* =========================================================
   TARJETA «TEMPORADA» DEL RANKING (solo devuelve HTML)
   Muestra la temporada vigente, quién descendería si se finalizara
   ahora y el botón «Finalizar temporada» (lo valida
   funciones/ranking_cerrar_temporada.php en el servidor).
   ========================================================= */

function renderTemporada($conexion, $csrfToken, $codigoAccion = '') {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    $mensajes = [
        'temporada_cerrada' => ['exito', 'Temporada finalizada: los puntos volvieron a 0 y se aplicaron los descensos.'],
        'temporada_ya_cerrada' => ['error', 'Esa temporada ya estaba finalizada. Se muestra la temporada vigente.'],
        'sin_temporada' => ['error', 'No hay una temporada abierta.'],
        'token' => ['error', 'La sesión de seguridad venció. Intentá nuevamente.'],
        'datos_invalidos' => ['error', 'Datos inválidos.'],
        'error_bd' => ['error', 'Ocurrió un error al finalizar la temporada. No se aplicó ningún cambio.'],
    ];

    $temporada = temporadaAbierta($conexion);
    $descensos = $temporada ? previsualizarCierreTemporada($conexion) : [];
    $ultimo = ultimoCierreTemporada($conexion);

    ob_start();
    ?>

    <?php if ($codigoAccion !== '' && isset($mensajes[$codigoAccion])) { [$tipo, $texto] = $mensajes[$codigoAccion]; ?>
        <div class="<?php echo $tipo === 'exito' ? 'mensaje-exito' : 'mensaje-error'; ?> mensaje-error-compacto" style="margin-bottom:16px;">
            <i class="bi <?php echo $tipo === 'exito' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'; ?>"></i>
            <?php echo $e($texto); ?>
        </div>
    <?php } ?>

    <?php if ($temporada) { ?>

        <section class="rk rk-temporada">

            <div class="rk-temporada-cabecera">
                <div>
                    <span class="rk-etiqueta">TEMPORADA VIGENTE</span>
                    <h2><?php echo $e($temporada['nombre']); ?></h2>
                    <small>Desde el <?php echo $e(date('d/m/Y', strtotime($temporada['fecha_inicio']))); ?></small>
                </div>

                <form action="funciones/ranking_cerrar_temporada.php" method="POST"
                      onsubmit="return confirm('¿Finalizar la <?php echo $e($temporada['nombre']); ?>?\n\nLos puntos de TODOS vuelven a 0 y el último del ranking de cada categoría desciende. No se puede deshacer.');">
                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                    <input type="hidden" name="id_temporada" value="<?php echo (int) $temporada['id_temporada']; ?>">
                    <button type="submit" class="rk-btn rk-btn-peligro"><i class="bi bi-flag-fill"></i> Finalizar temporada</button>
                </form>
            </div>

            <p class="rk-temporada-texto">
                Al finalizarla, los puntos de todos los jugadores vuelven a 0 y el <strong>último del ranking de cada categoría desciende</strong>
                una categoría. Solo cuentan quienes jugaron algún torneo en la temporada.
            </p>

            <?php if (empty($descensos)) { ?>
                <p class="rk-temporada-vacio"><i class="bi bi-info-circle"></i> Con el ranking actual no descendería nadie.</p>
            <?php } else { ?>
                <div class="rk-descensos">
                    <strong><i class="bi bi-arrow-down-circle-fill"></i> Descenderían hoy (<?php echo count($descensos); ?>)</strong>
                    <ul>
                        <?php foreach ($descensos as $d) { ?>
                            <li>
                                <span><?php echo $e($d['nombre']); ?></span>
                                <em><?php echo $e($d['de_nombre']); ?> → <?php echo $e($d['a_nombre']); ?></em>
                                <small><?php echo rkNumero($d['puntos']); ?> pts · último de <?php echo (int) $d['competidores']; ?></small>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>

            <?php if ($ultimo) { ?>
                <details class="rk-ultimo-cierre">
                    <summary>
                        Última temporada finalizada: <?php echo $e($ultimo['nombre']); ?>
                        · <?php echo $e(date('d/m/Y H:i', strtotime($ultimo['fecha_cierre']))); ?>
                        · <?php echo count($ultimo['descensos']); ?> <?php echo count($ultimo['descensos']) === 1 ? 'descenso' : 'descensos'; ?>
                    </summary>
                    <?php if (empty($ultimo['descensos'])) { ?>
                        <p>No hubo descensos.</p>
                    <?php } else { ?>
                        <ul>
                            <?php foreach ($ultimo['descensos'] as $d) { ?>
                                <li><?php echo $e($d['nombre']); ?>: <?php echo $e($d['de']); ?> → <strong><?php echo $e($d['a']); ?></strong> (<?php echo rkNumero($d['puntos_alcanzados']); ?> puntos)</li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </details>
            <?php } ?>

        </section>

    <?php } ?>

    <?php
    return ob_get_clean();

}
