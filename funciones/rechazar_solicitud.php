<?php

include("../config/no_cache.php");
include("../config/conexion.php");

session_start();

/* ==========================================
   VERIFICAR ADMINISTRADOR
========================================== */

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {

    header("Location: ../login.php");
    exit();

}


/* ==========================================
   RECIBIR ID DE SOLICITUD
========================================== */

if (!isset($_GET['id_solicitud'])) {

    header("Location: ../paneles.php?seccion=solicitudes");
    exit();

}

$id_solicitud = intval($_GET['id_solicitud']);


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

    die(
        "Error al preparar la consulta: "
        . mysqli_error($conexion)
    );

}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_solicitud
);


if (!mysqli_stmt_execute($stmt)) {

    die(
        "Error al rechazar la solicitud: "
        . mysqli_stmt_error($stmt)
    );

}


/* ==========================================
   VOLVER AL PANEL
========================================== */

header(
    "Location: ../paneles.php?seccion=solicitudes"
);

exit();

?>