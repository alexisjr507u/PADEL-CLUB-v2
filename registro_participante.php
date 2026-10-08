<?php
include("config/no_cache.php");
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registro - The Bull Padel Club</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="CSS/style.css">

</head>

<body>

    <div class="contenedor">

        <div class="login-box registro-box">

            <img src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                alt="Logo"
                class="logo">

            <h2>Crear Cuenta de participante</h2>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'email'){ ?>

<div id="mensajeError" class="mensaje-error">
     <i class="bi bi-exclamation-circle-fill"></i> Este correo ya está registrado.
</div>

<?php } ?>
              <?php if(isset($_GET['error']) && $_GET['error'] == 'telefono'){ ?>

<div id="mensajeError" class="mensaje-error">
     <i class="bi bi-exclamation-circle-fill"></i> Este número de teléfono ya está registrado.
</div>

<?php } ?>

            <form action="funciones/registro_logica.php"
                method="POST" autocomplete="off">

                <div class="form-grid">

                    <div class="input-group">

                        <label>Nombre</label>

                    <input type="text"
    name="nombre"
    placeholder="Ingrese su nombre"
    required>

                    </div>

                    <div class="input-group">

                        <label>Apellido</label>

                        <input type="text"
    name="apellido"
    placeholder="Ingrese su apellido"
    required>

                    </div>

                    <div class="input-group">

                        <label>Fecha de nacimiento</label>

                        <input type="date"
                            name="fecha_nacimiento"
                            required>

                    </div>

                    <div class="input-group">
        

                        <label>Teléfono</label>

                      <input type="text"
    name="telefono"
    placeholder="Ingrese su teléfono"
    maxlength="10"
    required>

                    </div>

                    <div class="input-group form-full">

                        <label>Email</label>

                        <input type="email"
                            name="email"
                            placeholder="Ingrese su email"
                            required>

                    </div>

                    <div class="form-full">

                        <button type="submit">
                            Continuar
                        </button>

                    </div>

                </div>

            </form>

            <div class="links">

                <a href="login.php">
                    ¿Ya tienes cuenta? Iniciar sesión
                </a>

            </div>

        </div>

    </div>
<script src="js/padel.js?v=2"></script>
</body>

</html>
