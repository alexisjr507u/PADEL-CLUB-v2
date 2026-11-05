<?php

require_once __DIR__ . '/ranking_puntos.php';

/* =========================================================
   TEMPORADAS DEL RANKING: "Finalizar temporada"

   Al finalizar la temporada:
   - El ÚLTIMO del ranking de cada categoría desciende una categoría
     (la más baja, Principiante, no puede descender).
   - Los puntos de todos vuelven a 0: los puntos de la temporada cerrada
     no se borran (siguen en ranking_puntos con su id_temporada), solo dejan
     de contar en el ranking vigente.
   - Se abre la temporada siguiente.

   Quién cuenta como "último" (por categoría):
   - Solo quienes jugaron al menos un torneo en la temporada (quien no compitió
     no es "último": no se lo castiga por no haber jugado).
   - Tiene que haber al menos 2 competidores y el último no puede estar
     empatado con el primero.
   - Si el último está empatado en TODOS los criterios con otros, descienden todos
     los que comparten esa última posición.
   ========================================================= */


/**
 * Quiénes descenderían si se finalizara la temporada ahora. No escribe nada.
 * @return array lista de ['id_usuario','nombre','de','de_nombre','a','a_nombre','puntos','posicion','competidores']
 */
function previsualizarCierreTemporada($conexion) {

    $categorias = mysqli_fetch_all(
        mysqli_query($conexion, "SELECT id_categoria, nombre FROM categoria ORDER BY id_categoria ASC"),
        MYSQLI_ASSOC
    );

    $descensos = [];

    foreach ($categorias as $i => $cat) {

        // La categoría más baja no tiene a dónde descender.
        if ($i === 0) {
            continue;
        }

        $idCat = (int) $cat['id_categoria'];

        $competidores = array_values(array_filter(
            rankingDeCategoria($conexion, $idCat),
            fn($f) => $f['torneos'] >= 1
        ));

        if (count($competidores) < 2) {
            continue;
        }

        $ultima = $competidores[count($competidores) - 1]['posicion'];

        if ($ultima === $competidores[0]['posicion']) {
            continue;
        }

        $inferior = $categorias[$i - 1];

        foreach ($competidores as $f) {

            if ($f['posicion'] !== $ultima) {
                continue;
            }

            $descensos[] = [
                'id_usuario' => $f['id_usuario'],
                'nombre' => $f['nombre_completo'],
                'de' => $idCat,
                'de_nombre' => $cat['nombre'],
                'a' => (int) $inferior['id_categoria'],
                'a_nombre' => $inferior['nombre'],
                'puntos' => $f['puntos'],
                'posicion' => $f['posicion'],
                'competidores' => count($competidores),
            ];

        }

    }

    return $descensos;

}


/**
 * Finaliza la temporada vigente (una sola transacción): aplica los descensos, la cierra y abre la siguiente.
 * Quien llama valida sesión, rol y CSRF.
 *
 * @param int $idTemporadaEsperada la temporada que la persona vio al pulsar el botón; si ya se cerró
 *                                 (doble clic, otro usuario) no se vuelve a cerrar la siguiente.
 * @return array ['ok' => true, 'temporada' => nombre, 'descensos' => [...]] o ['ok' => false, 'error' => código]
 */
function cerrarTemporada($conexion, $idUsuario, $idTemporadaEsperada) {

    mysqli_begin_transaction($conexion);

    try {

        $stmt = mysqli_prepare($conexion, "SELECT id_temporada, nombre FROM temporada WHERE estado = 'Abierta' FOR UPDATE");
        mysqli_stmt_execute($stmt);
        $temporada = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$temporada) {
            throw new Exception('sin_temporada');
        }

        $idTemporada = (int) $temporada['id_temporada'];

        if ($idTemporada !== (int) $idTemporadaEsperada) {
            throw new Exception('temporada_ya_cerrada');
        }

        $descensos = previsualizarCierreTemporada($conexion);
        $ultimoId = (int) mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COALESCE(MAX(id_ranking_puntos), 0) AS m FROM ranking_puntos"))['m'];

        $stmtUsuario = mysqli_prepare($conexion, "SELECT id_categoria FROM usuario WHERE id_usuario = ? FOR UPDATE");
        $stmtHistorial = mysqli_prepare(
            $conexion,
            "INSERT INTO categoria_historial
                (id_usuario, id_categoria_anterior, id_categoria_nueva, tipo, motivo,
                 id_temporada, puntos_alcanzados, ultimo_id_ranking_puntos)
             VALUES (?, ?, ?, 'Descenso por temporada', ?, ?, ?, ?)"
        );
        $stmtCambio = mysqli_prepare($conexion, "UPDATE usuario SET id_categoria = ? WHERE id_usuario = ? AND id_categoria = ?");

        foreach ($descensos as $d) {

            $idU = (int) $d['id_usuario'];

            mysqli_stmt_bind_param($stmtUsuario, "i", $idU);
            mysqli_stmt_execute($stmtUsuario);
            $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUsuario));

            // Si cambió de categoría mientras tanto, no se lo toca.
            if (!$u || (int) $u['id_categoria'] !== $d['de']) {
                continue;
            }

            $motivo = 'Último del ranking de ' . $d['de_nombre'] . ' al finalizar la ' . $temporada['nombre']
                . ' (' . $d['puntos'] . ' puntos, puesto ' . $d['posicion'] . ' de ' . $d['competidores'] . ')';

            mysqli_stmt_bind_param($stmtHistorial, "iiisiii", $idU, $d['de'], $d['a'], $motivo, $idTemporada, $d['puntos'], $ultimoId);
            mysqli_stmt_execute($stmtHistorial);

            mysqli_stmt_bind_param($stmtCambio, "iii", $d['a'], $idU, $d['de']);
            mysqli_stmt_execute($stmtCambio);

            if (mysqli_stmt_affected_rows($stmtCambio) !== 1) {
                throw new Exception('error_bd');
            }

        }

        $stmtCierre = mysqli_prepare(
            $conexion,
            "UPDATE temporada SET estado = 'Cerrada', fecha_cierre = NOW(), id_usuario_cierre = ?
             WHERE id_temporada = ? AND estado = 'Abierta'"
        );
        mysqli_stmt_bind_param($stmtCierre, "ii", $idUsuario, $idTemporada);
        mysqli_stmt_execute($stmtCierre);

        if (mysqli_stmt_affected_rows($stmtCierre) !== 1) {
            throw new Exception('temporada_ya_cerrada');
        }

        $numero = (int) mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM temporada"))['n'] + 1;
        $nombreNueva = 'Temporada ' . $numero;
        $stmtNueva = mysqli_prepare($conexion, "INSERT INTO temporada (nombre) VALUES (?)");
        mysqli_stmt_bind_param($stmtNueva, "s", $nombreNueva);
        mysqli_stmt_execute($stmtNueva);

        mysqli_commit($conexion);

        return ['ok' => true, 'temporada' => $temporada['nombre'], 'nueva' => $nombreNueva, 'descensos' => $descensos];

    } catch (Throwable $e) {

        mysqli_rollback($conexion);

        $codigo = $e->getMessage();

        if (!in_array($codigo, ['sin_temporada', 'temporada_ya_cerrada'], true)) {
            error_log("Error al finalizar la temporada: " . $codigo);
            $codigo = 'error_bd';
        }

        return ['ok' => false, 'error' => $codigo];

    }

}


/** Última temporada cerrada con los descensos que produjo (null si todavía no se cerró ninguna). */
function ultimoCierreTemporada($conexion) {

    $t = mysqli_fetch_assoc(mysqli_query(
        $conexion,
        "SELECT t.id_temporada, t.nombre, t.fecha_cierre, CONCAT(u.nombre, ' ', u.apellido) AS cerro
         FROM temporada t LEFT JOIN usuario u ON u.id_usuario = t.id_usuario_cierre
         WHERE t.estado = 'Cerrada' ORDER BY t.id_temporada DESC LIMIT 1"
    ));

    if (!$t) {
        return null;
    }

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT CONCAT(u.nombre, ' ', u.apellido) AS nombre, ca.nombre AS de, cn.nombre AS a, h.puntos_alcanzados
         FROM categoria_historial h
         INNER JOIN usuario u ON u.id_usuario = h.id_usuario
         INNER JOIN categoria ca ON ca.id_categoria = h.id_categoria_anterior
         INNER JOIN categoria cn ON cn.id_categoria = h.id_categoria_nueva
         WHERE h.id_temporada = ? AND h.tipo = 'Descenso por temporada'
         ORDER BY h.id_categoria_anterior DESC, h.id_historial ASC"
    );
    $idT = (int) $t['id_temporada'];
    mysqli_stmt_bind_param($stmt, "i", $idT);
    mysqli_stmt_execute($stmt);
    $t['descensos'] = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    return $t;

}
