<?php
include("config/no_cache.php");

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="contenedor">

    <div class="login-box">

        <h2>Recuperar Contraseña</h2>

        <?php
        if(isset($_GET['error']) && $_GET['error'] == 'email'){
            echo '<div class="mensaje-error" id="mensajeError">
                    No existe una cuenta con ese correo.
                    </div>';
        }
        ?>

        <form action="funciones/recuperar_password_logica.php" method="POST" autocomplete="off">

            <div class="input-group">

                <label>Correo electrónico</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Ingrese su correo"
                    required
                >

            </div>

            <button type="submit">
                Enviar código
            </button>

        </form>

        <div class="links">
            <a href="login.php">Volver al inicio de sesión</a>
        </div>

    </div>

</div>
<script src="js/padel.js"></script>
</body>
</html>