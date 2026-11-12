<?php

require_once __DIR__ . '/sesion.php';

$host = "localhost";
$usuario = "root";
$password = "root";
$bd = "bd_padel";

$conexion = mysqli_connect($host, $usuario, $password, $bd);

if (!$conexion) {
    die("Error de conexión");
}

// Registro del historial de actividades (ver funciones/actividad.php)
require_once __DIR__ . '/../funciones/actividad.php';