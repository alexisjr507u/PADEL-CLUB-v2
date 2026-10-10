<?php
include("../config/no_cache.php");
include("../config/conexion.php");
require_once("enviar_codigo.php");
require_once("csrf.php");

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../registro_participante.php");
    exit();

}

$nombre = $_POST['nombre'];
$apellido = $_POST['apellido'];
$fecha_nacimiento = $_POST['fecha_nacimiento'];
$telefono = $_POST['telefono'];

$sql_telefono = "SELECT id_usuario FROM usuario WHERE telefono = ?";
$stmt_telefono = mysqli_prepare($conexion, $sql_telefono);
mysqli_stmt_bind_param($stmt_telefono, "s", $telefono);
mysqli_stmt_execute($stmt_telefono);
$resultado_telefono = mysqli_stmt_get_result($stmt_telefono);

if(mysqli_num_rows($resultado_telefono) > 0){

    header("Location: ../registro_participante.php?error=telefono");
    exit();

}

$email = $_POST['email'];

$sql_verificar = "SELECT id_usuario FROM usuario WHERE email = ?";
$stmt_verificar = mysqli_prepare($conexion, $sql_verificar);
mysqli_stmt_bind_param($stmt_verificar, "s", $email);
mysqli_stmt_execute($stmt_verificar);
$resultado = mysqli_stmt_get_result($stmt_verificar);

if(mysqli_num_rows($resultado) > 0){

    header("Location: ../registro_participante.php?error=email");
    exit();

}

// Generar código de 6 dígitos
$codigo = rand(100000, 999999);

// Un código nuevo invalida cualquier verificación previa de esta sesión
unset($_SESSION['verificacion_ok']);
unset($_SESSION['email_verificado']);
$_SESSION['intentos_codigo'] = 0;

// Guardar datos temporalmente en la sesión
$_SESSION['nombre'] = $nombre;
$_SESSION['apellido'] = $apellido;
$_SESSION['fecha_nacimiento'] = $fecha_nacimiento;
$_SESSION['telefono'] = $telefono;
$_SESSION['email'] = $email;
$_SESSION['codigo'] = $codigo;
$_SESSION['tipo_verificacion'] = "registro";
$_SESSION['tiempo_codigo'] = time();
if(enviarCodigo($email, $codigo)){
    
    header("Location: ../verificar_codigo.php");
    exit();

}else{

    echo "Error al enviar el correo";
}


?>