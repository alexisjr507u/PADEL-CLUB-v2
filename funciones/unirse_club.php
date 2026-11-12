<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("club_categoria.php");


/* =========================================================
   «UNIRME AL CLUB»

   Crea la relación del participante con un club (usuario_club). No modifica nada de los demás clubes: ni su
   categoría, ni sus puntos, ni su historial. La membresía nace CONFIRMADA con la categoría de su encuesta de
   registro como categoría inicial en ese club (sin revisión: puede inscribirse de inmediato).

   Seguridad: sesión de participante (el rol se confirma en la base), método POST, token CSRF, club existente,
   y una sola relación por jugador y club (índice único).
   ========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    header("Location: ../paneles.php?seccion=torneos&error=token");
    exit;
}

$idClub = filter_var($_POST['id_club'] ?? null, FILTER_VALIDATE_INT);
$idUsuario = (int) $_SESSION['id_usuario'];

if (!$idClub || $idClub <= 0) {
    header("Location: ../paneles.php?seccion=torneos&error=club_no_existe");
    exit;
}

$resultado = unirseAlClub($conexion, $idUsuario, (int) $idClub);

if (!$resultado['ok']) {
    header("Location: ../paneles.php?seccion=torneos&error=" . urlencode($resultado['error']));
    exit;
}

header("Location: ../paneles.php?seccion=torneos&union=exito&club=" . (int) $idClub);
exit;
