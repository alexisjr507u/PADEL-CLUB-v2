<?php

require_once __DIR__ . '/admin_datos.php';
require_once __DIR__ . '/ranking_vista.php';   // rkNumero()

/* =========================================================
   VISTAS (solo HTML) DE LAS SECCIONES «CLUBES» Y «USUARIOS» DEL ADMINISTRADOR
   Las muestra paneles.php únicamente al administrador; los datos salen de admin_datos.php.
   ========================================================= */

function agE($t) {
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

function agFecha($f, $conHora = false) {

    if (empty($f) || $f === '0000-00-00') {
        return '—';
    }

    return date($conHora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($f));

}

function agEdad($fechaNacimiento) {

    $f = DateTime::createFromFormat('!Y-m-d', (string) $fechaNacimiento);

    return $f ? (int) $f->diff(new DateTime('today'))->y : null;

}

function agBadgeEstadoTorneo($estado) {

    $clases = ['Abierto' => 'ag-badge-ok', 'Cerrado' => 'ag-badge-aviso', 'En curso' => 'ag-badge-info', 'Finalizado' => 'ag-badge-neutro'];

    return '<span class="ag-badge ' . ($clases[$estado] ?? 'ag-badge-neutro') . '">' . agE($estado) . '</span>';

}

function agBadgeRol($idRol, $nombre) {

    $clases = [1 => 'ag-badge-rojo', 2 => 'ag-badge-info', 3 => 'ag-badge-ok'];

    return '<span class="ag-badge ' . ($clases[(int) $idRol] ?? 'ag-badge-neutro') . '">' . agE($nombre) . '</span>';

}

/** Paginación que conserva los filtros de la URL. */
function agPaginacion($pagina, $paginas, array $parametros) {

    if ($paginas <= 1) {
        return '';
    }

    $enlace = function ($n) use ($parametros) {
        return 'paneles.php?' . http_build_query(array_merge($parametros, ['pagina' => $n]));
    };

    $desde = max(1, $pagina - 2);
    $hasta = min($paginas, $pagina + 2);

    ob_start();
    ?>
    <nav class="ag-paginacion" aria-label="Paginación">
        <?php if ($pagina > 1) { ?>
            <a href="<?php echo agE($enlace($pagina - 1)); ?>" class="ag-pag-btn" aria-label="Página anterior"><i class="bi bi-chevron-left"></i></a>
        <?php } ?>
        <?php if ($desde > 1) { ?>
            <a href="<?php echo agE($enlace(1)); ?>" class="ag-pag-btn">1</a><?php if ($desde > 2) { ?><span class="ag-pag-puntos">…</span><?php } ?>
        <?php } ?>
        <?php for ($n = $desde; $n <= $hasta; $n++) { ?>
            <a href="<?php echo agE($enlace($n)); ?>" class="ag-pag-btn <?php echo $n === $pagina ? 'activo' : ''; ?>" <?php echo $n === $pagina ? 'aria-current="page"' : ''; ?>><?php echo $n; ?></a>
        <?php } ?>
        <?php if ($hasta < $paginas) { ?>
            <?php if ($hasta < $paginas - 1) { ?><span class="ag-pag-puntos">…</span><?php } ?><a href="<?php echo agE($enlace($paginas)); ?>" class="ag-pag-btn"><?php echo $paginas; ?></a>
        <?php } ?>
        <?php if ($pagina < $paginas) { ?>
            <a href="<?php echo agE($enlace($pagina + 1)); ?>" class="ag-pag-btn" aria-label="Página siguiente"><i class="bi bi-chevron-right"></i></a>
        <?php } ?>
    </nav>
    <?php
    return ob_get_clean();

}


/* =========================================================
   CLUBES
   ========================================================= */

function renderAdminClubes($conexion) {

    $idClub = filter_input(INPUT_GET, 'club', FILTER_VALIDATE_INT);

    if ($idClub) {
        return renderAdminClubDetalle($conexion, (int) $idClub);
    }

    $busqueda = trim((string) ($_GET['q'] ?? ''));
    $busqueda = function_exists('mb_substr') ? mb_substr($busqueda, 0, 100, 'UTF-8') : substr($busqueda, 0, 100);
    $datos = adminListarClubes($conexion, $busqueda, (int) ($_GET['pagina'] ?? 1));

    ob_start();
    ?>
    <div class="ag">

        <header class="topbar">
            <h1>Clubes</h1>
            <p>Todos los clubes registrados en la plataforma, con su organizador y su actividad.</p>
        </header>

        <form method="GET" action="paneles.php" class="ag-filtros" role="search">
            <input type="hidden" name="seccion" value="clubes">
            <label class="ag-buscador">
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="<?php echo agE($busqueda); ?>" placeholder="Buscar por club, organizador o correo..." maxlength="100" aria-label="Buscar club">
            </label>
            <button type="submit" class="ag-btn ag-btn-primario"><i class="bi bi-funnel-fill"></i> Buscar</button>
            <?php if ($busqueda !== '') { ?>
                <a href="paneles.php?seccion=clubes" class="ag-btn">Limpiar</a>
            <?php } ?>
        </form>

        <p class="ag-resumen">
            <?php echo (int) $datos['total']; ?> <?php echo $datos['total'] === 1 ? 'club' : 'clubes'; ?>
            <?php echo $busqueda !== '' ? 'para «' . agE($busqueda) . '»' : ''; ?>
            · Página <?php echo (int) $datos['pagina']; ?> de <?php echo (int) $datos['paginas']; ?>
        </p>

        <?php if (empty($datos['filas'])) { ?>

            <div class="ag-vacio">
                <i class="bi bi-buildings"></i>
                <p>No hay clubes que coincidan con la búsqueda.</p>
            </div>

        <?php } else { ?>

            <div class="ag-tabla-envoltorio">
                <table class="ag-tabla">
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Organizador</th>
                            <th>Contacto</th>
                            <th class="ag-num">Torneos</th>
                            <th class="ag-num">Inscripciones</th>
                            <th class="ag-num">Canchas</th>
                            <th>Alta</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos['filas'] as $c) { ?>
                            <tr>
                                <td data-label="Club">
                                    <strong><?php echo agE($c['nombre']); ?></strong>
                                    <span class="ag-sub"><?php echo agE($c['direccion']); ?></span>
                                </td>
                                <td data-label="Organizador">
                                    <?php echo agE(trim($c['org_nombre'] . ' ' . $c['org_apellido'])); ?>
                                </td>
                                <td data-label="Contacto">
                                    <span class="ag-sub ag-sub-claro"><?php echo agE($c['org_email']); ?></span>
                                    <span class="ag-sub"><?php echo agE($c['telefono']); ?></span>
                                </td>
                                <td class="ag-num" data-label="Torneos"><?php echo (int) $c['torneos']; ?></td>
                                <td class="ag-num" data-label="Inscripciones">
                                    <?php echo (int) $c['inscripciones']; ?>
                                    <span class="ag-sub"><?php echo (int) $c['participantes']; ?> participantes</span>
                                </td>
                                <td class="ag-num" data-label="Canchas">
                                    <?php echo (int) $c['canchas']; ?>
                                    <?php if ((int) $c['canchas'] !== (int) $c['cantidad_canchas']) { ?>
                                        <span class="ag-sub" title="Cantidad declarada al registrar el club">declaradas: <?php echo (int) $c['cantidad_canchas']; ?></span>
                                    <?php } ?>
                                </td>
                                <td data-label="Alta"><?php echo agFecha($c['alta']); ?></td>
                                <td class="ag-acciones">
                                    <a href="paneles.php?seccion=clubes&amp;club=<?php echo (int) $c['id_club']; ?>" class="ag-btn ag-btn-primario">
                                        <i class="bi bi-eye-fill"></i> Ver detalles
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php echo agPaginacion($datos['pagina'], $datos['paginas'], array_filter(['seccion' => 'clubes', 'q' => $busqueda])); ?>

        <?php } ?>

    </div>
    <?php
    return ob_get_clean();

}


function renderAdminClubDetalle($conexion, $idClub) {

    $d = adminDetalleClub($conexion, $idClub);

    if ($d === null) {
        return '<div class="ag"><header class="topbar"><h1>Clubes</h1></header>'
            . '<div class="ag-vacio"><i class="bi bi-buildings"></i><p>Ese club no existe.</p></div>'
            . '<a href="paneles.php?seccion=clubes" class="ag-btn"><i class="bi bi-arrow-left"></i> Volver a Clubes</a></div>';
    }

    $c = $d['club'];
    $a = $d['actividad'];

    ob_start();
    ?>
    <div class="ag">

        <a href="paneles.php?seccion=clubes" class="ag-volver"><i class="bi bi-arrow-left"></i> Volver a Clubes</a>

        <header class="topbar">
            <h1><?php echo agE($c['nombre']); ?></h1>
            <p><?php echo agE($c['direccion']); ?></p>
        </header>

        <section class="cards ag-cards">
            <div class="card"><h3>Torneos</h3><span><?php echo (int) $a['torneos']; ?></span><small><?php echo (int) $a['activos']; ?> activos · <?php echo (int) $a['finalizados']; ?> finalizados</small></div>
            <div class="card"><h3>Inscripciones</h3><span><?php echo (int) $a['inscripciones']; ?></span><small><?php echo (int) $a['participantes']; ?> participantes distintos</small></div>
            <div class="card"><h3>Partidos jugados</h3><span><?php echo (int) $a['partidos_jugados']; ?></span><small>en todos sus torneos</small></div>
            <div class="card"><h3>Canchas</h3><span><?php echo count($d['canchas']); ?></span><small>registradas (declaradas: <?php echo (int) $c['cantidad_canchas']; ?>)</small></div>
        </section>

        <div class="ag-dos-columnas">

            <section class="ag-panel">
                <h2><i class="bi bi-building-fill"></i> Información del club</h2>
                <dl class="ag-datos">
                    <div><dt>Nombre</dt><dd><?php echo agE($c['nombre']); ?></dd></div>
                    <div><dt>Dirección</dt><dd><?php echo agE($c['direccion']); ?></dd></div>
                    <div><dt>Teléfono</dt><dd><?php echo agE($c['telefono']); ?></dd></div>
                    <div><dt>Horario</dt><dd><?php echo agE($c['horario'] ?: trim(($c['horario_manana'] ?? '') . ' ' . ($c['horario_tarde'] ?? '')) ?: '—'); ?></dd></div>
                    <div><dt>Alta del club</dt><dd><?php echo $c['alta'] ? agFecha($c['alta'], true) . ' <span class="ag-sub">(solicitud aceptada)</span>' : '—'; ?></dd></div>
                </dl>
            </section>

            <section class="ag-panel">
                <h2><i class="bi bi-person-badge-fill"></i> Organizador responsable</h2>
                <dl class="ag-datos">
                    <div><dt>Nombre</dt><dd><?php echo agE(trim($c['org_nombre'] . ' ' . $c['org_apellido'])); ?></dd></div>
                    <div><dt>Correo</dt><dd><?php echo agE($c['org_email']); ?></dd></div>
                    <div><dt>Teléfono</dt><dd><?php echo agE($c['org_telefono']); ?></dd></div>
                    <div><dt>Cuenta verificada</dt><dd><?php echo (int) $c['org_verificado'] === 1 ? 'Sí' : 'No'; ?></dd></div>
                </dl>
                <a href="paneles.php?seccion=usuarios&amp;usuario=<?php echo (int) $c['org_id']; ?>" class="ag-btn"><i class="bi bi-person-lines-fill"></i> Ver su cuenta</a>
            </section>

        </div>

        <section class="ag-panel">
            <h2><i class="bi bi-grid-3x3-gap-fill"></i> Canchas registradas</h2>
            <?php if (empty($d['canchas'])) { ?>
                <p class="ag-sub">Este club todavía no registró canchas.</p>
            <?php } else { ?>
                <ul class="ag-lista-chips">
                    <?php foreach ($d['canchas'] as $ca) { ?>
                        <li><?php echo agE($ca['nombre']); ?>
                            <span class="ag-badge <?php echo $ca['estado'] === 'Disponible' ? 'ag-badge-ok' : 'ag-badge-aviso'; ?>"><?php echo agE($ca['estado']); ?></span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </section>

        <section class="ag-panel">
            <h2><i class="bi bi-trophy-fill"></i> Torneos creados</h2>
            <?php if (empty($d['torneos'])) { ?>
                <p class="ag-sub">Este club todavía no creó torneos.</p>
            <?php } else { ?>
                <div class="ag-tabla-envoltorio">
                    <table class="ag-tabla">
                        <thead>
                            <tr><th>Torneo</th><th>Categoría</th><th>Tipo</th><th>Fechas</th><th>Estado</th><th class="ag-num">Inscriptos</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($d['torneos'] as $t) { ?>
                                <tr>
                                    <td data-label="Torneo"><strong><?php echo agE($t['nombre']); ?></strong></td>
                                    <td data-label="Categoría"><?php echo agE($t['categoria']); ?></td>
                                    <td data-label="Tipo"><?php echo agE($t['tipo']); ?></td>
                                    <td data-label="Fechas"><?php echo agFecha($t['fecha_inicio']); ?> – <?php echo agFecha($t['fecha_fin']); ?></td>
                                    <td data-label="Estado"><?php echo agBadgeEstadoTorneo($t['estado']); ?></td>
                                    <td class="ag-num" data-label="Inscriptos">
                                        <?php echo (int) $t['inscriptos']; ?> / <?php echo (int) $t['max_participantes']; ?>
                                        <?php if ((int) $t['descalificados'] > 0) { ?><span class="ag-sub"><?php echo (int) $t['descalificados']; ?> descalif.</span><?php } ?>
                                    </td>
                                    <td class="ag-acciones">
                                        <a href="ver_torneo.php?id=<?php echo (int) $t['id_torneo']; ?>" class="ag-btn">Ver</a>
                                        <a href="gestionar_participantes.php?id_torneo=<?php echo (int) $t['id_torneo']; ?>" class="ag-btn">Participantes</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <p class="ag-nota"><i class="bi bi-info-circle"></i> Los clubes no se pueden eliminar: sus torneos, resultados e inscripciones forman parte del historial de la plataforma.</p>

    </div>
    <?php
    return ob_get_clean();

}


/* =========================================================
   USUARIOS
   ========================================================= */

function renderAdminUsuarios($conexion, $csrfToken) {

    $idUsuario = filter_input(INPUT_GET, 'usuario', FILTER_VALIDATE_INT);

    if ($idUsuario) {
        return renderAdminUsuarioDetalle($conexion, (int) $idUsuario, $csrfToken);
    }

    $roles = adminRoles($conexion);
    $categorias = adminConsulta($conexion, "SELECT id_categoria, nombre FROM categoria ORDER BY id_categoria");

    $q = trim((string) ($_GET['q'] ?? ''));
    $q = function_exists('mb_substr') ? mb_substr($q, 0, 100, 'UTF-8') : substr($q, 0, 100);
    $rol = (int) ($_GET['rol'] ?? 0);
    $rol = isset($roles[$rol]) ? $rol : 0;
    $categoria = (string) ($_GET['categoria'] ?? '');
    $categoria = ($categoria === 'sin' || in_array((int) $categoria, array_map('intval', array_column($categorias, 'id_categoria')), true)) ? $categoria : '';

    $datos = adminListarUsuarios($conexion, ['q' => $q, 'rol' => $rol, 'categoria' => $categoria], (int) ($_GET['pagina'] ?? 1));
    $parametros = array_filter(['seccion' => 'usuarios', 'q' => $q, 'rol' => $rol ?: '', 'categoria' => $categoria], fn($v) => $v !== '' && $v !== null);
    $hayFiltros = ($q !== '' || $rol || $categoria !== '');

    ob_start();
    ?>
    <div class="ag">

        <header class="topbar">
            <h1>Usuarios</h1>
            <p>Todas las cuentas de la plataforma. Consultá su actividad y corregí sus datos personales.</p>
        </header>

        <form method="GET" action="paneles.php" class="ag-filtros ag-filtros-varios" role="search">
            <input type="hidden" name="seccion" value="usuarios">

            <label class="ag-buscador">
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="<?php echo agE($q); ?>" placeholder="Buscar por nombre, apellido o correo..." maxlength="100" aria-label="Buscar usuario">
            </label>

            <select name="rol" aria-label="Filtrar por rol">
                <option value="">Rol: todos</option>
                <?php foreach ($roles as $idR => $nombreR) { ?>
                    <option value="<?php echo (int) $idR; ?>" <?php echo $rol === (int) $idR ? 'selected' : ''; ?>><?php echo agE($nombreR); ?></option>
                <?php } ?>
            </select>

            <select name="categoria" aria-label="Filtrar por categoría">
                <option value="">Categoría: todas</option>
                <option value="sin" <?php echo $categoria === 'sin' ? 'selected' : ''; ?>>Sin categoría</option>
                <?php foreach ($categorias as $cat) { ?>
                    <option value="<?php echo (int) $cat['id_categoria']; ?>" <?php echo $categoria === (string) $cat['id_categoria'] ? 'selected' : ''; ?>><?php echo agE($cat['nombre']); ?></option>
                <?php } ?>
            </select>

            <button type="submit" class="ag-btn ag-btn-primario"><i class="bi bi-funnel-fill"></i> Filtrar</button>
            <?php if ($hayFiltros) { ?><a href="paneles.php?seccion=usuarios" class="ag-btn">Limpiar</a><?php } ?>
        </form>

        <p class="ag-resumen">
            <?php echo (int) $datos['total']; ?> <?php echo $datos['total'] === 1 ? 'usuario' : 'usuarios'; ?>
            · Página <?php echo (int) $datos['pagina']; ?> de <?php echo (int) $datos['paginas']; ?>
        </p>

        <?php if (empty($datos['filas'])) { ?>

            <div class="ag-vacio">
                <i class="bi bi-people"></i>
                <p>No hay usuarios que coincidan con los filtros.</p>
            </div>

        <?php } else { ?>

            <div class="ag-tabla-envoltorio">
                <table class="ag-tabla">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Rol</th>
                            <th title="Categoría con la que se registró (cada club tiene la suya; ver el detalle del usuario)">Categoría de registro</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos['filas'] as $u) { ?>
                            <tr>
                                <td data-label="Usuario">
                                    <strong><?php echo agE(trim($u['apellido'] . ', ' . $u['nombre'])); ?></strong>
                                </td>
                                <td data-label="Correo"><?php echo agE($u['email']); ?></td>
                                <td data-label="Teléfono"><?php echo agE($u['telefono']); ?></td>
                                <td data-label="Rol"><?php echo agBadgeRol($u['id_rol'], $roles[(int) $u['id_rol']] ?? ''); ?></td>
                                <td data-label="Categoría"><?php echo $u['categoria'] !== null ? agE($u['categoria']) : '<span class="ag-nd">—</span>'; ?></td>
                                <td class="ag-acciones">
                                    <a href="paneles.php?seccion=usuarios&amp;usuario=<?php echo (int) $u['id_usuario']; ?>" class="ag-btn ag-btn-primario">
                                        <i class="bi bi-eye-fill"></i> Ver detalles
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <?php echo agPaginacion($datos['pagina'], $datos['paginas'], $parametros); ?>

        <?php } ?>

    </div>
    <?php
    return ob_get_clean();

}


function renderAdminUsuarioDetalle($conexion, $idUsuario, $csrfToken) {

    $d = adminDetalleUsuario($conexion, $idUsuario);

    if ($d === null) {
        return '<div class="ag"><header class="topbar"><h1>Usuarios</h1></header>'
            . '<div class="ag-vacio"><i class="bi bi-people"></i><p>Ese usuario no existe.</p></div>'
            . '<a href="paneles.php?seccion=usuarios" class="ag-btn"><i class="bi bi-arrow-left"></i> Volver a Usuarios</a></div>';
    }

    $u = $d['usuario'];
    $rankings = $d['ranking'];
    $edad = agEdad($u['fecha_nacimiento']);

    $mensajes = [
        'guardado' => ['exito', 'Los datos se guardaron correctamente.'],
        'nombre_vacio' => ['error', 'El nombre y el apellido no pueden estar vacíos.'],
        'nombre_largo' => ['error', 'El nombre o el apellido es demasiado largo (máximo 100 caracteres).'],
        'nombre_invalido' => ['error', 'El nombre y el apellido solo pueden tener letras, espacios, apóstrofes, puntos y guiones.'],
        'telefono_invalido' => ['error', 'El teléfono no es válido (entre 6 y 20 caracteres: números, +, espacios, guiones y paréntesis).'],
        'fecha_invalida' => ['error', 'La fecha de nacimiento no es válida.'],
        'token' => ['error', 'Tu sesión expiró, volvé a intentarlo.'],
        'error_bd' => ['error', 'Ocurrió un error al guardar. Intentá nuevamente.'],
    ];
    $accion = $_GET['accion'] ?? '';
    $mensaje = $mensajes[$accion] ?? null;

    ob_start();
    ?>
    <div class="ag">

        <a href="paneles.php?seccion=usuarios" class="ag-volver"><i class="bi bi-arrow-left"></i> Volver a Usuarios</a>

        <header class="topbar">
            <h1><?php echo agE(trim($u['nombre'] . ' ' . $u['apellido'])); ?> <?php echo agBadgeRol($u['id_rol'], $u['rol']); ?></h1>
            <p><?php echo agE($u['email']); ?></p>
        </header>

        <?php if ($mensaje) { ?>
            <div class="<?php echo $mensaje[0] === 'exito' ? 'mensaje-exito' : 'mensaje-error'; ?> mensaje-error-compacto">
                <i class="bi <?php echo $mensaje[0] === 'exito' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'; ?>"></i> <?php echo agE($mensaje[1]); ?>
            </div>
        <?php } ?>

        <div class="ag-dos-columnas">

            <section class="ag-panel">
                <h2><i class="bi bi-person-lines-fill"></i> Datos personales</h2>

                <form action="funciones/admin_editar_usuario.php" method="POST" class="ag-formulario">
                    <input type="hidden" name="csrf_token" value="<?php echo agE($csrfToken); ?>">
                    <input type="hidden" name="id_usuario" value="<?php echo (int) $u['id_usuario']; ?>">

                    <div class="ag-campos">
                        <label>Nombre
                            <input type="text" name="nombre" value="<?php echo agE($u['nombre']); ?>" maxlength="100" required>
                        </label>
                        <label>Apellido
                            <input type="text" name="apellido" value="<?php echo agE($u['apellido']); ?>" maxlength="100" required>
                        </label>
                        <label>Teléfono
                            <input type="text" name="telefono" value="<?php echo agE($u['telefono']); ?>" maxlength="20" required>
                        </label>
                        <label>Fecha de nacimiento<?php echo $edad !== null ? ' (' . $edad . ' años)' : ''; ?>
                            <input type="date" name="fecha_nacimiento" value="<?php echo agE($u['fecha_nacimiento']); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </label>
                        <label>Correo
                            <input type="email" value="<?php echo agE($u['email']); ?>" readonly tabindex="-1" aria-describedby="nota-correo">
                        </label>
                    </div>

                    <p class="ag-nota" id="nota-correo"><i class="bi bi-info-circle"></i> El correo identifica la cuenta y no se cambia desde acá. Tampoco el rol, la categoría ni la contraseña.</p>

                    <button type="submit" class="ag-btn ag-btn-primario"><i class="bi bi-check-lg"></i> Guardar datos</button>
                </form>
            </section>

            <section class="ag-panel">
                <h2><i class="bi bi-shield-check"></i> Cuenta y categoría</h2>
                <dl class="ag-datos">
                    <div><dt>Rol</dt><dd><?php echo agBadgeRol($u['id_rol'], $u['rol']); ?></dd></div>
                    <div><dt>Cuenta verificada</dt><dd><?php echo (int) $u['verificado'] === 1 ? 'Sí' : 'No'; ?></dd></div>
                    <?php if ((int) $u['id_rol'] === 3) { ?>
                        <?php if (empty($d['membresias'])) { ?>
                            <div><dt>Clubes</dt><dd>Todavía no pertenece a ningún club</dd></div>
                        <?php } ?>
                        <?php foreach ($d['membresias'] as $m) { ?>
                            <div>
                                <dt>Categoría en <?php echo agE($m['club']); ?></dt>
                                <dd><?php echo $m['categoria'] !== null ? agE($m['categoria']) : 'Sin categoría'; ?></dd>
                            </div>
                        <?php } ?>
                    <?php } ?>
                    <?php foreach ($d['clubes'] as $club) { ?>
                        <div><dt>Club</dt><dd><a href="paneles.php?seccion=clubes&amp;club=<?php echo (int) $club['id_club']; ?>"><?php echo agE($club['nombre']); ?></a> <span class="ag-sub"><?php echo (int) $club['torneos']; ?> torneos</span></dd></div>
                    <?php } ?>
                </dl>
                <?php if ((int) $u['id_rol'] === 3) { ?>
                    <p class="ag-nota"><i class="bi bi-info-circle"></i> Cada club tiene su propia categoría para el jugador: cambia solo por los ascensos y descensos automáticos de ese club.</p>
                <?php } ?>
            </section>

        </div>

        <?php if ((int) $u['id_rol'] === 3) { ?>

            <?php /* Información SEPARADA POR CLUB: cada club tiene su categoría, sus puntos y sus posiciones (nunca se suman entre clubes). */ ?>
            <?php if (empty($rankings)) { ?>
                <section class="ag-panel">
                    <h2><i class="bi bi-buildings-fill"></i> Clubes</h2>
                    <p class="ag-nota"><i class="bi bi-info-circle"></i> Todavía no pertenece a ningún club, por eso no tiene categoría, puntos ni posiciones.</p>
                </section>
            <?php } ?>
            <?php foreach ($rankings as $r) { ?>
            <section class="ag-panel ag-club-ranking">
                <h2><i class="bi bi-buildings-fill"></i> <?php echo agE($r['club']); ?></h2>
                <section class="cards ag-cards">
                    <div class="card">
                        <h3>Categoría actual</h3>
                        <span><?php echo $r['categoria_nombre'] !== null ? agE($r['categoria_nombre']) : '—'; ?></span>
                        <small>En <?php echo agE($r['club']); ?></small>
                    </div>
                    <div class="card">
                        <h3>Puntos históricos</h3>
                        <span><?php echo rkNumero($r['puntos_totales']); ?></span>
                        <small>Puesto #<?php echo (int) $r['pos_historico']; ?> de <?php echo (int) $r['total_jugadores']; ?> en el ranking del club</small>
                    </div>
                    <div class="card">
                        <h3>Temporada actual</h3>
                        <span><?php echo rkNumero($r['puntos_temporada']); ?></span>
                        <small><?php echo $r['temporada'] ? agE($r['temporada']) . ' · ' : ''; ?>Puesto #<?php echo (int) $r['pos_temporada']; ?></small>
                    </div>
                    <div class="card">
                        <h3>En su categoría</h3>
                        <span><?php echo $r['categoria'] ? '#' . (int) $r['categoria']['posicion'] : '—'; ?></span>
                        <small><?php echo $r['categoria'] ? rkNumero($r['categoria']['puntos']) . ' puntos · de ' . (int) $r['categoria']['total'] . ' jugadores' : 'Sin categoría en este club'; ?></small>
                    </div>
                </section>
            </section>
            <?php } ?>

            <section class="cards ag-cards">
                <div class="card">
                    <h3>Torneos jugados</h3>
                    <span><?php echo (int) $d['resumen']['torneos']; ?></span>
                    <small><?php echo (int) $d['resumen']['campeonatos']; ?> campeonatos · <?php echo (int) $d['resumen']['pg']; ?> PG / <?php echo (int) $d['resumen']['pp']; ?> PP</small>
                </div>
            </section>

        <?php } ?>

        <section class="ag-panel">
            <h2><i class="bi bi-clipboard-check-fill"></i> Participación en torneos</h2>
            <?php if (empty($d['participaciones'])) { ?>
                <p class="ag-sub">Este usuario no tiene inscripciones<?php echo (int) $u['id_rol'] !== 3 ? ' (solo los participantes se inscriben a torneos)' : ''; ?>.</p>
            <?php } else { ?>
                <div class="ag-tabla-envoltorio">
                    <table class="ag-tabla">
                        <thead>
                            <tr><th>Torneo</th><th>Categoría</th><th>Estado del torneo</th><th>Inscripción</th><th>Resultado</th><th class="ag-num">Puntos</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($d['participaciones'] as $p) {
                                $anulado = ((int) $p['anulado'] === 1);
                                $puntos = (int) $p['puntos_grupos'] + (int) $p['puntos_cruces'];
                            ?>
                                <tr>
                                    <td data-label="Torneo"><strong><?php echo agE($p['torneo']); ?></strong><span class="ag-sub"><?php echo agFecha($p['fecha_inicio']); ?> · <?php echo agE($p['tipo']); ?></span></td>
                                    <td data-label="Categoría"><?php echo agE($p['categoria_torneo']); ?></td>
                                    <td data-label="Estado del torneo"><?php echo agBadgeEstadoTorneo($p['estado_torneo']); ?></td>
                                    <td data-label="Inscripción"><?php echo agE($p['estado_inscripcion']); ?></td>
                                    <td data-label="Resultado">
                                        <?php if ($p['instancia'] === null) { ?>
                                            <span class="ag-nd">—</span>
                                        <?php } else { ?>
                                            <?php echo agE(RANKING_NOMBRE_INSTANCIA[$p['instancia']] ?? $p['instancia']); ?>
                                            <span class="ag-sub"><?php echo (int) $p['partidos_ganados']; ?> PG / <?php echo (int) $p['partidos_perdidos']; ?> PP</span>
                                        <?php } ?>
                                    </td>
                                    <td class="ag-num" data-label="Puntos">
                                        <?php if ($p['instancia'] === null) { ?>
                                            <span class="ag-nd">—</span>
                                        <?php } elseif ($anulado) { ?>
                                            0 <span class="ag-sub">anulados: <?php echo $puntos; ?> (descalificado)</span>
                                        <?php } else { ?>
                                            <strong><?php echo rkNumero($puntos); ?></strong>
                                        <?php } ?>
                                    </td>
                                    <td class="ag-acciones">
                                        <a href="ver_torneo.php?id=<?php echo (int) $p['id_torneo']; ?>" class="ag-btn">Ver torneo</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </section>

        <?php if (!empty($d['historial_categoria'])) { ?>
            <section class="ag-panel">
                <h2><i class="bi bi-arrow-left-right"></i> Historial de categoría</h2>
                <ul class="ag-linea">
                    <?php foreach ($d['historial_categoria'] as $h) { ?>
                        <li>
                            <strong><?php echo agE($h['de']); ?> → <?php echo agE($h['a']); ?></strong>
                            <span class="ag-sub"><?php echo $h['club'] !== null ? agE($h['club']) . ' · ' : ''; ?><?php echo agE($h['tipo']); ?> · <?php echo agFecha($h['fecha'], true); ?> · <?php echo agE($h['motivo']); ?></span>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <p class="ag-nota"><i class="bi bi-info-circle"></i> Las cuentas no se eliminan: sus inscripciones, resultados, puntos e historial se conservan.</p>

    </div>
    <?php
    return ob_get_clean();

}
