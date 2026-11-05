<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("standings.php");


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
$id_grupo = intval($_POST['id_grupo'] ?? 0);

if ($id_torneo <= 0 || $id_grupo <= 0) {
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

    $stmtGrupo = mysqli_prepare(
        $conexion,
        "SELECT id_grupo FROM grupo WHERE id_grupo = ? AND id_torneo = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtGrupo, "ii", $id_grupo, $id_torneo);
    mysqli_stmt_execute($stmtGrupo);

    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmtGrupo))) {
        throw new Exception("grupo_no_existe");
    }

    $esIndividual = ($torneo['tipo'] === 'Individual');
    $tablaPartidos = $esIndividual ? 'partido_individual' : 'partido_pareja';

    $stmtExistentes = mysqli_prepare(
        $conexion,
        "SELECT id_partido, estado FROM $tablaPartidos WHERE id_grupo = ? AND fase = 'Grupos' FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtExistentes, "i", $id_grupo);
    mysqli_stmt_execute($stmtExistentes);
    $existentes = mysqli_fetch_all(mysqli_stmt_get_result($stmtExistentes), MYSQLI_ASSOC);

    foreach ($existentes as $partidoExistente) {
        if ($partidoExistente['estado'] === 'Jugado') {
            throw new Exception("partidos_con_resultados");
        }
    }

    $datos = obtenerMiembrosYPartidosDeGrupo($conexion, $id_grupo, $esIndividual);
    $miembros = $datos['miembros'];

    if (count($miembros) < 2) {
        throw new Exception("miembros_insuficientes");
    }

    if (!empty($existentes)) {
        mysqli_query($conexion, "DELETE FROM $tablaPartidos WHERE id_grupo = $id_grupo AND fase = 'Grupos'");
    }

    $stmtInsertar = mysqli_prepare(
        $conexion,
        "INSERT INTO $tablaPartidos (id_grupo, fase, orden, id_entrante_1, id_entrante_2)
         VALUES (?, 'Grupos', ?, ?, ?)"
    );

    $orden = 0;
    $cantidadMiembros = count($miembros);

    for ($i = 0; $i < $cantidadMiembros; $i++) {
        for ($j = $i + 1; $j < $cantidadMiembros; $j++) {

            $orden++;
            $idEntrante1 = (int) $miembros[$i]['id_entrante'];
            $idEntrante2 = (int) $miembros[$j]['id_entrante'];

            mysqli_stmt_bind_param($stmtInsertar, "iiii", $id_grupo, $orden, $idEntrante1, $idEntrante2);

            if (!mysqli_stmt_execute($stmtInsertar)) {
                throw new Exception("error_bd");
            }

        }
    }

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=partidos_generados");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al generar partidos del grupo $id_grupo: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
