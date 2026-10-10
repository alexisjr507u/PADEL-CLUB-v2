<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");

/* ==========================================
   VERIFICAR ADMINISTRADOR
========================================== */

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {

    header("Location: ../login.php");
    exit();

}


/* ==========================================
   VERIFICAR MÉTODO Y TOKEN CSRF
========================================== */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !verificar_csrf_token($_POST['csrf_token'] ?? '')
) {

    header("Location: ../paneles.php?seccion=solicitudes");
    exit();

}


/* ==========================================
   RECIBIR ID DE SOLICITUD
========================================== */

if (!isset($_POST['id_solicitud'])) {

    header("Location: ../paneles.php?seccion=solicitudes");
    exit();

}

$id_solicitud = intval($_POST['id_solicitud']);


/* ==========================================
   RECHAZAR SOLICITUD
========================================== */

$sql = "
    UPDATE solicitud_organizador
    SET estado_solicitud = 'Rechazada'
    WHERE id_solicitud = ?
    AND estado_solicitud = 'Pendiente'
";


$stmt = mysqli_prepare(
    $conexion,
    $sql
);


if (!$stmt) {

    error_log("Error al preparar consulta de rechazo: " . mysqli_error($conexion));

    die("Ocurrió un error. Intentá nuevamente.");

}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_solicitud
);


if (!mysqli_stmt_execute($stmt)) {

    error_log("Error al rechazar solicitud: " . mysqli_stmt_error($stmt));

    die("Ocurrió un error. Intentá nuevamente.");

}


/* ==========================================
   VOLVER AL PANEL
========================================== */

header(
    "Location: ../paneles.php?seccion=solicitudes"
);

exit();

?>