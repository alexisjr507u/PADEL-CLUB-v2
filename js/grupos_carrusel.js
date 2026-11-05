/* Carrusel de grupos (Gestionar grupos > Posiciones y resultados).

   Los grupos ya están todos en la página; se muestra uno por vez (atributo
   hidden) y se cambia con las flechas de cada tarjeta o con el selector de
   grupos. Al cambiar, la tarjeta actual sale deslizándose hacia un lado y la
   nueva entra desde el otro. Nada se elimina del DOM, así que los formularios
   de resultados siguen enviándose igual. Con prefers-reduced-motion el cambio
   es inmediato. */
(function () {

    var contenedor = document.getElementById("gruposCarrusel");

    if (!contenedor) {
        return;
    }

    var tarjetas = [].slice.call(contenedor.querySelectorAll(".grupos-panel"));
    var chips = [].slice.call(document.querySelectorAll("[data-grupo-chip]"));
    var flechas = [].slice.call(contenedor.querySelectorAll("[data-grupo-dir]"));

    if (tarjetas.length < 2) {
        return;
    }

    var SALIDA_MS = 130;
    var ENTRADA_MS = 170;
    var ALTURA_MS = 260;
    var DESPLAZAMIENTO_PX = 26;

    var clave = "gruposCarrusel:" + contenedor.getAttribute("data-torneo");
    var actual = 0;     // tarjeta visible
    var destino = 0;    // a la que se está yendo
    var animando = false;
    var pendiente = null;

    var reducir = window.matchMedia
        ? window.matchMedia("(prefers-reduced-motion: reduce)")
        : { matches: false };

    function guardar(indice) {
        try { sessionStorage.setItem(clave, String(indice)); } catch (e) { }
    }

    function leerGuardado() {
        try {
            var v = parseInt(sessionStorage.getItem(clave), 10);
            return isNaN(v) ? null : v;
        } catch (e) {
            return null;
        }
    }

    function limitar(indice) {
        return Math.max(0, Math.min(tarjetas.length - 1, indice));
    }

    function actualizarControles(indice) {

        flechas.forEach(function (boton) {
            var dir = parseInt(boton.getAttribute("data-grupo-dir"), 10);
            boton.disabled = (dir < 0 && indice === 0) || (dir > 0 && indice === tarjetas.length - 1);
        });

        chips.forEach(function (chip) {
            var activo = parseInt(chip.getAttribute("data-grupo-chip"), 10) === indice;
            chip.classList.toggle("activo", activo);
            chip.setAttribute("aria-selected", activo ? "true" : "false");
        });

    }

    function mostrar(indice) {

        tarjetas.forEach(function (tarjeta, i) {
            tarjeta.hidden = (i !== indice);
        });

        actual = indice;
        guardar(indice);

    }

    function cambiarConAnimacion(indice, direccion) {

        animando = true;

        var origen = tarjetas[actual];
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

                mostrar(indice);

                contenedor.style.height = "auto";
                var alturaFinal = contenedor.getBoundingClientRect().height;
                contenedor.style.height = alturaInicial + "px";

                var entrada = tarjetas[indice].animate(
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

    function irA(indice) {

        indice = limitar(indice);

        if (animando) {
            // Se recuerda el último pedido; los controles ya reflejan el destino.
            pendiente = indice;
            destino = indice;
            actualizarControles(indice);
            return;
        }

        if (indice === actual) {
            return;
        }

        var direccion = indice > actual ? 1 : -1;

        destino = indice;
        actualizarControles(indice);

        if (reducir.matches || typeof Element.prototype.animate !== "function") {
            mostrar(indice);
            return;
        }

        cambiarConAnimacion(indice, direccion);

    }

    flechas.forEach(function (boton) {
        boton.addEventListener("click", function () {
            irA(destino + parseInt(boton.getAttribute("data-grupo-dir"), 10));
        });
    });

    chips.forEach(function (chip) {
        chip.addEventListener("click", function () {
            irA(parseInt(chip.getAttribute("data-grupo-chip"), 10));
        });
    });

    // Tarjeta inicial: la del partido al que apunta el hash (#partido-12, tras guardar
    // un resultado), si no la última que se estaba viendo, si no la primera.
    var inicial = null;
    var objetivo = null;

    if (/^#partido-\d+$/.test(location.hash)) {

        objetivo = document.getElementById(location.hash.slice(1));

        if (objetivo) {
            var tarjetaObjetivo = objetivo.closest(".grupos-panel");
            var posicion = tarjetas.indexOf(tarjetaObjetivo);
            inicial = posicion >= 0 ? posicion : null;
        }

    }

    if (inicial === null) {
        var guardado = leerGuardado();
        inicial = guardado === null ? 0 : limitar(guardado);
    }

    destino = inicial;
    mostrar(inicial);
    actualizarControles(inicial);

    // Con la tarjeta ya visible se lleva el partido guardado a la vista.
    if (objetivo && !objetivo.closest("[hidden]")) {
        objetivo.scrollIntoView();
    }

})();
