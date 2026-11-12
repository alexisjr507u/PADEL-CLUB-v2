/* Confirmación con el modal del sitio (en lugar del cuadro nativo del navegador).

   Uso: un formulario con data-confirmar pide confirmación antes de enviarse.
     <form data-confirmar
           data-confirmar-titulo="¿Finalizar la temporada?"
           data-confirmar-texto="Los puntos de todos vuelven a 0. No se puede deshacer."
           data-confirmar-boton="Sí, finalizar"        (opcional)
           data-confirmar-cancelar="No, volver"          (opcional)
           data-confirmar-tipo="peligro"                 (opcional: botón rojo)
           data-confirmar-icono="bi-award-fill">         (opcional)

   Usa las mismas clases que los demás modales (modal-mensaje-exito, btn-cancelar-inscripcion...).
   - Cierra con «No, volver», la ×, o Escape. No se cierra al hacer click afuera (es una acción delicada).
   - El foco empieza en el botón seguro (cancelar) y se queda dentro del cuadro con Tab.
   - Al confirmar se bloquean los botones para que un doble click no envíe dos veces.
   - Aparece y desaparece con un fundido; con prefers-reduced-motion es inmediato. */
(function () {

    var modal = null;
    var elementos = {};
    var formulario = null;
    var disparador = null;
    var cerrando = false;

    var reducir = window.matchMedia
        ? window.matchMedia("(prefers-reduced-motion: reduce)")
        : { matches: false };

    function crear() {

        modal = document.createElement("div");
        modal.className = "modal-mensaje-exito";
        modal.setAttribute("role", "alertdialog");
        modal.setAttribute("aria-modal", "true");
        modal.setAttribute("aria-labelledby", "confirmarTitulo");
        modal.setAttribute("aria-describedby", "confirmarTexto");

        modal.innerHTML =
            '<div class="modal-mensaje-exito-contenido">' +
                '<button type="button" class="modal-mensaje-exito-cerrar" data-cf-cerrar aria-label="Cerrar">×</button>' +
                '<div class="modal-mensaje-exito-icono"><i class="bi bi-exclamation-triangle-fill" style="font-size:32px;" data-cf-icono></i></div>' +
                '<h2 id="confirmarTitulo"></h2>' +
                '<p id="confirmarTexto"></p>' +
                '<div class="modal-inscripcion-botones">' +
                    '<button type="button" class="btn-cancelar-inscripcion" data-cf-cancelar></button>' +
                    '<button type="button" class="btn-confirmar-inscripcion" data-cf-aceptar></button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);

        elementos.titulo = modal.querySelector("#confirmarTitulo");
        elementos.texto = modal.querySelector("#confirmarTexto");
        elementos.icono = modal.querySelector("[data-cf-icono]");
        elementos.cerrar = modal.querySelector("[data-cf-cerrar]");
        elementos.cancelar = modal.querySelector("[data-cf-cancelar]");
        elementos.aceptar = modal.querySelector("[data-cf-aceptar]");

        elementos.cerrar.addEventListener("click", cerrar);
        elementos.cancelar.addEventListener("click", cerrar);
        elementos.aceptar.addEventListener("click", confirmar);

        document.addEventListener("keydown", function (evento) {

            if (!modal.classList.contains("mostrar")) {
                return;
            }

            if (evento.key === "Escape") {
                evento.preventDefault();
                cerrar();
                return;
            }

            // Tab se queda dentro del cuadro.
            if (evento.key === "Tab") {

                var foco = [elementos.cerrar, elementos.cancelar, elementos.aceptar].filter(function (b) { return !b.disabled; });
                var actual = foco.indexOf(document.activeElement);

                if (evento.shiftKey && actual <= 0) {
                    evento.preventDefault();
                    foco[foco.length - 1].focus();
                } else if (!evento.shiftKey && actual === foco.length - 1) {
                    evento.preventDefault();
                    foco[0].focus();
                }

            }

        });

    }

    function abrir(form) {

        if (!modal) {
            crear();
        }

        formulario = form;
        disparador = document.activeElement;
        cerrando = false;

        var peligro = form.getAttribute("data-confirmar-tipo") === "peligro";

        elementos.titulo.textContent = form.getAttribute("data-confirmar-titulo") || "¿Confirmás esta acción?";
        elementos.texto.textContent = form.getAttribute("data-confirmar-texto") || "";
        elementos.cancelar.textContent = form.getAttribute("data-confirmar-cancelar") || "No, volver";
        elementos.aceptar.textContent = form.getAttribute("data-confirmar-boton") || "Sí, continuar";
        elementos.aceptar.classList.toggle("btn-confirmar-cancelacion", peligro);
        elementos.aceptar.disabled = false;
        elementos.cancelar.disabled = false;
        elementos.cerrar.disabled = false;

        elementos.icono.className = "bi " + (form.getAttribute("data-confirmar-icono") || "bi-exclamation-triangle-fill");

        modal.classList.add("mostrar");

        document.documentElement.style.overflow = "hidden";
        document.body.style.overflow = "hidden";

        elementos.cancelar.focus();

    }

    function cerrar() {

        if (!modal || cerrando || !modal.classList.contains("mostrar")) {
            return;
        }

        cerrando = true;

        var terminar = function () {

            modal.classList.remove("mostrar");
            modal.style.opacity = "";

            document.documentElement.style.overflow = "";
            document.body.style.overflow = "";

            cerrando = false;

            if (disparador && typeof disparador.focus === "function") {
                disparador.focus();
            }

            formulario = null;

        };

        if (reducir.matches || typeof modal.animate !== "function") {
            terminar();
            return;
        }

        var salida = modal.animate(
            [{ opacity: 1 }, { opacity: 0 }],
            { duration: 160, easing: "ease-in", fill: "forwards" }
        );

        salida.finished.then(function () {
            salida.cancel();
            terminar();
        }).catch(function () {
            terminar();
        });

    }

    function confirmar() {

        if (!formulario || elementos.aceptar.disabled) {
            return;
        }

        // Un solo envío aunque se haga doble click.
        elementos.aceptar.disabled = true;
        elementos.cancelar.disabled = true;
        elementos.cerrar.disabled = true;

        var enviar = formulario;

        // submit() directo no vuelve a disparar este control ni los demás eventos "submit".
        HTMLFormElement.prototype.submit.call(enviar);

    }

    document.addEventListener("submit", function (evento) {

        var form = evento.target;

        if (form && form.matches && form.matches("form[data-confirmar]")) {
            evento.preventDefault();
            abrir(form);
        }

    }, true);

})();
