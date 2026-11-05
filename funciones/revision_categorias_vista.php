<?php

require_once __DIR__ . '/clasificacion_categoria.php';

/* =========================================================
   REVISIÓN DE CATEGORÍAS (solo administrador)
   Se llama únicamente desde el bloque del administrador de
   paneles.php. La decisión se guarda en funciones/revisar_categoria.php,
   que vuelve a validar el rol en el servidor.
   ========================================================= */

function cantidadCategoriasPendientes($conexion) {

    $r = mysqli_query($conexion, "SELECT COUNT(*) AS n FROM usuario WHERE id_rol = 3 AND categoria_estado = 'Pendiente'");

    return $r ? (int) mysqli_fetch_assoc($r)['n'] : 0;

}


function renderRevisionCategorias($conexion, $csrfToken, $codigoAccion) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    $mensajes = [
        'categoria_confirmada' => ['exito', 'Categoría confirmada.'],
        'categoria_modificada' => ['exito', 'Categoría asignada y confirmada.'],
        'no_pendiente' => ['error', 'Esa clasificación ya no está pendiente.'],
        'no_existe' => ['error', 'No se encontró la clasificación.'],
        'categoria_invalida' => ['error', 'La categoría elegida no es válida.'],
        'datos_invalidos' => ['error', 'Datos inválidos.'],
        'token' => ['error', 'La sesión de seguridad venció. Intentá nuevamente.'],
        'error_bd' => ['error', 'Ocurrió un error al guardar la decisión.'],
    ];

    $categorias = mysqli_fetch_all(
        mysqli_query($conexion, "SELECT id_categoria, nombre FROM categoria ORDER BY id_categoria"),
        MYSQLI_ASSOC
    );

    $pendientes = mysqli_fetch_all(mysqli_query(
        $conexion,
        "SELECT u.id_usuario, u.nombre, u.apellido, u.email,
                cc.*, est.nombre AS categoria_estimada
         FROM usuario u
         INNER JOIN clasificacion_categoria cc ON cc.id_usuario = u.id_usuario
         INNER JOIN categoria est ON est.id_categoria = cc.id_categoria_estimada
         WHERE u.id_rol = 3 AND u.categoria_estado = 'Pendiente'
         ORDER BY cc.fecha_encuesta ASC"
    ), MYSQLI_ASSOC);

    $recientes = mysqli_fetch_all(mysqli_query(
        $conexion,
        "SELECT u.nombre, u.apellido, est.nombre AS estimada, fin.nombre AS final,
                cc.decision, cc.fecha_revision, rev.nombre AS revisor
         FROM clasificacion_categoria cc
         INNER JOIN usuario u ON u.id_usuario = cc.id_usuario
         INNER JOIN categoria est ON est.id_categoria = cc.id_categoria_estimada
         INNER JOIN categoria fin ON fin.id_categoria = cc.id_categoria_final
         LEFT JOIN usuario rev ON rev.id_usuario = cc.id_revisor
         WHERE cc.decision IS NOT NULL
         ORDER BY cc.fecha_revision DESC LIMIT 10"
    ), MYSQLI_ASSOC);

    ob_start();
    ?>

    <header class="topbar">
        <h1>Revisión de categorías</h1>
        <p>Participantes cuya clasificación por encuesta requiere la decisión de un administrador.</p>
    </header>

    <?php if ($codigoAccion !== '' && isset($mensajes[$codigoAccion])) { [$tipo, $texto] = $mensajes[$codigoAccion]; ?>
        <div class="<?php echo $tipo === 'exito' ? 'mensaje-exito' : 'mensaje-error'; ?> mensaje-error-compacto" style="margin-bottom:16px;">
            <i class="bi <?php echo $tipo === 'exito' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'; ?>"></i>
            <?php echo $e($texto); ?>
        </div>
    <?php } ?>

    <div class="rev-lista">

        <?php if (empty($pendientes)) { ?>
            <div class="rev-vacio"><i class="bi bi-check2-circle"></i> No hay clasificaciones pendientes de revisión.</div>
        <?php } ?>

        <?php foreach ($pendientes as $p) {
            // Cada clasificación se muestra con las preguntas de la versión con que se contestó.
            $version = (int) $p['version_encuesta'];
            $preguntas = preguntasEncuesta($version);
        ?>

            <article class="rev-tarjeta">

                <div class="rev-cabecera">
                    <div>
                        <h2><?php echo $e($p['nombre'] . ' ' . $p['apellido']); ?></h2>
                        <span class="rev-email"><?php echo $e($p['email']); ?> · encuesta del <?php echo $e(date('d/m/Y H:i', strtotime($p['fecha_encuesta']))); ?></span>
                    </div>
                    <div class="rev-estimada">
                        <small>Categoría estimada</small>
                        <strong><?php echo $e($p['categoria_estimada']); ?></strong>
                    </div>
                </div>

                <?php if ($p['motivos'] !== '') { ?>
                    <p class="rev-motivos"><i class="bi bi-info-circle-fill"></i> <?php echo $e($p['motivos']); ?></p>
                <?php } ?>

                <div class="rev-areas">
                    <?php foreach (areasEncuesta($version) as $clave => [$nombreArea, $maximo]) {
                        $valor = (int) $p['puntos_' . $clave];
                        $pct = $maximo > 0 ? round($valor / $maximo * 100) : 0;
                    ?>
                        <div class="rev-area">
                            <span><?php echo $e($nombreArea); ?></span>
                            <div class="rev-barra"><i style="width:<?php echo $pct; ?>%"></i></div>
                            <strong><?php echo $valor; ?>/<?php echo $maximo; ?></strong>
                        </div>
                    <?php } ?>
                    <div class="rev-area rev-total">
                        <span>Total</span>
                        <div class="rev-barra"><i style="width:<?php echo (int) $p['puntos_total']; ?>%"></i></div>
                        <strong><?php echo (int) $p['puntos_total']; ?>/100</strong>
                    </div>
                </div>

                <details class="rev-respuestas">
                    <summary>Ver respuestas (<?php echo $version === VERSION_ENCUESTA_ORIGINAL ? 'encuesta original' : 'encuesta con técnica y táctica'; ?>)</summary>
                    <ol>
                        <?php foreach ($preguntas as $n => $pregunta) {
                            $letra = $p['r' . $n];
                            $textoOpcion = $pregunta['opciones'][$letra][0] ?? '—';
                        ?>
                            <li>
                                <span><?php echo $e($pregunta['texto']); ?></span>
                                <strong><?php echo $e($textoOpcion); ?></strong>
                                <em><?php echo (int) ($pregunta['opciones'][$letra][1] ?? 0); ?> pts</em>
                            </li>
                        <?php } ?>
                    </ol>
                </details>

                <form action="funciones/revisar_categoria.php" method="POST" class="rev-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $e($csrfToken); ?>">
                    <input type="hidden" name="id_usuario" value="<?php echo (int) $p['id_usuario']; ?>">

                    <button type="submit" name="accion" value="confirmar" class="rev-btn rev-btn-confirmar">
                        <i class="bi bi-check2-circle"></i> Confirmar <?php echo $e($p['categoria_estimada']); ?>
                    </button>

                    <span class="rev-o">o asignar otra:</span>

                    <select name="id_categoria" aria-label="Categoría a asignar">
                        <?php foreach ($categorias as $c) { ?>
                            <option value="<?php echo (int) $c['id_categoria']; ?>" <?php echo (int) $c['id_categoria'] === (int) $p['id_categoria_estimada'] ? 'selected' : ''; ?>>
                                <?php echo $e($c['nombre']); ?>
                            </option>
                        <?php } ?>
                    </select>

                    <button type="submit" name="accion" value="asignar" class="rev-btn">
                        <i class="bi bi-pencil-square"></i> Asignar
                    </button>
                </form>

            </article>

        <?php } ?>

    </div>

    <?php if (!empty($recientes)) { ?>
        <section class="rev-recientes">
            <h2>Decisiones recientes</h2>
            <table>
                <thead><tr><th>Participante</th><th>Estimada</th><th>Final</th><th>Decisión</th><th>Revisor</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($recientes as $r) { ?>
                        <tr>
                            <td><?php echo $e($r['nombre'] . ' ' . $r['apellido']); ?></td>
                            <td><?php echo $e($r['estimada']); ?></td>
                            <td><strong><?php echo $e($r['final']); ?></strong></td>
                            <td><?php echo $e($r['decision']); ?></td>
                            <td><?php echo $e($r['revisor']); ?></td>
                            <td><?php echo $e(date('d/m/Y H:i', strtotime($r['fecha_revision']))); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>
    <?php } ?>

    <?php
    return ob_get_clean();

}
