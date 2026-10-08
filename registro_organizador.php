<?php

include("config/no_cache.php");

session_start();

/*
|--------------------------------------------------------------------------
| RECUPERAR DATOS DESPUÉS DE UN ERROR
|--------------------------------------------------------------------------
*/

$error = $_SESSION['error_registro'] ?? '';
$datos = $_SESSION['datos_registro'] ?? [];

/*
|--------------------------------------------------------------------------
| ELIMINAR LOS DATOS DE LA SESIÓN DESPUÉS DE RECUPERARLOS
|--------------------------------------------------------------------------
*/

unset($_SESSION['error_registro']);
unset($_SESSION['datos_registro']);

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Registro Organizador</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/organizador.css"
    >

</head>


<body class="organizador-body">


<div class="organizador-wrapper">


    <!-- ========================================= -->
    <!-- MENSAJE DE ERROR -->
    <!-- ========================================= -->

    <?php if (!empty($error)): ?>

        <div class="mensaje-error-registro">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <form
        action="funciones/guardar_organizador.php"
        method="POST"
        enctype="multipart/form-data"
        autocomplete="off"
    >


        <div class="organizador-container">


            <!-- ================================= -->
            <!-- IZQUIERDA: ORGANIZADOR -->
            <!-- ================================= -->

            <div class="organizador-izquierda">


                <div class="organizador-card">


                    <div class="organizador-logo">

                        <img
                            src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                            class="logo"
                            alt="Logo The Bull"
                        >

                        <h2>
                            Registro de Organizador
                        </h2>

                    </div>


                    <h3>
                        Información Personal
                    </h3>


                    <!-- ================================= -->
                    <!-- NOMBRE -->
                    <!-- ================================= -->

                    <div class="input-group">

                        <label for="nombre">
                            Nombre
                        </label>

                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            placeholder="Ingrese su nombre"
                            maxlength="100"
                            value="<?= htmlspecialchars($datos['nombre'] ?? '') ?>"
                            required
                        >

                    </div>


                    <!-- ================================= -->
                    <!-- APELLIDO -->
                    <!-- ================================= -->

                    <div class="input-group">

                        <label for="apellido">
                            Apellido
                        </label>

                        <input
                            type="text"
                            id="apellido"
                            name="apellido"
                            placeholder="Ingrese su apellido"
                            maxlength="100"
                            value="<?= htmlspecialchars($datos['apellido'] ?? '') ?>"
                            required
                        >

                    </div>


                    <!-- ================================= -->
                    <!-- FECHA DE NACIMIENTO -->
                    <!-- ================================= -->

                    <div class="input-group">

                        <label for="fecha_nacimiento">
                            Fecha de nacimiento
                        </label>

                        <input
                            type="date"
                            name="fecha_nacimiento"
                            id="fecha_nacimiento"
                            value="<?= htmlspecialchars($datos['fecha_nacimiento'] ?? '') ?>"
                            required
                        >

                        <small
                            id="mensaje-edad"
                            class="mensaje-ayuda"
                        >
                            Debe tener al menos 18 años.
                        </small>

                    </div>


                    <!-- ================================= -->
                    <!-- TELEFONO -->
                    <!-- ================================= -->

                    <div class="input-group">

                        <label for="telefono">
                            Teléfono
                        </label>

                        <input
                            type="text"
                            id="telefono"
                            name="telefono"
                            placeholder="3825123456"
                            maxlength="10"
                            minlength="10"
                            pattern="[0-9]{10}"
                            inputmode="numeric"
                            value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>"
                            required
                        >

                        <small>
                            Ingrese 10 números.
                            Ejemplo: 3825123456.
                            No coloque +54.
                        </small>

                    </div>


                    <!-- ================================= -->
                    <!-- EMAIL -->
                    <!-- ================================= -->

                    <div class="input-group">

                        <label for="email">
                            Correo electrónico
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Ingrese su correo electrónico"
                            maxlength="100"
                            value="<?= htmlspecialchars($datos['email'] ?? '') ?>"
                            required
                        >

                    </div>


                    <!-- ================================= -->
                    <!-- CONTRASEÑA -->
                    <!-- ================================= -->

                    <div class="input-group">

                        <label for="password">
                            Contraseña
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Ejemplo: Alex123!"
                            maxlength="8"
                            minlength="8"
                            required
                        >

                        <small>
                            Debe tener exactamente 8 caracteres,
                            una mayúscula, un número y un carácter especial.
                            Ejemplo: Alex123!
                        </small>

                        <small
                            id="mensaje-password"
                            class="mensaje-validacion"
                        ></small>

                    </div>


                    <!-- ================================= -->
                    <!-- CONFIRMAR CONTRASEÑA -->
                    <!-- ================================= -->

                    <div class="input-group">

                        <label for="confirmar_password">
                            Confirmar contraseña
                        </label>

                        <input
                            type="password"
                            id="confirmar_password"
                            name="confirmar_password"
                            placeholder="Repita la contraseña"
                            maxlength="8"
                            minlength="8"
                            required
                        >

                        <small
                            id="mensaje-confirmar-password"
                            class="mensaje-validacion"
                        ></small>

                    </div>


                </div>


            </div>


            <!-- ================================= -->
            <!-- DERECHA -->
            <!-- ================================= -->

            <div class="organizador-derecha">


                <!-- ================================= -->
                <!-- INFORMACIÓN DEL CLUB -->
                <!-- ================================= -->

                <div class="organizador-card">


                                <div class="organizador-card club-card">

    <h3>
        Información del Club
    </h3>


    <div class="form-grid">


        <!-- ================================= -->
        <!-- NOMBRE DEL CLUB -->
        <!-- ================================= -->

        <div class="input-group">

            <label for="nombre_club">
                Nombre del club
            </label>

            <input
                type="text"
                id="nombre_club"
                name="nombre_club"
                placeholder="Ingrese el nombre del club"
                maxlength="100"
                value="<?= htmlspecialchars($datos['nombre_club'] ?? '') ?>"
                required
            >

        </div>


        <!-- ================================= -->
        <!-- DIRECCIÓN -->
        <!-- ================================= -->

        <div class="input-group">

            <label for="direccion">
                Dirección
            </label>

            <input
                type="text"
                id="direccion"
                name="direccion"
                placeholder="Ingrese la dirección"
                maxlength="150"
                value="<?= htmlspecialchars($datos['direccion'] ?? '') ?>"
                required
            >

        </div>


        <!-- ================================= -->
        <!-- CANTIDAD DE CANCHAS -->
        <!-- ================================= -->

        <div class="input-group cantidad-canchas-centro">

            <label for="cantidad_canchas">
                Cantidad de canchas
            </label>

            <input
                type="number"
                id="cantidad_canchas"
                name="cantidad_canchas"
                min="1"
                max="20"
                placeholder="Ingrese la cantidad"
                value="<?= htmlspecialchars($datos['cantidad_canchas'] ?? '') ?>"
                required
            >

            <small class="mensaje-ayuda">
                Puede registrar entre 1 y 20 canchas.
            </small>

        </div>


        <!-- ================================= -->
        <!-- HORARIOS -->
        <!-- ================================= -->

        <div class="horarios-bloque">

            <div class="titulo-horarios">
                Horario de atención
            </div>


            <div class="horarios-container">


                <!-- MAÑANA -->

                <div class="turno-horario">

                    <div class="titulo-turno">
                        <span></span>
                        Horario de mañana
                    </div>

                    <div class="horario-fila">

                        <div class="horario-campo">

                            <label>
                                Desde
                            </label>

                            <input
                                type="time"
                                name="horario_manana_desde"
                                value="<?= htmlspecialchars($datos['horario_manana_desde'] ?? '') ?>"
                            >

                        </div>


                        <div class="horario-campo">

                            <label>
                                Hasta
                            </label>

                            <input
                                type="time"
                                name="horario_manana_hasta"
                                value="<?= htmlspecialchars($datos['horario_manana_hasta'] ?? '') ?>"
                            >

                        </div>

                    </div>

                </div>


                <!-- TARDE -->

                <div class="turno-horario">

                    <div class="titulo-turno">
                        <span></span>
                        Horario de tarde
                    </div>

                    <div class="horario-fila">

                        <div class="horario-campo">

                            <label>
                                Desde
                            </label>

                            <input
                                type="time"
                                name="horario_tarde_desde"
                                value="<?= htmlspecialchars($datos['horario_tarde_desde'] ?? '') ?>"
                            >

                        </div>


                        <div class="horario-campo">

                            <label>
                                Hasta
                            </label>

                            <input
                                type="time"
                                name="horario_tarde_hasta"
                                value="<?= htmlspecialchars($datos['horario_tarde_hasta'] ?? '') ?>"
                            >

                        </div>

                    </div>

                </div>


            </div>

        </div>


        <!-- ================================= -->
        <!-- TELÉFONO DEL CLUB -->
        <!-- ================================= -->

        <div class="input-group form-full">

            <label for="telefono_club">
                Teléfono del club
            </label>

            <input
                type="text"
                id="telefono_club"
                name="telefono_club"
                placeholder="3825123456"
                maxlength="10"
                minlength="10"
                pattern="[0-9]{10}"
                inputmode="numeric"
                value="<?= htmlspecialchars($datos['telefono_club'] ?? '') ?>"
                required
            >

            <small class="mensaje-ayuda">
                Ingrese 10 números, sin +54. Ejemplo: 3825123456.
            </small>

        </div>


    </div>

</div>

                <!-- ================================= -->
                <!-- INFORMACIÓN DE LAS CANCHAS -->
                <!-- ================================= -->

                <div class="organizador-card">


                    <h3>
                        Información de las Canchas
                    </h3>


                    <p class="texto-ayuda-canchas">

                        Ingrese primero la cantidad de canchas.
                        Luego aparecerán los datos que debe completar
                        para cada una.

                    </p>


                    <div id="contenedor-canchas"></div>


                </div>


                <!-- ================================= -->
                <!-- BOTÓN -->
                <!-- ================================= -->

                <div class="organizador-card">


                    <button
                        type="submit"
                    >

                        Enviar solicitud

                    </button>


                </div>


            </div>


        </div>


    </form>


</div>


<!-- ========================================= -->
<!-- DATOS RECUPERADOS PARA JAVASCRIPT -->
<!-- ========================================= -->
<script src="js/padel.js"></script>
<script src="js/organizador.js"></script>


</body>

</html>