<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");

/* ==========================================
   VERIFICAR QUE SEA ADMINISTRADOR
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
   RECIBIR ID DE LA SOLICITUD
========================================== */

if (!isset($_POST['id_solicitud'])) {

    header("Location: ../paneles.php?seccion=solicitudes");
    exit();

}

$id_solicitud = intval($_POST['id_solicitud']);


/* ==========================================
   INICIAR TRANSACCIÓN
========================================== */

mysqli_begin_transaction($conexion);

try {


    /* ==========================================
       1. BUSCAR SOLICITUD DEL ORGANIZADOR
    ========================================== */

    $sqlSolicitud = "
        SELECT *
        FROM solicitud_organizador
        WHERE id_solicitud = ?
        AND estado_solicitud = 'Pendiente'
    ";

    $stmtSolicitud = mysqli_prepare($conexion, $sqlSolicitud);

    mysqli_stmt_bind_param(
        $stmtSolicitud,
        "i",
        $id_solicitud
    );

    mysqli_stmt_execute($stmtSolicitud);

    $resultadoSolicitud = mysqli_stmt_get_result($stmtSolicitud);

    $solicitud = mysqli_fetch_assoc($resultadoSolicitud);


    if (!$solicitud) {

        throw new Exception("La solicitud no existe o ya fue procesada.");

    }


    /* ==========================================
       2. CREAR USUARIO ORGANIZADOR
    ========================================== */

    $sqlUsuario = "
        INSERT INTO usuario
        (
            nombre,
            apellido,
            fecha_nacimiento,
            telefono,
            email,
            pass,
            id_rol,
            verificado
        )
        VALUES (?, ?, ?, ?, ?, ?, 2, 1)
    ";

    $stmtUsuario = mysqli_prepare($conexion, $sqlUsuario);

    mysqli_stmt_bind_param(
        $stmtUsuario,
        "ssssss",
        $solicitud['nombre'],
        $solicitud['apellido'],
        $solicitud['fecha_nacimiento'],
        $solicitud['telefono'],
        $solicitud['email'],
        $solicitud['pass']
    );

    if (!mysqli_stmt_execute($stmtUsuario)) {

        throw new Exception(
            "No se pudo crear el usuario: " . mysqli_error($conexion)
        );

    }


    /* ==========================================
       OBTENER ID DEL USUARIO
    ========================================== */

    $id_usuario = mysqli_insert_id($conexion);


    /* ==========================================
       3. CREAR CLUB
    ========================================== */

    $sqlClub = "
        INSERT INTO club
        (
            nombre,
            direccion,
            telefono,
            horario,
            cantidad_canchas,
            id_usuario
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ";

    $stmtClub = mysqli_prepare($conexion, $sqlClub);

    mysqli_stmt_bind_param(
        $stmtClub,
        "ssssii",
        $solicitud['nombre_club'],
        $solicitud['direccion'],
        $solicitud['telefono_club'],
        $solicitud['horario'],
        $solicitud['cantidad_canchas'],
        $id_usuario
    );

    if (!mysqli_stmt_execute($stmtClub)) {

        throw new Exception(
            "No se pudo crear el club: " . mysqli_error($conexion)
        );

    }


    /* ==========================================
       OBTENER ID DEL CLUB
    ========================================== */

    $id_club = mysqli_insert_id($conexion);


    /* ==========================================
       4. BUSCAR TODAS LAS CANCHAS
    ========================================== */

    $sqlCanchas = "
        SELECT nombre, estado, imagen
        FROM solicitud_cancha
        WHERE id_solicitud = ?
    ";

    $stmtCanchas = mysqli_prepare($conexion, $sqlCanchas);

    mysqli_stmt_bind_param(
        $stmtCanchas,
        "i",
        $id_solicitud
    );

    mysqli_stmt_execute($stmtCanchas);

    $resultadoCanchas = mysqli_stmt_get_result($stmtCanchas);


    /* ==========================================
       5. CREAR LAS CANCHAS
    ========================================== */

    $sqlCancha = "
        INSERT INTO cancha
        (
            nombre,
            estado,
            imagen,
            id_club
        )
        VALUES (?, ?, ?, ?)
    ";

    $stmtCancha = mysqli_prepare($conexion, $sqlCancha);


    while ($cancha = mysqli_fetch_assoc($resultadoCanchas)) {

        mysqli_stmt_bind_param(
            $stmtCancha,
            "sssi",
            $cancha['nombre'],
            $cancha['estado'],
            $cancha['imagen'],
            $id_club
        );

        if (!mysqli_stmt_execute($stmtCancha)) {

            throw new Exception(
                "No se pudo crear una cancha: " .
                mysqli_error($conexion)
            );

        }

    }


    /* ==========================================
       6. CAMBIAR ESTADO DE LA SOLICITUD
    ========================================== */

    $sqlActualizar = "
        UPDATE solicitud_organizador
        SET estado_solicitud = 'Aceptada'
        WHERE id_solicitud = ?
    ";

    $stmtActualizar = mysqli_prepare(
        $conexion,
        $sqlActualizar
    );

    mysqli_stmt_bind_param(
        $stmtActualizar,
        "i",
        $id_solicitud
    );

    if (!mysqli_stmt_execute($stmtActualizar)) {

        throw new Exception(
            "No se pudo actualizar la solicitud."
        );

    }


    /* ==========================================
       TODO CORRECTO
    ========================================== */

    mysqli_commit($conexion);


    header("Location: ../paneles.php?mensaje=aceptada");
    exit();


} catch (Exception $e) {


    /* ==========================================
       SI ALGO FALLA
       DESHACER TODO
    ========================================== */

    mysqli_rollback($conexion);

    error_log("Error al aceptar solicitud de organizador: " . $e->getMessage());

    echo "<h2>Error al aceptar la solicitud</h2>";
    echo "<p>Ocurrió un error al procesar la solicitud. Intentá nuevamente.</p>";
    echo '<br>';
    echo '<a href="../paneles.php">Volver al panel</a>';

}
?>