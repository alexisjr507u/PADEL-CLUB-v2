<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");


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

if ($id_torneo <= 0) {
    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=datos_invalidos");
    exit;
}


mysqli_begin_transaction($conexion);

try {

    $stmtTorneo = mysqli_prepare(
        $conexion,
        "SELECT t.tipo, c.id_usuario AS id_organizador
         FROM torneos t
         INNER JOIN club c ON c.id_club = t.id_club
         WHERE t.id_torneo = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtTorneo, "i", $id_torneo);
    mysqli_stmt_execute($stmtTorneo);
    $torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTorneo));

    if (!$torneo) {
        throw new Exception("no_existe");
    }

    if (!$esAdmin && (int) $torneo['id_organizador'] !== (int) $_SESSION['id_usuario']) {
        throw new Exception("sin_permiso");
    }

    $esIndividual = ($torneo['tipo'] === 'Individual');
    $tablaPartidos = $esIndividual ? 'partido_individual' : 'partido_pareja';
    $tablaEntrantes = $esIndividual ? 'grupo_inscripcion' : 'grupo_pareja';
    $colEntrante = $esIndividual ? 'id_grupo_inscripcion' : 'id_grupo_pareja';

    $stmtSemis = mysqli_prepare(
        $conexion,
        "SELECT p.id_partido, p.orden, p.estado, p.id_entrante_1, p.id_entrante_2, p.id_ganador
         FROM $tablaPartidos p
         INNER JOIN $tablaEntrantes e ON e.$colEntrante = p.id_entrante_1
         INNER JOIN grupo g ON g.id_grupo = e.id_grupo
         WHERE p.fase = 'Semifinal' AND g.id_torneo = ?
         ORDER BY p.orden ASC
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtSemis, "i", $id_torneo);
    mysqli_stmt_execute($stmtSemis);
    $semifinales = mysqli_fetch_all(mysqli_stmt_get_result($stmtSemis), MYSQLI_ASSOC);

    if (count($semifinales) !== 2) {
        throw new Exception("requiere_semifinales");
    }

    foreach ($semifinales as $semi) {
        if ($semi['estado'] !== 'Jugado' || !$semi['id_ganador']) {
            throw new Exception("semifinales_incompletas");
        }
    }

    // Chequeamos si ya existe una Final generada para estos dos ganadores.
    $idGanador1 = (int) $semifinales[0]['id_ganador'];
    $idGanador2 = (int) $semifinales[1]['id_ganador'];

    $stmtYaExisteFinal = mysqli_prepare(
        $conexion,
        "SELECT id_partido FROM $tablaPartidos
         WHERE fase = 'Final'
           AND (id_entrante_1 IN (?, ?) OR id_entrante_2 IN (?, ?))
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmtYaExisteFinal, "iiii", $idGanador1, $idGanador2, $idGanador1, $idGanador2);
    mysqli_stmt_execute($stmtYaExisteFinal);

    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtYaExisteFinal))) {
        throw new Exception("final_ya_generada");
    }

    $stmtInsertar = mysqli_prepare(
        $conexion,
        "INSERT INTO $tablaPartidos (id_grupo, fase, orden, id_entrante_1, id_entrante_2)
         VALUES (NULL, 'Final', 1, ?, ?)"
    );
    mysqli_stmt_bind_param($stmtInsertar, "ii", $idGanador1, $idGanador2);

    if (!mysqli_stmt_execute($stmtInsertar)) {
        throw new Exception("error_bd");
    }

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=final_generada");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al generar la final del torneo $id_torneo: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
