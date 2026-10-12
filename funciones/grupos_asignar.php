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
$id_grupo = intval($_POST['id_grupo'] ?? 0);
$tipo = $_POST['tipo'] ?? '';
$id_elemento = intval($_POST['id_elemento'] ?? 0);

if (
    $id_torneo <= 0 ||
    $id_grupo <= 0 ||
    $id_elemento <= 0 ||
    !in_array($tipo, ['inscripcion', 'pareja'], true)
) {
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

    // El grupo debe existir y pertenecer a este mismo torneo.
    $stmtGrupo = mysqli_prepare(
        $conexion,
        "SELECT id_grupo FROM grupo WHERE id_grupo = ? AND id_torneo = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmtGrupo, "ii", $id_grupo, $id_torneo);
    mysqli_stmt_execute($stmtGrupo);

    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmtGrupo))) {
        throw new Exception("grupo_no_existe");
    }

    // Coherencia de modalidad antes de intentar el INSERT (el trigger
    // también lo valida, pero así damos un mensaje más claro).
    $esIndividual = ($torneo['tipo'] === 'Individual');

    if (($tipo === 'inscripcion' && !$esIndividual) || ($tipo === 'pareja' && $esIndividual)) {
        throw new Exception("modalidad_incompatible");
    }

    if ($tipo === 'inscripcion') {

        $stmtInsc = mysqli_prepare(
            $conexion,
            "SELECT id_inscripcion FROM inscripcion
             WHERE id_inscripcion = ? AND id_torneo = ? AND estado = 'Confirmada'
             FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmtInsc, "ii", $id_elemento, $id_torneo);
        mysqli_stmt_execute($stmtInsc);

        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmtInsc))) {
            throw new Exception("elemento_no_disponible");
        }

        $stmtAsignar = mysqli_prepare(
            $conexion,
            "INSERT INTO grupo_inscripcion (id_grupo, id_inscripcion) VALUES (?, ?)"
        );
        mysqli_stmt_bind_param($stmtAsignar, "ii", $id_grupo, $id_elemento);

    } else {

        $stmtPareja = mysqli_prepare(
            $conexion,
            "SELECT id_pareja FROM pareja WHERE id_pareja = ? AND id_torneo = ? FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmtPareja, "ii", $id_elemento, $id_torneo);
        mysqli_stmt_execute($stmtPareja);

        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPareja))) {
            throw new Exception("elemento_no_disponible");
        }

        $stmtAsignar = mysqli_prepare(
            $conexion,
            "INSERT INTO grupo_pareja (id_grupo, id_pareja) VALUES (?, ?)"
        );
        mysqli_stmt_bind_param($stmtAsignar, "ii", $id_grupo, $id_elemento);

    }

    if (!mysqli_stmt_execute($stmtAsignar)) {

        if (mysqli_errno($conexion) === 1062) {
            throw new Exception("ya_asignado");
        }

        throw new Exception("error_bd");

    }

    mysqli_commit($conexion);

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=asignado");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    $motivo = $e->getMessage();

    if ($motivo === 'error_bd') {
        error_log("Error al asignar $tipo $id_elemento al grupo $id_grupo: " . mysqli_error($conexion));
    }

    header("Location: ../gestionar_grupos.php?id_torneo=$id_torneo&accion=" . urlencode($motivo));
    exit;

}
