<?php

session_start();

require_once "../config/conexion.php";
require_once "../config/no_cache.php";

// =========================================
// SEGURIDAD
// =========================================

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 2) {
    header("Location: ../login.php");
    exit;
}

// =========================================
// SOLO POST
// =========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=canchas");
    exit;
}

// =========================================
// VALIDAR ID
// =========================================

$id_cancha = filter_input(
    INPUT_POST,
    'id_cancha',
    FILTER_VALIDATE_INT
);

if (!$id_cancha) {
    header("Location: ../paneles.php?seccion=canchas&error=cancha_invalida");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

// =========================================
// BUSCAR CANCHA Y COMPROBAR PROPIETARIO
// =========================================

$sql = "
    SELECT
        cancha.id_cancha,
        cancha.imagen
    FROM cancha
    INNER JOIN club
        ON cancha.id_club = club.id_club
    WHERE cancha.id_cancha = ?
      AND club.id_usuario = ?
";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    header("Location: ../paneles.php?seccion=canchas&error=error_servidor");
    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $id_cancha,
    $id_usuario
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$cancha = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

// =========================================
// LA CANCHA NO EXISTE O NO ES DEL CLUB
// =========================================

if (!$cancha) {
    header("Location: ../paneles.php?seccion=canchas&error=sin_permiso");
    exit;
}

// =========================================
// ELIMINAR CANCHA
// =========================================

$sqlEliminar = "
    DELETE FROM cancha
    WHERE id_cancha = ?
";

$stmtEliminar = mysqli_prepare(
    $conexion,
    $sqlEliminar
);

if (!$stmtEliminar) {
    header("Location: ../paneles.php?seccion=canchas&error=error_servidor");
    exit;
}

mysqli_stmt_bind_param(
    $stmtEliminar,
    "i",
    $id_cancha
);

if (mysqli_stmt_execute($stmtEliminar)) {

    mysqli_stmt_close($stmtEliminar);

    // =========================================
    // ELIMINAR IMAGEN DEL SERVIDOR
    // =========================================

    if (!empty($cancha['imagen'])) {

        $rutaImagen = "../" . $cancha['imagen'];

        if (file_exists($rutaImagen)) {
            unlink($rutaImagen);
        }
    }

    header(
        "Location: ../paneles.php?seccion=canchas&ok=cancha_eliminada"
    );
    exit;
}

mysqli_stmt_close($stmtEliminar);

header(
    "Location: ../paneles.php?seccion=canchas&error=no_se_pudo_eliminar"
);
exit;