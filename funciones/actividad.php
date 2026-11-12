<?php

/* =========================================================
   HISTORIAL DE ACTIVIDADES

   Registra en `historial_actividad` las operaciones importantes de la plataforma, para mostrarlas en el
   Panel del administrador. Reglas:
   - Solo se guardan operaciones REALES, en el momento en que ocurren (no hay registros inventados).
   - Registrar nunca debe romper la operación principal: cualquier error se anota en el log de PHP y se sigue.
   - Si quien llama está dentro de una transacción, el registro forma parte de ella: si se deshace la
     operación, también se deshace su registro.
   - Todas las consultas son preparadas. Nunca se guardan contraseñas ni datos de contacto.
   - Quién: por omisión, la persona de la sesión. Con 'actor' => 'sistema' se registra como «Sistema»
     (ascensos automáticos, etc.); con 'actor' => id, esa persona (p. ej. quien se registra sin sesión).
   ========================================================= */

/** Tipos de operación: código => [etiqueta, icono Bootstrap, color (azul|verde|ambar|rojo)]. */
const ACTIVIDAD_TIPOS = [
    'torneo_creado'        => ['Torneo creado',       'bi-trophy-fill',        'azul'],
    'torneo_modificado'    => ['Torneo modificado',   'bi-pencil-square',      'ambar'],
    'fase_generada'        => ['Fase generada',       'bi-diagram-3-fill',     'azul'],
    'resultado_confirmado' => ['Resultado',           'bi-check2-circle',      'verde'],
    'inscripcion'          => ['Inscripción',         'bi-person-plus-fill',   'azul'],
    'retiro'               => ['Retiro',              'bi-box-arrow-left',     'ambar'],
    'descalificacion'      => ['Descalificación',     'bi-person-x-fill',      'rojo'],
    'club_registrado'      => ['Club nuevo',          'bi-buildings-fill',     'azul'],
    'club_modificado'      => ['Club modificado',     'bi-building-gear',      'ambar'],
    'solicitud_rechazada'  => ['Solicitud rechazada', 'bi-x-circle-fill',      'rojo'],
    'usuario_registrado'   => ['Usuario nuevo',       'bi-person-plus-fill',   'azul'],
    'union_club'           => ['Unión a un club',     'bi-door-open-fill',     'azul'],
    'usuario_modificado'   => ['Usuario modificado',  'bi-person-lines-fill',  'ambar'],
    'categoria_cambiada'   => ['Cambio de categoría', 'bi-arrow-repeat',       'ambar'],
    'temporada_creada'     => ['Temporada creada',    'bi-calendar-plus-fill', 'azul'],
    'temporada_cerrada'    => ['Temporada cerrada',   'bi-calendar-check-fill', 'ambar'],
];

const ACTIVIDAD_ROLES = [1 => 'Administrador', 2 => 'Organizador', 3 => 'Participante'];


/** Texto recortado al largo máximo de la columna, sin cortar un carácter UTF-8 a la mitad. */
function actividadRecortar($texto, $max) {

    $texto = trim((string) $texto);

    if (function_exists('mb_substr')) {
        return mb_strlen($texto, 'UTF-8') > $max ? mb_substr($texto, 0, $max - 1, 'UTF-8') . '…' : $texto;
    }

    return strlen($texto) > $max ? substr($texto, 0, $max - 1) . '…' : $texto;

}


/** Una fila (o null) de una consulta preparada. */
function actividadFila($conexion, $sql, $tipos, array $params) {

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $fila ?: null;

}


/** «Nombre Apellido» de un usuario ('' si no existe). */
function actividadNombreUsuario($conexion, $idUsuario) {

    $f = actividadFila($conexion, "SELECT nombre, apellido FROM usuario WHERE id_usuario = ?", 'i', [(int) $idUsuario]);

    return $f ? trim($f['nombre'] . ' ' . $f['apellido']) : '';

}


/** Datos de un torneo para describirlo: nombre, club (id y nombre), categoría. */
function actividadDatosTorneo($conexion, $idTorneo) {

    return actividadFila(
        $conexion,
        "SELECT t.id_torneo, t.nombre, t.id_club, c.nombre AS club
         FROM torneos t LEFT JOIN club c ON c.id_club = t.id_club
         WHERE t.id_torneo = ?",
        'i',
        [(int) $idTorneo]
    );

}


/**
 * Inserta una actividad. Nunca lanza: devuelve true si se guardó.
 *
 * @param string $tipo         clave de ACTIVIDAD_TIPOS
 * @param string $descripcion  frase que sigue al nombre de quien actuó («creó el torneo «X»»);
 *                             con actor Sistema es una frase completa («Subió de categoría…»)
 * @param array  $o            id_club, id_torneo, id_usuario_afectado, actor ('sistema' | id de usuario)
 */
function registrarActividad($conexion, $tipo, $descripcion, array $o = []) {

    try {

        if (!isset(ACTIVIDAD_TIPOS[$tipo])) {
            error_log("registrarActividad: tipo desconocido «$tipo»");
            return false;
        }

        $idActor = null;
        $idRol = null;
        $nombre = 'Sistema';
        $rol = 'Sistema';

        if (($o['actor'] ?? null) !== 'sistema') {

            $idPedido = isset($o['actor']) ? (int) $o['actor'] : (int) ($_SESSION['id_usuario'] ?? 0);

            // Nombre y rol salen de la base, no de la sesión.
            $u = $idPedido > 0
                ? actividadFila($conexion, "SELECT nombre, apellido, id_rol FROM usuario WHERE id_usuario = ?", 'i', [$idPedido])
                : null;

            if ($u) {
                $idActor = $idPedido;
                $idRol = (int) $u['id_rol'];
                $nombre = trim($u['nombre'] . ' ' . $u['apellido']);
                $rol = ACTIVIDAD_ROLES[$idRol] ?? 'Usuario';
            }

        }

        $nombre = actividadRecortar($nombre, 200);
        $descripcion = actividadRecortar($descripcion, 500);
        $idClub = isset($o['id_club']) && (int) $o['id_club'] > 0 ? (int) $o['id_club'] : null;
        $idTorneo = isset($o['id_torneo']) && (int) $o['id_torneo'] > 0 ? (int) $o['id_torneo'] : null;
        $idAfectado = isset($o['id_usuario_afectado']) && (int) $o['id_usuario_afectado'] > 0 ? (int) $o['id_usuario_afectado'] : null;

        $stmt = mysqli_prepare(
            $conexion,
            "INSERT INTO historial_actividad
                (id_usuario, nombre_usuario, id_rol, rol, tipo, descripcion, id_club, id_torneo, id_usuario_afectado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "isisssiii", $idActor, $nombre, $idRol, $rol, $tipo, $descripcion, $idClub, $idTorneo, $idAfectado);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return true;

    } catch (Throwable $e) {

        error_log("registrarActividad($tipo): " . $e->getMessage());
        return false;

    }

}


/* ---------------------------------------------------------
   Registradores por operación: reciben ids y arman el texto con datos reales de la base.
   --------------------------------------------------------- */

function actividadTorneoCreado($conexion, $idTorneo) {

    $t = actividadDatosTorneo($conexion, $idTorneo);

    if ($t) {
        registrarActividad($conexion, 'torneo_creado', 'creó el torneo «' . $t['nombre'] . '»', ['id_club' => $t['id_club'], 'id_torneo' => $idTorneo]);
    }

}

function actividadTorneoModificado($conexion, $idTorneo) {

    $t = actividadDatosTorneo($conexion, $idTorneo);

    if ($t) {
        registrarActividad($conexion, 'torneo_modificado', 'modificó el torneo «' . $t['nombre'] . '»', ['id_club' => $t['id_club'], 'id_torneo' => $idTorneo]);
    }

}

/** $que: texto como «los partidos de la fase de grupos», «las semifinales», «la final». */
function actividadFaseGenerada($conexion, $idTorneo, $que, $idActor = null) {

    $t = actividadDatosTorneo($conexion, $idTorneo);

    if ($t) {
        $o = ['id_club' => $t['id_club'], 'id_torneo' => $idTorneo];
        if ($idActor !== null) { $o['actor'] = $idActor; }
        registrarActividad($conexion, 'fase_generada', 'generó ' . $que . ' del torneo «' . $t['nombre'] . '»', $o);
    }

}

/** Resultado de un partido ya guardado: $tabla es partido_individual o partido_pareja. */
function actividadResultadoConfirmado($conexion, $tabla, $idPartido, $idTorneo) {

    if (!in_array($tabla, ['partido_individual', 'partido_pareja'], true)) {
        return;
    }

    $t = actividadDatosTorneo($conexion, $idTorneo);
    $p = actividadFila(
        $conexion,
        "SELECT fase, resultado_tipo, id_entrante_1, id_entrante_2, id_ganador,
                set1_games_1, set1_games_2, set2_games_1, set2_games_2, set3_games_1, set3_games_2
         FROM $tabla WHERE id_partido = ?",
        'i',
        [(int) $idPartido]
    );

    if (!$t || !$p) {
        return;
    }

    $esIndividual = ($tabla === 'partido_individual');

    $nombreDe = function ($idEntrante) use ($conexion, $esIndividual) {

        if ($esIndividual) {
            $f = actividadFila(
                $conexion,
                "SELECT CONCAT(u.nombre, ' ', u.apellido) AS n
                 FROM grupo_inscripcion gi
                 INNER JOIN inscripcion i ON i.id_inscripcion = gi.id_inscripcion
                 INNER JOIN usuario u ON u.id_usuario = i.id_usuario
                 WHERE gi.id_grupo_inscripcion = ?",
                'i',
                [(int) $idEntrante]
            );
        } else {
            $f = actividadFila(
                $conexion,
                "SELECT CONCAT(u1.nombre, ' ', u1.apellido, ' / ', u2.nombre, ' ', u2.apellido) AS n
                 FROM grupo_pareja gp
                 INNER JOIN pareja pa ON pa.id_pareja = gp.id_pareja
                 INNER JOIN inscripcion i1 ON i1.id_inscripcion = pa.id_inscripcion_1
                 INNER JOIN inscripcion i2 ON i2.id_inscripcion = pa.id_inscripcion_2
                 INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
                 INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
                 WHERE gp.id_grupo_pareja = ?",
                'i',
                [(int) $idEntrante]
            );
        }

        return $f ? $f['n'] : '—';

    };

    if (($p['resultado_tipo'] ?? 'Normal') === 'WO') {
        $marcador = 'W.O.';
    } else {
        $sets = [$p['set1_games_1'] . '-' . $p['set1_games_2'], $p['set2_games_1'] . '-' . $p['set2_games_2']];
        if ($p['set3_games_1'] !== null) { $sets[] = $p['set3_games_1'] . '-' . $p['set3_games_2']; }
        $marcador = implode(' / ', $sets);
    }

    registrarActividad(
        $conexion,
        'resultado_confirmado',
        'confirmó el resultado ' . $marcador . ' entre ' . $nombreDe($p['id_entrante_1']) . ' y ' . $nombreDe($p['id_entrante_2'])
            . ' (' . $p['fase'] . ') en «' . $t['nombre'] . '»',
        ['id_club' => $t['id_club'], 'id_torneo' => $idTorneo]
    );

}

/** Inscripción individual propia (quien está en sesión). */
function actividadInscripcion($conexion, $idTorneo, $idUsuario) {

    $t = actividadDatosTorneo($conexion, $idTorneo);

    if ($t) {
        registrarActividad($conexion, 'inscripcion', 'se inscribió en el torneo «' . $t['nombre'] . '»', [
            'id_club' => $t['id_club'], 'id_torneo' => $idTorneo, 'id_usuario_afectado' => $idUsuario, 'actor' => $idUsuario,
        ]);
    }

}

/** Pareja formada al aceptar una invitación: quien actúa es quien aceptó. */
function actividadParejaFormada($conexion, $idTorneo, $idQuienAcepta, $idTitular) {

    $t = actividadDatosTorneo($conexion, $idTorneo);

    if ($t) {
        registrarActividad(
            $conexion,
            'inscripcion',
            'se inscribió en el torneo «' . $t['nombre'] . '» formando pareja con ' . actividadNombreUsuario($conexion, $idTitular),
            ['id_club' => $t['id_club'], 'id_torneo' => $idTorneo, 'id_usuario_afectado' => $idQuienAcepta, 'actor' => $idQuienAcepta]
        );
    }

}

/** Retiro o descalificación de una o dos personas (la pareja). $tipo: 'retiro' | 'descalificacion'. */
function actividadBajaDeTorneo($conexion, $tipo, $idTorneo, array $idsUsuario, $motivo, $idActor) {

    $t = actividadDatosTorneo($conexion, $idTorneo);

    if (!$t || empty($idsUsuario)) {
        return;
    }

    $nombres = array_filter(array_map(fn($id) => actividadNombreUsuario($conexion, $id), $idsUsuario));
    $verbo = ($tipo === 'retiro') ? 'retiró a ' : 'descalificó a ';
    $texto = $verbo . implode(' y ', $nombres) . ' del torneo «' . $t['nombre'] . '»';

    if ($motivo !== null && trim((string) $motivo) !== '') {
        $texto .= '. Motivo: ' . trim($motivo);
    }

    registrarActividad($conexion, $tipo, $texto, [
        'id_club' => $t['id_club'], 'id_torneo' => $idTorneo, 'id_usuario_afectado' => (int) $idsUsuario[0], 'actor' => (int) $idActor,
    ]);

}

/** Club dado de alta al aceptar la solicitud de un organizador (más el alta del organizador). */
function actividadClubRegistrado($conexion, $idClub, $idOrganizador) {

    $club = actividadFila($conexion, "SELECT nombre FROM club WHERE id_club = ?", 'i', [(int) $idClub]);
    $nombreOrg = actividadNombreUsuario($conexion, $idOrganizador);

    if ($club) {
        registrarActividad($conexion, 'club_registrado', 'registró el club «' . $club['nombre'] . '» (organizador: ' . $nombreOrg . ')', [
            'id_club' => $idClub, 'id_usuario_afectado' => $idOrganizador,
        ]);
        registrarActividad($conexion, 'usuario_registrado', 'dio de alta como organizador a ' . $nombreOrg, [
            'id_club' => $idClub, 'id_usuario_afectado' => $idOrganizador,
        ]);
    }

}

function actividadSolicitudRechazada($conexion, $idSolicitud) {

    $s = actividadFila($conexion, "SELECT nombre, apellido, nombre_club FROM solicitud_organizador WHERE id_solicitud = ?", 'i', [(int) $idSolicitud]);

    if ($s) {
        registrarActividad($conexion, 'solicitud_rechazada', 'rechazó la solicitud de organizador de ' . trim($s['nombre'] . ' ' . $s['apellido']) . ' (club «' . $s['nombre_club'] . '»)');
    }

}

/** Participante que crea su cuenta: la acción la hace él mismo (no hay sesión). */
function actividadUsuarioRegistrado($conexion, $idUsuario) {

    registrarActividad($conexion, 'usuario_registrado', 'se registró como participante', [
        'actor' => $idUsuario, 'id_usuario_afectado' => $idUsuario,
    ]);

}

function actividadUsuarioModificado($conexion, $idUsuario) {

    registrarActividad($conexion, 'usuario_modificado', 'modificó los datos de ' . actividadNombreUsuario($conexion, $idUsuario), [
        'id_usuario_afectado' => $idUsuario,
    ]);

}

/** Cambios en un club: canchas agregadas, editadas, con otro estado o eliminadas. */
function actividadClubModificado($conexion, $idClub, $texto) {

    $club = actividadFila($conexion, "SELECT nombre FROM club WHERE id_club = ?", 'i', [(int) $idClub]);

    if ($club) {
        registrarActividad($conexion, 'club_modificado', $texto . ' en el club «' . $club['nombre'] . '»', ['id_club' => $idClub]);
    }

}

/** Un participante se une a un club y recibe en él su categoría inicial (la de su encuesta de registro). */
function actividadUnionClub($conexion, $idUsuario, $idClub, $idCategoria = null) {

    $club = actividadFila($conexion, "SELECT nombre FROM club WHERE id_club = ?", 'i', [(int) $idClub]);
    $cat = $idCategoria ? actividadFila($conexion, "SELECT nombre FROM categoria WHERE id_categoria = ?", 'i', [(int) $idCategoria]) : null;

    if ($club) {
        registrarActividad($conexion, 'union_club', 'se unió al club «' . $club['nombre'] . '»' . ($cat ? ' con la categoría ' . $cat['nombre'] : ''), [
            'actor' => $idUsuario, 'id_club' => $idClub, 'id_usuario_afectado' => $idUsuario,
        ]);
    }

}

/** Ascenso por puntos o descenso por temporada: lo hace el sistema. */
function actividadCategoriaAutomatica($conexion, $idUsuario, $idCategoriaAnterior, $idCategoriaNueva, $motivo, $idTorneo = null, $idClub = null) {

    $a = actividadFila($conexion, "SELECT nombre FROM categoria WHERE id_categoria = ?", 'i', [(int) $idCategoriaAnterior]);
    $n = actividadFila($conexion, "SELECT nombre FROM categoria WHERE id_categoria = ?", 'i', [(int) $idCategoriaNueva]);
    $verbo = ((int) $idCategoriaNueva > (int) $idCategoriaAnterior) ? 'subió' : 'bajó';

    $o = ['actor' => 'sistema', 'id_usuario_afectado' => $idUsuario];

    // El club donde cambió la categoría (cada club tiene la suya): el indicado o, si no, el del torneo.
    if ($idClub) {
        $o['id_club'] = $idClub;
        $club = actividadFila($conexion, "SELECT nombre FROM club WHERE id_club = ?", 'i', [(int) $idClub]);
        $motivo .= $club ? ' en el club «' . $club['nombre'] . '»' : '';
    }

    if ($idTorneo) {
        $t = actividadDatosTorneo($conexion, $idTorneo);
        if ($t) { $o['id_torneo'] = $idTorneo; $o['id_club'] = $o['id_club'] ?? $t['id_club']; }
    }

    registrarActividad(
        $conexion,
        'categoria_cambiada',
        actividadNombreUsuario($conexion, $idUsuario) . ' ' . $verbo . ' de ' . ($a['nombre'] ?? '?') . ' a ' . ($n['nombre'] ?? '?') . ' (' . $motivo . ')',
        $o
    );

}

/** Una temporada se abre o se cierra EN UN CLUB. $idActor null = lo hizo el sistema (p. ej. la primera temporada de un club nuevo). */
function actividadTemporada($conexion, $tipo, $nombreTemporada, $idActor, $extra = '', $idClub = null) {

    $club = $idClub ? actividadFila($conexion, "SELECT nombre FROM club WHERE id_club = ?", 'i', [(int) $idClub]) : null;
    $enClub = $club ? ' del club «' . $club['nombre'] . '»' : '';

    $texto = ($tipo === 'temporada_cerrada')
        ? 'cerró la «' . $nombreTemporada . '»' . $enClub . $extra
        : 'abrió la «' . $nombreTemporada . '»' . $enClub;

    $o = ['actor' => $idActor === null ? 'sistema' : $idActor];

    if ($idClub) {
        $o['id_club'] = $idClub;
    }

    registrarActividad($conexion, $tipo, $texto, $o);

}
