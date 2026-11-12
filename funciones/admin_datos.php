<?php

require_once __DIR__ . '/ranking_puntos.php';
require_once __DIR__ . '/ranking_general.php';
require_once __DIR__ . '/club_categoria.php';

/* =========================================================
   DATOS DE LAS SECCIONES «CLUBES» Y «USUARIOS» DEL ADMINISTRADOR

   Solo LEEN de las tablas reales (club, usuario, rol, cancha, torneos, inscripcion, ranking_puntos,
   categoria_historial...). Nada se borra. Nunca se leen contraseñas ni códigos de verificación.
   Todas las consultas son preparadas.

   No existe en la base ningún estado de club ni de cuenta (habilitada/deshabilitada) ni fecha de
   registro del usuario; por eso esas columnas no se muestran. Ver el informe.
   ========================================================= */

const ADMIN_POR_PAGINA = 10;


/** Escapa % y _ para usarlos dentro de un LIKE. */
function adminLike($texto) {
    return '%' . addcslashes($texto, '%_\\') . '%';
}


/** Ejecuta una consulta preparada y devuelve todas las filas. $tipos es el string de bind_param. */
function adminConsulta($conexion, $sql, $tipos = '', array $params = []) {

    $stmt = mysqli_prepare($conexion, $sql);

    if ($tipos !== '') {
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    }

    mysqli_stmt_execute($stmt);

    return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

}


/** Página pedida (>= 1) acotada al total de páginas. */
function adminPagina($pedida, $total, $porPagina = ADMIN_POR_PAGINA) {

    $paginas = max(1, (int) ceil($total / $porPagina));

    return [min(max(1, (int) $pedida), $paginas), $paginas];

}


/* ---------------------------------------------------------
   CLUBES
   --------------------------------------------------------- */

/**
 * Lista de clubes con su organizador y sus cifras.
 * Búsqueda por nombre del club, del organizador o su correo.
 */
function adminListarClubes($conexion, $busqueda, $pagina) {

    $where = '';
    $tipos = '';
    $params = [];

    if ($busqueda !== '') {
        $where = "WHERE (c.nombre LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ? OR CONCAT(u.nombre, ' ', u.apellido) LIKE ? OR u.email LIKE ?)";
        $like = adminLike($busqueda);
        $tipos = 'sssss';
        $params = [$like, $like, $like, $like, $like];
    }

    $total = (int) adminConsulta(
        $conexion,
        "SELECT COUNT(*) AS n FROM club c INNER JOIN usuario u ON u.id_usuario = c.id_usuario $where",
        $tipos,
        $params
    )[0]['n'];

    [$pagina, $paginas] = adminPagina($pagina, $total);
    $desde = ($pagina - 1) * ADMIN_POR_PAGINA;

    $filas = adminConsulta(
        $conexion,
        "SELECT c.id_club, c.nombre, c.direccion, c.telefono, c.cantidad_canchas,
                u.id_usuario, u.nombre AS org_nombre, u.apellido AS org_apellido, u.email AS org_email,
                (SELECT COUNT(*) FROM torneos t WHERE t.id_club = c.id_club) AS torneos,
                (SELECT COUNT(*) FROM inscripcion i INNER JOIN torneos t ON t.id_torneo = i.id_torneo
                  WHERE t.id_club = c.id_club AND i.estado = 'Confirmada') AS inscripciones,
                (SELECT COUNT(DISTINCT i.id_usuario) FROM inscripcion i INNER JOIN torneos t ON t.id_torneo = i.id_torneo
                  WHERE t.id_club = c.id_club AND i.estado = 'Confirmada') AS participantes,
                (SELECT COUNT(*) FROM cancha ca WHERE ca.id_club = c.id_club) AS canchas,
                (SELECT MIN(s.fecha_solicitud) FROM solicitud_organizador s
                  WHERE s.email = u.email AND s.estado_solicitud = 'Aceptada') AS alta
         FROM club c
         INNER JOIN usuario u ON u.id_usuario = c.id_usuario
         $where
         ORDER BY c.nombre ASC, c.id_club ASC
         LIMIT " . ADMIN_POR_PAGINA . " OFFSET $desde",
        $tipos,
        $params
    );

    return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];

}


/** Detalle de un club: datos, organizador, canchas, torneos y actividad. null si no existe. */
function adminDetalleClub($conexion, $idClub) {

    $club = adminConsulta(
        $conexion,
        "SELECT c.*, u.id_usuario AS org_id, u.nombre AS org_nombre, u.apellido AS org_apellido,
                u.email AS org_email, u.telefono AS org_telefono, u.verificado AS org_verificado,
                (SELECT MIN(s.fecha_solicitud) FROM solicitud_organizador s
                  WHERE s.email = u.email AND s.estado_solicitud = 'Aceptada') AS alta
         FROM club c INNER JOIN usuario u ON u.id_usuario = c.id_usuario
         WHERE c.id_club = ?",
        'i',
        [$idClub]
    );

    if (!$club) {
        return null;
    }

    $club = $club[0];

    $canchas = adminConsulta(
        $conexion,
        "SELECT id_cancha, nombre, estado FROM cancha WHERE id_club = ? ORDER BY nombre ASC",
        'i',
        [$idClub]
    );

    $torneos = adminConsulta(
        $conexion,
        "SELECT t.id_torneo, t.nombre, t.estado, t.tipo, t.fecha_inicio, t.fecha_fin, t.max_participantes,
                cat.nombre AS categoria,
                (SELECT COUNT(*) FROM inscripcion i WHERE i.id_torneo = t.id_torneo AND i.estado = 'Confirmada') AS inscriptos,
                (SELECT COUNT(*) FROM inscripcion i WHERE i.id_torneo = t.id_torneo AND i.estado = 'Descalificada') AS descalificados
         FROM torneos t INNER JOIN categoria cat ON cat.id_categoria = t.id_categoria
         WHERE t.id_club = ?
         ORDER BY t.fecha_inicio DESC, t.id_torneo DESC",
        'i',
        [$idClub]
    );

    $actividad = adminConsulta(
        $conexion,
        "SELECT
            (SELECT COUNT(*) FROM torneos WHERE id_club = ?) AS torneos,
            (SELECT COUNT(*) FROM torneos WHERE id_club = ? AND estado IN ('Abierto', 'Cerrado', 'En curso')) AS activos,
            (SELECT COUNT(*) FROM torneos WHERE id_club = ? AND estado = 'Finalizado') AS finalizados,
            (SELECT COUNT(*) FROM inscripcion i INNER JOIN torneos t ON t.id_torneo = i.id_torneo
              WHERE t.id_club = ? AND i.estado = 'Confirmada') AS inscripciones,
            (SELECT COUNT(DISTINCT i.id_usuario) FROM inscripcion i INNER JOIN torneos t ON t.id_torneo = i.id_torneo
              WHERE t.id_club = ? AND i.estado = 'Confirmada') AS participantes,
            ((SELECT COUNT(*) FROM partido_individual p INNER JOIN grupo_inscripcion gi ON gi.id_grupo_inscripcion = p.id_entrante_1
               INNER JOIN grupo g ON g.id_grupo = gi.id_grupo INNER JOIN torneos t ON t.id_torneo = g.id_torneo
               WHERE t.id_club = ? AND p.estado = 'Jugado')
           + (SELECT COUNT(*) FROM partido_pareja p INNER JOIN grupo_pareja gp ON gp.id_grupo_pareja = p.id_entrante_1
               INNER JOIN grupo g ON g.id_grupo = gp.id_grupo INNER JOIN torneos t ON t.id_torneo = g.id_torneo
               WHERE t.id_club = ? AND p.estado = 'Jugado')) AS partidos_jugados",
        'iiiiiii',
        array_fill(0, 7, $idClub)
    )[0];

    return ['club' => $club, 'canchas' => $canchas, 'torneos' => $torneos, 'actividad' => $actividad];

}


/* ---------------------------------------------------------
   USUARIOS
   --------------------------------------------------------- */

/** Roles (id => nombre legible) tal como figuran en la tabla rol. */
function adminRoles($conexion) {

    $nombres = ['admin' => 'Administrador', 'organizador' => 'Organizador', 'participante' => 'Participante'];
    $roles = [];

    foreach (adminConsulta($conexion, "SELECT id_rol, tipo FROM rol ORDER BY id_rol") as $r) {
        $roles[(int) $r['id_rol']] = $nombres[$r['tipo']] ?? ucfirst($r['tipo']);
    }

    return $roles;

}


/**
 * Lista de usuarios con filtros. $filtros: q, rol (id), categoria (id o 'sin').
 */
function adminListarUsuarios($conexion, array $filtros, $pagina) {

    $condiciones = [];
    $tipos = '';
    $params = [];

    if (($filtros['q'] ?? '') !== '') {
        $like = adminLike($filtros['q']);
        $condiciones[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR CONCAT(u.nombre, ' ', u.apellido) LIKE ? OR CONCAT(u.apellido, ' ', u.nombre) LIKE ? OR u.email LIKE ?)";
        $tipos .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }

    if (($filtros['rol'] ?? 0) > 0) {
        $condiciones[] = "u.id_rol = ?";
        $tipos .= 'i';
        $params[] = (int) $filtros['rol'];
    }

    if (($filtros['categoria'] ?? '') === 'sin') {
        $condiciones[] = "u.id_categoria IS NULL";
    } elseif ((int) ($filtros['categoria'] ?? 0) > 0) {
        $condiciones[] = "u.id_categoria = ?";
        $tipos .= 'i';
        $params[] = (int) $filtros['categoria'];
    }

    $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

    $total = (int) adminConsulta($conexion, "SELECT COUNT(*) AS n FROM usuario u $where", $tipos, $params)[0]['n'];

    [$pagina, $paginas] = adminPagina($pagina, $total);
    $desde = ($pagina - 1) * ADMIN_POR_PAGINA;

    $filas = adminConsulta(
        $conexion,
        "SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.id_rol, u.verificado,
                u.id_categoria, c.nombre AS categoria, u.categoria_estado
         FROM usuario u
         LEFT JOIN categoria c ON c.id_categoria = u.id_categoria
         $where
         ORDER BY u.apellido ASC, u.nombre ASC, u.id_usuario ASC
         LIMIT " . ADMIN_POR_PAGINA . " OFFSET $desde",
        $tipos,
        $params
    );

    return ['filas' => $filas, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];

}


/**
 * Detalle de un usuario: datos personales (sin clave), club, participación en torneos, ranking e
 * historial de categoría. null si no existe.
 */
function adminDetalleUsuario($conexion, $idUsuario) {

    // Nunca se selecciona pass ni codigo_verificacion.
    $usuario = adminConsulta(
        $conexion,
        "SELECT u.id_usuario, u.nombre, u.apellido, u.fecha_nacimiento, u.telefono, u.email, u.verificado,
                u.id_rol, u.id_categoria, c.nombre AS categoria, u.categoria_estado
         FROM usuario u LEFT JOIN categoria c ON c.id_categoria = u.id_categoria
         WHERE u.id_usuario = ?",
        'i',
        [$idUsuario]
    );

    if (!$usuario) {
        return null;
    }

    $usuario = $usuario[0];
    $roles = adminRoles($conexion);
    $usuario['rol'] = $roles[(int) $usuario['id_rol']] ?? '';

    $clubes = adminConsulta(
        $conexion,
        "SELECT c.id_club, c.nombre, (SELECT COUNT(*) FROM torneos t WHERE t.id_club = c.id_club) AS torneos
         FROM club c WHERE c.id_usuario = ? ORDER BY c.nombre",
        'i',
        [$idUsuario]
    );

    $participaciones = adminConsulta(
        $conexion,
        "SELECT i.id_inscripcion, i.estado AS estado_inscripcion, i.fecha_inscripcion,
                t.id_torneo, t.nombre AS torneo, t.tipo, t.estado AS estado_torneo, t.fecha_inicio,
                cat.nombre AS categoria_torneo,
                rp.instancia, rp.puntos_grupos, rp.puntos_cruces, rp.partidos_ganados, rp.partidos_perdidos, rp.anulado
         FROM inscripcion i
         INNER JOIN torneos t ON t.id_torneo = i.id_torneo
         INNER JOIN categoria cat ON cat.id_categoria = t.id_categoria
         LEFT JOIN ranking_puntos rp ON rp.id_torneo = i.id_torneo AND rp.id_usuario = i.id_usuario
         WHERE i.id_usuario = ?
         ORDER BY t.fecha_inicio DESC, t.id_torneo DESC",
        'i',
        [$idUsuario]
    );

    $resumen = ['torneos' => 0, 'campeonatos' => 0, 'pg' => 0, 'pp' => 0];

    foreach ($participaciones as $p) {

        if ($p['estado_inscripcion'] === 'Confirmada') {
            $resumen['torneos']++;
        }

        if ($p['instancia'] !== null && (int) $p['anulado'] === 0) {
            $resumen['pg'] += (int) $p['partidos_ganados'];
            $resumen['pp'] += (int) $p['partidos_perdidos'];
            if ($p['instancia'] === 'Campeon') {
                $resumen['campeonatos']++;
            }
        }

    }

    // Ranking: solo existe para participantes, y es POR CLUB (cada club tiene su propio ranking, puntos y categoría):
    // una entrada por cada club al que pertenece. Nunca se suman puntos de clubes distintos.
    $ranking = [];

    if ((int) $usuario['id_rol'] === 3) {

        foreach (clubesDelUsuario($conexion, (int) $idUsuario) as $m) {

            $idClubM = (int) $m['id_club'];
            $general = rankingGeneral($conexion, $idClubM, 'historico');

            foreach ($general['filas'] as $f) {

                if ($f['id_usuario'] !== (int) $idUsuario) {
                    continue;
                }

                $entrada = [
                    'id_club' => $idClubM,
                    'club' => $m['club'],
                    'categoria_nombre' => $m['categoria'],   // categoría ACTUAL en este club (usuario_club.id_categoria)
                    'puntos_totales' => $f['puntos_totales'],
                    'puntos_temporada' => $f['puntos_temporada'],
                    'pos_historico' => $f['pos_historico'],
                    'pos_temporada' => $f['pos_temporada'],
                    'total_jugadores' => count($general['filas']),
                    'temporada' => $general['temporada']['nombre'] ?? null,
                    'categoria' => null,
                ];

                $porCategoria = rankingDeUsuario($conexion, (int) $idUsuario, $idClubM);

                if ($porCategoria) {
                    $entrada['categoria'] = [
                        'posicion' => $porCategoria['posicion'],
                        'total' => $porCategoria['total_categoria'],
                        'puntos' => $porCategoria['puntos'],
                    ];
                }

                $ranking[] = $entrada;
                break;

            }

        }

    }

    $historial = adminConsulta(
        $conexion,
        "SELECT h.fecha, h.tipo, h.motivo, ca.nombre AS de, cn.nombre AS a, cl.nombre AS club
         FROM categoria_historial h
         INNER JOIN categoria ca ON ca.id_categoria = h.id_categoria_anterior
         INNER JOIN categoria cn ON cn.id_categoria = h.id_categoria_nueva
         LEFT JOIN club cl ON cl.id_club = h.id_club
         WHERE h.id_usuario = ?
         ORDER BY h.fecha DESC, h.id_historial DESC",
        'i',
        [$idUsuario]
    );

    return [
        'usuario' => $usuario,
        'clubes' => $clubes,
        // Clubes a los que pertenece el jugador y su categoría en cada uno (cada club tiene la suya).
        'membresias' => (int) $usuario['id_rol'] === 3 ? clubesDelUsuario($conexion, (int) $idUsuario) : [],
        'participaciones' => $participaciones,
        'resumen' => $resumen,
        'ranking' => $ranking,
        'historial_categoria' => $historial,
    ];

}


/**
 * Valida los datos personales editables. Devuelve ['ok' => true, 'datos' => [...]] o ['ok' => false, 'error' => código].
 * Solo nombre, apellido, teléfono y fecha de nacimiento: el correo identifica la cuenta, y el rol, la categoría y
 * la clave NO se pueden cambiar desde acá.
 */
function adminValidarDatosUsuario(array $entrada) {

    $nombre = trim((string) ($entrada['nombre'] ?? ''));
    $apellido = trim((string) ($entrada['apellido'] ?? ''));
    $telefono = trim((string) ($entrada['telefono'] ?? ''));
    $fecha = trim((string) ($entrada['fecha_nacimiento'] ?? ''));

    $largo = fn($t) => function_exists('mb_strlen') ? mb_strlen($t, 'UTF-8') : strlen($t);

    if ($nombre === '' || $apellido === '') {
        return ['ok' => false, 'error' => 'nombre_vacio'];
    }

    if ($largo($nombre) > 100 || $largo($apellido) > 100) {
        return ['ok' => false, 'error' => 'nombre_largo'];
    }

    if (!preg_match("/^[\p{L}][\p{L}\s'.-]*$/u", $nombre) || !preg_match("/^[\p{L}][\p{L}\s'.-]*$/u", $apellido)) {
        return ['ok' => false, 'error' => 'nombre_invalido'];
    }

    if (!preg_match('/^[0-9+()\s-]{6,20}$/', $telefono)) {
        return ['ok' => false, 'error' => 'telefono_invalido'];
    }

    $f = DateTime::createFromFormat('!Y-m-d', $fecha);

    if (!$f || $f->format('Y-m-d') !== $fecha) {
        return ['ok' => false, 'error' => 'fecha_invalida'];
    }

    $hoy = new DateTime('today');

    if ($f > $hoy || (int) $f->format('Y') < 1900) {
        return ['ok' => false, 'error' => 'fecha_invalida'];
    }

    return ['ok' => true, 'datos' => [
        'nombre' => $nombre, 'apellido' => $apellido, 'telefono' => $telefono, 'fecha_nacimiento' => $fecha,
    ]];

}
