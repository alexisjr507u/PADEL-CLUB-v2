<?php
include("../config/no_cache.php");
require '../config/conexion.php';
require 'enviar_codigo.php';
require 'csrf.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

        header("Location: ../recuperar_password.php");
        exit();

    }

    $email = mysqli_real_escape_string($conexion, $_POST['email']);

    $consulta = mysqli_query(
        $conexion,
        "SELECT * FROM usuario WHERE email='$email'"
    );

    if (mysqli_num_rows($consulta) > 0) {

        $codigo = rand(100000, 999999);
        $_SESSION['codigo'] = $codigo;
        $_SESSION['tipo_verificacion'] = "recuperacion";
$_SESSION['tiempo_codigo'] = time();

        $_SESSION['codigo_recuperacion'] = $codigo;
        $_SESSION['email_recuperacion'] = $email;

        // Un código nuevo invalida cualquier verificación previa de esta sesión
        unset($_SESSION['verificacion_ok']);
        unset($_SESSION['email_verificado']);
        $_SESSION['intentos_codigo'] = 0;

        if (enviarCodigo($email, $codigo)) {

            header("Location: ../verificar_codigo.php");
            exit();

        } else {

            echo "Error al enviar el correo.";

        }

    } else {

    header("Location: ../recuperar_password.php?error=email");
exit();
    }
}