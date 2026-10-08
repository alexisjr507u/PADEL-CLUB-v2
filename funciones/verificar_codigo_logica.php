<?php
include("../config/no_cache.php");
include("../config/conexion.php");

if (
    !isset($_SESSION['codigo']) ||
    !isset($_SESSION['tiempo_codigo']) ||
    !isset($_SESSION['tipo_verificacion'])
) {

    header("Location: ../login.php");
    exit();

}

if ((time() - $_SESSION['tiempo_codigo']) > 300) {

    header("Location: ../verificar_codigo.php?error=expirado");
    exit();

}

$codigo_ingresado = $_POST['codigo'] ?? '';

if (hash_equals((string) $_SESSION['codigo'], (string) $codigo_ingresado)) {

    // Marcamos del lado del servidor que el código fue validado,
    // atado al email exacto que se está verificando.
    $_SESSION['verificacion_ok'] = true;

    if ($_SESSION['tipo_verificacion'] == "registro") {

        $_SESSION['email_verificado'] = $_SESSION['email'] ?? null;

        header("Location: ../crear_password.php");

    } else {

        $_SESSION['email_verificado'] = $_SESSION['email_recuperacion'] ?? null;

        header("Location: ../nueva_password.php");

    }

    exit();

} else {

    header("Location: ../verificar_codigo.php?error=incorrecto");
    exit();

}