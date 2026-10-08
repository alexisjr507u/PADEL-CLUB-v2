<?php
include("../config/no_cache.php");
include("../config/conexion.php");
require_once("enviar_codigo.php");

$correo = "";

if(isset($_SESSION['email'])){
    $correo = $_SESSION['email'];
}

if(isset($_SESSION['email_recuperacion'])){
    $correo = $_SESSION['email_recuperacion'];
    
}if($correo != ""){

    $nuevo_codigo = rand(100000, 999999);

    $_SESSION['codigo'] = $nuevo_codigo;
    $_SESSION['tiempo_codigo'] = time();

    // Un código nuevo invalida cualquier verificación previa de esta sesión
    unset($_SESSION['verificacion_ok']);
    unset($_SESSION['email_verificado']);

    enviarCodigo($correo, $nuevo_codigo);
}

header("Location: ../verificar_codigo.php?reenviado=1");
exit();