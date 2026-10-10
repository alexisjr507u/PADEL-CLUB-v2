<?php

include("config/no_cache.php");

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

require_once("funciones/csrf.php");

$csrf_token = generar_csrf_token();
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cambiar Contraseña</title>


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="CSS/style.css">

</head>

<body>

    <div class="contenedor">

        <div class="login-box">

            <img src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                alt="Logo"
                class="logo">

            <h2>Cambiar Contraseña</h2>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'actual'){ ?>

<div id="mensajeError" class="mensaje-error">
    <i class="bi bi-exclamation-circle-fill"></i> La contraseña actual es incorrecta.
</div>

<?php } ?>

<?php if(isset($_GET['error']) && $_GET['error'] == 'coinciden'){ ?>

<div id="mensajeError" class="mensaje-error">
    <i class="bi bi-exclamation-triangle-fill"></i> Las nuevas contraseñas no coinciden.
</div>

<?php } ?>

<?php if(isset($_GET['success'])){ ?>

<div id="mensajeExito" class="mensaje-exito">
    <i class="bi bi-check-circle-fill"></i> Contraseña actualizada correctamente.
</div>

<?php } ?>

            <form action="funciones/cambiar_password_logica.php "
                method="POST" autocomplete="off" data-loader="Cambiando contraseña...">

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="input-group">

                    <label>Contraseña actual</label>

                    <div class="password-container">

                        <input type="password"
                            name="password_actual"
                            required>

                        <i class="bi bi-eye-slash toggle-password"></i>

                    </div>

                </div>

                <div class="input-group">

                    <label>Nueva contraseña</label>

                    <div class="password-container">

                        <input type="password"
                            name="password_nueva"
                            required>

                        <i class="bi bi-eye-slash toggle-password"></i>

                    </div>

                    <div class="strength">

    <small id="strengthText">
        Nivel de seguridad: -
    </small>

    <div class="bar">
        <div class="bar-fill" id="strengthBar"></div>
    </div>

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
                    Cambiar contraseña
                </button>


            </form>

        </div>

    </div>

    <script src="js/padel.js?v=3"></script>

</body>

</html>