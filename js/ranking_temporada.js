/* Tarjeta «Temporada» del ranking: tres botones (Ascensos, Descensos de hoy, Última temporada)
   que despliegan su detalle con una animación fluida.

   - Un solo panel abierto a la vez; volver a tocar el botón activo lo cierra.
   - El alto del contenedor se anima entre el valor actual y el nuevo (sin saltos), y el contenido
     se desvanece y entra con un leve desplazamiento.
   - Clics rápidos: cada cambio cancela el anterior y parte del alto que se ve en ese momento.
   - Todo el contenido ya está en la página (solo se oculta con `hidden`); sin JavaScript se ven los
     botones y los paneles quedan ocultos, pero la información sigue en el HTML.
   - Con prefers-reduced-motion el cambio es inmediato. */
(function () {

    var raiz = document.querySelector("[data-rk-temporada]");

    if (!raiz) {
        return;
    }

    var contenedor = raiz.querySelector("[data-rk-paneles]");
    var botones = [].slice.call(raiz.querySelectorAll("[data-rk-panel]"));
    var paneles = {};

    botones.forEach(function (boton) {
        var nombre = boton.getAttribute("data-rk-panel");
        var panel = document.getElementById("rk-panel-" + nombre);
        if (panel) {
            paneles[nombre] = panel;
        }
    });

    if (!contenedor || botones.length === 0) {
        return;
    }

    var ALTURA_MS = 380;
    var ENTRADA_MS = 260;
    var SALIDA_MS = 140;
    var CURVA = "cubic-bezier(0.22, 0.8, 0.3, 1)";

    var reducir = window.matchMedia
        ? window.matchMedia("(prefers-reduced-motion: reduce)")
        : { matches: false };

    var activo = null;
    var ficha = 0;
    var animaciones = [];

    function cancelarAnimaciones() {
        animaciones.forEach(function (a) {
            try { a.cancel(); } catch (e) { }
        });
        animaciones = [];
    }

    function marcarBotones(nombre) {
        botones.forEach(function (boton) {
            var activoAhora = boton.getAttribute("data-rk-panel") === nombre;
            boton.classList.toggle("activo", activoAhora);
            boton.setAttribute("aria-expanded", activoAhora ? "true" : "false");
        });
    }

    function mostrarSolo(nombre) {
        Object.keys(paneles).forEach(function (n) {
            paneles[n].hidden = (n !== nombre);
        });
        contenedor.classList.toggle("abierto", !!nombre);
    }

    function limpiarEstilos() {
        contenedor.style.height = "";
        contenedor.style.overflow = "";
    }

    function alturaNatural() {
        contenedor.style.height = "auto";
        var h = contenedor.getBoundingClientRect().height;
        return h;
    }

    function cambiar(nombre) {

        var miFicha = ++ficha;
        var anterior = activo;

        // Alto que se ve ahora (si había una animación a medias, parte de ahí).
        var altoInicial = contenedor.getBoundingClientRect().height;

        cancelarAnimaciones();

        activo = nombre;
        marcarBotones(nombre);

        if (reducir.matches || typeof contenedor.animate !== "function") {
            mostrarSolo(nombre);
            limpiarEstilos();
            return;
        }

        contenedor.style.overflow = "hidden";
        contenedor.style.height = altoInicial + "px";

        var panelAnterior = anterior ? paneles[anterior] : null;
        var haciaPanel = nombre ? paneles[nombre] : null;

        // --- Cerrar: el contenido se desvanece mientras el alto baja a 0 ---
        if (!haciaPanel) {

            var cierre = contenedor.animate(
                [{ height: altoInicial + "px" }, { height: "0px" }],
                { duration: ALTURA_MS - 60, easing: CURVA }
            );
            animaciones.push(cierre);

            if (panelAnterior) {
                animaciones.push(panelAnterior.animate(
                    [{ opacity: 1 }, { opacity: 0 }],
                    { duration: SALIDA_MS + 60, easing: "ease-in", fill: "forwards" }
                ));
            }

            cierre.finished.then(function () {
                if (miFicha !== ficha) { return; }
                cancelarAnimaciones();
                mostrarSolo(null);
                limpiarEstilos();
            }).catch(function () { });

            return;

        }

        // --- Abrir o cambiar de panel ---
        var preparar = function () {

            if (miFicha !== ficha) { return; }

            mostrarSolo(nombre);

            var altoFinal = alturaNatural();
            contenedor.style.height = altoInicial + "px";

            var altura = contenedor.animate(
                [{ height: altoInicial + "px" }, { height: altoFinal + "px" }],
                { duration: ALTURA_MS, easing: CURVA }
            );
            animaciones.push(altura);

            animaciones.push(haciaPanel.animate(
                [
                    { opacity: 0, transform: "translateY(10px)" },
                    { opacity: 1, transform: "translateY(0)" }
                ],
                { duration: ENTRADA_MS, delay: 40, easing: "ease-out", fill: "backwards" }
            ));

            altura.finished.then(function () {
                if (miFicha !== ficha) { return; }
                cancelarAnimaciones();
                limpiarEstilos();
            }).catch(function () { });

        };

        // Si había otro panel abierto, primero se desvanece (rápido) y recién después se cambia.
        if (panelAnterior && !panelAnterior.hidden) {

            var salida = panelAnterior.animate(
                [{ opacity: 1 }, { opacity: 0 }],
                { duration: SALIDA_MS, easing: "ease-in", fill: "forwards" }
            );
            animaciones.push(salida);

            salida.finished.then(function () {
                if (miFicha !== ficha) { return; }
                cancelarAnimaciones();
                preparar();
            }).catch(function () { });

        } else {

            preparar();

        }

    }

    botones.forEach(function (boton) {
        boton.addEventListener("click", function () {
            var nombre = boton.getAttribute("data-rk-panel");
            if (!paneles[nombre]) { return; }
            cambiar(activo === nombre ? null : nombre);
        });
    });

    // Estado inicial: todo cerrado.
    mostrarSolo(null);
    marcarBotones(null);

})();
