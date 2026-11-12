<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("estado_grupos.php");
require_once("descalificacion.php");
require_once("ranking_puntos.php");
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

    // El estado del torneo se lee de la base: según el estado hay acciones que ya no corresponden.
    $estadoTorneoActual = exigirEstadoParaGrupos($conexion, $id_torneo, 'rondas');

    $esIndividual = ($torneo['tipo'] === 'Individual');
    $tablaPartidos = $esIndividual ? 'partido_individual' : 'partido_pareja';

    $stmtGrupos = mysqli_prepare(
        $conexion,
        "SELECT id_grupo FROM grupo WHERE id_torneo = ? ORDER BY orden ASC, id_grupo ASC FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtGrupos, "i", $id_torneo);
    mysqli_stmt_execute($stmtGrupos);
    $grupos = mysqli_fetch_all(mysqli_stmt_get_result($stmtGrupos), MYSQLI_ASSOC);

    if (count($grupos) !== 2) {
        throw new Exception("requiere_dos_grupos");
    }

    $stmtYaExiste = mysqli_prepare(
        $conexion,
        "SELECT id_partido FROM $tablaPartidos WHERE fase = 'Semifinal' AND id_entrante_1 IN (
            SELECT id_grupo_" . ($esIndividual ? 'inscripcion' : 'pareja') . "
            FROM " . ($esIndividual ? 'grupo_inscripcion' : 'grupo_pareja') . "
            WHERE id_grupo IN (?, ?)
         ) LIMIT 1"
    );
    $idGrupoA = (int) $grupos[0]['id_grupo'];
    $idGrupoB = (int) $grupos[1]['id_grupo'];
    mysqli_stmt_bind_param($stmtYaExiste, "ii", $idGrupoA, $idGrupoB);
    mysqli_stmt_execute($stmtYaExiste);

    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtYaExiste))) {
        throw new Exception("semifinales_ya_generadas");
    }

    $standingsPorGrupo = [];

    foreach ([$idGrupoA, $idGrupoB] as $idGrupo) {

        $datos = obtenerMiembrosYPartidosDeGrupo($conexion, $idGrupo, $esIndividual);

        if (count($datos['miembros']) < 2) {
            throw new Exception("grupos_incompletos");
        }

        foreach ($datos['partidos'] as $partido) {
            if ($partido['estado'] !== 'Jugado') {
                throw new Exception("grupos_incompletos");
            }
        }

        $standingsPorGrupo[] = calcularStandings($datos['miembros'], $datos['partidos']);

    }

    // Los descalificados no pasan de fase: clasifican los 2 mejores que siguen en el torneo.
    $clasificadosA = clasificadosDeTabla($standingsPorGrupo[0]);
    $clasificadosB = clasificadosDeTabla($standingsPorGrupo[1]);

    if (count($clasificadosA) < CLASIFICAN_POR_GRUPO || count($clasificadosB) < CLASIFICAN_POR_GRUPO) {
        throw new Exception("grupos_incompletos");
    }

    $primeroA = $clasificadosA[0];
    $segundoA = $clasificadosA[1];
    $primeroB = $clasificadosB[0];
    $segundoB = $clasificadosB[1];

    $stmtInsertar = mysqli_prepare(
        $conexion,
        "INSERT INTO $tablaPartidos (id_grupo, fase, orden, id_entrante_1, id_entrante_2)
         VALUES (NULL, 'Semifinal', ?, ?, ?)"
    );

    $orden = 1;
    mysqli_stmt_bind_param($stmtInsertar, "iii", $orden, $primeroA, $segundoB);
    if (!mysqli_stmt_execute($stmtInsertar)) {
        throw new Exception("error_bd");
    }

    $orden = 2;
    mysqli_stmt_bind_param($stmtInsertar, "iii", $orden, $primeroB, $segundoA);
    if (!mysqli_stmt_execute($stmtInsertar)) {
        throw new Exception("error_bd");
    }

    resolverPartidosConDescalificados($conexion, $id_torneo);
    sincronizarPuntosTorneo($conexion, $id_torneo, (int) $_SESSION['id_usuario']);

    actividadFaseGenerada($conexion, $id_torneo, 'las semifinales');

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=semifinales_generadas");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al generar semifinales del torneo $id_torneo: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
