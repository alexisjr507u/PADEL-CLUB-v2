// ==========================================
// GESTIONAR PARTICIPANTES - MODAL DE CANCELACIÓN
// ==========================================

document.addEventListener("DOMContentLoaded", function () {

    const botonesCancelar = document.querySelectorAll(".btn-cancelar-gestion");

    if (!botonesCancelar.length) {
        return;
    }

    const modal = document.getElementById("modalCancelarParticipante");
    const texto = document.getElementById("textoCancelarParticipante");
    const idOculto = document.getElementById("idElementoCancelar");
    const cerrar = document.getElementById("cerrarCancelarParticipante");
    const noCancelar = document.getElementById("noCancelarParticipante");

    if (!modal || !texto || !idOculto) {
        return;
    }

    function cerrarModalCancelar() {

        modal.classList.remove("mostrar");

        document.documentElement.style.overflow = "";
        document.body.style.overflow = "";

    }

    botonesCancelar.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const nombre = boton.dataset.nombre || "esta participación";

            texto.textContent = "¿Seguro que querés cancelar a \"" + nombre + "\"?";

            idOculto.value = boton.dataset.id || "";

            modal.classList.add("mostrar");

            document.documentElement.style.overflow = "hidden";
            document.body.style.overflow = "hidden";

        });

    });

    if (cerrar) {
        cerrar.addEventListener("click", cerrarModalCancelar);
    }

    if (noCancelar) {
        noCancelar.addEventListener("click", cerrarModalCancelar);
    }

});


//loader "pelota de pádel" al enviar formularios (login, registros, verificar código)
(function () {

    const overlay = document.createElement("div");
    overlay.className = "loader-overlay";
    overlay.setAttribute("role", "status");
    overlay.setAttribute("aria-live", "polite");
    overlay.setAttribute("aria-busy", "false");

    overlay.innerHTML =
        '<div class="padel-ball"><span class="padel-ball-costuras"></span></div>' +
        '<p class="loader-texto"></p>';

    document.addEventListener("DOMContentLoaded", () => {
        document.body.appendChild(overlay);
    });

    const textoLoader = overlay.querySelector(".loader-texto");

    function mostrarLoader(texto) {

        textoLoader.textContent = texto || "Procesando...";
        overlay.classList.add("activo");
        overlay.setAttribute("aria-busy", "true");

    }

    document.addEventListener("DOMContentLoaded", () => {

        document.querySelectorAll("form[data-loader]").forEach((form) => {

            form.addEventListener("submit", (evento) => {

                // Se espera un tick para que terminen de correr otras
                // validaciones del formulario (por ejemplo las de
                // organizador.js) antes de decidir si se muestra el loader.
                setTimeout(() => {

                    if (evento.defaultPrevented) {
                        return;
                    }

                    mostrarLoader(form.dataset.loader);

                    const boton = form.querySelector('button[type="submit"]');

                    if (boton) {
                        boton.disabled = true;
                    }

                }, 0);

            });

        });

    });

})();
//tiempo de mensaje
setTimeout(() => {

    const mensaje = document.getElementById("mensajeError");

    if(mensaje){

        mensaje.style.transition = "opacity 0.5s";
        mensaje.style.opacity = "0";

        setTimeout(() => {
            mensaje.remove();
        }, 500);

    }

}, 3000);
//ocultar/mostrar contraseña (funciona para cualquier cantidad de campos en la página)
document.querySelectorAll(".toggle-password").forEach((icono) => {

    const campo = icono.previousElementSibling;

    if (!campo) return;

    icono.addEventListener("click", () => {

        const esPassword = campo.type === "password";

        campo.type = esPassword ? "text" : "password";

        icono.classList.toggle("bi-eye");
        icono.classList.toggle("bi-eye-slash");

    });

});
//renviar codigo existoso
const mensajeExito = document.getElementById("mensajeExito");

if(mensajeExito){

    setTimeout(() => {

        mensajeExito.style.transition = "opacity 0.5s";
        mensajeExito.style.opacity = "0";

        setTimeout(() => {
            mensajeExito.remove();
        }, 500);

    }, 3000);

}
//barra de cambiar contraseña 
const nuevaPassword =
    document.querySelector('[name="password_nueva"]');

if(nuevaPassword){

    nuevaPassword.addEventListener('input', () => {

        const password = nuevaPassword.value;

        let fuerza = 0;

        if(password.length >= 8) fuerza++;
        if(/[A-Z]/.test(password)) fuerza++;
        if(/[0-9]/.test(password)) fuerza++;
        if(/[^A-Za-z0-9]/.test(password)) fuerza++;

        const barra =
            document.getElementById("strengthBar");

        const texto =
            document.getElementById("strengthText");

        if(!barra || !texto) return;

        if(fuerza === 1){

            barra.style.width = "25%";
            barra.style.background = "#dc3545";
            texto.innerText =
                "Nivel de seguridad: Baja";

        }else if(fuerza === 2){

            barra.style.width = "50%";
            barra.style.background = "#fd7e14";
            texto.innerText =
                "Nivel de seguridad: Media";

        }else if(fuerza === 3){

            barra.style.width = "75%";
            barra.style.background = "#0d6efd";
            texto.innerText =
                "Nivel de seguridad: Media/Alta";

        }else if(fuerza === 4){

            barra.style.width = "100%";
            barra.style.background = "#198754";
            texto.innerText =
                "Nivel de seguridad: Alta";

        }else{

            barra.style.width = "0%";
            texto.innerText =
                "Nivel de seguridad: -";

        }

    });

}
//cache
window.addEventListener("pageshow", function(event) {

    if (event.persisted) {
        window.location.reload();
    }

});
window.onload = function() {

    const formularios = document.querySelectorAll("form");

    formularios.forEach(formulario => {
        formulario.reset();
    });

}
//ventanas de navegacion
window.addEventListener("pageshow", function(event) {

    if (event.persisted) {
        window.location.href = "login.php";
    }

});
//encuesta pregunta nunca
document.addEventListener("DOMContentLoaded", function () {

    const nuncaJugo = document.querySelector('input[name="p1"][value="nunca"]');

    if (!nuncaJugo) return;

    const preguntas = [
        document.getElementById("pregunta2"),
        document.getElementById("pregunta3"),
        document.getElementById("pregunta4"),
        document.getElementById("pregunta5")
    ];

    const boton = document.getElementById("btnContinuar");

    const modal = document.getElementById("modalConfirmacion");
    const btnAceptar = document.getElementById("btnAceptar");
    const btnCancelar = document.getElementById("btnCancelar");

    // Mostrar modal
    nuncaJugo.addEventListener("change", function () {

        if (this.checked) {

            modal.style.display = "flex";

        }

    });

    // Cancelar
    btnCancelar.addEventListener("click", function () {

        modal.style.display = "none";

        nuncaJugo.checked = false;

    });

    // Aceptar
    btnAceptar.addEventListener("click", function () {

        modal.style.display = "none";

        document.getElementById("principiante").value = "1";

        preguntas.forEach(function (pregunta) {

            pregunta.style.display = "none";

            pregunta.querySelectorAll("input").forEach(function (input) {

                input.disabled = true;

            });

        });

        boton.textContent = "Finalizar encuesta ";

    });

});
// ==========================================
// GENERAR CAMPOS DE CANCHAS
// ==========================================

const cantidadCanchas = document.getElementById("cantidad_canchas");

const contenedorCanchas = document.getElementById("contenedor-canchas");


if (cantidadCanchas && contenedorCanchas) {


    cantidadCanchas.addEventListener("input", function () {


        let cantidad = parseInt(this.value);


        // Limpiar canchas anteriores

        contenedorCanchas.innerHTML = "";


        // Si no hay cantidad válida

        if (isNaN(cantidad) || cantidad < 1) {

            return;

        }


        // Máximo 20

        if (cantidad > 20) {

            cantidad = 20;

            this.value = 20;

        }


        // Crear las canchas

        for (let i = 1; i <= cantidad; i++) {


            const cancha = document.createElement("div");

            cancha.classList.add("cancha-registro");


            cancha.innerHTML = `

                <h4>
                    Cancha ${i}
                </h4>


                <div class="input-group">

                    <label>
                        Nombre de la cancha
                    </label>

                    <input
                        type="text"
                        name="nombre_cancha[]"
                        value="Cancha ${i}"
                        placeholder="Ej: Cancha 1"
                        required
                    >

                </div>


                <div class="input-group">

                    <label>
                        Estado
                    </label>

                    <select
                        name="estado_cancha[]"
                        required
                    >

                        <option value="">
                            Seleccione...
                        </option>

                        <option value="Disponible">
                            Disponible
                        </option>

                        <option value="Ocupada">
                            Ocupada
                        </option>

                    </select>

                </div>


                <div class="input-group">

                    <label>
                        Imagen de la cancha
                    </label>

                    <input
                        type="file"
                        name="imagen_cancha[]"
                        accept="image/*"
                        required
                    >

                </div>

            `;


            contenedorCanchas.appendChild(cancha);

        }

    });

}
// ==========================================
// REGISTRO DE ORGANIZADOR - CANCHAS
// ==========================================

document.addEventListener("DOMContentLoaded", function () {

    const cantidadCanchas = document.getElementById("cantidad_canchas");
    const contenedorCanchas = document.getElementById("contenedor-canchas");

    // Verificar que estamos en el formulario de organizador
    if (!cantidadCanchas || !contenedorCanchas) {
        return;
    }

    cantidadCanchas.addEventListener("input", function () {

        let cantidad = parseInt(this.value);

        // Limpiar
        contenedorCanchas.innerHTML = "";

        // Si no hay una cantidad válida
        if (isNaN(cantidad) || cantidad < 1) {
            return;
        }

        // Máximo 20
        if (cantidad > 20) {
            cantidad = 20;
            this.value = 20;
        }

        // Crear las canchas
        for (let i = 1; i <= cantidad; i++) {

            const cancha = document.createElement("div");

            cancha.className = "cancha-registro";

            cancha.innerHTML = `
                
                <h4>Cancha ${i}</h4>

                <div class="input-group">

                    <label>Nombre de la cancha</label>

                    <input
                        type="text"
                        name="nombre_cancha[]"
                        value="Cancha ${i}"
                        placeholder="Ej: Cancha 1"
                        required
                    >

                </div>


                <div class="input-group">

                    <label>Estado</label>

                    <select
                        name="estado_cancha[]"
                        required
                    >

                        <option value="">
                            Seleccione...
                        </option>

                        <option value="Disponible">
                            Disponible
                        </option>

                        <option value="Ocupada">
                            Ocupada
                        </option>

                    </select>

                </div>


                <div class="input-group">

                    <label>Imagen de la cancha</label>

                    <input
                        type="file"
                        name="imagen_cancha[]"
                        accept="image/*"
                        required
                    >

                </div>

            `;

            contenedorCanchas.appendChild(cancha);

        }

    });

});
/* ==========================================
   CARRUSEL DE CANCHAS
========================================== */

function moverCancha(boton, direccion) {

    const carrusel = boton.closest(".carrusel-canchas");

    if (!carrusel) {
        return;
    }

    const slides = carrusel.querySelectorAll(".cancha-slide");

    const indicadores = carrusel.parentElement.querySelectorAll(
        ".indicador-cancha"
    );

    if (slides.length === 0) {
        return;
    }

    let actual = 0;

    slides.forEach((slide, index) => {

        if (slide.style.display === "block") {
            actual = index;
        }

    });


    let siguiente = actual + direccion;


    if (siguiente >= slides.length) {

        siguiente = 0;

    }


    if (siguiente < 0) {

        siguiente = slides.length - 1;

    }


    slides.forEach((slide, index) => {

        slide.style.display =
            index === siguiente ? "block" : "none";

    });


    indicadores.forEach((indicador, index) => {

        indicador.classList.toggle(
            "activo",
            index === siguiente
        );

    });

}
/* ==========================================
   MODALES DE SOLICITUDES
========================================== */

document.addEventListener("DOMContentLoaded", function () {


    // ========================================
    // ABRIR MODAL
    // ========================================

    const botonesVer =
        document.querySelectorAll(".btn-ver-solicitud");


    botonesVer.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const id =
                this.dataset.solicitud;

            const modal =
                document.getElementById(
                    "modal-" + id
                );


            if (modal) {

                modal.classList.add("activo");

                document.body.style.overflow =
                    "hidden";

            }

        });

    });


    // ========================================
    // CERRAR MODAL
    // ========================================

    const botonesCerrar =
        document.querySelectorAll(
            ".modal-cerrar"
        );


    botonesCerrar.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const id =
                this.dataset.cerrarModal;

            const modal =
                document.getElementById(
                    "modal-" + id
                );


            if (modal) {

                modal.classList.remove("activo");

                document.body.style.overflow =
                    "";

            }

        });

    });


    // ========================================
    // CERRAR HACIENDO CLICK AFUERA
    // ========================================

    document
        .querySelectorAll(".modal-solicitud")
        .forEach(function (modal) {


            modal.addEventListener(
                "click",
                function (evento) {

                    if (
                        evento.target === modal
                    ) {

                        modal.classList.remove(
                            "activo"
                        );

                        document.body.style.overflow =
                            "";

                    }

                }
            );


        });


    // ========================================
    // CERRAR CON ESC
    // ========================================

    document.addEventListener(
        "keydown",
        function (evento) {

            if (evento.key === "Escape") {

                document
                    .querySelectorAll(
                        ".modal-solicitud.activo"
                    )
                    .forEach(function (modal) {

                        modal.classList.remove(
                            "activo"
                        );

                    });

                document.body.style.overflow =
                    "";

            }

        }
    );


    // ========================================
    // CARRUSELES
    // ========================================

    document
        .querySelectorAll(
            ".carrusel-canchas"
        )
        .forEach(function (carrusel) {


            const slides =
                carrusel.querySelectorAll(
                    ".cancha-slide"
                );


            const indicadores =
                carrusel.querySelectorAll(
                    ".carrusel-indicadores span"
                );


            let actual = 0;


            function mostrarSlide(numero) {

                if (slides.length === 0) {
                    return;
                }


                if (numero < 0) {

                    numero =
                        slides.length - 1;

                }


                if (
                    numero >=
                    slides.length
                ) {

                    numero = 0;

                }


                slides.forEach(
                    function (slide, index) {

                        slide.classList.toggle(
                            "cancha-slide-activa",
                            index === numero
                        );

                    }
                );


                indicadores.forEach(
                    function (
                        indicador,
                        index
                    ) {

                        indicador.classList.toggle(
                            "indicador-activo",
                            index === numero
                        );

                    }
                );


                actual = numero;

            }


            // ANTERIOR

            const anterior =
                carrusel.querySelector(
                    ".carrusel-anterior"
                );


            if (anterior) {

                anterior.addEventListener(
                    "click",
                    function () {

                        mostrarSlide(
                            actual - 1
                        );

                    }
                );

            }


            // SIGUIENTE

            const siguiente =
                carrusel.querySelector(
                    ".carrusel-siguiente"
                );


            if (siguiente) {

                siguiente.addEventListener(
                    "click",
                    function () {

                        mostrarSlide(
                            actual + 1
                        );

                    }
                );

            }


            // INDICADORES

            indicadores.forEach(
                function (
                    indicador,
                    index
                ) {

                    indicador.addEventListener(
                        "click",
                        function () {

                            mostrarSlide(index);

                        }
                    );

                }
            );


        });


});
//
// ==========================================
// MODAL AGREGAR CANCHA
// ==========================================

document.addEventListener("DOMContentLoaded", function () {

    const btnAbrir = document.getElementById("btnAbrirAgregarCancha");
    const modal = document.getElementById("modalAgregarCancha");

    const btnCerrar = document.getElementById("cerrarAgregarCancha");
    const btnCancelar = document.getElementById("cancelarAgregarCancha");

    const inputImagen = document.getElementById("imagen_cancha");
    const preview = document.getElementById("previewCancha");


    // ==========================================
    // ABRIR MODAL
    // ==========================================

    if (btnAbrir && modal) {

        btnAbrir.addEventListener("click", function () {

            modal.classList.add("mostrar");

        });

    }


    // ==========================================
    // FUNCIÓN PARA CERRAR
    // ==========================================

    function cerrarModal() {

        if (modal) {
            modal.classList.remove("mostrar");
        }

    }


    // ==========================================
    // BOTÓN X
    // ==========================================

    if (btnCerrar) {

        btnCerrar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModal();

        });

    }


    // ==========================================
    // BOTÓN CANCELAR
    // ==========================================

    if (btnCancelar) {

        btnCancelar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModal();

        });

    }


    // ==========================================
    // CERRAR HACIENDO CLICK FUERA
    // ==========================================

    if (modal) {

        modal.addEventListener("click", function (e) {

            if (e.target === modal) {

                cerrarModal();

            }

        });

    }


    // ==========================================
    // VISTA PREVIA DE LA IMAGEN
    // ==========================================

    if (inputImagen && preview) {

        inputImagen.addEventListener("change", function () {

            const archivo = this.files[0];

            // Si no seleccionó imagen
            if (!archivo) {

                preview.innerHTML = "";

                return;

            }


            // Verificar tipo
            if (!archivo.type.match(/^image\/(jpeg|png|webp)$/)) {

                alert("Seleccione una imagen JPG, PNG o WEBP.");

                this.value = "";

                preview.innerHTML = "";

                return;

            }


            // Verificar tamaño: máximo 5 MB
            if (archivo.size > 5 * 1024 * 1024) {

                alert("La imagen no puede superar los 5 MB.");

                this.value = "";

                preview.innerHTML = "";

                return;

            }


            // Crear vista previa
            const lector = new FileReader();

            lector.onload = function (e) {

                preview.innerHTML = `
                    <img
                        src="${e.target.result}"
                        alt="Vista previa"
                    >
                `;

            };

            lector.readAsDataURL(archivo);

        });

    }

});
// ==========================================
// CAMBIAR ESTADO DE CANCHA
// ==========================================

document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById("modalCambiarEstado");

    const cerrar = document.getElementById("cerrarCambiarEstado");
    const cancelar = document.getElementById("cancelarCambiarEstado");

    const idCancha = document.getElementById("estadoIdCancha");
    const nombreCancha = document.getElementById("estadoNombreCancha");
    const nuevoEstado = document.getElementById("nuevoEstadoCancha");

    const botonesEstado = document.querySelectorAll(".btn-cancha.estado");


    // ==========================================
    // ABRIR MODAL
    // ==========================================

    botonesEstado.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            const estado = this.dataset.estado;


            // Pasar datos al formulario

            if (idCancha) {
                idCancha.value = id;
            }

            if (nombreCancha) {
                nombreCancha.value = nombre;
            }

            if (nuevoEstado) {
                nuevoEstado.value = estado;
            }


            // Mostrar modal

            if (modal) {
                modal.classList.add("mostrar");
            }

        });

    });


    // ==========================================
    // CERRAR MODAL
    // ==========================================

    function cerrarModalEstado() {

        if (modal) {
            modal.classList.remove("mostrar");
        }

    }


    // BOTÓN X

    if (cerrar) {

        cerrar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModalEstado();

        });

    }


    // BOTÓN CANCELAR

    if (cancelar) {

        cancelar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModalEstado();

        });

    }


    // ==========================================
    // CERRAR HACIENDO CLICK FUERA
    // ==========================================

    if (modal) {

        modal.addEventListener("click", function (e) {

            if (e.target === modal) {

                cerrarModalEstado();

            }

        });

    }

});
// ==========================================
// MODAL EDITAR CANCHA
// ==========================================

document.addEventListener("DOMContentLoaded", function () {

    const modalEditar = document.getElementById("modalEditarCancha");
    const cerrarEditar = document.getElementById("cerrarEditarCancha");
    const cancelarEditar = document.getElementById("cancelarEditarCancha");

    const idEditar = document.getElementById("editarIdCancha");
    const nombreEditar = document.getElementById("editarNombreCancha");
    const imagenEditar = document.getElementById("editarImagenCancha");
    const previewEditar = document.getElementById("previewEditarCancha");

    const botonesEditar = document.querySelectorAll(".btn-cancha.editar");


    // ==========================================
    // ABRIR MODAL
    // ==========================================

    botonesEditar.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            const imagen = this.dataset.imagen;

            // Cargar datos actuales
            if (idEditar) {
                idEditar.value = id;
            }

            if (nombreEditar) {
                nombreEditar.value = nombre;
            }

            // Mostrar imagen actual
            if (previewEditar) {

                if (imagen) {

                    previewEditar.innerHTML = `
                        <img
                            src="${imagen}"
                            alt="Imagen actual de la cancha"
                        >
                    `;

                } else {

                    previewEditar.innerHTML = `
                        <div class="preview-cancha-vacio">
                            🏟
                            <span>Sin imagen</span>
                        </div>
                    `;

                }
            }

            // Limpiar selección de archivo
            if (imagenEditar) {
                imagenEditar.value = "";
            }

            // Abrir modal
            if (modalEditar) {
                modalEditar.classList.add("mostrar");
            }

        });

    });


    // ==========================================
    // PREVISUALIZAR NUEVA IMAGEN
    // ==========================================

    if (imagenEditar) {

        imagenEditar.addEventListener("change", function () {

            const archivo = this.files[0];

            if (!archivo) {
                return;
            }

            const lector = new FileReader();

            lector.onload = function (evento) {

                if (previewEditar) {

                    previewEditar.innerHTML = `
                        <img
                            src="${evento.target.result}"
                            alt="Nueva imagen de la cancha"
                        >
                    `;

                }

            };

            lector.readAsDataURL(archivo);

        });

    }


    // ==========================================
    // CERRAR MODAL
    // ==========================================

    function cerrarModalEditar() {

        if (modalEditar) {
            modalEditar.classList.remove("mostrar");
        }

    }


    if (cerrarEditar) {

        cerrarEditar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModalEditar();

        });

    }


    if (cancelarEditar) {

        cancelarEditar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModalEditar();

        });

    }


    // Cerrar haciendo clic fuera del modal

    if (modalEditar) {

        modalEditar.addEventListener("click", function (e) {

            if (e.target === modalEditar) {

                cerrarModalEditar();

            }

        });

    }

});
// =========================================
// BUSCADOR Y FILTRO DE CANCHAS
// =========================================

document.addEventListener("DOMContentLoaded", function () {

    const buscador = document.getElementById("buscarCancha");
    const filtroEstado = document.getElementById("filtroEstado");
    const canchas = document.querySelectorAll(".cancha-card");

    function filtrarCanchas() {

        const texto = buscador
            ? buscador.value.toLowerCase().trim()
            : "";

        const estadoSeleccionado = filtroEstado
            ? filtroEstado.value
            : "todos";

        canchas.forEach(function (cancha) {

            const nombre = (
                cancha.dataset.nombre || ""
            ).toLowerCase();

            const estado = (
                cancha.dataset.estado || ""
            ).toLowerCase();

            const coincideNombre =
                nombre.includes(texto);

            const coincideEstado =
                estadoSeleccionado === "todos" ||
                estado === estadoSeleccionado.toLowerCase();

            if (coincideNombre && coincideEstado) {
                cancha.style.display = "";
            } else {
                cancha.style.display = "none";
            }

        });
    }

    // Buscar mientras escribís
    if (buscador) {
        buscador.addEventListener("input", filtrarCanchas);
    }

    // Filtrar cuando elegís un estado
    if (filtroEstado) {
        filtroEstado.addEventListener("change", filtrarCanchas);
    }

}); 
// =========================================
// BUSCADOR Y FILTRO DE CANCHAS
// =========================================

document.addEventListener("DOMContentLoaded", function () {

    const buscador = document.getElementById("buscarCancha");
    const filtroEstado = document.getElementById("filtroEstado");
    const canchas = document.querySelectorAll(".cancha-card");

    function filtrarCanchas() {

        const texto = buscador
            ? buscador.value.toLowerCase().trim()
            : "";

        const estadoSeleccionado = filtroEstado
            ? filtroEstado.value
            : "todos";

        canchas.forEach(function (cancha) {

            const nombre = (
                cancha.dataset.nombre || ""
            ).toLowerCase();

            const estado = (
                cancha.dataset.estado || ""
            ).toLowerCase();

            const coincideNombre =
                nombre.includes(texto);

            const coincideEstado =
                estadoSeleccionado === "todos" ||
                estado === estadoSeleccionado.toLowerCase();

            if (coincideNombre && coincideEstado) {
                cancha.style.display = "";
            } else {
                cancha.style.display = "none";
            }

        });
    }

    // Buscar mientras escribís
    if (buscador) {
        buscador.addEventListener("input", filtrarCanchas);
    }

    // Filtrar al cambiar el estado
    if (filtroEstado) {
        filtroEstado.addEventListener("change", filtrarCanchas);
    }

});
// =========================================
// ELIMINAR CANCHA - MODAL PERSONALIZADO
// =========================================

document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById("modalEliminarCancha");
    const cerrar = document.getElementById("cerrarEliminarCancha");
    const cancelar = document.getElementById("cancelarEliminarCancha");

    const idCancha = document.getElementById("idCanchaEliminar");
    const nombreCancha = document.getElementById("nombreCanchaEliminar");

    const botonesEliminar = document.querySelectorAll(
        ".btn-cancha.eliminar"
    );


    // =========================================
    // ABRIR MODAL
    // =========================================

    botonesEliminar.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const id = this.dataset.id;
            const nombre = this.dataset.nombre;

            if (idCancha) {
                idCancha.value = id;
            }

            if (nombreCancha) {
                nombreCancha.textContent = '"' + nombre + '"';
            }

            if (modal) {
                modal.classList.add("mostrar");
            }

        });

    });


    // =========================================
    // CERRAR MODAL
    // =========================================

    function cerrarModalEliminar() {

        if (modal) {
            modal.classList.remove("mostrar");
        }

    }


    // BOTÓN X

    if (cerrar) {

        cerrar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModalEliminar();

        });

    }


    // BOTÓN CANCELAR

    if (cancelar) {

        cancelar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModalEliminar();

        });

    }


    // CERRAR HACIENDO CLICK FUERA

    if (modal) {

        modal.addEventListener("click", function (e) {

            if (e.target === modal) {

                cerrarModalEliminar();

            }

        });

    }

});
// =========================================
// =========================================
// VER TORNEO
// =========================================

document.addEventListener("DOMContentLoaded", function () {

    const botonesVer = document.querySelectorAll(".btn-torneo.ver");

    botonesVer.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const id = this.dataset.id;

            if (id) {
                window.location.href = "ver_torneo.php?id=" + id;
            }

        });

    });

});

// =========================================
// ELIMINAR TORNEO - MODAL PERSONALIZADO
// =========================================

document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById("modalEliminarTorneo");
    const cerrar = document.getElementById("cerrarEliminarTorneo");
    const cancelar = document.getElementById("cancelarEliminarTorneo");

    const idTorneo = document.getElementById("idTorneoEliminar");
    const nombreTorneo = document.getElementById("nombreTorneoEliminar");

    const botonesEliminar = document.querySelectorAll(
        ".btn-torneo.eliminar"
    );


    // =========================================
    // ABRIR MODAL
    // =========================================

    botonesEliminar.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const id = this.dataset.id;
            const nombre = this.dataset.nombre;

            if (idTorneo) {
                idTorneo.value = id;
            }

            if (nombreTorneo) {
                nombreTorneo.textContent = '"' + nombre + '"';
            }

            if (modal) {
                modal.classList.add("mostrar");
            }

        });

    });


    // =========================================
    // CERRAR MODAL
    // =========================================

    function cerrarModalEliminarTorneo() {

        if (modal) {
            modal.classList.remove("mostrar");
        }

    }


    // BOTÓN X

    if (cerrar) {

        cerrar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModalEliminarTorneo();

        });

    }


    // BOTÓN CANCELAR

    if (cancelar) {

        cancelar.addEventListener("click", function (e) {

            e.preventDefault();

            cerrarModalEliminarTorneo();

        });

    }


    // CERRAR HACIENDO CLICK FUERA

    if (modal) {

        modal.addEventListener("click", function (e) {

            if (e.target === modal) {

                cerrarModalEliminarTorneo();

            }

        });

    }

});
/* =========================================================
   MODAL CREAR TORNEO
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById("modalCrearTorneo");

    const abrir = document.getElementById("btnAbrirCrearTorneo");

    const cerrar = document.getElementById("cerrarCrearTorneo");

    const cancelar = document.getElementById("cancelarCrearTorneo");

    const imagen = document.getElementById("imagenTorneo");

    const preview = document.getElementById("previewTorneo");


    /* =====================================================
       ABRIR MODAL
       ===================================================== */

    if (abrir) {

        abrir.addEventListener("click", function () {

            if (modal) {
                modal.classList.add("mostrar");
            }

        });

    }


    /* =====================================================
       CERRAR MODAL
       ===================================================== */

    function cerrarModalTorneo() {

        if (modal) {
            modal.classList.remove("mostrar");
        }

    }


    if (cerrar) {

        cerrar.addEventListener("click", function (evento) {

            evento.preventDefault();

            cerrarModalTorneo();

        });

    }


    if (cancelar) {

        cancelar.addEventListener("click", function (evento) {

            evento.preventDefault();

            cerrarModalTorneo();

        });

    }


    /* =====================================================
       CERRAR AL HACER CLICK FUERA
       ===================================================== */

    if (modal) {

        modal.addEventListener("click", function (evento) {

            if (evento.target === modal) {

                cerrarModalTorneo();

            }

        });

    }


    /* =====================================================
       PREVISUALIZAR IMAGEN
       ===================================================== */

    if (imagen) {

        imagen.addEventListener("change", function () {

            const archivo = this.files[0];

            if (!archivo) {

                if (preview) {

                    preview.innerHTML = "";

                    preview.classList.remove("mostrar");

                }
                
                document.documentElement.style.overflow = "hidden";
                document.body.style.overflow = "hidden";

                return;

            }


            const tiposPermitidos = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];


            if (!tiposPermitidos.includes(archivo.type)) {

                alert("Solo se permiten imágenes JPG, PNG o WEBP.");

                this.value = "";

                return;

            }


            if (archivo.size > 5 * 1024 * 1024) {

                alert("La imagen no puede superar los 5 MB.");

                this.value = "";

                return;

            }


            const lector = new FileReader();


            lector.onload = function (evento) {

                if (preview) {

                    preview.innerHTML = `
                        <img
                            src="${evento.target.result}"
                            alt="Vista previa del torneo"
                        >
                    `;

                    preview.classList.add("mostrar");

                }

            };


            lector.readAsDataURL(archivo);

        });

    }

    /* =========================================================
   VALIDACIONES CREAR TORNEO
   ========================================================= */

const formularioTorneo = document.getElementById("formCrearTorneo");

const nombreTorneo = document.getElementById("nombreTorneo");
const lugarTorneo = document.getElementById("lugarTorneo");

const fechaInicioTorneo = document.getElementById("fechaInicioTorneo");
const fechaFinTorneo = document.getElementById("fechaFinTorneo");

const categoriaTorneo = document.getElementById("categoriaTorneo");
const tipoTorneo = document.getElementById("tipoTorneo");

const maxParticipantes = document.getElementById("maxParticipantes");
const estadoTorneo = document.getElementById("estadoTorneo");


/* =====================================================
   FECHA DE FINALIZACIÓN
   No puede ser anterior a la fecha de inicio
   ===================================================== */

if (fechaInicioTorneo && fechaFinTorneo) {

    fechaInicioTorneo.addEventListener("change", function () {

        fechaFinTorneo.min = this.value;

        if (
            fechaFinTorneo.value &&
            fechaFinTorneo.value < this.value
        ) {
            fechaFinTorneo.value = "";
        }

    });

}


/* =====================================================
   MÁXIMO DE PARTICIPANTES
   ===================================================== */

if (maxParticipantes) {

    maxParticipantes.addEventListener("input", function () {

        let valor = parseInt(this.value);

        if (valor < 2) {
            this.value = 2;
        }

        if (valor > 1000) {
            this.value = 1000;
        }

    });

}


/* =====================================================
   VALIDACIÓN DEL FORMULARIO
   ===================================================== */

if (formularioTorneo) {

    formularioTorneo.addEventListener("submit", function (evento) {

        /* ---------------------------------------------
           NOMBRE
           --------------------------------------------- */

        if (!nombreTorneo.value.trim()) {

            evento.preventDefault();

            nombreTorneo.focus();

            alert("Ingresá el nombre del torneo.");

            return;

        }


        /* ---------------------------------------------
           LUGAR
           --------------------------------------------- */

        if (!lugarTorneo.value.trim()) {

            evento.preventDefault();

            lugarTorneo.focus();

            alert("Ingresá el lugar donde se realizará el torneo.");

            return;

        }


        /* ---------------------------------------------
           FECHAS
           --------------------------------------------- */

        if (!fechaInicioTorneo.value) {

            evento.preventDefault();

            fechaInicioTorneo.focus();

            alert("Seleccioná la fecha de inicio.");

            return;

        }


        if (!fechaFinTorneo.value) {

            evento.preventDefault();

            fechaFinTorneo.focus();

            alert("Seleccioná la fecha de finalización.");

            return;

        }


        if (
            fechaFinTorneo.value <
            fechaInicioTorneo.value
        ) {

            evento.preventDefault();

            fechaFinTorneo.focus();

            alert(
                "La fecha de finalización no puede ser anterior a la fecha de inicio."
            );

            return;

        }


        /* ---------------------------------------------
           CATEGORÍA
           --------------------------------------------- */

        if (!categoriaTorneo.value) {

            evento.preventDefault();

            categoriaTorneo.focus();

            alert("Seleccioná una categoría.");

            return;

        }


        /* ---------------------------------------------
           TIPO
           --------------------------------------------- */

        if (!tipoTorneo.value) {

            evento.preventDefault();

            tipoTorneo.focus();

            alert("Seleccioná el tipo de torneo.");

            return;

        }


        /* ---------------------------------------------
           PARTICIPANTES
           --------------------------------------------- */

        const cantidad = parseInt(
            maxParticipantes.value
        );

        if (
            isNaN(cantidad) ||
            cantidad < 2 ||
            cantidad > 1000
        ) {

            evento.preventDefault();

            maxParticipantes.focus();

            alert(
                "La cantidad máxima de participantes debe estar entre 2 y 1000."
            );

            return;

        }


        /* ---------------------------------------------
           ESTADO
           --------------------------------------------- */

        const estadosPermitidos = [
            "Abierto",
            "Cerrado",
            "En curso",
            "Finalizado"
        ];

        if (
            !estadosPermitidos.includes(
                estadoTorneo.value
            )
        ) {

            evento.preventDefault();

            estadoTorneo.focus();

            alert("Seleccioná un estado válido para el torneo.");

            return;

        }

    });

}


/* =========================================================
   EDITAR TORNEO
   ========================================================= */

const botonesEditarTorneo =
    document.querySelectorAll(".btn-torneo.editar");

const idTorneoEditar =
    document.getElementById("idTorneoEditar");

const modalTorneoTitulo =
    document.getElementById("modalTorneoTitulo");

const modalTorneoSubtitulo =
    document.getElementById("modalTorneoSubtitulo");

const textoBotonGuardarTorneo =
    document.getElementById("textoBotonGuardarTorneo");

const horaInicioTorneo =
    document.getElementById("horaInicioTorneo");

const horaFinTorneo =
    document.getElementById("horaFinTorneo");

const maxParticipantesTorneo =
    document.getElementById("maxParticipantes");


function activarModoCrearTorneo() {

    if (formularioTorneo) {

        formularioTorneo.setAttribute(
            "action",
            "funciones/crear_torneo.php"
        );

        formularioTorneo.reset();

    }

    if (idTorneoEditar) {
        idTorneoEditar.value = "";
    }

    if (modalTorneoTitulo) {
        modalTorneoTitulo.textContent = "Crear torneo";
    }

    if (modalTorneoSubtitulo) {

        modalTorneoSubtitulo.textContent =
            "Completá los datos del nuevo torneo.";

    }

    if (textoBotonGuardarTorneo) {
        textoBotonGuardarTorneo.textContent = "Crear torneo";
    }

    if (preview) {

        preview.innerHTML = "";

        preview.classList.remove("mostrar");

    }

}


function activarModoEditarTorneo(boton) {

    if (formularioTorneo) {

        formularioTorneo.setAttribute(
            "action",
            "funciones/editar_torneo.php"
        );

    }

    if (idTorneoEditar) {
        idTorneoEditar.value = boton.dataset.id || "";
    }

    if (nombreTorneo) {
        nombreTorneo.value = boton.dataset.nombre || "";
    }

    if (fechaInicioTorneo) {

        fechaInicioTorneo.value =
            boton.dataset.fechaInicio || "";

    }

    if (horaInicioTorneo) {
        horaInicioTorneo.value = boton.dataset.horaInicio || "";
    }

    if (fechaFinTorneo) {

        fechaFinTorneo.min = boton.dataset.fechaInicio || "";

        fechaFinTorneo.value = boton.dataset.fechaFin || "";

    }

    if (horaFinTorneo) {
        horaFinTorneo.value = boton.dataset.horaFin || "";
    }

    if (categoriaTorneo) {
        categoriaTorneo.value = boton.dataset.categoria || "";
    }

    if (tipoTorneo) {
        tipoTorneo.value = boton.dataset.tipo || "";
    }

    if (maxParticipantesTorneo) {

        maxParticipantesTorneo.value =
            boton.dataset.maxParticipantes || "";

    }

    if (estadoTorneo) {
        estadoTorneo.value = boton.dataset.estado || "Abierto";
    }

    if (imagen) {
        imagen.value = "";
    }

    if (preview) {

        if (boton.dataset.imagen) {

            preview.innerHTML = `
                <img
                    src="${boton.dataset.imagen}"
                    alt="Imagen actual del torneo"
                >
            `;

            preview.classList.add("mostrar");

        } else {

            preview.innerHTML = "";

            preview.classList.remove("mostrar");

        }

    }

    if (modalTorneoTitulo) {
        modalTorneoTitulo.textContent = "Editar torneo";
    }

    if (modalTorneoSubtitulo) {

        modalTorneoSubtitulo.textContent =
            "Modificá los datos del torneo.";

    }

    if (textoBotonGuardarTorneo) {

        textoBotonGuardarTorneo.textContent =
            "Guardar cambios";

    }

    if (modal) {
    modal.classList.add("mostrar");

    document.documentElement.style.overflow = "hidden";
    document.body.style.overflow = "hidden";
}

}


if (botonesEditarTorneo.length > 0) {

    botonesEditarTorneo.forEach(function (boton) {

        boton.addEventListener("click", function () {
            activarModoEditarTorneo(boton);
        });

    });

}


if (abrir) {

    abrir.addEventListener("click", function () {
        activarModoCrearTorneo();
    });

}
});

/* =========================================================
   AVISO RESULTADO TORNEO (crear / editar)
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const parametros = new URLSearchParams(window.location.search);

    const ok = parametros.get("ok");
    const error = parametros.get("error");

    const mensajesOk = {
        torneo_creado: "El torneo se creó correctamente.",
        torneo_actualizado: "Los cambios del torneo se guardaron correctamente.",
        torneo_eliminado: "El torneo se eliminó correctamente."
    };

    const mensajesError = {
        nombre: "Ingresá el nombre del torneo.",
        nombre_largo: "El nombre del torneo es demasiado largo.",
        fechas: "Completá las fechas del torneo.",
        fecha_invalida: "La fecha de finalización no puede ser anterior a la de inicio.",
        horas: "Completá los horarios del torneo.",
        hora_invalida: "La hora de finalización no puede ser anterior a la de inicio.",
        categoria: "Seleccioná una categoría válida.",
        categoria_no_encontrada: "La categoría seleccionada no existe.",
        tipo: "Seleccioná un tipo de torneo válido.",
        participantes: "La cantidad máxima de participantes no es válida.",
        estado: "Seleccioná un estado válido.",
        club_no_encontrado: "No se encontró el club asociado a tu cuenta.",
        torneo_invalido: "No se especificó un torneo válido.",
        torneo_no_encontrado: "El torneo no existe o no te pertenece.",
        sin_permiso: "El torneo no existe o no te pertenece.",
        imagen: "Ocurrió un error al subir la imagen.",
        imagen_peso: "La imagen no puede superar los 5 MB.",
        imagen_tipo: "El formato de la imagen no es válido.",
        no_se_pudo_crear: "No se pudo crear el torneo. Intentá nuevamente.",
        no_se_pudo_actualizar: "No se pudieron guardar los cambios. Intentá nuevamente.",
        no_se_pudo_eliminar: "No se pudo eliminar el torneo. Intentá nuevamente.",
        servidor: "Ocurrió un error en el servidor. Intentá nuevamente."
    };

   /* =========================================================
   MENSAJES DEL SISTEMA
   ========================================================= */

const modalMensajeExito =
    document.getElementById("modalMensajeExito");

const tituloMensajeExito =
    document.getElementById("tituloMensajeExito");

const textoMensajeExito =
    document.getElementById("textoMensajeExito");

const cerrarMensajeExito =
    document.getElementById("cerrarMensajeExito");

const aceptarMensajeExito =
    document.getElementById("aceptarMensajeExito");


function cerrarModalMensaje() {

    if (!modalMensajeExito) {
        return;
    }

    modalMensajeExito.classList.remove("mostrar");

    document.documentElement.style.overflow = "";
    document.body.style.overflow = "";
}


/* =========================================================
   MOSTRAR MENSAJE
   ========================================================= */

if (
    modalMensajeExito &&
    ok &&
    mensajesOk[ok]
) {

    /* ---------------------------------------------
       MENSAJE SEGÚN LA ACCIÓN
       --------------------------------------------- */

    if (ok === "torneo_actualizado") {

        tituloMensajeExito.textContent =
            "¡Cambios guardados!";

        textoMensajeExito.textContent =
            "Los cambios del torneo se guardaron correctamente.";

    } else if (ok === "torneo_creado") {

        tituloMensajeExito.textContent =
            "¡Torneo creado!";

        textoMensajeExito.textContent =
            "El torneo se creó correctamente.";

    } else if (ok === "torneo_eliminado") {

        tituloMensajeExito.textContent =
            "¡Torneo eliminado!";

        textoMensajeExito.textContent =
            "El torneo se eliminó correctamente.";

    } else {

        tituloMensajeExito.textContent =
            "¡Operación realizada!";

        textoMensajeExito.textContent =
            mensajesOk[ok];

    }


    /* ---------------------------------------------
       MOSTRAR MODAL
       --------------------------------------------- */

    modalMensajeExito.classList.add("mostrar");

    document.documentElement.style.overflow = "hidden";
    document.body.style.overflow = "hidden";


    /* ---------------------------------------------
       ELIMINAR EL PARÁMETRO DE LA URL
       --------------------------------------------- */

    const url =
        new URL(window.location.href);

    url.searchParams.delete("ok");

    window.history.replaceState(
        {},
        document.title,
        url.pathname + url.search
    );

}


/* =========================================================
   CERRAR CON X
   ========================================================= */

if (cerrarMensajeExito) {

    cerrarMensajeExito.addEventListener(
        "click",
        function () {

            cerrarModalMensaje();

        }
    );

}


/* =========================================================
   CERRAR CON ACEPTAR
   ========================================================= */

if (aceptarMensajeExito) {

    aceptarMensajeExito.addEventListener(
        "click",
        function () {

            cerrarModalMensaje();

        }
    );

}


/* =========================================================
   CERRAR HACIENDO CLICK AFUERA
   ========================================================= */

if (modalMensajeExito) {

    modalMensajeExito.addEventListener(
        "click",
        function (evento) {

            if (
                evento.target ===
                modalMensajeExito
            ) {

                cerrarModalMensaje();

            }

        }
    );

}


/* =========================================================
   CERRAR CON ESC
   ========================================================= */

document.addEventListener(
    "keydown",
    function (evento) {

        if (
            evento.key === "Escape" &&
            modalMensajeExito &&
            modalMensajeExito.classList.contains("mostrar")
        ) {

            cerrarModalMensaje();

        }

    }
);

});

/* =========================================================
   MENSAJE CANCHA REPETIDA
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const mensajeCanchaError =
        document.getElementById("mensajeCanchaError");

    const cerrarMensajeCanchaError =
        document.getElementById("cerrarMensajeCanchaError");

    if (cerrarMensajeCanchaError && mensajeCanchaError) {

        cerrarMensajeCanchaError.addEventListener(
            "click",
            function () {

                mensajeCanchaError.remove();

                const url =
                    new URL(window.location.href);

                url.searchParams.delete("error");

                window.history.replaceState(
                    {},
                    document.title,
                    url.pathname + url.search
                );

            }
        );

    }

});
/* =========================================================
   MODAL ERROR - CANCHA REPETIDA
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const modalErrorCancha =
        document.getElementById("modalErrorCancha");

    const cerrarModalErrorCancha =
        document.getElementById("cerrarModalErrorCancha");

    const aceptarModalErrorCancha =
        document.getElementById("aceptarModalErrorCancha");


    if (!modalErrorCancha) {
        return;
    }


    /* ==========================================
       CERRAR CON LA X
       ========================================== */

    if (cerrarModalErrorCancha) {

        cerrarModalErrorCancha.addEventListener(
            "click",
            function () {

                cerrarModalCancha();

            }
        );

    }


    /* ==========================================
       CERRAR CON ACEPTAR
       ========================================== */

    if (aceptarModalErrorCancha) {

        aceptarModalErrorCancha.addEventListener(
            "click",
            function () {

                cerrarModalCancha();

            }
        );

    }


    /* ==========================================
       FUNCIÓN PARA CERRAR
       ========================================== */

    function cerrarModalCancha() {

        modalErrorCancha.remove();

        const url =
            new URL(window.location.href);

        url.searchParams.delete("error");

        window.history.replaceState(
            {},
            document.title,
            url.pathname + url.search
        );

    }

});

/* =========================================================
   TORNEOS DEL PARTICIPANTE
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* ---------------------------------------------
       MENÚ MÓVIL
       --------------------------------------------- */

    const btnMenuMovil = document.getElementById("btnMenuMovilParticipante");
    const sidebarParticipante = document.querySelector(".panel .sidebar");

    if (btnMenuMovil && sidebarParticipante) {

        btnMenuMovil.addEventListener("click", function () {
            sidebarParticipante.classList.toggle("mostrar-movil");
        });

    }


    /* ---------------------------------------------
       BUSCADOR Y FILTRO DE ESTADO
       --------------------------------------------- */

    const inputBuscar = document.getElementById("buscarTorneoParticipante");
    const selectFiltro = document.getElementById("filtroTorneoParticipante");
    const tarjetasTorneo = document.querySelectorAll(".torneo-card-participante");
    const contenedorTorneos = document.querySelector(".torneos-participante");

    function aplicarFiltrosTorneos() {

        const texto = inputBuscar
            ? inputBuscar.value.trim().toLowerCase()
            : "";

        const estado = selectFiltro ? selectFiltro.value : "todos";

        let visibles = 0;

        tarjetasTorneo.forEach(function (tarjeta) {

            const coincideTexto =
                texto === "" ||
                tarjeta.dataset.nombre.includes(texto);

            const coincideEstado =
                estado === "todos" ||
                tarjeta.dataset.estado === estado;

            const mostrar = coincideTexto && coincideEstado;

            tarjeta.style.display = mostrar ? "" : "none";

            if (mostrar) {
                visibles++;
            }

        });

        let vacio = document.getElementById("vacioFiltroTorneos");

        if (visibles === 0 && contenedorTorneos) {

            if (!vacio) {

                vacio = document.createElement("div");
                vacio.id = "vacioFiltroTorneos";
                vacio.className = "torneos-participante-vacio";

                vacio.innerHTML =
                    "<h3>No se encontraron torneos</h3>" +
                    "<p>Probá con otra búsqueda o cambiá el filtro de estado.</p>";

                contenedorTorneos.appendChild(vacio);

            }

        } else if (vacio) {

            vacio.remove();

        }

    }

    if (inputBuscar) {
        inputBuscar.addEventListener("input", aplicarFiltrosTorneos);
    }

    if (selectFiltro) {
        selectFiltro.addEventListener("change", aplicarFiltrosTorneos);
    }


    /* ---------------------------------------------
       INSCRIBIRSE
       Paso 1 (ambas modalidades): modal de confirmación.
       Individual -> se inscribe directo.
       Parejas/Mixto -> abre el paso 2 (invitar compañero).
       --------------------------------------------- */

    const botonesInscribirse = document.querySelectorAll(
        ".btn-inscribirse-participante"
    );

    const modalMensajeExitoInsc =
        document.getElementById("modalMensajeExito");

    const tituloMensajeExitoInsc =
        document.getElementById("tituloMensajeExito");

    const textoMensajeExitoInsc =
        document.getElementById("textoMensajeExito");

    const modalConfirmarInscripcion =
        document.getElementById("modalConfirmarInscripcion");

    const textoConfirmarInscripcion =
        document.getElementById("textoConfirmarInscripcion");

    const idTorneoInscribir =
        document.getElementById("idTorneoInscribir");

    const cerrarConfirmarInscripcion =
        document.getElementById("cerrarConfirmarInscripcion");

    const noInscribirme =
        document.getElementById("noInscribirme");

    const formInscribirTorneo =
        document.getElementById("formInscribirTorneo");

    const modalInvitarCompanero =
        document.getElementById("modalInvitarCompanero");

    const textoInvitarCompanero =
        document.getElementById("textoInvitarCompanero");

    const idTorneoInvitar =
        document.getElementById("idTorneoInvitar");

    const emailCompaneroInput =
        document.getElementById("emailCompanero");

    const emailCompaneroOculto =
        document.getElementById("emailCompaneroOculto");

    const buscarCompaneroBtn =
        document.getElementById("buscarCompaneroBtn");

    const resultadoBusquedaCompanero =
        document.getElementById("resultadoBusquedaCompanero");

    const enviarInvitacionBtn =
        document.getElementById("enviarInvitacionBtn");

    const cerrarInvitarCompanero =
        document.getElementById("cerrarInvitarCompanero");

    const cancelarInvitarCompanero =
        document.getElementById("cancelarInvitarCompanero");

    const csrfTokenInput =
        document.querySelector('#formInvitarCompanero input[name="csrf_token"]');

    let tipoTorneoActual = "";

    function cerrarModalConfirmarInscripcion() {

        if (!modalConfirmarInscripcion) {
            return;
        }

        modalConfirmarInscripcion.classList.remove("mostrar");

        document.documentElement.style.overflow = "";
        document.body.style.overflow = "";

    }

    function cerrarModalInvitarCompanero() {

        if (!modalInvitarCompanero) {
            return;
        }

        modalInvitarCompanero.classList.remove("mostrar");

        document.documentElement.style.overflow = "";
        document.body.style.overflow = "";

    }

    if (cerrarConfirmarInscripcion) {
        cerrarConfirmarInscripcion.addEventListener(
            "click",
            cerrarModalConfirmarInscripcion
        );
    }

    if (noInscribirme) {
        noInscribirme.addEventListener(
            "click",
            cerrarModalConfirmarInscripcion
        );
    }

    botonesInscribirse.forEach(function (boton) {

        boton.addEventListener("click", function () {

            const nombre = boton.dataset.nombre || "este torneo";
            const tipo = boton.dataset.tipo || "";

            if (!modalConfirmarInscripcion || !textoConfirmarInscripcion || !idTorneoInscribir) {
                return;
            }

            tipoTorneoActual = tipo;

            textoConfirmarInscripcion.textContent =
                "¿Seguro que querés inscribirte a este torneo: \"" +
                nombre +
                "\"?";

            idTorneoInscribir.value = boton.dataset.id || "";
            idTorneoInscribir.dataset.nombre = nombre;

            modalConfirmarInscripcion.classList.add("mostrar");

            document.documentElement.style.overflow = "hidden";
            document.body.style.overflow = "hidden";

        });

    });

    // El "Inscribirme" del paso 1 solo se envía directo si es Individual.
    // En Parejas/Mixto abre el paso 2 en vez de mandar el formulario.
    if (formInscribirTorneo) {

        formInscribirTorneo.addEventListener("submit", function (evento) {

            if (tipoTorneoActual === "Individual") {
                return; // envío normal, ya tiene data-loader
            }

            evento.preventDefault();

            cerrarModalConfirmarInscripcion();

            if (!modalInvitarCompanero || !idTorneoInvitar || !textoInvitarCompanero) {
                return;
            }

            const nombre = idTorneoInscribir ? (idTorneoInscribir.dataset.nombre || "este torneo") : "este torneo";

            idTorneoInvitar.value = idTorneoInscribir.value;

            textoInvitarCompanero.textContent =
                "Ingresá el correo de tu compañero para el torneo \"" + nombre + "\".";

            if (emailCompaneroInput) emailCompaneroInput.value = "";
            if (emailCompaneroOculto) emailCompaneroOculto.value = "";
            if (resultadoBusquedaCompanero) resultadoBusquedaCompanero.textContent = "";
            if (resultadoBusquedaCompanero) resultadoBusquedaCompanero.className = "resultado-busqueda-companero";
            if (enviarInvitacionBtn) enviarInvitacionBtn.disabled = true;

            modalInvitarCompanero.classList.add("mostrar");

            document.documentElement.style.overflow = "hidden";
            document.body.style.overflow = "hidden";

        });

    }

    if (cerrarInvitarCompanero) {
        cerrarInvitarCompanero.addEventListener("click", cerrarModalInvitarCompanero);
    }

    if (cancelarInvitarCompanero) {
        cancelarInvitarCompanero.addEventListener("click", cerrarModalInvitarCompanero);
    }

    /* ---------------------------------------------
       PASO 2: buscar/verificar el correo del compañero
       antes de poder enviar la invitación.
       --------------------------------------------- */

    if (buscarCompaneroBtn) {

        buscarCompaneroBtn.addEventListener("click", function () {

            const email = emailCompaneroInput ? emailCompaneroInput.value.trim() : "";

            if (!resultadoBusquedaCompanero) {
                return;
            }

            if (enviarInvitacionBtn) enviarInvitacionBtn.disabled = true;
            if (emailCompaneroOculto) emailCompaneroOculto.value = "";

            if (!email) {
                resultadoBusquedaCompanero.textContent = "Ingresá un correo primero.";
                resultadoBusquedaCompanero.className = "resultado-busqueda-companero resultado-error";
                return;
            }

            resultadoBusquedaCompanero.textContent = "Buscando...";
            resultadoBusquedaCompanero.className = "resultado-busqueda-companero";

            const datos = new URLSearchParams();
            datos.set("email", email);
            datos.set("id_torneo", idTorneoInvitar ? idTorneoInvitar.value : "");
            datos.set("csrf_token", csrfTokenInput ? csrfTokenInput.value : "");

            fetch("funciones/buscar_companero.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: datos.toString()
            })
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (data) {

                    resultadoBusquedaCompanero.textContent = data.mensaje || "No se pudo verificar el correo.";

                    if (data.ok && data.puede_invitar) {

                        resultadoBusquedaCompanero.className = "resultado-busqueda-companero resultado-ok";

                        if (enviarInvitacionBtn) enviarInvitacionBtn.disabled = false;
                        if (emailCompaneroOculto) emailCompaneroOculto.value = email;

                    } else {

                        resultadoBusquedaCompanero.className = "resultado-busqueda-companero resultado-error";

                    }

                })
                .catch(function () {

                    resultadoBusquedaCompanero.textContent = "No se pudo conectar con el servidor.";
                    resultadoBusquedaCompanero.className = "resultado-busqueda-companero resultado-error";

                });

        });

    }

    // Si cambia el email a mano después de buscar, hay que buscar de nuevo.
    if (emailCompaneroInput) {

        emailCompaneroInput.addEventListener("input", function () {

            if (enviarInvitacionBtn) enviarInvitacionBtn.disabled = true;
            if (emailCompaneroOculto) emailCompaneroOculto.value = "";

        });

    }

    if (
        window.location.search.includes("inscripcion=exito") &&
        modalMensajeExitoInsc && tituloMensajeExitoInsc && textoMensajeExitoInsc
    ) {

        tituloMensajeExitoInsc.textContent = "¡Listo!";

        textoMensajeExitoInsc.textContent =
            "Te inscribiste correctamente al torneo.";

        modalMensajeExitoInsc.classList.add("mostrar");

        document.documentElement.style.overflow = "hidden";
        document.body.style.overflow = "hidden";

    }

    if (
        window.location.search.includes("invitacion=exito") &&
        modalMensajeExitoInsc && tituloMensajeExitoInsc && textoMensajeExitoInsc
    ) {

        tituloMensajeExitoInsc.textContent = "¡Invitación enviada!";

        textoMensajeExitoInsc.textContent =
            "Le avisamos a tu compañero. En cuanto responda vas a ver la novedad en Notificaciones.";

        modalMensajeExitoInsc.classList.add("mostrar");

        document.documentElement.style.overflow = "hidden";
        document.body.style.overflow = "hidden";

    }

});


// ==========================================
// PARTICIPANTE - REGISTRO
// ==========================================

document.addEventListener("DOMContentLoaded", function () {

    const camposNombre = document.querySelectorAll(
        'input[name="nombre"], input[name="apellido"]'
    );

    camposNombre.forEach(function (campo) {

        campo.addEventListener("input", function () {

            this.value = this.value.replace(
                /[^A-Za-zÁÉÍÓÚáéíóúÑñ ]/g,
                ""
            );

        });

    });


    const telefonoParticipante =
        document.querySelector(
            'input[name="telefono"][maxlength="10"]'
        );

    if (telefonoParticipante) {

        telefonoParticipante.addEventListener(
            "input",
            function () {

                this.value = this.value.replace(
                    /[^0-9]/g,
                    ""
                );

            }
        );

    }

});


// ==========================================
// SOLICITUDES - PANEL
// ==========================================

document.addEventListener("DOMContentLoaded", function () {

    const tarjetas =
        document.querySelectorAll(".solicitud-card");

    if (!tarjetas.length) {
        return;
    }

    tarjetas.forEach(function (tarjeta) {

        const cabecera =
            tarjeta.querySelector(".solicitud-card-toggle");

        if (!cabecera) {
            return;
        }

        function alternar() {

            const estabaAbierta =
                tarjeta.classList.contains("abierto");

            tarjetas.forEach(function (otraTarjeta) {

                otraTarjeta.classList.remove("abierto");

                const otraCabecera =
                    otraTarjeta.querySelector(
                        ".solicitud-card-toggle"
                    );

                if (otraCabecera) {

                    otraCabecera.setAttribute(
                        "aria-expanded",
                        "false"
                    );

                }

            });

            if (!estabaAbierta) {

                tarjeta.classList.add("abierto");

                cabecera.setAttribute(
                    "aria-expanded",
                    "true"
                );

            }

        }

        cabecera.addEventListener(
            "click",
            alternar
        );

        cabecera.addEventListener(
            "keydown",
            function (evento) {

                if (
                    evento.key === "Enter" ||
                    evento.key === " "
                ) {

                    evento.preventDefault();

                    alternar();

                }

            }
        );

    });

});


// ==========================================
// LANDING - CARRUSEL
// ==========================================

document.addEventListener("DOMContentLoaded", function () {

    const slides =
        document.querySelectorAll(
            ".landing-hero-slide"
        );

    const puntos =
        document.querySelectorAll(
            ".landing-hero-dots button"
        );

    if (!slides.length) {
        return;
    }

    let actual = 0;
    let intervalo = null;

    function mostrarSlide(indice) {

        slides.forEach(function (slide, i) {

            slide.classList.toggle(
                "activo",
                i === indice
            );

        });

        puntos.forEach(function (punto, i) {

            punto.classList.toggle(
                "activo",
                i === indice
            );

        });

        actual = indice;

    }

    function siguienteSlide() {

        mostrarSlide(
            (actual + 1) % slides.length
        );

    }

    function iniciarAutoplay() {

        clearInterval(intervalo);

        intervalo = setInterval(
            siguienteSlide,
            5000
        );

    }

    puntos.forEach(function (punto, i) {

        punto.addEventListener(
            "click",
            function () {

                mostrarSlide(i);

                iniciarAutoplay();

            }
        );

    });

    if (slides.length > 1) {
        iniciarAutoplay();
    }

});


// ==========================================
// VER TORNEO - FASE DE GRUPOS
// ==========================================

document.addEventListener("DOMContentLoaded", function () {

    const tabla =
        document.querySelector(
            ".vt-tabla-grupo[data-grupos]"
        );

    if (!tabla) {
        return;
    }

    const datosGrupos =
        tabla.getAttribute("data-grupos");

    if (!datosGrupos) {
        return;
    }

    let grupos;

    try {

        grupos = JSON.parse(datosGrupos);

    } catch (error) {

        console.error(
            "No se pudieron cargar los grupos del torneo.",
            error
        );

        return;
    }

    if (!Array.isArray(grupos) || !grupos.length) {
        return;
    }

    const letra =
        document.getElementById("vtGrupoLetra");

    const nombre =
        document.getElementById("vtGrupoNombre");

    const cantidad =
        document.getElementById("vtGrupoCantidad");

    const cuerpo =
        document.getElementById("vtGrupoTabla");

    const anterior =
        document.getElementById("vtGrupoAnterior");

    const siguiente =
        document.getElementById("vtGrupoSiguiente");

    if (
        !letra ||
        !nombre ||
        !cantidad ||
        !cuerpo ||
        !anterior ||
        !siguiente
    ) {
        return;
    }

    let grupoActual = 0;

    /*
    --------------------------------------------------
    TIPO DE TORNEO
    --------------------------------------------------

    Lo obtenemos desde el HTML generado por PHP.
    */

    const tipoTorneo =
        document.body.dataset.tipoTorneo || "Parejas";


    function pintarGrupo(indice) {

        const grupo = grupos[indice];

        if (!grupo) {
            return;
        }

        letra.textContent =
            grupo.letra;

        nombre.textContent =
            grupo.nombre;

        cuerpo.innerHTML = "";


        /*
        --------------------------------------------------
        INDIVIDUAL
        --------------------------------------------------
        */

        if (tipoTorneo === "Individual") {

            const participantes =
                grupo.participantes || [];

            cantidad.textContent =
                participantes.length +
                " participantes";


            participantes.forEach(
                function (participante, i) {

                    const nombreParticipante =
                        participante.nombre || "";

                    const inicial =
                        nombreParticipante
                            .charAt(0)
                            .toUpperCase();


                    const fila =
                        document.createElement("tr");


                    fila.innerHTML = `

                        <td>${i + 1}</td>

                        <td>

                            <div class="vt-pareja-fila">

                                <div class="vt-avatar">
                                    ${inicial}
                                </div>

                                <div class="vt-pareja-nombres">

                                    <strong>
                                        ${nombreParticipante}
                                    </strong>

                                </div>

                            </div>

                        </td>

                        <td>${participante.pj || 0}</td>

                        <td>${participante.pg || 0}</td>

                        <td>
                            <strong>
                                ${participante.pts || 0}
                            </strong>
                        </td>

                    `;

                    cuerpo.appendChild(fila);

                }
            );

        }


        /*
        --------------------------------------------------
        PAREJAS / MIXTO
        --------------------------------------------------
        */

        else {

            const parejas =
                grupo.parejas || [];

            cantidad.textContent =
                parejas.length +
                (
                    tipoTorneo === "Mixto"
                        ? " parejas"
                        : " parejas"
                );


            parejas.forEach(
                function (pareja, i) {

                    const nombres =
                        pareja.nombres || [];


                    const nombre1 =
                        nombres[0] || "";

                    const nombre2 =
                        nombres[1] || "";


                    const iniciales = (

                        nombre1.charAt(0) +
                        nombre2.charAt(0)

                    ).toUpperCase();


                    const fila =
                        document.createElement("tr");


                    fila.innerHTML = `

                        <td>${i + 1}</td>

                        <td>

                            <div class="vt-pareja-fila">

                                <div class="vt-avatar">
                                    ${iniciales}
                                </div>

                                <div class="vt-pareja-nombres">

                                    <strong>
                                        ${nombre1}
                                    </strong>

                                    <span>
                                        ${nombre2}
                                    </span>

                                </div>

                            </div>

                        </td>

                        <td>${pareja.pj || 0}</td>

                        <td>${pareja.pg || 0}</td>

                        <td>
                            <strong>
                                ${pareja.pts || 0}
                            </strong>
                        </td>

                    `;

                    cuerpo.appendChild(fila);

                }
            );

        }


        anterior.disabled =
            indice === 0;

        siguiente.disabled =
            indice === grupos.length - 1;

        grupoActual = indice;

    }


    anterior.addEventListener(
        "click",
        function () {

            if (grupoActual > 0) {

                pintarGrupo(
                    grupoActual - 1
                );

            }

        }
    );


    siguiente.addEventListener(
        "click",
        function () {

            if (
                grupoActual <
                grupos.length - 1
            ) {

                pintarGrupo(
                    grupoActual + 1
                );

            }

        }
    );


    pintarGrupo(0);

});
