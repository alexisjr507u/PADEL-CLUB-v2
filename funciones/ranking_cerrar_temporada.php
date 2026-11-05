<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("ranking_temporada.php");


// ==========================================
// SEGURIDAD: organizador o administrador (se valida en el servidor)
// ==========================================

if (!isset($_SESSION['id_usuario']) || !in_array((int) ($_SESSION['id_rol'] ?? 0), [1, 2], true)) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=ranking");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../paneles.php?seccion=ranking&accion=token");
    exit;
}

$idTemporada = intval($_POST['id_temporada'] ?? 0);

if ($idTemporada <= 0) {
    header("Location: ../paneles.php?seccion=ranking&accion=datos_invalidos");
    exit;
}

$resultado = cerrarTemporada($conexion, (int) $_SESSION['id_usuario'], $idTemporada);

$accion = $resultado['ok'] ? 'temporada_cerrada' : $resultado['error'];

header("Location: ../paneles.php?seccion=ranking&accion=" . urlencode($accion));
exit;
