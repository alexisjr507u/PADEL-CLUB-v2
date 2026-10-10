<?php
include("../config/no_cache.php");

include("../config/conexion.php");
require_once("csrf.php");

// Este controlador no tenía ninguna verificación de sesión: cualquiera
// podía llamarlo directo. Lo exigimos acá como defensa en profundidad,
// además del chequeo que ya hace la vista cambiar_password.php.
if (!isset($_SESSION['id_usuario'])) {

    header("Location: ../login.php");
    exit();

}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../cambiar_password.php");
    exit();

}

$password_actual = $_POST['password_actual'];
$password_nueva = $_POST['password_nueva'];
$confirmar_password = $_POST['confirmar_password'];

$id_usuario = $_SESSION['id_usuario'];

$sql = "SELECT pass
        FROM usuario
        WHERE id_usuario = '$id_usuario'";

$resultado = mysqli_query($conexion, $sql);

$usuario = mysqli_fetch_assoc($resultado);

if (!password_verify($password_actual, $usuario['pass'])) {

    header("Location: ../cambiar_password.php?error=actual");
    exit();

}

if ($password_nueva != $confirmar_password) {

    header("Location: ../cambiar_password.php?error=coinciden");
    exit();

}

$password_hash = password_hash(
    $password_nueva,
    PASSWORD_DEFAULT
);

$sql_update = "UPDATE usuario
               SET pass = '$password_hash'
               WHERE id_usuario = '$id_usuario'";

if (mysqli_query($conexion, $sql_update)) {

    header("Location: ../cambiar_password.php?success=1");
    exit();

} else {

    header("Location: ../cambiar_password.php?error=bd");
    exit();

}