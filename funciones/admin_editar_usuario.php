<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("admin_datos.php");


/* =========================================================
   EDITAR DATOS PERSONALES BÁSICOS DE UN USUARIO (solo administrador)

   Se puede cambiar: nombre, apellido, teléfono y fecha de nacimiento.
   NO se puede cambiar desde acá: correo (identifica la cuenta), contraseña, rol, categoría ni su estado
   (la categoría de cada club cambia solo por ascensos y descensos automáticos). Tampoco existe ninguna función para borrar usuarios.
   ========================================================= */

// El rol se comprueba en el servidor, contra la base, en cada petición.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit;
}

$idActor = (int) $_SESSION['id_usuario'];
$rolActor = adminConsulta($conexion, "SELECT id_rol FROM usuario WHERE id_usuario = ?", 'i', [$idActor]);

if (!$rolActor || (int) $rolActor[0]['id_rol'] !== 1 || (int) ($_SESSION['id_rol'] ?? 0) !== 1) {
    header("Location: ../paneles.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=usuarios");
    exit;
}

$idUsuario = intval($_POST['id_usuario'] ?? 0);
$volver = "../paneles.php?seccion=usuarios&usuario=$idUsuario";

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: $volver&accion=token");
    exit;
}

if ($idUsuario <= 0) {
    header("Location: ../paneles.php?seccion=usuarios");
    exit;
}

$existe = adminConsulta($conexion, "SELECT id_usuario FROM usuario WHERE id_usuario = ?", 'i', [$idUsuario]);

if (!$existe) {
    header("Location: ../paneles.php?seccion=usuarios&accion=no_existe");
    exit;
}

$validacion = adminValidarDatosUsuario($_POST);

if (!$validacion['ok']) {
    header("Location: $volver&accion=" . urlencode($validacion['error']));
    exit;
}

$d = $validacion['datos'];

// Solo estas cuatro columnas: nunca rol, categoría, correo, clave ni estado.
$stmt = mysqli_prepare(
    $conexion,
    "UPDATE usuario SET nombre = ?, apellido = ?, telefono = ?, fecha_nacimiento = ? WHERE id_usuario = ?"
);
mysqli_stmt_bind_param($stmt, "ssssi", $d['nombre'], $d['apellido'], $d['telefono'], $d['fecha_nacimiento'], $idUsuario);

if (!mysqli_stmt_execute($stmt)) {
    error_log("Error al editar el usuario $idUsuario: " . mysqli_error($conexion));
    header("Location: $volver&accion=error_bd");
    exit;
}

actividadUsuarioModificado($conexion, $idUsuario);

// Si el administrador se edita a sí mismo, el nombre del panel se actualiza.
if ($idUsuario === $idActor) {
    $_SESSION['nombre'] = $d['nombre'];
}

header("Location: $volver&accion=guardado");
exit;
