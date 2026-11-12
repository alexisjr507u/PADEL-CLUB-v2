<?php
include("../config/no_cache.php");
require_once __DIR__ . '/../config/sesion.php';
$_SESSION["id_categoria"]=1;
header("Location: guardar_usuario.php");
exit();
?>