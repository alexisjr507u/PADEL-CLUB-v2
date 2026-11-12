<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("estado_grupos.php");
require_once("descalificacion.php");
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
    $estadoTorneoActual = exigirEstadoParaGrupos($conexion, $id_torneo, 'partidos');

    $esIndividual = ($torneo['tipo'] === 'Individual');
    $tablaPartidos = $esIndividual ? 'partido_individual' : 'partido_pareja';

    $stmtGrupos = mysqli_prepare(
        $conexion,
        "SELECT id_grupo FROM grupo WHERE id_torneo = ? ORDER BY orden ASC, id_grupo ASC FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtGrupos, "i", $id_torneo);
    mysqli_stmt_execute($stmtGrupos);
    $grupos = mysqli_fetch_all(mysqli_stmt_get_result($stmtGrupos), MYSQLI_ASSOC);

    if (empty($grupos)) {
        throw new Exception("grupo_no_existe");
    }

    $stmtInsertar = mysqli_prepare(
        $conexion,
        "INSERT INTO $tablaPartidos (id_grupo, fase, orden, id_entrante_1, id_entrante_2)
         VALUES (?, 'Grupos', ?, ?, ?)"
    );

    // Antes de empezar (Abierto/Cerrado) se puede rearmar el fixture de los grupos sin resultados.
    // Con el torneo En curso nunca se regenera ni se borra nada: solo se arman grupos que no tengan partidos.
    $regenerable = in_array($estadoTorneoActual, ESTADOS_ANTES_DE_EMPEZAR, true);
    $gruposArmados = 0;

    foreach ($grupos as $filaGrupo) {

        $idGrupoActual = (int) $filaGrupo['id_grupo'];

        $stmtExistentes = mysqli_prepare(
            $conexion,
            "SELECT id_partido, estado FROM $tablaPartidos WHERE id_grupo = ? AND fase = 'Grupos' FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmtExistentes, "i", $idGrupoActual);
        mysqli_stmt_execute($stmtExistentes);
        $existentes = mysqli_fetch_all(mysqli_stmt_get_result($stmtExistentes), MYSQLI_ASSOC);

        if (!empty($existentes) && !$regenerable) {
            continue;
        }

        // Si ya hay algún resultado cargado en este grupo, lo dejamos como está
        // (no se pisan resultados ya jugados al regenerar el fixture completo).
        $tieneResultados = false;
        foreach ($existentes as $partidoExistente) {
            if ($partidoExistente['estado'] === 'Jugado') {
                $tieneResultados = true;
                break;
            }
        }

        if ($tieneResultados) {
            continue;
        }

        $datos = obtenerMiembrosYPartidosDeGrupo($conexion, $idGrupoActual, $esIndividual);
        // Los descalificados no juegan partidos nuevos.
        $miembros = miembrosHabilitados($datos['miembros']);

        if (count($miembros) < 2) {
            continue;
        }

        if (!empty($existentes)) {
            mysqli_query($conexion, "DELETE FROM $tablaPartidos WHERE id_grupo = $idGrupoActual AND fase = 'Grupos'");
        }

        $orden = 0;
        $cantidadMiembros = count($miembros);
        $gruposArmados++;

        for ($i = 0; $i < $cantidadMiembros; $i++) {
            for ($j = $i + 1; $j < $cantidadMiembros; $j++) {

                $orden++;
                $idEntrante1 = (int) $miembros[$i]['id_entrante'];
                $idEntrante2 = (int) $miembros[$j]['id_entrante'];

                mysqli_stmt_bind_param($stmtInsertar, "iiii", $idGrupoActual, $orden, $idEntrante1, $idEntrante2);

                if (!mysqli_stmt_execute($stmtInsertar)) {
                    throw new Exception("error_bd");
                }

            }
        }

    }

    // En curso, si todos los grupos ya tenían sus partidos no había nada que generar.
    if (!$regenerable && $gruposArmados === 0) {
        throw new Exception("partidos_ya_generados");
    }

    actividadFaseGenerada($conexion, $id_torneo, 'los partidos de la fase de grupos');

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=partidos_generados");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al generar partidos del torneo $id_torneo: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
