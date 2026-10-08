<?php

include("../config/no_cache.php");
include("../config/conexion.php");


// ==========================================
// VERIFICAR SESIÓN
// ==========================================

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}


// ==========================================
// VERIFICAR QUE SEA ADMIN
// ==========================================

if ($_SESSION['id_rol'] != 1) {
    header("Location: ../paneles.php");
    exit();
}


// ==========================================
// VERIFICAR DATOS
// ==========================================

if (
    !isset($_POST['id_solicitud']) ||
    !isset($_POST['accion'])
) {
    header("Location: ../paneles.php?seccion=solicitudes");
    exit();
}


$id_solicitud = intval($_POST['id_solicitud']);
$accion = $_POST['accion'];


// ==========================================
// BUSCAR SOLICITUD
// ==========================================

$sql = "SELECT *
        FROM solicitud_organizador
        WHERE id_solicitud = ?
        AND estado_solicitud = 'Pendiente'
        LIMIT 1";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_solicitud
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$solicitud = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);


if (!$solicitud) {

    die("La solicitud no existe o ya fue procesada.");

}


// ==========================================
// RECHAZAR
// ==========================================

if ($accion === "rechazar") {

    $sql = "UPDATE solicitud_organizador
            SET estado_solicitud = 'Rechazada'
            WHERE id_solicitud = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id_solicitud
    );

    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    header("Location: ../paneles.php?seccion=solicitudes");
    exit();

}


// ==========================================
// ACEPTAR
// ==========================================

if ($accion === "aceptar") {

    /*
     * Vamos a hacer las tres inserciones
     * como una sola operación.
     */

    mysqli_begin_transaction($conexion);


    try {


        // ==================================
        // 1. CREAR USUARIO
        // ==================================

        $sqlUsuario = "INSERT INTO usuario
        (
            nombre,
            apellido,
            fecha_nacimiento,
            telefono,
            email,
            pass,
            id_rol
        )
        VALUES (?, ?, ?, ?, ?, ?, 2)";


        $stmtUsuario = mysqli_prepare(
            $conexion,
            $sqlUsuario
        );


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


        mysqli_stmt_execute($stmtUsuario);


        $id_usuario = mysqli_insert_id($conexion);


        mysqli_stmt_close($stmtUsuario);


        // ==================================
        // 2. CREAR CLUB
        // ==================================

        $sqlClub = "INSERT INTO club
        (
            nombre,
            direccion,
            telefono,
            horario,
            cantidad_canchas,
            id_usuario
        )
        VALUES (?, ?, ?, ?, ?, ?)";


        $stmtClub = mysqli_prepare(
            $conexion,
            $sqlClub
        );


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


        mysqli_stmt_execute($stmtClub);


        $id_club = mysqli_insert_id($conexion);


        mysqli_stmt_close($stmtClub);


        // ==================================
        // 3. CREAR PRIMERA CANCHA
        // ==================================

$numero_canchas = intval($solicitud['cantidad_canchas']);

$sqlCancha = "INSERT INTO cancha
(
    numero,
    estado,
    imagen,
    id_club
)
VALUES (?, 'Disponible', NULL, ?)";

$stmtCancha = mysqli_prepare(
    $conexion,
    $sqlCancha
);


for ($i = 1; $i <= $numero_canchas; $i++) {

    // Como numero permite texto:
    // Cancha 1, Cancha 2, etc.

    $numero = "Cancha " . $i;

    mysqli_stmt_bind_param(
        $stmtCancha,
        "si",
        $numero,
        $id_club
    );

    mysqli_stmt_execute($stmtCancha);
}


mysqli_stmt_close($stmtCancha);

        // ==================================
        // 4. MARCAR SOLICITUD COMO ACEPTADA
        // ==================================

        $sqlSolicitud = "UPDATE solicitud_organizador
                         SET estado_solicitud = 'Aceptada'
                         WHERE id_solicitud = ?";


        $stmtSolicitud = mysqli_prepare(
            $conexion,
            $sqlSolicitud
        );


        mysqli_stmt_bind_param(
            $stmtSolicitud,
            "i",
            $id_solicitud
        );


        mysqli_stmt_execute($stmtSolicitud);

        mysqli_stmt_close($stmtSolicitud);


        // ==================================
        // CONFIRMAR TODO
        // ==================================

        mysqli_commit($conexion);


        header(
            "Location: ../paneles.php?seccion=solicitudes"
        );

        exit();


    } catch (Exception $e) {

        // Si algo falla, no se guarda nada.

        mysqli_rollback($conexion);

        die(
            "Error al aceptar la solicitud: "
            . $e->getMessage()
        );

    }

}


// ==========================================
// ACCIÓN NO VÁLIDA
// ==========================================

header("Location: ../paneles.php?seccion=solicitudes");
exit();

?>