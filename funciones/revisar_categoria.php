<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");


// ==========================================
// SEGURIDAD: solo el administrador (id_rol = 1)
// Se valida en el servidor en cada petición.
// ==========================================

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit;
}

if ((int) ($_SESSION['id_rol'] ?? 0) !== 1) {
    http_response_code(403);
    exit("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=revision_categorias");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../paneles.php?seccion=revision_categorias&accion=token");
    exit;
}

$idRevisor = (int) $_SESSION['id_usuario'];
$idUsuario = (int) ($_POST['id_usuario'] ?? 0);
$accion = $_POST['accion'] ?? '';

if ($idUsuario <= 0 || !in_array($accion, ['confirmar', 'asignar'], true)) {
    header("Location: ../paneles.php?seccion=revision_categorias&accion=datos_invalidos");
    exit;
}

mysqli_begin_transaction($conexion);

try {

    // Solo se revisan participantes con clasificación pendiente.
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT u.id_rol, u.categoria_estado, cc.id_categoria_estimada
         FROM usuario u
         INNER JOIN clasificacion_categoria cc ON cc.id_usuario = u.id_usuario
         WHERE u.id_usuario = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmt, "i", $idUsuario);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$fila || (int) $fila['id_rol'] !== 3) {
        throw new Exception("no_existe");
    }

    if ($fila['categoria_estado'] !== 'Pendiente') {
        throw new Exception("no_pendiente");
    }

    $estimada = (int) $fila['id_categoria_estimada'];

    if ($accion === 'confirmar') {

        $idFinal = $estimada;

    } else {

        $idFinal = (int) ($_POST['id_categoria'] ?? 0);

        $stmtCat = mysqli_prepare($conexion, "SELECT id_categoria FROM categoria WHERE id_categoria = ?");
        mysqli_stmt_bind_param($stmtCat, "i", $idFinal);
        mysqli_stmt_execute($stmtCat);

        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCat))) {
            throw new Exception("categoria_invalida");
        }

    }

    $decision = ($idFinal === $estimada) ? 'Confirmada' : 'Modificada';

    $stmtUsuario = mysqli_prepare(
        $conexion,
        "UPDATE usuario SET id_categoria = ?, categoria_estado = 'Confirmada'
         WHERE id_usuario = ? AND categoria_estado = 'Pendiente'"
    );
    mysqli_stmt_bind_param($stmtUsuario, "ii", $idFinal, $idUsuario);

    if (!mysqli_stmt_execute($stmtUsuario) || mysqli_stmt_affected_rows($stmtUsuario) !== 1) {
        throw new Exception("error_bd");
    }

    $stmtClasif = mysqli_prepare(
        $conexion,
        "UPDATE clasificacion_categoria
         SET decision = ?, id_categoria_final = ?, id_revisor = ?, fecha_revision = NOW()
         WHERE id_usuario = ?"
    );
    mysqli_stmt_bind_param($stmtClasif, "siii", $decision, $idFinal, $idRevisor, $idUsuario);

    if (!mysqli_stmt_execute($stmtClasif)) {
        throw new Exception("error_bd");
    }

    mysqli_commit($conexion);

    header("Location: ../paneles.php?seccion=revision_categorias&accion=" . ($decision === 'Confirmada' ? 'categoria_confirmada' : 'categoria_modificada'));
    exit;

} catch (Exception $e) {

    mysqli_rollback($conexion);

    if ($e->getMessage() === 'error_bd') {
        error_log("Error al revisar la categoría del usuario $idUsuario: " . mysqli_error($conexion));
    }

    header("Location: ../paneles.php?seccion=revision_categorias&accion=" . urlencode($e->getMessage()));
    exit;

}
