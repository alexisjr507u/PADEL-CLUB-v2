<?php

include("../config/conexion.php");
require_once("csrf.php");

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../crear_password.php");
    exit();

}

$password = $_POST['password'];
$confirmar_password = $_POST['confirmar_password'];

if (strlen($password) < 8) {

    header("Location: ../crear_password.php?error=minimo");
    exit();

}

if ($password != $confirmar_password) {

    header("Location: ../crear_password.php?error=password");
    exit();

}

$_SESSION['password'] = $password;

header("Location: ../encuesta_categoria.php");
exit();

?>