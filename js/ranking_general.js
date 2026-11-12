/* Selector de temporada: al elegir una temporada el formulario se envía solo (sin JavaScript queda el botón «Ver»). */
(function () {

    var selectores = [].slice.call(document.querySelectorAll("select[data-rk-autoenviar]"));

    selectores.forEach(function (select) {

        var formulario = select.form;

        if (!formulario) {
            return;
        }

        [].slice.call(formulario.querySelectorAll("[data-rk-sin-js]")).forEach(function (boton) {
            boton.hidden = true;
        });

        select.addEventListener("change", function () {
            formulario.submit();
        });

    });

})();

/* Ranking general: muestra los 10 primeros jugadores y permite desplegar de a 10 más («Ver más»)
   o volver a reducir la lista («Ver menos»).

   - Todas las filas ya están en la página (solo se ocultan con `hidden`): sin JavaScript se ven todas.
   - «Ver más» agrega las siguientes con un fundido escalonado; «Ver menos» vuelve a las 10 primeras.
   - «Ver mi fila» (solo participantes) despliega lo necesario, se desplaza hasta su fila y la resalta.
   - Con prefers-reduced-motion los cambios son inmediatos. */
(function () {

    var raiz = document.querySelector("[data-rk-general]");

    if (!raiz) {
        return;
    }

    var filas = [].slice.call(raiz.querySelectorAll("[data-rk-fila]"));
    var zona = raiz.querySelector("[data-rk-mas]");
    var botonMas = raiz.querySelector("[data-rk-ver-mas]");
    var botonMenos = raiz.querySelector("[data-rk-ver-menos]");
    var contador = raiz.querySelector("[data-rk-contador]");
    var irPropia = raiz.querySelector("[data-rk-ir-propia]");

    var INICIAL = parseInt(raiz.getAttribute("data-rk-inicial"), 10) || 10;
    var PASO = parseInt(raiz.getAttribute("data-rk-paso"), 10) || 10;
    var total = filas.length;

    var reducir = window.matchMedia
        ? window.matchMedia("(prefers-reduced-motion: reduce)")
        : { matches: false };

    var propiaIndice = -1;
    filas.forEach(function (fila, i) {
        if (fila.hasAttribute("data-rk-propia")) { propiaIndice = i; }
    });

    if (irPropia && propiaIndice === -1) {
        irPropia.hidden = true;
    }

    // Con 10 jugadores o menos no hace falta ningún botón.
    if (!zona || total <= INICIAL) {
        if (irPropia) {
            irPropia.addEventListener("click", function () { destacar(propiaIndice); });
        }
        return;
    }

    var visibles = INICIAL;
    var ficha = 0;

    function pintarEstado() {

        contador.textContent = "Mostrando " + visibles + " de " + total + " jugadores";

        botonMas.hidden = visibles >= total;
        botonMenos.hidden = visibles <= INICIAL;

    }

    function mostrarHasta(n) {
        filas.forEach(function (fila, i) {
            fila.hidden = i >= n;
        });
    }

    function destacar(indice) {

        if (indice < 0 || !filas[indice]) {
            return;
        }

        var fila = filas[indice];

        fila.scrollIntoView({ behavior: reducir.matches ? "auto" : "smooth", block: "center" });

        fila.classList.add("rk-destello");
        window.setTimeout(function () { fila.classList.remove("rk-destello"); }, 1600);

    }

    function cambiar(nuevo, animar) {

        var miFicha = ++ficha;
        var anterior = visibles;

        nuevo = Math.max(INICIAL, Math.min(total, nuevo));
        visibles = nuevo;

        pintarEstado();

        if (!animar || reducir.matches || typeof filas[0].animate !== "function") {
            mostrarHasta(nuevo);
            return Promise.resolve();
        }

        // Más filas: aparecen con un fundido escalonado.
        if (nuevo > anterior) {

            mostrarHasta(nuevo);

            // El escalonado dura como máximo ~280 ms en total; si se agregan muchas filas de golpe
            // (por ejemplo «Ver mi fila») entran todas juntas, solo con un fundido.
            var muchas = (nuevo - anterior) > 30;

            for (var i = anterior; i < nuevo; i++) {
                filas[i].animate(
                    muchas
                        ? [{ opacity: 0 }, { opacity: 1 }]
                        : [
                            { opacity: 0, transform: "translateY(8px)" },
                            { opacity: 1, transform: "translateY(0)" }
                        ],
                    { duration: muchas ? 220 : 260, delay: muchas ? 0 : Math.min((i - anterior) * 28, 280), easing: "ease-out", fill: "backwards" }
                );
            }

            return Promise.resolve();

        }

        // Menos filas: se desvanecen y recién después se ocultan.
        var saliendo = [];

        for (var j = nuevo; j < anterior; j++) {
            saliendo.push(filas[j].animate(
                [{ opacity: 1 }, { opacity: 0 }],
                { duration: 160, easing: "ease-in", fill: "forwards" }
            ));
        }

        return Promise.all(saliendo.map(function (a) { return a.finished; }))
            .catch(function () { })
            .then(function () {
                saliendo.forEach(function (a) { try { a.cancel(); } catch (e) { } });
                if (miFicha === ficha) {
                    mostrarHasta(nuevo);
                }
            });

    }

    botonMas.addEventListener("click", function () {
        cambiar(visibles + PASO, true);
    });

    botonMenos.addEventListener("click", function () {

        var arriba = raiz.getBoundingClientRect().top;

        cambiar(INICIAL, true);

        // Si el inicio de la tabla quedó fuera de pantalla, se vuelve a él.
        if (arriba < 0) {
            raiz.scrollIntoView({ behavior: reducir.matches ? "auto" : "smooth", block: "start" });
        }

    });

    if (irPropia) {
        irPropia.addEventListener("click", function () {

            if (propiaIndice < 0) { return; }

            var necesarias = Math.max(INICIAL, Math.ceil((propiaIndice + 1) / PASO) * PASO);

            cambiar(Math.max(visibles, necesarias), true).then(function () {
                destacar(propiaIndice);
            });

        });
    }

    // Estado inicial: 10 filas y la zona de botones a la vista.
    mostrarHasta(INICIAL);
    zona.hidden = false;
    pintarEstado();

})();
