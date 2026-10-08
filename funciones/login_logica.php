<?php

include("no_cache.php");
include("../config/no_cache.php");

session_start();

include("../config/conexion.php");


// ==========================================
// RECIBIR DATOS
// ==========================================

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';


// ==========================================
// VERIFICAR QUE NO ESTÉN VACÍOS
// ==========================================

if ($email === '' || $password === '') {

    header("Location: ../login.php?error=1");
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

    header("Location: ../login.php?error=1");
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

    header("Location: ../login.php?error=1");
    exit();

}


$usuario = mysqli_fetch_assoc($resultado);


// ==========================================
// VERIFICAR CONTRASEÑA
// ==========================================

if (!password_verify($password, $usuario['pass'])) {

    header("Location: ../login.php?error=1");
    exit();

}


// ==========================================
// CREAR SESIÓN
// ==========================================

$_SESSION['id_usuario'] = $usuario['id_usuario'];

$_SESSION['nombre'] = $usuario['nombre'];

$_SESSION['id_rol'] = $usuario['id_rol'];


// ==========================================
// ENTRAR AL PANEL
// ==========================================

header("Location: ../paneles.php");

exit();

?>