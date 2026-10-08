<?php
include("../config/no_cache.php");
session_start();
$_SESSION["id_categoria"]=1;
header("Location: guardar_usuario.php");
exit();
?>