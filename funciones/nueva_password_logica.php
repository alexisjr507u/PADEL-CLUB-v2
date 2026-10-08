<?php
include("../config/no_cache.php");


include("../config/conexion.php");

// Defensa en profundidad: aunque la vista ya lo valida, este
// controlador también exige que el código haya sido verificado.
if (
    !isset($_SESSION['verificacion_ok']) || $_SESSION['verificacion_ok'] !== true ||
    !isset($_SESSION['tipo_verificacion']) || $_SESSION['tipo_verificacion'] !== 'recuperacion' ||
    !isset($_SESSION['email_verificado']) || !isset($_SESSION['email_recuperacion']) ||
    $_SESSION['email_verificado'] !== $_SESSION['email_recuperacion']
) {
    header("Location: ../login.php");
    exit();
}

$password_nueva = $_POST['password_nueva'];
$confirmar_password = $_POST['confirmar_password'];

if (strlen($password_nueva) < 8) {

    header("Location: ../nueva_password.php?error=minimo");
    exit();

}

if ($password_nueva != $confirmar_password) {

    header("Location: ../nueva_password.php?error=password");
    exit();
}



$email = $_SESSION['email_recuperacion'];

$password_hash = password_hash(
    $password_nueva,
    PASSWORD_DEFAULT
);

$sql = "UPDATE usuario
        SET pass = ?
        WHERE email = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "ss", $password_hash, $email);

if (mysqli_stmt_execute($stmt)) {

    unset($_SESSION['codigo']);
    unset($_SESSION['tiempo_codigo']);
    unset($_SESSION['email_recuperacion']);
    unset($_SESSION['tipo_verificacion']);
    unset($_SESSION['verificacion_ok']);
    unset($_SESSION['email_verificado']);

    header("Location: ../login.php?success=password");
    exit();

} else {

    error_log("Error al actualizar password: " . mysqli_stmt_error($stmt));
    echo "Ha ocurrido un error al actualizar la contraseña. Intenta nuevamente.";

}