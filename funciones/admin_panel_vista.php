<?php

require_once __DIR__ . '/admin_datos.php';
require_once __DIR__ . '/hora.php';
require_once __DIR__ . '/actividad.php';

/* =========================================================
   PANEL PRINCIPAL DEL ADMINISTRADOR (solo lectura)

   - Estadísticas: todas calculadas con consultas preparadas a la base en cada carga (nada escrito a mano).
   - Historial de actividades: lee `historial_actividad`, que solo contiene operaciones reales
     registradas desde su implementación (funciones/actividad.php).
   Solo el administrador llega acá: paneles.php confirma el rol contra la base antes de mostrar nada.
   ========================================================= */

const ADMIN_HISTORIAL_PANEL = 8;      // actividades en el Panel
const ADMIN_HISTORIAL_PAGINA = 20;    // actividades por página en «Ver todo el historial»

const AP_DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const AP_MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];


/**
 * «Hoy» (Y-m-d) según el servidor MySQL, que es la misma hora con la que se guardan las actividades (NOW()).
 * PHP trabaja en UTC, así que de noche ya marcaría el día siguiente.
 */
function adminPanelHoy() {

    return fechaHoyServidor();

}


/** Fecha de hoy en español: «miércoles, 11 de noviembre de 2026». */
function adminPanelFechaHoy() {

    $t = strtotime(adminPanelHoy());

    return AP_DIAS[(int) date('w', $t)] . ', ' . (int) date('j', $t) . ' de ' . AP_MESES[(int) date('n', $t)] . ' de ' . date('Y', $t);

}


/** Título del día de un grupo del historial: «Hoy», «Ayer» o «11 de noviembre de 2026». */
function adminPanelTituloDia($fechaYmd) {

    if ($fechaYmd === adminPanelHoy()) {
        return 'Hoy';
    }

    if ($fechaYmd === date('Y-m-d', strtotime(adminPanelHoy() . ' -1 day'))) {
        return 'Ayer';
    }

    $t = strtotime($fechaYmd);

    return (int) date('j', $t) . ' de ' . AP_MESES[(int) date('n', $t)] . ' de ' . date('Y', $t);

}


/** Las ocho cifras del Panel, calculadas en el momento. */
function adminPanelEstadisticas($conexion) {

    $n = function ($sql) use ($conexion) {
        return (int) (adminConsulta($conexion, $sql)[0]['n'] ?? 0);
    };

    $torneosPorEstado = [];
    foreach (adminConsulta($conexion, "SELECT estado, COUNT(*) AS n FROM torneos GROUP BY estado") as $f) {
        $torneosPorEstado[$f['estado']] = (int) $f['n'];
    }

    $usuariosPorRol = [];
    foreach (adminConsulta($conexion, "SELECT id_rol, COUNT(*) AS n FROM usuario GROUP BY id_rol") as $f) {
        $usuariosPorRol[(int) $f['id_rol']] = (int) $f['n'];
    }

    $partidosIndividuales = $n("SELECT COUNT(*) AS n FROM partido_individual WHERE estado = 'Jugado'");
    $partidosPareja = $n("SELECT COUNT(*) AS n FROM partido_pareja WHERE estado = 'Jugado'");
    $solicitudesOrganizador = $n("SELECT COUNT(*) AS n FROM solicitud_organizador WHERE estado_solicitud = 'Pendiente'");

    return [
        'torneos' => $n("SELECT COUNT(*) AS n FROM torneos"),
        'torneos_abiertos' => $torneosPorEstado['Abierto'] ?? 0,
        'torneos_cerrados' => $torneosPorEstado['Cerrado'] ?? 0,
        'torneos_en_curso' => $torneosPorEstado['En curso'] ?? 0,
        'torneos_finalizados' => $torneosPorEstado['Finalizado'] ?? 0,
        'clubes' => $n("SELECT COUNT(*) AS n FROM club"),
        'clubes_con_torneos' => $n("SELECT COUNT(DISTINCT id_club) AS n FROM torneos"),
        'usuarios' => $n("SELECT COUNT(*) AS n FROM usuario"),
        'participantes' => $usuariosPorRol[3] ?? 0,
        'organizadores' => $usuariosPorRol[2] ?? 0,
        'inscritos' => $n("SELECT COUNT(DISTINCT id_usuario) AS n FROM inscripcion WHERE estado = 'Confirmada'"),
        'inscripciones' => $n("SELECT COUNT(*) AS n FROM inscripcion WHERE estado = 'Confirmada'"),
        'partidos' => $partidosIndividuales + $partidosPareja,
        'solicitudes_organizador' => $solicitudesOrganizador,
        'solicitudes' => $solicitudesOrganizador,
    ];

}


/* ---------------------------------------------------------
   HISTORIAL
   --------------------------------------------------------- */

/** Filtros del historial tomados de la URL; todo lo inválido se descarta. */
function adminHistorialFiltros(array $get) {

    // Solo valores simples: un parámetro tipo arreglo (?tipo[]=x) se trata como vacío.
    $texto = fn($clave) => is_scalar($get[$clave] ?? null) ? (string) $get[$clave] : '';
    $entero = fn($clave, $defecto) => is_scalar($get[$clave] ?? null) ? (int) filter_var($get[$clave], FILTER_VALIDATE_INT) : $defecto;

    $tipo = $texto('tipo');
    $desde = $texto('desde');
    $hasta = $texto('hasta');
    $fechaValida = fn($f) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) === 1 && checkdate((int) substr($f, 5, 2), (int) substr($f, 8, 2), (int) substr($f, 0, 4));

    return [
        'tipo' => isset(ACTIVIDAD_TIPOS[$tipo]) ? $tipo : '',
        'usuario' => mb_substr(trim($texto('usuario')), 0, 60),
        'club' => max(0, $entero('club', 0)),
        'desde' => $fechaValida($desde) ? $desde : '',
        'hasta' => $fechaValida($hasta) ? $hasta : '',
        'pagina' => max(1, $entero('pagina', 1)),
    ];

}


/**
 * Actividades (más recientes primero) según los filtros.
 * @return array ['filas' => [...], 'total' => N, 'pagina' => P, 'paginas' => X]
 */
function adminHistorialListar($conexion, array $f, $porPagina) {

    $cond = [];
    $tipos = '';
    $params = [];

    if ($f['tipo'] !== '') {
        $cond[] = "h.tipo = ?";
        $tipos .= 's';
        $params[] = $f['tipo'];
    }

    if ($f['usuario'] !== '') {
        $cond[] = "h.nombre_usuario LIKE ?";
        $tipos .= 's';
        $params[] = adminLike($f['usuario']);
    }

    if ($f['club'] > 0) {
        $cond[] = "h.id_club = ?";
        $tipos .= 'i';
        $params[] = $f['club'];
    }

    if ($f['desde'] !== '') {
        $cond[] = "h.fecha >= ?";
        $tipos .= 's';
        $params[] = $f['desde'] . ' 00:00:00';
    }

    if ($f['hasta'] !== '') {
        $cond[] = "h.fecha <= ?";
        $tipos .= 's';
        $params[] = $f['hasta'] . ' 23:59:59';
    }

    $where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

    $total = (int) adminConsulta($conexion, "SELECT COUNT(*) AS n FROM historial_actividad h $where", $tipos, $params)[0]['n'];

    [$pagina, $paginas] = adminPagina($f['pagina'], $total, $porPagina);
    $desdeFila = ($pagina - 1) * $porPagina;

    $filas = adminConsulta(
        $conexion,
        "SELECT h.id_actividad, h.fecha, h.id_usuario, h.nombre_usuario, h.id_rol, h.rol, h.tipo, h.descripcion,
                h.id_club, c.nombre AS club, h.id_torneo, t.nombre AS torneo
         FROM historial_actividad h
         LEFT JOIN club c ON c.id_club = h.id_club
         LEFT JOIN torneos t ON t.id_torneo = h.id_torneo
         $where
         ORDER BY h.fecha DESC, h.id_actividad DESC
         LIMIT " . (int) $porPagina . " OFFSET " . (int) $desdeFila,
        $tipos,
        $params
    );

    return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];

}


/** Lista de actividades agrupada por día (HTML). */
function adminHistorialHtml(array $filas) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
    $porDia = [];

    foreach ($filas as $f) {
        $porDia[substr($f['fecha'], 0, 10)][] = $f;
    }

    ob_start();

    foreach ($porDia as $dia => $eventos) { ?>

        <div class="ap-dia"><?php echo $e(adminPanelTituloDia($dia)); ?></div>

        <div class="ap-eventos">
            <?php foreach ($eventos as $ev) {
                [$etiqueta, $icono, $color] = ACTIVIDAD_TIPOS[$ev['tipo']] ?? [$ev['tipo'], 'bi-circle-fill', 'azul'];
                $esSistema = ($ev['id_rol'] === null);
            ?>
                <div class="ap-evento">
                    <div class="ap-ev-icono <?php echo $color; ?>"><i class="bi <?php echo $e($icono); ?>"></i></div>
                    <div class="ap-ev-texto">
                        <p><?php if (!$esSistema) { ?><b><?php echo $e($ev['nombre_usuario']); ?></b> <?php } ?><?php echo $e($ev['descripcion']); ?></p>
                        <div class="ap-ev-meta">
                            <span class="ap-etiqueta <?php echo $color; ?>"><?php echo $e($etiqueta); ?></span>
                            <?php if ($ev['club'] !== null) { ?><span><i class="bi bi-buildings-fill"></i><?php echo $e($ev['club']); ?></span><?php } ?>
                            <?php if ($ev['torneo'] !== null) { ?><span><i class="bi bi-trophy"></i><?php echo $e($ev['torneo']); ?></span><?php } ?>
                        </div>
                    </div>
                    <div class="ap-ev-hora"><?php echo $e(date('H:i', strtotime($ev['fecha']))); ?> hs<small><?php echo $e($ev['rol']); ?></small></div>
                </div>
            <?php } ?>
        </div>

    <?php }

    return ob_get_clean();

}


/* ---------------------------------------------------------
   PANTALLAS
   --------------------------------------------------------- */

/** Panel principal: estadísticas, últimas actividades y accesos rápidos. */
function renderAdminPanel($conexion) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
    $s = adminPanelEstadisticas($conexion);
    $num = fn($v) => number_format((int) $v, 0, ',', '.');
    $h = adminHistorialListar($conexion, adminHistorialFiltros([]), ADMIN_HISTORIAL_PANEL);

    $tarjetas = [
        ['bi-trophy-fill', '', $s['torneos'], 'Total de torneos', 'En todos los clubes', null],
        ['bi-buildings-fill', '', $s['clubes'], 'Clubes registrados', $s['clubes_con_torneos'] . ($s['clubes_con_torneos'] === 1 ? ' club con torneos' : ' clubes con torneos'), null],
        ['bi-people-fill', '', $s['usuarios'], 'Usuarios registrados', $num($s['participantes']) . ' participantes · ' . $num($s['organizadores']) . ($s['organizadores'] === 1 ? ' organizador' : ' organizadores'), null],
        ['bi-person-check-fill', '', $s['inscritos'], 'Participantes inscritos', $num($s['inscripciones']) . ' inscripciones confirmadas', null],
        ['bi-activity', '', $s['partidos'], 'Partidos disputados', 'Con resultado cargado', null],
        ['bi-play-circle-fill', 'verde', $s['torneos_en_curso'], 'Torneos en curso', $num($s['torneos_abiertos']) . ($s['torneos_abiertos'] === 1 ? ' torneo abierto' : ' torneos abiertos') . ' para inscribirse', null],
        ['bi-flag-fill', 'gris', $s['torneos_finalizados'], 'Torneos finalizados', 'Con todos sus resultados', null],
        ['bi-hourglass-split', $s['solicitudes'] > 0 ? 'ambar' : 'gris', $s['solicitudes'], 'Solicitudes pendientes',
            $s['solicitudes'] > 0
                ? $num($s['solicitudes_organizador']) . ($s['solicitudes_organizador'] === 1 ? ' solicitud de organizador' : ' solicitudes de organizador')
                : 'Nada por revisar',
            $s['solicitudes'] > 0 ? 'paneles.php?seccion=solicitudes' : null],
    ];

    ob_start();
    ?>

    <div class="ap">

        <header class="ap-encabezado">
            <div>
                <h1>Panel de administración</h1>
                <p>Resumen general de The Bull Padel Club</p>
            </div>
            <span class="ap-fecha"><i class="bi bi-calendar3"></i> <?php echo $e(adminPanelFechaHoy()); ?></span>
        </header>

        <div class="ap-titulo"><h2>Estadísticas generales</h2><small>Calculadas al momento</small></div>

        <section class="ap-stats" aria-label="Estadísticas">
            <?php foreach ($tarjetas as [$icono, $color, $valor, $titulo, $detalle, $enlace]) {
                $abre = $enlace ? '<a href="' . $e($enlace) . '" class="ap-stat' . ($color === 'ambar' ? ' alerta' : '') . '">' : '<article class="ap-stat">';
                $cierra = $enlace ? '</a>' : '</article>';
                echo $abre; ?>
                    <div class="ap-icono <?php echo $color; ?>"><i class="bi <?php echo $icono; ?>"></i></div>
                    <div>
                        <strong><?php echo $num($valor); ?></strong>
                        <span><?php echo $e($titulo); ?></span>
                        <small><?php echo $e($detalle); ?></small>
                    </div>
                <?php echo $cierra;
            } ?>
        </section>

        <div class="ap-abajo">

            <section class="ap-tarjeta ap-historial" aria-label="Historial de actividades">

                <div class="ap-titulo"><h2>Historial de actividades</h2><small>Operaciones registradas en la plataforma</small></div>

                <?php if (empty($h['filas'])) { ?>

                    <div class="ap-vacio">
                        <i class="bi bi-clock-history"></i>
                        <p>Todavía no hay actividades registradas.</p>
                        <small>A partir de ahora se irán registrando acá las operaciones importantes: torneos, inscripciones, resultados, cambios de categoría y más.</small>
                    </div>

                <?php } else { echo adminHistorialHtml($h['filas']); } ?>

                <div class="ap-historial-pie">
                    <a href="paneles.php?seccion=historial" class="ap-btn"><i class="bi bi-clock-history"></i> Ver todo el historial</a>
                </div>

            </section>

            <section aria-label="Accesos rápidos">

                <div class="ap-titulo"><h2>Accesos rápidos</h2></div>

                <div class="ap-accesos">
                    <a href="paneles.php?seccion=clubes" class="ap-acceso">
                        <div class="ap-icono"><i class="bi bi-buildings-fill"></i></div>
                        <div><h3>Gestionar clubes</h3><p>Datos, canchas y organizadores de cada club</p></div>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="paneles.php?seccion=usuarios" class="ap-acceso">
                        <div class="ap-icono"><i class="bi bi-people-fill"></i></div>
                        <div><h3>Gestionar usuarios</h3><p>Buscar, editar datos y ver inscripciones</p></div>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="paneles.php?seccion=torneos" class="ap-acceso">
                        <div class="ap-icono"><i class="bi bi-trophy-fill"></i></div>
                        <div><h3>Consultar torneos</h3><p>Estados, inscriptos, grupos y resultados</p></div>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

            </section>

        </div>

    </div>

    <?php
    return ob_get_clean();

}


/** «Ver todo el historial»: lista completa con filtros y paginación. */
function renderAdminHistorial($conexion) {

    $e = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
    $f = adminHistorialFiltros($_GET);
    $r = adminHistorialListar($conexion, $f, ADMIN_HISTORIAL_PAGINA);
    $clubes = adminConsulta($conexion, "SELECT id_club, nombre FROM club ORDER BY nombre ASC");
    $hayFiltros = ($f['tipo'] !== '' || $f['usuario'] !== '' || $f['club'] > 0 || $f['desde'] !== '' || $f['hasta'] !== '');

    $url = function (array $cambios = []) use ($f) {
        $d = array_merge(['seccion' => 'historial', 'tipo' => $f['tipo'], 'usuario' => $f['usuario'], 'club' => $f['club'] ?: '', 'desde' => $f['desde'], 'hasta' => $f['hasta'], 'pagina' => $f['pagina'] > 1 ? $f['pagina'] : ''], $cambios);
        return 'paneles.php?' . http_build_query(array_filter($d, fn($v) => $v !== '' && $v !== null && $v !== 0));
    };

    ob_start();
    ?>

    <div class="ap">

        <header class="ap-encabezado">
            <div>
                <h1>Historial de actividades</h1>
                <p>Todas las operaciones registradas en la plataforma, de la más reciente a la más antigua</p>
            </div>
            <a href="paneles.php?seccion=panel" class="ap-btn"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
        </header>

        <form method="get" action="paneles.php" class="ap-filtros" role="search">

            <input type="hidden" name="seccion" value="historial">

            <label class="ap-campo">
                <span>Tipo de actividad</span>
                <select name="tipo">
                    <option value="">Todas</option>
                    <?php foreach (ACTIVIDAD_TIPOS as $codigo => $info) { ?>
                        <option value="<?php echo $e($codigo); ?>" <?php echo $f['tipo'] === $codigo ? 'selected' : ''; ?>><?php echo $e($info[0]); ?></option>
                    <?php } ?>
                </select>
            </label>

            <label class="ap-campo">
                <span>Usuario</span>
                <input type="search" name="usuario" value="<?php echo $e($f['usuario']); ?>" maxlength="60" placeholder="Nombre de quien actuó">
            </label>

            <label class="ap-campo">
                <span>Club</span>
                <select name="club">
                    <option value="0">Todos los clubes</option>
                    <?php foreach ($clubes as $c) { ?>
                        <option value="<?php echo (int) $c['id_club']; ?>" <?php echo $f['club'] === (int) $c['id_club'] ? 'selected' : ''; ?>><?php echo $e($c['nombre']); ?></option>
                    <?php } ?>
                </select>
            </label>

            <label class="ap-campo">
                <span>Desde</span>
                <input type="date" name="desde" value="<?php echo $e($f['desde']); ?>">
            </label>

            <label class="ap-campo">
                <span>Hasta</span>
                <input type="date" name="hasta" value="<?php echo $e($f['hasta']); ?>">
            </label>

            <div class="ap-filtros-botones">
                <button type="submit" class="ap-btn ap-btn-primario"><i class="bi bi-funnel-fill"></i> Filtrar</button>
                <?php if ($hayFiltros) { ?><a href="paneles.php?seccion=historial" class="ap-btn">Limpiar</a><?php } ?>
            </div>

        </form>

        <p class="ap-contador" aria-live="polite"><?php echo (int) $r['total']; ?> <?php echo $r['total'] === 1 ? 'actividad' : 'actividades'; ?></p>

        <section class="ap-tarjeta ap-historial">

            <?php if (empty($r['filas'])) { ?>

                <div class="ap-vacio">
                    <i class="bi bi-clock-history"></i>
                    <p><?php echo $hayFiltros ? 'No hay actividades con esos filtros.' : 'Todavía no hay actividades registradas.'; ?></p>
                    <?php if ($hayFiltros) { ?><small>Probá con otros filtros o limpialos.</small><?php } ?>
                </div>

            <?php } else { echo adminHistorialHtml($r['filas']); } ?>

        </section>

        <?php if ($r['paginas'] > 1) { ?>
            <nav class="ap-paginacion" aria-label="Páginas del historial">
                <?php if ($r['pagina'] > 1) { ?><a href="<?php echo $e($url(['pagina' => $r['pagina'] - 1])); ?>" class="ap-btn">‹ Anterior</a><?php } ?>
                <span>Página <?php echo (int) $r['pagina']; ?> de <?php echo (int) $r['paginas']; ?></span>
                <?php if ($r['pagina'] < $r['paginas']) { ?><a href="<?php echo $e($url(['pagina' => $r['pagina'] + 1])); ?>" class="ap-btn">Siguiente ›</a><?php } ?>
            </nav>
        <?php } ?>

    </div>

    <?php
    return ob_get_clean();

}
