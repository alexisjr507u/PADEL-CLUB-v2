<?php

include("../config/conexion.php");

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