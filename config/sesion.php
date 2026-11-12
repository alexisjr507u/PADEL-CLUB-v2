<?php

/* =========================================================
   SESIÓN DE LA APLICACIÓN (un solo lugar; todas las páginas pasan por acá)

   - Cookie propia (PADEL_SID), acotada a la carpeta de la aplicación, HttpOnly y SameSite=Lax.
   - Archivos de sesión en su propia carpeta (sesiones/), que otros proyectos de localhost no limpian.
   - Protección contra fijación de sesión: use_strict_mode + session_regenerate_id() al iniciar sesión.

   LÍMITES (se controlan acá, de forma exacta, no por la limpieza aleatoria de PHP):
   - Inactividad: SESION_INACTIVIDAD sin pedir ninguna página. Cada petición (también las AJAX que
     dispara el usuario) cuenta como actividad y renueva el reloj.
   - Duración total: SESION_MAXIMA desde que se inició sesión, aunque haya actividad.
   Al vencer, la sesión se vacía y se manda al login con un aviso que explica el motivo.

   Historial del problema «me cierra la sesión»: ver informe. Lo principal NO era la sesión sino un
   script de js/padel.js que mandaba a login.php al volver a una página con «Atrás/Adelante»
   (el navegador la restaura de su caché con pageshow.persisted = true), con la sesión todavía viva.
   ========================================================= */

const SESION_INACTIVIDAD = 4 * 60 * 60;    // 4 horas sin actividad
const SESION_MAXIMA = 12 * 60 * 60;        // 12 horas como máximo desde el inicio de sesión

if (session_status() === PHP_SESSION_NONE) {

    // Carpeta propia para los archivos de sesión (si no se pudiera usar, queda la de PHP).
    $carpetaSesiones = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sesiones';

    if (!is_dir($carpetaSesiones)) {
        @mkdir($carpetaSesiones, 0700, true);
    }

    if (is_dir($carpetaSesiones) && is_writable($carpetaSesiones)) {

        if (!is_file($carpetaSesiones . DIRECTORY_SEPARATOR . '.htaccess')) {
            @file_put_contents($carpetaSesiones . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\n");
        }

        session_save_path($carpetaSesiones);

    }

    // Ruta de la aplicación dentro del sitio (p. ej. /padel): la cookie solo viaja hacia ahí.
    $rutaCookieSesion = '/';
    $raizWeb = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $raizApp = realpath(dirname(__DIR__));

    if ($raizWeb !== false && $raizApp !== false && strpos($raizApp, $raizWeb) === 0) {
        $relativa = str_replace(chr(92), '/', substr($raizApp, strlen($raizWeb)));
        $rutaCookieSesion = ($relativa === '') ? '/' : $relativa;
    }

    // PHP borra los archivos más viejos que esto; el límite real de inactividad lo aplica el código de abajo.
    ini_set('session.gc_maxlifetime', (string) SESION_MAXIMA);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => SESION_MAXIMA,
        'path' => $rutaCookieSesion,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('PADEL_SID');
    session_start();

    // ---- Control de inactividad y duración (solo si hay un usuario con la sesión iniciada) ----
    if (isset($_SESSION['id_usuario'])) {

        $ahoraSesion = time();
        $inicioSesion = (int) ($_SESSION['_sesion_inicio'] ?? $ahoraSesion);
        $actividadSesion = (int) ($_SESSION['_sesion_actividad'] ?? $ahoraSesion);

        $vencePorInactividad = ($ahoraSesion - $actividadSesion) > SESION_INACTIVIDAD;
        $vencePorDuracion = ($ahoraSesion - $inicioSesion) > SESION_MAXIMA;

        if ($vencePorInactividad || $vencePorDuracion) {

            // Se vacía la sesión y se cambia su identificador; las páginas privadas, al no ver
            // usuario, mandan al login como siempre.
            $_SESSION = [];
            session_regenerate_id(true);

            if (!headers_sent()) {
                setcookie('PADEL_AVISO', $vencePorInactividad ? 'inactividad' : 'duracion', [
                    'expires' => time() + 120,
                    'path' => $rutaCookieSesion,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

        } else {

            $_SESSION['_sesion_inicio'] = $inicioSesion;
            $_SESSION['_sesion_actividad'] = $ahoraSesion;

        }

    }

}
