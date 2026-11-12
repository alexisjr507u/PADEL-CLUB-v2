<?php

require_once __DIR__ . '/actividad.php';

/* =========================================================
   CATEGORÍA DEL JUGADOR EN CADA CLUB (tabla usuario_club)

   Cada jugador tiene su propia categoría en cada club. Estas funciones son el único lugar que decide:
   - si un jugador pertenece a un club y con qué categoría;
   - si esa categoría le sirve para inscribirse en un torneo de ESE club;
   - cómo se une a un club nuevo (sin tocar nada de los clubes donde ya participa).

   No hay revisión ni aprobación de categorías: al unirse, el jugador recibe en ese club la categoría de su encuesta de
   registro (id_categoria, estado Confirmada) y puede inscribirse de inmediato. Después, su categoría en cada club solo
   cambia por los ascensos y descensos automáticos de ESE club.
   Todas las consultas son preparadas.
   ========================================================= */

/** Una fila (o null) de una consulta preparada. */
function ccFila($conexion, $sql, $tipos, array $params) {

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $fila ?: null;

}


/** Todas las filas de una consulta preparada. */
function ccFilas($conexion, $sql, $tipos = '', array $params = []) {

    $stmt = mysqli_prepare($conexion, $sql);

    if ($tipos !== '') {
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $filas;

}


/**
 * La membresía de un jugador en un club, o null si no pertenece.
 * $bloquear = true la bloquea (FOR UPDATE) dentro de una transacción.
 */
function membresiaEnClub($conexion, $idUsuario, $idClub, $bloquear = false) {

    return ccFila(
        $conexion,
        "SELECT id_usuario_club, id_usuario, id_club, id_categoria, categoria_estado
         FROM usuario_club
         WHERE id_usuario = ? AND id_club = ?" . ($bloquear ? " FOR UPDATE" : ""),
        'ii',
        [(int) $idUsuario, (int) $idClub]
    );

}


/**
 * ¿Puede esta membresía inscribirse en un torneo de la categoría dada (del mismo club)?
 * Devuelve '' si puede; si no, el código de error que usa el servidor:
 *   no_pertenece_club | sin_categoria | categoria_incompatible
 */
function motivoSinCategoriaEnClub($membresia, $idCategoriaTorneo) {

    if (!$membresia) {
        return 'no_pertenece_club';
    }

    if ($membresia['id_categoria'] === null) {
        return 'sin_categoria';
    }

    if ((int) $membresia['id_categoria'] !== (int) $idCategoriaTorneo) {
        return 'categoria_incompatible';
    }

    return '';

}


/**
 * Categoría con la que el jugador salió de la encuesta de registro (la inicial para un club nuevo), o null.
 *
 * Solo LEE y NUNCA hereda ascensos ni descensos de ningún club:
 *  1) la estimada por la encuesta (clasificacion_categoria), que no cambia nunca;
 *  2) si no hay encuesta guardada (camino «principiante» o usuarios anteriores a la encuesta), la categoría con la que se
 *     registró. Hasta la Fase 3 el motor de puntos movía usuario.id_categoria al ascender o descender en The Bull; si el
 *     jugador tiene cambios de categoría anteriores se toma la categoría «anterior» de su PRIMER cambio: es la original.
 *     Desde la Fase 3 usuario.id_categoria ya no cambia: es solo la clasificación inicial del registro.
 */
function categoriaInicialDeRegistro($conexion, $idUsuario) {

    $f = ccFila(
        $conexion,
        "SELECT u.id_categoria, cc.id_categoria_estimada
         FROM usuario u
         LEFT JOIN clasificacion_categoria cc ON cc.id_usuario = u.id_usuario
         WHERE u.id_usuario = ?",
        'i',
        [(int) $idUsuario]
    );

    if (!$f) {
        return null;
    }

    if ($f['id_categoria_estimada'] !== null) {
        return (int) $f['id_categoria_estimada'];
    }

    $primerCambio = ccFila(
        $conexion,
        "SELECT id_categoria_anterior FROM categoria_historial WHERE id_usuario = ? ORDER BY id_historial ASC LIMIT 1",
        'i',
        [(int) $idUsuario]
    );

    if ($primerCambio) {
        return (int) $primerCambio['id_categoria_anterior'];
    }

    return $f['id_categoria'] !== null ? (int) $f['id_categoria'] : null;

}


/**
 * Une a un participante a un club. No modifica nada de los demás clubes ni sus puntos.
 * La membresía nace CONFIRMADA con la categoría de la encuesta de registro como categoría inicial en ese club
 * (sin revisión y sin heredar ascensos ni descensos de ningún otro club).
 *
 * @return array ['ok' => true, 'club' => nombre, 'id_categoria' => id] o ['ok' => false, 'error' => código]
 *         códigos: no_autorizado | club_no_existe | ya_pertenece | sin_categoria_inicial | error_bd
 */
function unirseAlClub($conexion, $idUsuario, $idClub) {

    $usuario = ccFila($conexion, "SELECT id_rol FROM usuario WHERE id_usuario = ?", 'i', [(int) $idUsuario]);

    // Solo los participantes se unen a clubes (el rol se confirma contra la base).
    if (!$usuario || (int) $usuario['id_rol'] !== 3) {
        return ['ok' => false, 'error' => 'no_autorizado'];
    }

    $club = ccFila($conexion, "SELECT id_club, nombre FROM club WHERE id_club = ?", 'i', [(int) $idClub]);

    if (!$club) {
        return ['ok' => false, 'error' => 'club_no_existe'];
    }

    $inicial = categoriaInicialDeRegistro($conexion, $idUsuario);

    // Sin categoría inicial no se inventa una: tiene que completar la encuesta de categoría.
    if ($inicial === null) {
        return ['ok' => false, 'error' => 'sin_categoria_inicial'];
    }

    try {

        // El índice único (id_usuario, id_club) impide duplicados aunque lleguen dos pedidos a la vez.
        $stmt = mysqli_prepare(
            $conexion,
            "INSERT INTO usuario_club (id_usuario, id_club, id_categoria, categoria_estado, origen)
             VALUES (?, ?, ?, 'Confirmada', 'Unión al club')"
        );
        mysqli_stmt_bind_param($stmt, "iii", $idUsuario, $idClub, $inicial);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

    } catch (Throwable $e) {

        if ((int) $e->getCode() === 1062 || (int) mysqli_errno($conexion) === 1062) {
            return ['ok' => false, 'error' => 'ya_pertenece'];
        }

        error_log("Error al unir al usuario $idUsuario al club $idClub: " . $e->getMessage());

        return ['ok' => false, 'error' => 'error_bd'];

    }

    actividadUnionClub($conexion, $idUsuario, $idClub, $inicial);

    return ['ok' => true, 'club' => $club['nombre'], 'id_categoria' => $inicial];

}


/**
 * Los clubes a los que pertenece un jugador, con su categoría en cada uno.
 * Cada fila: id_club, club, id_categoria, categoria (nombre o null), categoria_estado.
 */
function clubesDelUsuario($conexion, $idUsuario) {

    return ccFilas(
        $conexion,
        "SELECT uc.id_club, c.nombre AS club, uc.id_categoria, cat.nombre AS categoria,
                uc.categoria_estado
         FROM usuario_club uc
         INNER JOIN club c ON c.id_club = uc.id_club
         LEFT JOIN categoria cat ON cat.id_categoria = uc.id_categoria
         WHERE uc.id_usuario = ?
         ORDER BY c.nombre ASC",
        'i',
        [(int) $idUsuario]
    );

}

/**
 * Ids de los clubes cuyas temporadas (y ranking) puede gestionar quien está logueado. El rol sale de la BASE, no de la sesión:
 *  - administrador: todos los clubes;
 *  - organizador: solamente los clubes que administra (club.id_usuario = él);
 *  - cualquier otro: ninguno.
 */
function clubesQueGestiona($conexion, $idUsuario) {

    $u = ccFila($conexion, "SELECT id_rol FROM usuario WHERE id_usuario = ?", 'i', [(int) $idUsuario]);

    if (!$u) {
        return [];
    }

    $rol = (int) $u['id_rol'];

    if ($rol === 1) {
        return array_map('intval', array_column(ccFilas($conexion, "SELECT id_club FROM club ORDER BY nombre ASC"), 'id_club'));
    }

    if ($rol === 2) {
        return array_map('intval', array_column(
            ccFilas($conexion, "SELECT id_club FROM club WHERE id_usuario = ? ORDER BY nombre ASC", 'i', [(int) $idUsuario]),
            'id_club'
        ));
    }

    return [];

}


/** ¿Puede esta persona gestionar las temporadas de ese club? (administrador: cualquiera; organizador: solo el suyo). */
function puedeGestionarClub($conexion, $idUsuario, $idClub) {

    return in_array((int) $idClub, clubesQueGestiona($conexion, $idUsuario), true);

}
