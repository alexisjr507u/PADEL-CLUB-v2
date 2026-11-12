/* =========================================================
   ANIMACIÓN DE ENTRADA — decide si la página entra con animación.
   (Las pantallas de inicio de sesión, registro y encuesta ya tienen su propia animación y no dependen de esta.)
   Se carga en el <head> (sin defer) para que la clase esté puesta antes del primer dibujado y no haya parpadeos.
   La animación en sí está en css/animacion_entrada.css.

   NO anima cuando:
   - la persona tiene activado «reducir movimiento»;
   - se recarga la página (F5): no hay nada «nuevo» que mostrar;
   - se vuelve con el botón Atrás/Adelante del navegador;
   - se llega desde la misma sección (por ejemplo, al guardar un formulario que vuelve a la misma pantalla,
     cambiar un filtro o de página): son actualizaciones de datos, no una entrada.
   Si algo falla, la página simplemente se muestra sin animación.
   ========================================================= */
(function () {

    var raiz = document.documentElement;

    try {

        if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return;
        }

        var entrada = (window.performance && performance.getEntriesByType)
            ? performance.getEntriesByType("navigation")[0]
            : null;

        if (entrada && (entrada.type === "reload" || entrada.type === "back_forward")) {
            return;
        }

        // Misma sección que la página de origen (misma ruta, sección y torneo): no se vuelve a animar.
        var clave = function (direccion) {
            var u = new URL(direccion, window.location.href);
            return u.origin + u.pathname + "|" + ["seccion", "id", "id_torneo"].map(function (k) {
                return u.searchParams.get(k) || "";
            }).join("|");
        };

        if (document.referrer && clave(document.referrer) === clave(window.location.href)) {
            return;
        }

    } catch (error) {
        return;
    }

    raiz.classList.add("ae-entrando");

    window.setTimeout(function () {
        raiz.classList.remove("ae-entrando");
    }, 900);

})();
