/* Mini carrusel de partidos dentro de cada grupo (Gestionar grupos).

   Cada grupo tiene todos sus partidos en la página; se muestran de a
   PARTIDOS_POR_PAGINA y se cambia de página con las flechas o los puntos.
   Los formularios no se eliminan (solo se ocultan): lo que se haya escrito
   sin guardar se conserva y los envíos funcionan igual. Sin JavaScript se
   ven todos los partidos. Con prefers-reduced-motion el cambio es inmediato.

   Debe cargarse ANTES de grupos_carrusel.js para que, al volver de guardar
   un resultado (#partido-12), el partido ya esté en la página visible. */
(function () {

    var PARTIDOS_POR_PAGINA = 2;
    var SALIDA_MS = 120;
    var ENTRADA_MS = 160;
    var ALTURA_MS = 220;
    var DESPLAZAMIENTO_PX = 20;

    var listas = [].slice.call(document.querySelectorAll(".grupos-panel .grupos-marcadores"));

    var reducir = window.matchMedia
        ? window.matchMedia("(prefers-reduced-motion: reduce)")
        : { matches: false };

    listas.forEach(iniciar);


    function iniciar(lista) {

        var partidos = [].slice.call(lista.querySelectorAll(":scope > form.marcador"));

        if (partidos.length <= PARTIDOS_POR_PAGINA) {
            return;
        }

        var totalPaginas = Math.ceil(partidos.length / PARTIDOS_POR_PAGINA);
        var actual = 0;
        var destino = 0;
        var animando = false;
        var pendiente = null;

        // Controles: flechas + puntos + texto "Partidos 1-2 de 6".
        var barra = document.createElement("div");
        barra.className = "partidos-pag";

        var anterior = crearBoton("bi-chevron-left", "Partidos anteriores");
        var siguiente = crearBoton("bi-chevron-right", "Partidos siguientes");
        var texto = document.createElement("span");
        texto.className = "partidos-pag-texto";
        var puntos = document.createElement("div");
        puntos.className = "partidos-pag-puntos";

        var botonesPunto = [];

        for (var i = 0; i < totalPaginas; i++) {
            (function (indice) {
                var punto = document.createElement("button");
                punto.type = "button";
                punto.className = "partidos-pag-punto";
                punto.setAttribute("aria-label", "Ir a la página " + (indice + 1) + " de partidos");
                punto.addEventListener("click", function () { irA(indice); });
                puntos.appendChild(punto);
                botonesPunto.push(punto);
            })(i);
        }

        barra.appendChild(anterior);
        barra.appendChild(puntos);
        barra.appendChild(texto);
        barra.appendChild(siguiente);
        lista.parentNode.insertBefore(barra, lista.nextSibling);

        function crearBoton(icono, etiqueta) {
            var b = document.createElement("button");
            b.type = "button";
            b.className = "partidos-pag-flecha";
            b.setAttribute("aria-label", etiqueta);
            b.innerHTML = '<i class="bi ' + icono + '"></i>';
            return b;
        }

        function actualizarControles(pagina) {

            anterior.disabled = pagina === 0;
            siguiente.disabled = pagina === totalPaginas - 1;

            var desde = pagina * PARTIDOS_POR_PAGINA + 1;
            var hasta = Math.min(partidos.length, (pagina + 1) * PARTIDOS_POR_PAGINA);
            texto.textContent = "Partidos " + desde + (hasta > desde ? "-" + hasta : "") + " de " + partidos.length;

            botonesPunto.forEach(function (punto, i) {
                punto.classList.toggle("activo", i === pagina);
            });

        }

        function mostrar(pagina) {

            partidos.forEach(function (partido, i) {
                partido.hidden = Math.floor(i / PARTIDOS_POR_PAGINA) !== pagina;
            });

            actual = pagina;

        }

        function cambiarConAnimacion(pagina, direccion) {

            animando = true;

            var alturaInicial = lista.getBoundingClientRect().height;

            lista.style.overflow = "hidden";
            lista.style.height = alturaInicial + "px";

            var salida = lista.animate(
                [
                    { transform: "translateX(0)", opacity: 1 },
                    { transform: "translateX(" + (-direccion * DESPLAZAMIENTO_PX) + "px)", opacity: 0 }
                ],
                { duration: SALIDA_MS, easing: "ease-in", fill: "forwards" }
            );

            salida.finished
                .catch(function () { })
                .then(function () {

                    mostrar(pagina);

                    lista.style.height = "auto";
                    var alturaFinal = lista.getBoundingClientRect().height;
                    lista.style.height = alturaInicial + "px";

                    var entrada = lista.animate(
                        [
                            { transform: "translateX(" + (direccion * DESPLAZAMIENTO_PX) + "px)", opacity: 0 },
                            { transform: "translateX(0)", opacity: 1 }
                        ],
                        { duration: ENTRADA_MS, easing: "ease-out", fill: "backwards" }
                    );

                    salida.cancel();

                    var altura = lista.animate(
                        [{ height: alturaInicial + "px" }, { height: alturaFinal + "px" }],
                        { duration: ALTURA_MS, easing: "ease-in-out" }
                    );

                    return Promise.all([entrada.finished, altura.finished]);

                })
                .catch(function () { })
                .then(function () {

                    lista.style.height = "";
                    lista.style.overflow = "";
                    animando = false;

                    if (pendiente !== null && pendiente !== actual) {
                        var siguientePagina = pendiente;
                        pendiente = null;
                        irA(siguientePagina);
                    } else {
                        pendiente = null;
                    }

                });

        }

        function irA(pagina) {

            pagina = Math.max(0, Math.min(totalPaginas - 1, pagina));

            if (animando) {
                pendiente = pagina;
                destino = pagina;
                actualizarControles(pagina);
                return;
            }

            if (pagina === actual) {
                return;
            }

            var direccion = pagina > actual ? 1 : -1;

            destino = pagina;
            actualizarControles(pagina);

            if (reducir.matches || typeof Element.prototype.animate !== "function") {
                mostrar(pagina);
                return;
            }

            cambiarConAnimacion(pagina, direccion);

        }

        anterior.addEventListener("click", function () { irA(destino - 1); });
        siguiente.addEventListener("click", function () { irA(destino + 1); });

        // Página inicial: la del partido al que apunta el hash (#partido-12), si está acá.
        var inicial = 0;

        if (/^#partido-\d+$/.test(location.hash)) {

            var objetivo = document.getElementById(location.hash.slice(1));
            var posicion = objetivo ? partidos.indexOf(objetivo) : -1;

            if (posicion >= 0) {
                inicial = Math.floor(posicion / PARTIDOS_POR_PAGINA);
            }

        }

        destino = inicial;
        mostrar(inicial);
        actualizarControles(inicial);

    }

})();
