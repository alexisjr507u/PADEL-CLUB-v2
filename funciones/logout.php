<?php
include("../config/no_cache.php");
session_start();
session_destroy();

// Eliminar todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Eliminar cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Evitar caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header("Location: ../panel_principal.php");
exit();
?>