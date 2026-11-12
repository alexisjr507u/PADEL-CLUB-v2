<?php

require_once __DIR__ . '/actividad.php';

/* =========================================================
   TEMPORADAS POR CLUB

   Cada club tiene SUS temporadas. Puede haber una temporada abierta por club al mismo tiempo (la base lo
   garantiza con un índice único por club) y cerrar la de un club nunca toca la de otro.
   Todas las consultas son preparadas.
   ========================================================= */

/** Temporada abierta de ese club (id_temporada, nombre, id_club, estado, fecha_inicio, fecha_cierre), o null si no tiene. */
function temporadaAbierta($conexion, $idClub) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT id_temporada, nombre, id_club, estado, fecha_inicio, fecha_cierre FROM temporada WHERE id_club = ? AND estado = 'Abierta' LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $idClub);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $fila ?: null;

}


/**
 * Una temporada de ese club (actual o anterior), o null si no existe O PERTENECE A OTRO CLUB: el club se compara siempre,
 * así nadie puede pedir los puntos de la temporada de otro club cambiando el número en la URL.
 */
function temporadaDeClub($conexion, $idClub, $idTemporada) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT id_temporada, nombre, id_club, estado, fecha_inicio, fecha_cierre FROM temporada WHERE id_temporada = ? AND id_club = ?"
    );
    mysqli_stmt_bind_param($stmt, "ii", $idTemporada, $idClub);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $fila ?: null;

}


/** Todas las temporadas de un club, la más reciente primero (la historia de temporadas se conserva). */
function temporadasDeClub($conexion, $idClub) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT id_temporada, nombre, id_club, estado, fecha_inicio, fecha_cierre FROM temporada WHERE id_club = ? ORDER BY id_temporada DESC"
    );
    mysqli_stmt_bind_param($stmt, "i", $idClub);
    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $filas;

}


/**
 * Abre la siguiente temporada de un club («Temporada N», con N = las que ya tenía + 1) y devuelve su nombre.
 * Quien llama decide la transacción. No registra actividad (lo hace quien llama, con su texto).
 */
function abrirTemporadaDeClub($conexion, $idClub) {

    $stmtN = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM temporada WHERE id_club = ?");
    mysqli_stmt_bind_param($stmtN, "i", $idClub);
    mysqli_stmt_execute($stmtN);
    $numero = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtN))['n'] + 1;
    mysqli_stmt_close($stmtN);

    $nombre = 'Temporada ' . $numero;

    $stmt = mysqli_prepare($conexion, "INSERT INTO temporada (nombre, id_club) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "si", $nombre, $idClub);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $nombre;

}


/**
 * La temporada abierta del club; si todavía no tiene ninguna (club nuevo) se abre la primera.
 * Pensada para usarse DENTRO de una transacción: bloquea la fila del club para que dos pedidos simultáneos
 * no abran dos temporadas (además lo impide el índice único por club).
 */
function temporadaAbiertaOCrear($conexion, $idClub) {

    $stmt = mysqli_prepare($conexion, "SELECT id_club FROM club WHERE id_club = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $idClub);
    mysqli_stmt_execute($stmt);
    $club = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$club) {
        return null;
    }

    $temporada = temporadaAbierta($conexion, $idClub);

    if ($temporada) {
        return $temporada;
    }

    $nombre = abrirTemporadaDeClub($conexion, $idClub);
    actividadTemporada($conexion, 'temporada_creada', $nombre, null, '', $idClub);

    return temporadaAbierta($conexion, $idClub);

}
