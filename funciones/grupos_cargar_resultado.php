<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("estado_grupos.php");
require_once("standings.php");
require_once("ranking_puntos.php");


if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['id_rol'], [1, 2], true)) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

$id_torneo = intval($_POST['id_torneo'] ?? 0);

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=token");
    exit;
}

$esAdmin = ($_SESSION['id_rol'] == 1);
$id_partido = intval($_POST['id_partido'] ?? 0);
$tipo = $_POST['tipo'] ?? '';

$set1_1 = $_POST['set1_1'] ?? null;
$set1_2 = $_POST['set1_2'] ?? null;
$set2_1 = $_POST['set2_1'] ?? null;
$set2_2 = $_POST['set2_2'] ?? null;
$set3_1 = ($_POST['set3_1'] ?? '') === '' ? null : $_POST['set3_1'];
$set3_2 = ($_POST['set3_2'] ?? '') === '' ? null : $_POST['set3_2'];

// W.O.: 'wo' = 1 o 2 es el lado que NO se presentó (pierde). Vacío = partido normal con sets.
$woPerdedor = (string) ($_POST['wo'] ?? '');

if ($woPerdedor !== '' && !in_array($woPerdedor, ['1', '2'], true)) {
    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=datos_invalidos");
    exit;
}

$esWo = ($woPerdedor !== '');

if (
    $id_torneo <= 0 ||
    $id_partido <= 0 ||
    !in_array($tipo, ['inscripcion', 'pareja'], true)
) {
    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=datos_invalidos");
    exit;
}

// Vuelve a la página posicionada en el mismo partido, con el aviso al lado.
$volverAlPartido = "&partido=$id_partido#partido-$id_partido";

$tablaPartidos = ($tipo === 'inscripcion') ? 'partido_individual' : 'partido_pareja';
$tablaEntrantes = ($tipo === 'inscripcion') ? 'grupo_inscripcion' : 'grupo_pareja';
$colEntrante = ($tipo === 'inscripcion') ? 'id_grupo_inscripcion' : 'id_grupo_pareja';


mysqli_begin_transaction($conexion);

try {

    $stmtPartido = mysqli_prepare(
        $conexion,
        "SELECT p.id_partido, p.id_entrante_1, p.id_entrante_2, g.id_torneo, c.id_usuario AS id_organizador
         FROM $tablaPartidos p
         INNER JOIN $tablaEntrantes e ON e.$colEntrante = p.id_entrante_1
         INNER JOIN grupo g ON g.id_grupo = e.id_grupo
         INNER JOIN torneos t ON t.id_torneo = g.id_torneo
         INNER JOIN club c ON c.id_club = t.id_club
         WHERE p.id_partido = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtPartido, "i", $id_partido);
    mysqli_stmt_execute($stmtPartido);
    $partido = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPartido));

    if (!$partido || (int) $partido['id_torneo'] !== $id_torneo) {
        throw new Exception("partido_no_existe");
    }

    if (!$esAdmin && (int) $partido['id_organizador'] !== (int) $_SESSION['id_usuario']) {
        throw new Exception("sin_permiso");
    }

    // El estado del torneo se lee de la base: según el estado hay acciones que ya no corresponden.
    $estadoTorneoActual = exigirEstadoParaGrupos($conexion, $id_torneo, 'resultados');

    // Si los puntos de este torneo ya provocaron ascensos de categoría, los resultados quedan fijos.
    // Mientras no haya ascensos se puede corregir un resultado: los puntos se recalculan solos.
    if (torneoConAscensos($conexion, $id_torneo)) {
        throw new Exception("torneo_liquidado");
    }

    if ($esWo) {

        // Gana quien se presentó. Sin sets: no se inventa un marcador.
        $idGanador = ($woPerdedor === '1')
            ? (int) $partido['id_entrante_2']
            : (int) $partido['id_entrante_1'];

        $stmtWo = mysqli_prepare(
            $conexion,
            "UPDATE $tablaPartidos
             SET set1_games_1 = NULL, set1_games_2 = NULL,
                 set2_games_1 = NULL, set2_games_2 = NULL,
                 set3_games_1 = NULL, set3_games_2 = NULL,
                 id_ganador = ?, resultado_tipo = 'WO', estado = 'Jugado', fecha_resultado = NOW()
             WHERE id_partido = ?"
        );
        mysqli_stmt_bind_param($stmtWo, "ii", $idGanador, $id_partido);

        if (!mysqli_stmt_execute($stmtWo)) {
            throw new Exception("error_bd");
        }

        // Los puntos del ranking se suman en el acto (misma transacción que el resultado).
        sincronizarPuntosTorneo($conexion, $id_torneo, (int) $_SESSION['id_usuario']);

        actividadResultadoConfirmado($conexion, $tablaPartidos, $id_partido, $id_torneo);

        mysqli_commit($conexion);

        header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=resultado_wo_cargado$volverAlPartido");
        exit;

    }

    $resultado = calcularResultadoSets($set1_1, $set1_2, $set2_1, $set2_2, $set3_1, $set3_2);

    if (!$resultado['valido']) {
        throw new Exception($resultado['error']);
    }

    $idGanador = ($resultado['ganador'] === 1)
        ? (int) $partido['id_entrante_1']
        : (int) $partido['id_entrante_2'];

    $stmtGuardar = mysqli_prepare(
        $conexion,
        "UPDATE $tablaPartidos
         SET set1_games_1 = ?, set1_games_2 = ?,
             set2_games_1 = ?, set2_games_2 = ?,
             set3_games_1 = ?, set3_games_2 = ?,
             id_ganador = ?, resultado_tipo = 'Normal', estado = 'Jugado', fecha_resultado = NOW()
         WHERE id_partido = ?"
    );

    $s1_1 = (int) $set1_1;
    $s1_2 = (int) $set1_2;
    $s2_1 = (int) $set2_1;
    $s2_2 = (int) $set2_2;
    $s3_1 = ($set3_1 === null) ? null : (int) $set3_1;
    $s3_2 = ($set3_2 === null) ? null : (int) $set3_2;

    mysqli_stmt_bind_param(
        $stmtGuardar,
        "iiiiiiii",
        $s1_1, $s1_2, $s2_1, $s2_2, $s3_1, $s3_2, $idGanador, $id_partido
    );

    if (!mysqli_stmt_execute($stmtGuardar)) {
        throw new Exception("error_bd");
    }

    // Los puntos del ranking se suman en el acto (misma transacción que el resultado).
    sincronizarPuntosTorneo($conexion, $id_torneo, (int) $_SESSION['id_usuario']);

    actividadResultadoConfirmado($conexion, $tablaPartidos, $id_partido, $id_torneo);

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=resultado_cargado$volverAlPartido");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    $motivosConocidos = [
        'partido_no_existe', 'sin_permiso',
        'faltan_sets', 'set_incompleto', 'set_invalido',
        'falta_tercer_set', 'set_sobrante', 'resultado_inconcluso', 'torneo_liquidado', 'torneo_finalizado_grupos',
    ];

    if (!in_array($motivo, $motivosConocidos, true)) {
        error_log("Error al cargar resultado del partido $id_partido: " . $motivo);
        $motivo = 'error_bd';
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo) . $volverAlPartido);
    exit;

}
