<?php

/* =========================================================
   PANEL DEL ORGANIZADOR (sección "inicio")
   Todos los números salen de la base de datos y se limitan a los
   torneos de los clubes del organizador (club.id_usuario).
   ========================================================= */

const DASH_MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const DASH_DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];


function dashFilas($conexion, $sql, $tipos, array $params) {

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);

    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

}


function dashRangoFechas($inicio, $fin) {

    $a = strtotime($inicio);
    $b = strtotime($fin);
    $mes = fn($t) => substr(DASH_MESES[(int) date('n', $t) - 1], 0, 3);

    if ($inicio === $fin) {
        return date('j', $a) . ' ' . $mes($a) . ' ' . date('Y', $a);
    }

    if (date('Y-n', $a) === date('Y-n', $b)) {
        return date('j', $a) . ' - ' . date('j', $b) . ' ' . $mes($b) . ' ' . date('Y', $b);
    }

    return date('j', $a) . ' ' . $mes($a) . ' - ' . date('j', $b) . ' ' . $mes($b) . ' ' . date('Y', $b);

}


function dashFechaActividad($fecha) {

    $t = strtotime($fecha);
    $hora = date('H:i', $t);

    if (date('Y-m-d', $t) === date('Y-m-d')) {
        return 'Hoy - ' . $hora;
    }

    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Ayer - ' . $hora;
    }

    return date('j', $t) . ' ' . substr(DASH_MESES[(int) date('n', $t) - 1], 0, 3) . ' - ' . $hora;

}


/** Anillo (donut) con conic-gradient. $segmentos: [[etiqueta, valor, color], ...] */
function dashDonut(array $segmentos, $total, $unidad) {

    $partes = [];
    $acumulado = 0;

    foreach ($segmentos as [$etiqueta, $valor, $color]) {

        if ($valor <= 0 || $total <= 0) {
            continue;
        }

        $desde = $acumulado / $total * 100;
        $acumulado += $valor;
        $hasta = $acumulado / $total * 100;
        $partes[] = $color . ' ' . round($desde, 2) . '% ' . round($hasta, 2) . '%';

    }

    $fondo = $partes ? 'conic-gradient(' . implode(', ', $partes) . ')' : 'conic-gradient(#2a3b52 0 100%)';

    $html = '<div class="dash-donut-fila">
        <div class="dash-donut" style="background:' . $fondo . ';">
            <div class="dash-donut-centro"><strong>' . (int) $total . '</strong><span>' . htmlspecialchars($unidad, ENT_QUOTES, 'UTF-8') . '</span></div>
        </div>
        <ul class="dash-leyenda">';

    foreach ($segmentos as [$etiqueta, $valor, $color]) {

        $pct = $total > 0 ? round($valor / $total * 100) : 0;

        $html .= '<li>
            <span class="dash-punto" style="background:' . $color . ';"></span>
            <span class="dash-leyenda-nombre">' . htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') . '</span>
            <strong>' . (int) $valor . '</strong>
            <em>' . $pct . '%</em>
        </li>';

    }

    return $html . '</ul></div>';

}


function renderDashboardOrganizador($conexion, $idUsuario, $nombreUsuario) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    // ---- Torneos ----
    $porEstado = ['Abierto' => 0, 'Cerrado' => 0, 'En curso' => 0, 'Finalizado' => 0];

    foreach (dashFilas($conexion,
        "SELECT t.estado, COUNT(*) AS n FROM torneos t INNER JOIN club c ON c.id_club = t.id_club
         WHERE c.id_usuario = ? GROUP BY t.estado", "i", [$idUsuario]) as $f) {
        $porEstado[$f['estado']] = (int) $f['n'];
    }

    $totalTorneos = array_sum($porEstado);

    // ---- Canchas ----
    $canchas = ['Disponible' => 0, 'No disponible' => 0];

    foreach (dashFilas($conexion,
        "SELECT ca.estado, COUNT(*) AS n FROM cancha ca INNER JOIN club c ON c.id_club = ca.id_club
         WHERE c.id_usuario = ? GROUP BY ca.estado", "i", [$idUsuario]) as $f) {
        $canchas[$f['estado']] = (int) $f['n'];
    }

    $totalCanchas = array_sum($canchas);

    // ---- Inscripciones por categoría (confirmadas) ----
    $porCategoria = dashFilas($conexion,
        "SELECT cat.nombre, COUNT(*) AS n
         FROM inscripcion i
         INNER JOIN torneos t ON t.id_torneo = i.id_torneo
         INNER JOIN club c ON c.id_club = t.id_club
         INNER JOIN categoria cat ON cat.id_categoria = t.id_categoria
         WHERE c.id_usuario = ? AND i.estado = 'Confirmada'
         GROUP BY cat.id_categoria, cat.nombre ORDER BY n DESC", "i", [$idUsuario]);

    $totalInscripciones = array_sum(array_column($porCategoria, 'n'));

    // ---- Partidos jugados (individuales + de parejas) ----
    $partidosJugados = 0;

    foreach ([
        ['partido_individual', 'grupo_inscripcion', 'id_grupo_inscripcion'],
        ['partido_pareja', 'grupo_pareja', 'id_grupo_pareja'],
    ] as [$tp, $te, $ce]) {

        $r = dashFilas($conexion,
            "SELECT COUNT(*) AS n FROM $tp p
             INNER JOIN $te e ON e.$ce = p.id_entrante_1
             INNER JOIN grupo g ON g.id_grupo = e.id_grupo
             INNER JOIN torneos t ON t.id_torneo = g.id_torneo
             INNER JOIN club c ON c.id_club = t.id_club
             WHERE c.id_usuario = ? AND p.estado = 'Jugado'", "i", [$idUsuario]);

        $partidosJugados += (int) ($r[0]['n'] ?? 0);

    }

    // ---- Próximos / recientes torneos ----
    $torneos = dashFilas($conexion,
        "SELECT t.id_torneo, t.nombre, t.imagen, t.estado, t.tipo, t.fecha_inicio, t.fecha_fin,
                cat.nombre AS categoria,
                (SELECT COUNT(*) FROM pareja p WHERE p.id_torneo = t.id_torneo) AS parejas,
                (SELECT COUNT(*) FROM inscripcion i WHERE i.id_torneo = t.id_torneo AND i.estado = 'Confirmada') AS confirmadas
         FROM torneos t
         INNER JOIN club c ON c.id_club = t.id_club
         INNER JOIN categoria cat ON cat.id_categoria = t.id_categoria
         WHERE c.id_usuario = ?
         ORDER BY FIELD(t.estado, 'En curso', 'Abierto', 'Cerrado', 'Finalizado'), t.fecha_inicio DESC
         LIMIT 3", "i", [$idUsuario]);

    // ---- Actividad reciente (eventos reales, más nuevos primero) ----
    $eventos = [];

    foreach (dashFilas($conexion,
        "SELECT CONCAT(u.nombre, ' ', u.apellido) AS sub0, t.nombre AS torneo, i.fecha_inscripcion AS fecha
         FROM inscripcion i
         INNER JOIN torneos t ON t.id_torneo = i.id_torneo
         INNER JOIN club c ON c.id_club = t.id_club
         INNER JOIN usuario u ON u.id_usuario = i.id_usuario
         WHERE c.id_usuario = ? AND i.estado = 'Confirmada'
         ORDER BY i.fecha_inscripcion DESC LIMIT 5", "i", [$idUsuario]) as $f) {
        $eventos[] = ['icono' => 'bi-people-fill', 'color' => 'morado', 'titulo' => 'Nueva inscripción', 'sub' => $f['torneo'], 'fecha' => $f['fecha']];
    }

    foreach (dashFilas($conexion,
        "SELECT t.nombre AS torneo, p.fecha_resultado AS fecha, IF(p.fase = 'Grupos', g.nombre, p.fase) AS etapa
         FROM partido_pareja p
         INNER JOIN grupo_pareja e ON e.id_grupo_pareja = p.id_entrante_1
         INNER JOIN grupo g ON g.id_grupo = e.id_grupo
         INNER JOIN torneos t ON t.id_torneo = g.id_torneo
         INNER JOIN club c ON c.id_club = t.id_club
         WHERE c.id_usuario = ? AND p.estado = 'Jugado' AND p.fecha_resultado IS NOT NULL
         ORDER BY p.fecha_resultado DESC LIMIT 5", "i", [$idUsuario]) as $f) {
        $eventos[] = ['icono' => 'bi-trophy-fill', 'color' => 'verde', 'titulo' => 'Resultado actualizado', 'sub' => $f['torneo'] . ' - ' . $f['etapa'], 'fecha' => $f['fecha']];
    }

    foreach (dashFilas($conexion,
        "SELECT t.nombre AS torneo, IF(p.fase = 'Grupos', g.nombre, p.fase) AS etapa, p.fecha_resultado AS fecha
         FROM partido_individual p
         INNER JOIN grupo_inscripcion e ON e.id_grupo_inscripcion = p.id_entrante_1
         INNER JOIN grupo g ON g.id_grupo = e.id_grupo
         INNER JOIN torneos t ON t.id_torneo = g.id_torneo
         INNER JOIN club c ON c.id_club = t.id_club
         WHERE c.id_usuario = ? AND p.estado = 'Jugado' AND p.fecha_resultado IS NOT NULL
         ORDER BY p.fecha_resultado DESC LIMIT 5", "i", [$idUsuario]) as $f) {
        $eventos[] = ['icono' => 'bi-trophy-fill', 'color' => 'verde', 'titulo' => 'Resultado actualizado', 'sub' => $f['torneo'] . ' - ' . $f['etapa'], 'fecha' => $f['fecha']];
    }

    foreach (dashFilas($conexion,
        "SELECT t.nombre AS torneo, p.fecha_confirmacion AS fecha
         FROM pareja p
         INNER JOIN torneos t ON t.id_torneo = p.id_torneo
         INNER JOIN club c ON c.id_club = t.id_club
         WHERE c.id_usuario = ?
         ORDER BY p.fecha_confirmacion DESC LIMIT 5", "i", [$idUsuario]) as $f) {
        $eventos[] = ['icono' => 'bi-people-fill', 'color' => 'naranja', 'titulo' => 'Nueva pareja asignada', 'sub' => $f['torneo'], 'fecha' => $f['fecha']];
    }

    usort($eventos, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    $eventos = array_slice($eventos, 0, 5);

    $hoy = DASH_DIAS[(int) date('w')] . ', ' . date('j') . ' de ' . DASH_MESES[(int) date('n') - 1] . ' de ' . date('Y');

    ob_start();
    ?>

    <div class="dash">

        <header class="dash-cabecera">
            <div>
                <h1>Panel del Organizador</h1>
                <p>Bienvenido, <strong><?php echo $e($nombreUsuario); ?></strong> 👋</p>
            </div>
            <div class="dash-fecha"><i class="bi bi-calendar3"></i> <?php echo $e(ucfirst($hoy)); ?></div>
        </header>

        <section class="dash-kpis">

            <article class="dash-kpi">
                <span class="dash-kpi-icono azul"><i class="bi bi-trophy-fill"></i></span>
                <div>
                    <h3>Mis torneos</h3>
                    <strong><?php echo $totalTorneos; ?></strong>
                    <small><?php echo $porEstado['En curso']; ?> en curso · <?php echo $porEstado['Finalizado']; ?> finalizados</small>
                </div>
            </article>

            <article class="dash-kpi">
                <span class="dash-kpi-icono verde"><i class="bi bi-grid-3x3-gap-fill"></i></span>
                <div>
                    <h3>Mis canchas</h3>
                    <strong><?php echo $totalCanchas; ?></strong>
                    <small><?php echo $canchas['Disponible']; ?> disponibles · <?php echo $canchas['No disponible']; ?> no disponibles</small>
                </div>
            </article>

            <article class="dash-kpi">
                <span class="dash-kpi-icono morado"><i class="bi bi-people-fill"></i></span>
                <div>
                    <h3>Inscripciones</h3>
                    <strong><?php echo $totalInscripciones; ?></strong>
                    <small>confirmadas en mis torneos</small>
                </div>
            </article>

            <article class="dash-kpi">
                <span class="dash-kpi-icono rojo"><i class="bi bi-calendar-check-fill"></i></span>
                <div>
                    <h3>Partidos jugados</h3>
                    <strong><?php echo $partidosJugados; ?></strong>
                    <small>en todos mis torneos</small>
                </div>
            </article>

        </section>

        <section class="dash-graficos">

            <article class="dash-panel">
                <h2><i class="bi bi-trophy-fill"></i> Estado de mis torneos</h2>
                <?php echo dashDonut([
                    ['Abiertos', $porEstado['Abierto'], '#8b95a7'],
                    ['En curso', $porEstado['En curso'], '#22c58b'],
                    ['Cerrados', $porEstado['Cerrado'], '#f5a623'],
                    ['Finalizados', $porEstado['Finalizado'], '#2f6bff'],
                ], $totalTorneos, 'torneos'); ?>
            </article>

            <article class="dash-panel">
                <h2><i class="bi bi-grid-3x3-gap-fill"></i> Uso de mis canchas</h2>
                <?php echo dashDonut([
                    ['Disponibles', $canchas['Disponible'], '#22c58b'],
                    ['No disponibles', $canchas['No disponible'], '#f5a623'],
                ], $totalCanchas, 'canchas'); ?>
            </article>

            <article class="dash-panel">
                <h2><i class="bi bi-people-fill"></i> Inscripciones por categoría</h2>
                <?php
                $coloresCat = ['#2f6bff', '#7c4dff', '#f5a623', '#22c58b', '#ef4466'];
                $segCat = [];
                foreach ($porCategoria as $i => $c) {
                    $segCat[] = [$c['nombre'], (int) $c['n'], $coloresCat[$i % count($coloresCat)]];
                }
                echo dashDonut($segCat, $totalInscripciones, 'inscripciones');
                ?>
            </article>

        </section>

        <section class="dash-inferior">

            <article class="dash-panel">

                <div class="dash-panel-cab">
                    <h2><i class="bi bi-calendar3"></i> Mis torneos</h2>
                    <a href="paneles.php?seccion=torneos" class="dash-boton">Ver todos</a>
                </div>

                <?php if (empty($torneos)) { ?>
                    <p class="dash-vacio">Todavía no creaste ningún torneo.</p>
                <?php } ?>

                <ul class="dash-torneos">
                    <?php foreach ($torneos as $t) {

                        $esIndividual = ($t['tipo'] === 'Individual');
                        $cantidad = $esIndividual ? (int) $t['confirmadas'] : (int) $t['parejas'];
                        $unidad = $esIndividual ? ($cantidad === 1 ? 'participante' : 'participantes') : ($cantidad === 1 ? 'pareja' : 'parejas');
                        $claseEstado = ['En curso' => 'verde', 'Abierto' => 'azul', 'Cerrado' => 'naranja', 'Finalizado' => 'gris'][$t['estado']] ?? 'gris';
                    ?>
                        <li>
                            <a href="ver_torneo.php?id=<?php echo (int) $t['id_torneo']; ?>">
                                <?php if (!empty($t['imagen'])) { ?>
                                    <img src="<?php echo $e($t['imagen']); ?>" alt="" class="dash-torneo-img">
                                <?php } else { ?>
                                    <span class="dash-torneo-img dash-torneo-sin"><i class="bi bi-trophy"></i></span>
                                <?php } ?>
                                <div class="dash-torneo-info">
                                    <span class="dash-estado <?php echo $claseEstado; ?>"><?php echo $e($t['estado']); ?></span>
                                    <strong><?php echo $e($t['nombre']); ?></strong>
                                    <div class="dash-torneo-meta">
                                        <span><i class="bi bi-calendar3"></i> <?php echo $e(dashRangoFechas($t['fecha_inicio'], $t['fecha_fin'])); ?></span>
                                        <span><i class="bi bi-layers-fill"></i> <?php echo $e($t['categoria']); ?></span>
                                        <span><i class="bi bi-people-fill"></i> <?php echo $cantidad . ' ' . $unidad; ?></span>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right dash-flecha"></i>
                            </a>
                        </li>
                    <?php } ?>
                </ul>

            </article>

            <article class="dash-panel">

                <div class="dash-panel-cab">
                    <h2><i class="bi bi-bell-fill"></i> Actividad reciente</h2>
                </div>

                <?php if (empty($eventos)) { ?>
                    <p class="dash-vacio">Todavía no hay actividad en tus torneos.</p>
                <?php } ?>

                <ul class="dash-actividad">
                    <?php foreach ($eventos as $ev) { ?>
                        <li>
                            <span class="dash-kpi-icono chico <?php echo $ev['color']; ?>"><i class="bi <?php echo $ev['icono']; ?>"></i></span>
                            <div>
                                <strong><?php echo $e($ev['titulo']); ?></strong>
                                <small><?php echo $e($ev['sub']); ?></small>
                            </div>
                            <time><?php echo $e(dashFechaActividad($ev['fecha'])); ?></time>
                        </li>
                    <?php } ?>
                </ul>

            </article>

        </section>

    </div>

    <?php
    return ob_get_clean();

}
