document.addEventListener("DOMContentLoaded", function () {

    const cantidadCanchas =
        document.getElementById("cantidad_canchas");

    const contenedorCanchas =
        document.getElementById("contenedor-canchas");

    const fechaNacimiento =
        document.getElementById("fecha_nacimiento");

    const telefono =
        document.getElementById("telefono");

    const telefonoClub =
        document.getElementById("telefono_club");

    const password =
        document.getElementById("password");

    const confirmarPassword =
        document.getElementById("confirmar_password");

    const formulario =
        document.querySelector("form");


    if (!cantidadCanchas || !contenedorCanchas) {
        return;
    }


    let canchaActual = 0;
    let totalCanchas = 0;


    // ==========================================
    // FECHA DE NACIMIENTO - MAYOR DE 18
    // ==========================================

    if (fechaNacimiento) {

        const hoy = new Date();

        const fechaMaxima = new Date(
            hoy.getFullYear() - 18,
            hoy.getMonth(),
            hoy.getDate()
        );

        const fechaMaximaTexto =
            fechaMaxima.toISOString().split("T")[0];

        fechaNacimiento.max = fechaMaximaTexto;


        fechaNacimiento.addEventListener(
            "change",
            function () {

                if (!this.value) {
                    return;
                }

                const fechaSeleccionada =
                    new Date(this.value + "T00:00:00");

                if (fechaSeleccionada > fechaMaxima) {

                    this.value = "";

                    alert(
                        "Debes tener 18 años o más para registrarte como organizador."
                    );

                }

            }
        );

    }


    // ==========================================
    // SOLO NUMEROS EN TELEFONOS
    // ==========================================

    function permitirSoloNumeros(input) {

        if (!input) {
            return;
        }

        input.addEventListener(
            "input",
            function () {

                this.value =
                    this.value.replace(/\D/g, "");

                if (this.value.length > 10) {

                    this.value =
                        this.value.substring(0, 10);

                }

            }
        );

    }


    permitirSoloNumeros(telefono);
    permitirSoloNumeros(telefonoClub);


    // ==========================================
    // VALIDAR CONTRASEÑA
    // ==========================================

    function validarPassword(valor) {

        const tieneMayuscula =
            /[A-Z]/.test(valor);

        const tieneNumero =
            /[0-9]/.test(valor);

        const tieneEspecial =
            /[^A-Za-z0-9]/.test(valor);

        return (
            valor.length === 8 &&
            tieneMayuscula &&
            tieneNumero &&
            tieneEspecial
        );

    }


    if (password) {

        password.addEventListener(
            "input",
            function () {

                if (this.value.length > 8) {

                    this.value =
                        this.value.substring(0, 8);

                }

            }
        );

    }


    // ==========================================
    // GENERAR CANCHAS
    // ==========================================

    function generarCanchas() {

        let cantidad =
            parseInt(cantidadCanchas.value);

        contenedorCanchas.innerHTML = "";

        canchaActual = 0;


        if (isNaN(cantidad) || cantidad < 1) {
            return;
        }


        if (cantidad > 20) {

            cantidad = 20;

            cantidadCanchas.value = 20;

        }


        totalCanchas = cantidad;


        // ======================================
        // CARRUSEL
        // ======================================

        const carrusel =
            document.createElement("div");

        carrusel.className =
            "carrusel-canchas";


        // ======================================
        // TARJETAS
        // ======================================

        const tarjetas =
            document.createElement("div");

        tarjetas.className =
            "tarjetas-canchas";


        // ======================================
        // CREAR CANCHAS
        // ======================================

        for (
            let i = 1;
            i <= cantidad;
            i++
        ) {

            const tarjeta =
                document.createElement("div");

            tarjeta.className =
                "cancha-registro";


            if (i !== 1) {

                tarjeta.style.display =
                    "none";

            }


            tarjeta.innerHTML = `

                <div class="input-group">

    <label>
        Nombre de la cancha
    </label>

    <input
        type="text"
        name="nombre_cancha[]"
        value="Cancha ${i}"
        placeholder="Ej: Cancha 1"
        maxlength="50"
        required
    >

</div>


<div class="input-group">

    <label>
        Imagen de la cancha
    </label>

    <input
        type="file"
        name="imagen_cancha[]"
        accept="image/jpeg,image/png,image/webp"
        required
    >

    <small class="mensaje-ayuda">
        Suba una imagen de la cancha.
        Formatos permitidos: JPG, PNG o WEBP.
    </small>

</div>

            `;


            tarjetas.appendChild(tarjeta);

        }


        carrusel.appendChild(tarjetas);


        // ======================================
        // CONTROLES
        // ======================================

        const controles =
            document.createElement("div");

        controles.className =
            "controles-carrusel";


        const botonAnterior =
            document.createElement("button");

        botonAnterior.type =
            "button";

        botonAnterior.className =
            "btn-carrusel btn-anterior";

        botonAnterior.innerHTML =
            "← Anterior";


        const indicador =
            document.createElement("span");

        indicador.className =
            "indicador-carrusel";

        indicador.textContent =
            `Cancha 1 de ${cantidad}`;


        const botonSiguiente =
            document.createElement("button");

        botonSiguiente.type =
            "button";

        botonSiguiente.className =
            "btn-carrusel btn-siguiente";

        botonSiguiente.innerHTML =
            "Siguiente →";


        controles.appendChild(
            botonAnterior
        );

        controles.appendChild(
            indicador
        );

        controles.appendChild(
            botonSiguiente
        );


        carrusel.appendChild(controles);

        contenedorCanchas.appendChild(carrusel);


        // ======================================
        // ACTUALIZAR CARRUSEL
        // ======================================

        function actualizarCarrusel() {

            const tarjetas =
                contenedorCanchas.querySelectorAll(
                    ".cancha-registro"
                );


            tarjetas.forEach(
                function (tarjeta, index) {

                    if (
                        index === canchaActual
                    ) {

                        tarjeta.style.display =
                            "block";

                    } else {

                        tarjeta.style.display =
                            "none";

                    }

                }
            );


            indicador.textContent =
                `Cancha ${canchaActual + 1} de ${totalCanchas}`;


            // ANTERIOR

            botonAnterior.disabled =
                canchaActual === 0;


            // SIGUIENTE

            if (
                canchaActual ===
                totalCanchas - 1
            ) {

                botonSiguiente.textContent =
                    "Última cancha";

                botonSiguiente.disabled =
                    true;

            } else {

                botonSiguiente.textContent =
                    "Siguiente →";

                botonSiguiente.disabled =
                    false;

            }

        }


        // ======================================
        // ANTERIOR
        // ======================================

        botonAnterior.addEventListener(
            "click",
            function () {

                if (canchaActual > 0) {

                    canchaActual--;

                    actualizarCarrusel();

                }

            }
        );


        // ======================================
        // SIGUIENTE
        // ======================================

        botonSiguiente.addEventListener(
            "click",
            function () {

                if (
                    canchaActual <
                    totalCanchas - 1
                ) {

                    canchaActual++;

                    actualizarCarrusel();

                }

            }
        );


        actualizarCarrusel();

    }


    // ==========================================
    // CAMBIO DE CANTIDAD
    // ==========================================

    cantidadCanchas.addEventListener(
        "input",
        generarCanchas
    );


    cantidadCanchas.addEventListener(
        "change",
        generarCanchas
    );


    // ==========================================
    // VALIDACIÓN FINAL DEL FORMULARIO
    // ==========================================

    if (formulario) {

        formulario.addEventListener(
            "submit",
            function (event) {


                // -------------------------------
                // TELEFONO
                // -------------------------------

                if (
                    telefono &&
                    !/^[0-9]{10}$/.test(
                        telefono.value
                    )
                ) {

                    event.preventDefault();

                    alert(
                        "El teléfono debe tener exactamente 10 números y no debe incluir +54."
                    );

                    telefono.focus();

                    return;

                }


                // -------------------------------
                // TELEFONO CLUB
                // -------------------------------

                if (
                    telefonoClub &&
                    !/^[0-9]{10}$/.test(
                        telefonoClub.value
                    )
                ) {

                    event.preventDefault();

                    alert(
                        "El teléfono del club debe tener exactamente 10 números y no debe incluir +54."
                    );

                    telefonoClub.focus();

                    return;

                }


                // -------------------------------
                // CONTRASEÑA
                // -------------------------------

                if (
                    password &&
                    !validarPassword(
                        password.value
                    )
                ) {

                    event.preventDefault();

                    alert(
                        "La contraseña debe tener exactamente 8 caracteres, una mayúscula, un número y un carácter especial."
                    );

                    password.focus();

                    return;

                }


                // -------------------------------
                // CONFIRMAR CONTRASEÑA
                // -------------------------------

                if (
                    confirmarPassword &&
                    password.value !==
                    confirmarPassword.value
                ) {

                    event.preventDefault();

                    alert(
                        "Las contraseñas no coinciden."
                    );

                    confirmarPassword.focus();

                    return;

                }


                // -------------------------------
                // EDAD
                // -------------------------------

                if (fechaNacimiento) {

                    const fecha =
                        new Date(
                            fechaNacimiento.value +
                            "T00:00:00"
                        );

                    const hoy =
                        new Date();

                    let edad =
                        hoy.getFullYear() -
                        fecha.getFullYear();

                    const mes =
                        hoy.getMonth() -
                        fecha.getMonth();

                    if (
                        mes < 0 ||
                        (
                            mes === 0 &&
                            hoy.getDate() <
                            fecha.getDate()
                        )
                    ) {

                        edad--;

                    }


                    if (edad < 18) {

                        event.preventDefault();

                        alert(
                            "Debes tener 18 años o más para registrarte como organizador."
                        );

                        fechaNacimiento.focus();

                        return;

                    }

                }

            }
        );

    }

});
tarjeta.innerHTML = `

    <div class="cancha-titulo">

        <span class="numero-cancha">
            Cancha ${i}
        </span>

        <span class="contador-cancha">
            ${i} / ${cantidad}
        </span>

    </div>


    <div class="input-group">

        <label>
            Nombre de la cancha
        </label>

        <input
            type="text"
            name="nombre_cancha[]"
            value="Cancha ${i}"
            placeholder="Ej: Cancha 1"
            maxlength="50"
            required
        >

    </div>


    <div class="input-group">

        <label>
            Imagen de la cancha
        </label>

        <input
            type="file"
            name="imagen_cancha[]"
            accept="image/jpeg,image/png,image/webp"
            required
        >

        <small class="mensaje-ayuda">
            Suba una imagen de la cancha.
            Formatos permitidos: JPG, PNG o WEBP.
        </small>

    </div>

`;