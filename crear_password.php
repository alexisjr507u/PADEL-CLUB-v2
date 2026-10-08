<?php
include("config/no_cache.php");
include("config/conexion.php");

// El correo debe haber sido verificado por código en esta misma sesión
// antes de poder llegar a esta pantalla.
if (
    !isset($_SESSION['verificacion_ok']) || $_SESSION['verificacion_ok'] !== true ||
    !isset($_SESSION['tipo_verificacion']) || $_SESSION['tipo_verificacion'] !== 'registro' ||
    !isset($_SESSION['email_verificado']) || !isset($_SESSION['email']) ||
    $_SESSION['email_verificado'] !== $_SESSION['email']
) {
    header("Location: login.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Contraseña</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="CSS/style.css">
</head>

<body>

<div class="contenedor">

    <div class="login-box">

        <img src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
            alt="Logo"
            class="logo">

        <h2>Crear Contraseña</h2>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'minimo'){ ?>

<div id="mensajeError" class="mensaje-error">
    <i class="bi bi-exclamation-circle-fill"></i> La contraseña debe tener al menos 8 caracteres.
</div>

<?php } ?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'password'){ ?>

<div id="mensajeError" class="mensaje-error">
    <i class="bi bi-exclamation-circle-fill"></i> Las contraseñas no coinciden.
</div>

<?php } ?>

        <form action="funciones/guardar_password_temporal.php" method="POST"autocomplete="off">

    <div class="input-group">

        <label>Contraseña</label>

        <div class="password-container">

            <input type="password"
                name="password"
                minlength="8"
                required>

            <i class="bi bi-eye-slash toggle-password"></i>

        </div>

    </div>

    <div class="input-group">

        <label>Confirmar contraseña</label>

        <div class="password-container">

            <input type="password"
                name="confirmar_password"
                required>

            <i class="bi bi-eye-slash toggle-password"></i>

        </div>

    </div>

    <button type="submit">
        Crear contraseña
    </button>

</form>

    </div>

</div>
<script src="js/padel.js"></script>
</body>
</html>