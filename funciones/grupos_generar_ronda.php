<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("standings.php");

/* =========================================================
   CUADRO DE ELIMINACIÓN DIRECTA PARA 4 U 8 GRUPOS
   (con 2 grupos se sigue usando grupos_generar_semifinales.php / _final.php)

   Clasifican los 2 primeros de cada grupo. Cada llamada genera UNA ronda:

   1. Primera ronda (Octavos con 8 grupos, Cuartos con 4), cruzada por pares
      de grupos consecutivos (A-B, C-D, ...): 1°A-2°B, 1°B-2°A, 1°C-2°D, 1°D-2°C, ...
      Los ganadores de 1°A-2°B y 1°B-2°A se enfrentan entre sí en la ronda siguiente.
   2. Rondas siguientes: los ganadores de la ronda anterior se emparejan
      en el orden del cuadro (partido 1 con 2, 3 con 4, ...).
   ========================================================= */

const ORDEN_RONDAS = ['Octavos', 'Cuartos', 'Semifinal', 'Final'];


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

    $stmtGrupos = mysqli_prepare(
        $conexion,
        "SELECT id_grupo FROM grupo WHERE id_torneo = ? ORDER BY orden ASC, id_grupo ASC FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtGrupos, "i", $id_torneo);
    mysqli_stmt_execute($stmtGrupos);
    $grupos = mysqli_fetch_all(mysqli_stmt_get_result($stmtGrupos), MYSQLI_ASSOC);

    $cantidadGrupos = count($grupos);

    if ($cantidadGrupos !== 4 && $cantidadGrupos !== 8) {
        throw new Exception("cuadro_no_soportado");
    }

    // Partidos eliminatorios ya generados, por ronda.
    $stmtKO = mysqli_prepare(
        $conexion,
        "SELECT p.fase, p.orden, p.id_entrante_1, p.id_entrante_2, p.id_ganador, p.estado
         FROM $tablaPartidos p
         INNER JOIN $tablaEntrantes e ON e.$colEntrante = p.id_entrante_1
         INNER JOIN grupo g ON g.id_grupo = e.id_grupo
         WHERE g.id_torneo = ? AND p.fase IN ('Octavos', 'Cuartos', 'Semifinal', 'Final')
         ORDER BY p.orden ASC"
    );
    mysqli_stmt_bind_param($stmtKO, "i", $id_torneo);
    mysqli_stmt_execute($stmtKO);

    $porRonda = [];
    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmtKO), MYSQLI_ASSOC) as $p) {
        $porRonda[$p['fase']][] = $p;
    }

    $rondaInicial = ($cantidadGrupos === 8) ? 'Octavos' : 'Cuartos';
    $cruces = [];   // lista de [id_entrante_1, id_entrante_2] en orden
    $rondaNueva = null;

    if (empty($porRonda)) {

        // ---- Primera ronda: se necesitan todos los grupos terminados ----
        $primeros = [];
        $segundos = [];

        foreach ($grupos as $i => $g) {

            $datos = obtenerMiembrosYPartidosDeGrupo($conexion, (int) $g['id_grupo'], $esIndividual);

            if (count($datos['miembros']) < 2 || empty($datos['partidos'])) {
                throw new Exception("grupos_incompletos");
            }

            foreach ($datos['partidos'] as $partido) {
                if ($partido['estado'] !== 'Jugado') {
                    throw new Exception("grupos_incompletos");
                }
            }

            $tabla = calcularStandings($datos['miembros'], $datos['partidos']);

            $primeros[$i] = (int) $tabla[0]['id_entrante'];
            $segundos[$i] = (int) $tabla[1]['id_entrante'];

        }

        // Cada par de grupos aporta dos cruces consecutivos (1°A-2°B y 1°B-2°A), de modo
        // que sus ganadores se enfrentan entre sí en la ronda siguiente. Así cada mitad
        // del cuadro reúne a un conjunto de grupos (A-D a la izquierda, E-H a la derecha).
        for ($i = 0; $i < $cantidadGrupos; $i += 2) {
            $cruces[] = [$primeros[$i], $segundos[$i + 1]];
            $cruces[] = [$primeros[$i + 1], $segundos[$i]];
        }

        $rondaNueva = $rondaInicial;

    } else {

        // ---- Ronda siguiente: ganadores de la última ronda generada ----
        $ultima = null;
        foreach (ORDEN_RONDAS as $r) {
            if (!empty($porRonda[$r])) {
                $ultima = $r;
            }
        }

        if ($ultima === 'Final') {
            throw new Exception("cuadro_completo");
        }

        $indiceNueva = array_search($ultima, ORDEN_RONDAS, true) + 1;
        $rondaNueva = ORDEN_RONDAS[$indiceNueva];

        if (!empty($porRonda[$rondaNueva])) {
            throw new Exception("ronda_ya_generada");
        }

        $ganadores = [];

        foreach ($porRonda[$ultima] as $p) {

            if ($p['estado'] !== 'Jugado' || empty($p['id_ganador'])) {
                throw new Exception("ronda_incompleta");
            }

            $ganadores[] = (int) $p['id_ganador'];

        }

        for ($i = 0; $i + 1 < count($ganadores); $i += 2) {
            $cruces[] = [$ganadores[$i], $ganadores[$i + 1]];
        }

    }

    if (empty($cruces)) {
        throw new Exception("error_bd");
    }

    $stmtInsertar = mysqli_prepare(
        $conexion,
        "INSERT INTO $tablaPartidos (id_grupo, fase, orden, id_entrante_1, id_entrante_2)
         VALUES (NULL, ?, ?, ?, ?)"
    );

    foreach ($cruces as $k => [$a, $b]) {

        $orden = $k + 1;
        mysqli_stmt_bind_param($stmtInsertar, "siii", $rondaNueva, $orden, $a, $b);

        if (!mysqli_stmt_execute($stmtInsertar)) {
            throw new Exception("error_bd");
        }

    }

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=ronda_generada&ronda=" . urlencode($rondaNueva) . "#fase-final");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al generar la ronda del torneo $id_torneo: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo) . "#fase-final");
    exit;

}
