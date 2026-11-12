<?php

/* =========================================================
   DATOS DE LA CONSULTA PÚBLICA DE TORNEOS (solo lectura)

   - El club de cada torneo sale de la relación real torneos.id_club -> club.id_club.
   - Los clubes del filtro salen de la tabla `club` (solo los que tienen torneos),
     así que un club nuevo aparece solo en cuanto crea su primer torneo.
   - Nada de esto escribe en la base y no expone correos, teléfonos ni contraseñas.
   ========================================================= */

const PUBLICO_ESTADOS = ['Abierto', 'Cerrado', 'En curso', 'Finalizado'];
const PUBLICO_POR_PAGINA = 12;


/** Ejecuta un SELECT preparado y devuelve todas las filas. */
function publicoConsulta($conexion, $sql, $tipos = '', array $params = []) {

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        return [];
    }

    if ($tipos !== '') {
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $filas;

}


/** Clubes que tienen al menos un torneo (para el filtro). */
function publicoClubesConTorneos($conexion) {

    return publicoConsulta(
        $conexion,
        "SELECT c.id_club, c.nombre
         FROM club c
         WHERE EXISTS (SELECT 1 FROM torneos t WHERE t.id_club = c.id_club)
         ORDER BY c.nombre ASC"
    );

}


/** Todas las categorías, en el orden en que están cargadas. */
function publicoCategorias($conexion) {

    return publicoConsulta($conexion, "SELECT id_categoria, nombre FROM categoria ORDER BY id_categoria ASC");

}


/**
 * Normaliza los filtros que llegan por GET. Todo lo inválido se descarta (nunca se concatena a SQL).
 */
function publicoFiltrosDesdeGet(array $get) {

    $q = trim((string) ($get['q'] ?? ''));
    $q = mb_substr($q, 0, 60);

    $estado = (string) ($get['estado'] ?? '');

    return [
        'q'         => $q,
        'club'      => max(0, (int) filter_var($get['club'] ?? 0, FILTER_VALIDATE_INT)),
        'categoria' => max(0, (int) filter_var($get['categoria'] ?? 0, FILTER_VALIDATE_INT)),
        'estado'    => in_array($estado, PUBLICO_ESTADOS, true) ? $estado : '',
        'orden'     => (($get['orden'] ?? '') === 'antiguos') ? 'antiguos' : 'recientes',
        'pagina'    => max(1, (int) filter_var($get['pagina'] ?? 1, FILTER_VALIDATE_INT)),
    ];

}


/**
 * Torneos de todos los clubes según los filtros. Devuelve ['torneos' => [...], 'total' => N, 'paginas' => P, 'pagina' => X].
 * "confirmados" cuenta inscripciones Confirmadas (igual que el cupo que valida el servidor al inscribirse).
 */
function publicoListarTorneos($conexion, array $f) {

    $where = [];
    $tipos = '';
    $params = [];

    if ($f['q'] !== '') {
        // Se escapan % y _ para que se busquen como texto común.
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
        $where[] = "t.nombre LIKE ?";
        $tipos .= 's';
        $params[] = $like;
    }

    if ($f['club'] > 0) {
        $where[] = "t.id_club = ?";
        $tipos .= 'i';
        $params[] = $f['club'];
    }

    if ($f['categoria'] > 0) {
        $where[] = "t.id_categoria = ?";
        $tipos .= 'i';
        $params[] = $f['categoria'];
    }

    if ($f['estado'] !== '') {
        $where[] = "t.estado = ?";
        $tipos .= 's';
        $params[] = $f['estado'];
    }

    $condicion = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $totalFila = publicoConsulta(
        $conexion,
        "SELECT COUNT(*) AS n FROM torneos t $condicion",
        $tipos,
        $params
    );

    $total = (int) ($totalFila[0]['n'] ?? 0);
    $paginas = max(1, (int) ceil($total / PUBLICO_POR_PAGINA));
    $pagina = min($f['pagina'], $paginas);
    $offset = ($pagina - 1) * PUBLICO_POR_PAGINA;

    $orden = ($f['orden'] === 'antiguos')
        ? "t.fecha_inicio ASC, t.hora_inicio ASC, t.id_torneo ASC"
        : "t.fecha_inicio DESC, t.hora_inicio DESC, t.id_torneo DESC";

    $torneos = publicoConsulta(
        $conexion,
        "SELECT t.id_torneo, t.nombre, t.imagen, t.fecha_inicio, t.fecha_fin, t.hora_inicio, t.lugar,
                t.tipo, t.estado, t.max_participantes,
                c.id_club, c.nombre AS club,
                cat.nombre AS categoria,
                (SELECT COUNT(*) FROM inscripcion i WHERE i.id_torneo = t.id_torneo AND i.estado = 'Confirmada') AS confirmados
         FROM torneos t
         INNER JOIN club c ON c.id_club = t.id_club
         INNER JOIN categoria cat ON cat.id_categoria = t.id_categoria
         $condicion
         ORDER BY $orden
         LIMIT " . PUBLICO_POR_PAGINA . " OFFSET " . (int) $offset,
        $tipos,
        $params
    );

    return ['torneos' => $torneos, 'total' => $total, 'paginas' => $paginas, 'pagina' => $pagina];

}


/** Modalidad en palabras de la tarjeta. */
function publicoModalidad($tipo) {

    return ['Individual' => 'Individual', 'Parejas' => 'Parejas', 'Mixto' => 'Mixto'][$tipo] ?? $tipo;

}


/** Clase CSS del estado (Abierto verde, En curso ámbar, Finalizado gris, Cerrado rojo). */
function publicoClaseEstado($estado) {

    return [
        'Abierto'    => 'pub-estado-abierto',
        'En curso'   => 'pub-estado-curso',
        'Finalizado' => 'pub-estado-fin',
        'Cerrado'    => 'pub-estado-cerrado',
    ][$estado] ?? 'pub-estado-fin';

}


/**
 * Partidos de la fase de grupos para mostrarlos públicamente (solo nombres y resultado).
 * Devuelve una lista por grupo: ['nombre' => ..., 'partidos' => [ ['n1','n2','jugado','resultado','gano1','gano2'] ]].
 */
function publicoPartidosDeGrupos($conexion, $idTorneo, $esIndividual) {

    require_once __DIR__ . '/standings.php';

    $grupos = publicoConsulta(
        $conexion,
        "SELECT id_grupo, nombre FROM grupo WHERE id_torneo = ? ORDER BY orden ASC, id_grupo ASC",
        'i',
        [(int) $idTorneo]
    );

    $salida = [];

    foreach ($grupos as $g) {

        $datos = obtenerMiembrosYPartidosDeGrupo($conexion, (int) $g['id_grupo'], $esIndividual);

        $nombres = [];
        foreach ($datos['miembros'] as $m) {
            $nombres[(int) $m['id_entrante']] = $esIndividual
                ? trim($m['nombre'] . ' ' . $m['apellido'])
                : trim($m['nombre1'] . ' ' . $m['apellido1']) . ' / ' . trim($m['nombre2'] . ' ' . $m['apellido2']);
            if (!empty($m['descalificado'])) {
                $nombres[(int) $m['id_entrante']] .= ' (descalificado)';
            }
        }

        $lista = [];

        foreach ($datos['partidos'] as $p) {

            $jugado = ($p['estado'] === 'Jugado');
            $texto = '';

            if ($jugado && ($p['resultado_tipo'] ?? 'Normal') === 'WO') {
                $texto = 'W.O.';
            } elseif ($jugado) {
                $sets = [[$p['set1_games_1'], $p['set1_games_2']], [$p['set2_games_1'], $p['set2_games_2']]];
                if ($p['set3_games_1'] !== null) {
                    $sets[] = [$p['set3_games_1'], $p['set3_games_2']];
                }
                $texto = implode(' / ', array_map(fn($s) => $s[0] . '-' . $s[1], $sets));
            }

            $lista[] = [
                'n1'        => $nombres[(int) $p['id_entrante_1']] ?? '—',
                'n2'        => $nombres[(int) $p['id_entrante_2']] ?? '—',
                'jugado'    => $jugado,
                'resultado' => $texto,
                'gano1'     => $jugado && (int) $p['id_ganador'] === (int) $p['id_entrante_1'],
                'gano2'     => $jugado && (int) $p['id_ganador'] === (int) $p['id_entrante_2'],
            ];

        }

        $salida[] = ['nombre' => $g['nombre'], 'partidos' => $lista];

    }

    return $salida;

}
