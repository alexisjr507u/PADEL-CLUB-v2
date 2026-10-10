<?php
include("config/no_cache.php");
include("config/conexion.php");
require_once("funciones/csrf.php");

$csrf_token = generar_csrf_token();
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>The Bull Padel Club</title>

    <link rel="stylesheet" href="CSS/style.css">

</head>

<body>

    <div class="contenedor">

        <div class="login-box">

            <img src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                alt="Logo"
                class="logo">

            <h2>Iniciar Sesión</h2>
            <?php if(isset($_GET['error'])){ ?>

<div id="mensajeError" class="mensaje-error">
        Email o contraseña incorrectos.
</div>

<?php } ?>

            <form action="funciones/login_logica.php"
                method="POST"autocomplete="off" data-loader="Iniciando sesión...">

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="input-group">

                    <label>Email</label>

                    <input type="email"
                        name="email"
                        placeholder="Ingrese su email"
                        required>

                </div>

                <div class="input-group">

                    <label>Contraseña</label>
    <div class="password-container">

    <input type="password"
        id="password"
        name="password"
        placeholder="Ingrese su contraseña"
        required>

    <i class="bi bi-eye-slash toggle-password"
    id="togglePassword"></i>

</div>

                </div>

                <button type="submit">
                    Ingresar
                </button>

            </form>

            <div class="registro-botones">

                <a href="registro_participante.php" class="btn-registro">
                    Registrate como participante
                </a>

                <a href="registro_organizador.php" class="btn-registro">
                    Registrate como organizador
                </a>

            </div>

            <div class="links">

                <a href="recuperar_password.php">
                    ¿Olvidaste tu contraseña?
                </a>

                <a href="cambiar_password.php">
                    Cambiar contraseña
                </a>

            </div>

        </div>

    </div>
<script src="js/padel.js?v=3"></script>
</body>

</html>