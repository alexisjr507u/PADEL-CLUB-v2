<?php

include("../config/no_cache.php");
include("../config/conexion.php");

if (!isset($_SESSION['password'])) {
    die("Error: no se encontró la contraseña.");
}

// Defensa en profundidad: aunque crear_password.php ya lo exige,
// este es el punto final que inserta en la BD con verificado = 1,
// así que vuelve a comprobar que el código fue validado de verdad.
if (
    !isset($_SESSION['verificacion_ok']) || $_SESSION['verificacion_ok'] !== true ||
    !isset($_SESSION['tipo_verificacion']) || $_SESSION['tipo_verificacion'] !== 'registro' ||
    !isset($_SESSION['email_verificado']) || !isset($_SESSION['email']) ||
    $_SESSION['email_verificado'] !== $_SESSION['email']
) {
    die("Error: el correo no fue verificado correctamente.");
}

$password = $_SESSION['password'];

$password_hash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$nombre = $_SESSION['nombre'];
$apellido = $_SESSION['apellido'];
$fecha_nacimiento = $_SESSION['fecha_nacimiento'];
$telefono = $_SESSION['telefono'];
$email = $_SESSION['email'];
$codigo_verificacion = $_SESSION['codigo'];

$id_rol = 3;
$id_categoria = $_SESSION['id_categoria'];

$sql = "INSERT INTO usuario
(
nombre,
apellido,
fecha_nacimiento,
telefono,
email,
pass,
codigo_verificacion,
verificado,
id_rol,
id_categoria
)
VALUES
(?, ?, ?, ?, ?, ?, ?, 1, ?, ?)";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "sssssssii",
    $nombre,
    $apellido,
    $fecha_nacimiento,
    $telefono,
    $email,
    $password_hash,
    $codigo_verificacion,
    $id_rol,
    $id_categoria
);

if (mysqli_stmt_execute($stmt)) {

    session_unset();
    session_destroy();

    ?>

    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <title>Cuenta creada</title>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="../css/style.css">

        <meta http-equiv="refresh" content="3;url=../login.php">
    </head>

    <body>

        <div class="contenedor">

            <div class="login-box">

                <img src="../img/ChatGPT Image 29 may 2026, 17_54_13.png"
                    alt="Logo"
                    class="logo">

                <div class="mensaje-exito">

                    <i class="bi bi-check-circle-fill"></i> Cuenta creada correctamente.

                    <br><br>

                    Serás redirigido al login en unos segundos...

                </div>

            </div>

        </div>

    </body>

    </html>

    <?php

} else {

    error_log("Error al crear usuario: " . mysqli_stmt_error($stmt));
    echo "Ha ocurrido un error al crear la cuenta. Intenta nuevamente.";

}
