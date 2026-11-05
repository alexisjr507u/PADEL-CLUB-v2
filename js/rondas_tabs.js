/* Pestañas por ronda en "Fase final" (cuadro con octavos / cuartos / semifinales / final).

   Todas las rondas generadas ya están en la página; se muestra una por vez
   (atributo hidden). Nada se elimina del DOM, así que los formularios de
   resultados siguen enviándose igual. Al entrar se abre la última ronda
   generada; al volver de guardar un resultado (#partido-12) se abre la ronda
   de ese partido. Con prefers-reduced-motion el cambio es inmediato. */
(function () {

    var barra = document.getElementById("rondasTabs");
    var contenedor = document.getElementById("gruposRondas");

    if (!barra || !contenedor) {
        return;
    }

    var botones = [].slice.call(barra.querySelectorAll("[data-ronda-tab]"));
    var paneles = {};
    var orden = [];

    botones.forEach(function (boton) {
        var nombre = boton.getAttribute("data-ronda-tab");
        var panel = contenedor.querySelector('[data-ronda-panel="' + nombre + '"]');
        if (panel) {
            paneles[nombre] = panel;
            orden.push(nombre);
        }
    });

    var SALIDA_MS = 120;
    var ENTRADA_MS = 160;
    var ALTURA_MS = 220;
    var DESPLAZAMIENTO_PX = 22;

    var torneo = barra.getAttribute("data-torneo");
    var cantidad = barra.getAttribute("data-rondas");
    var clave = "rondaFinal:" + torneo;

    var actual = null;
    var destino = null;
    var animando = false;
    var pendiente = null;

    var reducir = window.matchMedia
        ? window.matchMedia("(prefers-reduced-motion: reduce)")
        : { matches: false };

    function leerGuardado() {
        // Si se generó una ronda nueva desde la última visita, manda la nueva.
        try {
            var v = JSON.parse(sessionStorage.getItem(clave));
            return v && v.n === cantidad && paneles[v.ronda] ? v.ronda : null;
        } catch (e) {
            return null;
        }
    }

    function guardar(nombre) {
        try { sessionStorage.setItem(clave, JSON.stringify({ n: cantidad, ronda: nombre })); } catch (e) { }
    }

    function actualizarBotones(nombre) {
        botones.forEach(function (boton) {
            var activo = boton.getAttribute("data-ronda-tab") === nombre;
            boton.classList.toggle("activo", activo);
            boton.setAttribute("aria-selected", activo ? "true" : "false");
        });
    }

    function mostrar(nombre) {
        orden.forEach(function (n) {
            paneles[n].hidden = (n !== nombre);
        });
        actual = nombre;
        guardar(nombre);
    }

    function cambiarConAnimacion(nombre, direccion) {

        animando = true;

        var origen = paneles[actual];
        var alturaInicial = contenedor.getBoundingClientRect().height;

        contenedor.style.overflow = "hidden";
        contenedor.style.height = alturaInicial + "px";

        var salida = origen.animate(
            [
                { transform: "translateX(0)", opacity: 1 },
                { transform: "translateX(" + (-direccion * DESPLAZAMIENTO_PX) + "px)", opacity: 0 }
            ],
            { duration: SALIDA_MS, easing: "ease-in", fill: "forwards" }
        );

        salida.finished
            .catch(function () { })
            .then(function () {

                mostrar(nombre);

                contenedor.style.height = "auto";
                var alturaFinal = contenedor.getBoundingClientRect().height;
                contenedor.style.height = alturaInicial + "px";

                var entrada = paneles[nombre].animate(
                    [
                        { transform: "translateX(" + (direccion * DESPLAZAMIENTO_PX) + "px)", opacity: 0 },
                        { transform: "translateX(0)", opacity: 1 }
                    ],
                    { duration: ENTRADA_MS, easing: "ease-out", fill: "backwards" }
                );

                salida.cancel();

                var altura = contenedor.animate(
                    [{ height: alturaInicial + "px" }, { height: alturaFinal + "px" }],
                    { duration: ALTURA_MS, easing: "ease-in-out" }
                );

                return Promise.all([entrada.finished, altura.finished]);

            })
            .catch(function () { })
            .then(function () {

                contenedor.style.height = "";
                contenedor.style.overflow = "";
                animando = false;

                if (pendiente !== null && pendiente !== actual) {
                    var siguiente = pendiente;
                    pendiente = null;
                    irA(siguiente);
                } else {
                    pendiente = null;
                }

            });

    }

    function irA(nombre) {

        if (!paneles[nombre]) {
            return;
        }

        if (animando) {
            pendiente = nombre;
            destino = nombre;
            actualizarBotones(nombre);
            return;
        }

        if (nombre === actual) {
            return;
        }

        var direccion = orden.indexOf(nombre) > orden.indexOf(actual) ? 1 : -1;

        destino = nombre;
        actualizarBotones(nombre);

        if (reducir.matches || typeof Element.prototype.animate !== "function") {
            mostrar(nombre);
            return;
        }

        cambiarConAnimacion(nombre, direccion);

    }

    botones.forEach(function (boton) {
        boton.addEventListener("click", function () {
            irA(boton.getAttribute("data-ronda-tab"));
        });
    });

    // Ronda inicial: la del partido del hash, si no la guardada, si no la última generada.
    var inicial = null;
    var objetivo = null;

    if (/^#partido-\d+$/.test(location.hash)) {

        objetivo = document.getElementById(location.hash.slice(1));

        if (objetivo) {
            var panelObjetivo = objetivo.closest("[data-ronda-panel]");
            inicial = panelObjetivo ? panelObjetivo.getAttribute("data-ronda-panel") : null;
        }

    }

    inicial = inicial || leerGuardado() || barra.getAttribute("data-inicial");

    if (!paneles[inicial]) {
        inicial = orden[orden.length - 1];
    }

    destino = inicial;
    mostrar(inicial);
    actualizarBotones(inicial);

    if (objetivo && !objetivo.closest("[hidden]")) {
        objetivo.scrollIntoView();
    }

})();
