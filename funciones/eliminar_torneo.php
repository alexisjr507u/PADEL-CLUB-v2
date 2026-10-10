<?php

session_start();

require_once "../config/conexion.php";
require_once "../config/no_cache.php";
require_once "csrf.php";

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
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

// =========================================
// VALIDAR ID
// =========================================

$id_torneo = filter_input(
    INPUT_POST,
    'id_torneo',
    FILTER_VALIDATE_INT
);

if (!$id_torneo) {
    header("Location: ../paneles.php?seccion=torneos&error=torneo_invalido");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];

// =========================================
// BUSCAR TORNEO Y COMPROBAR PROPIETARIO
// =========================================

$sql = "
    SELECT
        torneos.id_torneo,
        torneos.imagen
    FROM torneos
    INNER JOIN club
        ON torneos.id_club = club.id_club
    WHERE torneos.id_torneo = ?
      AND club.id_usuario = ?
";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    header("Location: ../paneles.php?seccion=torneos&error=servidor");
    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $id_torneo,
    $id_usuario
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$torneo = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

// =========================================
// EL TORNEO NO EXISTE O NO ES DEL CLUB
// =========================================

if (!$torneo) {
    header("Location: ../paneles.php?seccion=torneos&error=sin_permiso");
    exit;
}

// =========================================
// ELIMINAR TORNEO
// =========================================

$sqlEliminar = "
    DELETE FROM torneos
    WHERE id_torneo = ?
";

$stmtEliminar = mysqli_prepare(
    $conexion,
    $sqlEliminar
);

if (!$stmtEliminar) {
    header("Location: ../paneles.php?seccion=torneos&error=servidor");
    exit;
}

mysqli_stmt_bind_param(
    $stmtEliminar,
    "i",
    $id_torneo
);

if (mysqli_stmt_execute($stmtEliminar)) {

    mysqli_stmt_close($stmtEliminar);

    // =========================================
    // ELIMINAR IMAGEN DEL SERVIDOR
    // =========================================

    if (!empty($torneo['imagen'])) {

        $rutaImagen = "../" . $torneo['imagen'];

        if (file_exists($rutaImagen)) {
            unlink($rutaImagen);
        }
    }

    header(
        "Location: ../paneles.php?seccion=torneos&ok=torneo_eliminado"
    );
    exit;
}

mysqli_stmt_close($stmtEliminar);

header(
    "Location: ../paneles.php?seccion=torneos&error=no_se_pudo_eliminar"
);
exit;
