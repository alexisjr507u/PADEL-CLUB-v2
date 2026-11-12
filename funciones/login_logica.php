<?php

include("../config/no_cache.php");

require_once __DIR__ . '/../config/sesion.php';
include("../config/conexion.php");
require_once("csrf.php");

// Torneo de origen (consulta pública): solo un id entero positivo; si el login falla se conserva en la URL.
$idTorneoDestino = filter_var($_POST['torneo'] ?? '', FILTER_VALIDATE_INT);
$idTorneoDestino = ($idTorneoDestino !== false && $idTorneoDestino > 0) ? (int) $idTorneoDestino : 0;
$paramTorneo = $idTorneoDestino ? '&torneo=' . $idTorneoDestino : '';


// ==========================================
// VERIFICAR TOKEN CSRF
// ==========================================

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../login.php?error=1" . $paramTorneo);
    exit();

}


// ==========================================
// RECIBIR DATOS
// ==========================================

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';


// ==========================================
// VERIFICAR QUE NO ESTÉN VACÍOS
// ==========================================

if ($email === '' || $password === '') {

    header("Location: ../login.php?error=1" . $paramTorneo);
    exit();

}


// ==========================================
// BUSCAR USUARIO DE FORMA SEGURA
// ==========================================

$sql = "
    SELECT *
    FROM usuario
    WHERE email = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    header("Location: ../login.php?error=1" . $paramTorneo);
    exit();

}

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $email
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);


// ==========================================
// VERIFICAR SI EXISTE
// ==========================================

if (mysqli_num_rows($resultado) === 0) {

    header("Location: ../login.php?error=1" . $paramTorneo);
    exit();

}


$usuario = mysqli_fetch_assoc($resultado);


// ==========================================
// VERIFICAR CONTRASEÑA
// ==========================================

if (!password_verify($password, $usuario['pass'])) {

    header("Location: ../login.php?error=1" . $paramTorneo);
    exit();

}


// ==========================================
// CREAR SESIÓN
// ==========================================

// Un ID de sesión nuevo al autenticar evita que alguien que haya fijado
// el ID antes del login herede la sesión ya autenticada.
session_regenerate_id(true);

$_SESSION['id_usuario'] = $usuario['id_usuario'];

$_SESSION['nombre'] = $usuario['nombre'];

$_SESSION['id_rol'] = $usuario['id_rol'];


// ==========================================
// ENTRAR AL PANEL
// ==========================================

// Si venía de un torneo de la consulta pública y todavía existe, vuelve a él. No se inscribe a nadie:
// la inscripción siempre la confirma el usuario después, desde el propio torneo.
unset($_SESSION['torneo_destino']);

if ($idTorneoDestino) {

    $stmtDestino = mysqli_prepare($conexion, "SELECT id_torneo FROM torneos WHERE id_torneo = ?");
    mysqli_stmt_bind_param($stmtDestino, "i", $idTorneoDestino);
    mysqli_stmt_execute($stmtDestino);
    $existeDestino = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtDestino));

    if ($existeDestino) {
        header("Location: ../ver_torneo.php?id=" . $idTorneoDestino);
        exit();
    }

}

header("Location: ../paneles.php");

exit();

?>