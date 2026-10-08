<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$usuario = "root";
$password = "";
$bd = "bd_padel";

$conexion = mysqli_connect($host, $usuario, $password, $bd);

if (!$conexion) {
    die("Error de conexión");
}