<?php

require_once "../config/no_cache.php";
require_once "../config/conexion.php";
require_once "csrf.php";


// ==========================================
// VERIFICAR SESIÓN
// ==========================================

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../paneles.php?seccion=canchas");
    exit;
}


// ==========================================
// VERIFICAR QUE SEA ORGANIZADOR
// ==========================================

if ($_SESSION['id_rol'] != 2) {
    header("Location: ../paneles.php");
    exit;
}


// ==========================================
// SOLO POST
// ==========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=canchas");
    exit;
}


// ==========================================
// RECIBIR DATOS
// ==========================================

$idCancha = isset($_POST['id_cancha'])
    ? (int) $_POST['id_cancha']
    : 0;

$estado = $_POST['estado'] ?? '';


// ==========================================
// VALIDAR ID
// ==========================================

if ($idCancha <= 0) {
    header("Location: ../paneles.php?seccion=canchas&error=cancha_invalida");
    exit;
}


// ==========================================
// VALIDAR ESTADO
// ==========================================

$estadosPermitidos = [
    'Disponible',
    'No disponible'
];  
if (!in_array($estado, $estadosPermitidos, true)) {
    header("Location: ../paneles.php?seccion=canchas&error=estado_invalido");
    exit;
}


// ==========================================
// VERIFICAR QUE LA CANCHA SEA DEL CLUB
// DEL ORGANIZADOR LOGUEADO
// ==========================================

$sqlVerificar = "
    SELECT cancha.id_cancha
    FROM cancha
    INNER JOIN club
        ON cancha.id_club = club.id_club
    WHERE cancha.id_cancha = ?
      AND club.id_usuario = ?
";

$stmtVerificar = mysqli_prepare(
    $conexion,
    $sqlVerificar
);

mysqli_stmt_bind_param(
    $stmtVerificar,
    "ii",
    $idCancha,
    $_SESSION['id_usuario']
);

mysqli_stmt_execute($stmtVerificar);

$resultado = mysqli_stmt_get_result($stmtVerificar);

if (mysqli_num_rows($resultado) === 0) {

    mysqli_stmt_close($stmtVerificar);

    header("Location: ../paneles.php?seccion=canchas&error=cancha_no_autorizada");
    exit;
}

mysqli_stmt_close($stmtVerificar);


// ==========================================
// ACTUALIZAR ESTADO
// ==========================================

$sqlActualizar = "
    UPDATE cancha
    SET estado = ?
    WHERE id_cancha = ?
";

$stmtActualizar = mysqli_prepare(
    $conexion,
    $sqlActualizar
);

mysqli_stmt_bind_param(
    $stmtActualizar,
    "si",
    $estado,
    $idCancha
);

if (mysqli_stmt_execute($stmtActualizar)) {

    mysqli_stmt_close($stmtActualizar);

    header("Location: ../paneles.php?seccion=canchas&ok=estado_actualizado");
    exit;

}


// ==========================================
// ERROR
// ==========================================

mysqli_stmt_close($stmtActualizar);

header("Location: ../paneles.php?seccion=canchas&error=error_actualizar");
exit;