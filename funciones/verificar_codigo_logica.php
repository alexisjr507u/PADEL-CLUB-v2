<?php
include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");

if (
    !isset($_SESSION['codigo']) ||
    !isset($_SESSION['tiempo_codigo']) ||
    !isset($_SESSION['tipo_verificacion'])
) {

    header("Location: ../login.php");
    exit();

}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../verificar_codigo.php");
    exit();

}

if ((time() - $_SESSION['tiempo_codigo']) > 300) {

    header("Location: ../verificar_codigo.php?error=expirado");
    exit();

}

// Límite de intentos: máximo 5 por código antes de exigir uno nuevo
if (!isset($_SESSION['intentos_codigo'])) {
    $_SESSION['intentos_codigo'] = 0;
}

if ($_SESSION['intentos_codigo'] >= 5) {

    header("Location: ../verificar_codigo.php?error=bloqueado");
    exit();

}

$codigo_ingresado = $_POST['codigo'] ?? '';

if (hash_equals((string) $_SESSION['codigo'], (string) $codigo_ingresado)) {

    unset($_SESSION['intentos_codigo']);

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

    $_SESSION['intentos_codigo']++;

    header("Location: ../verificar_codigo.php?error=incorrecto");
    exit();

}