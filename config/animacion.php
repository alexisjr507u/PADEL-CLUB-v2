<?php

/* =========================================================
   ANIMACIÓN DE ENTRADA — punto único de activación

   Lo incluye config/no_cache.php, que ya usan todas las páginas. Agrega al <head> de cada página HTML
   los dos archivos de la animación (css/animacion_entrada.css y js/animacion_entrada.js), sin tener que
   tocar cada página.

   - Solo actúa sobre respuestas HTML con un <head>: redirecciones, JSON y otros archivos pasan intactos.
   - No actúa por línea de comandos (pruebas) ni dos veces en la misma respuesta.
   - Si el HTML no tiene </head> o algo falla, devuelve la página tal cual.
   ========================================================= */

if (PHP_SAPI !== 'cli' && !defined('ANIMACION_ENTRADA_ACTIVA')) {

    define('ANIMACION_ENTRADA_ACTIVA', true);

    ob_start(function ($html) {

        try {

            // Solo documentos HTML completos.
            foreach (headers_list() as $cabecera) {
                if (stripos($cabecera, 'Content-Type:') === 0 && stripos($cabecera, 'text/html') === false) {
                    return $html;
                }
            }

            $posicion = stripos($html, '</head>');

            if ($posicion === false || stripos($html, '<html') === false || strpos($html, 'ae-entrada-cargada') !== false) {
                return $html;
            }

            // Carpeta de la aplicación en la URL (las páginas de funciones/ están un nivel más adentro).
            $carpeta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

            if (substr($carpeta, -10) === '/funciones') {
                $carpeta = substr($carpeta, 0, -10);
            }

            $raiz = dirname(__DIR__);
            $v = function ($ruta) use ($raiz) {
                $archivo = $raiz . '/' . $ruta;
                return is_file($archivo) ? filemtime($archivo) : 1;
            };

            $etiquetas = "\n    <!-- ae-entrada-cargada -->\n"
                . '    <link rel="stylesheet" href="' . $carpeta . '/css/animacion_entrada.css?v=' . $v('css/animacion_entrada.css') . '">' . "\n"
                . '    <script src="' . $carpeta . '/js/animacion_entrada.js?v=' . $v('js/animacion_entrada.js') . '"></script>' . "\n";

            return substr($html, 0, $posicion) . $etiquetas . substr($html, $posicion);

        } catch (Throwable $e) {

            return $html;

        }

    });

}
