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
    <title>Recuperar Contraseña</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="contenedor">

    <div class="login-box">

        <img src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
            alt="Logo"
            class="logo">

        <h2>Recuperar Contraseña</h2>

        <?php
        if(isset($_GET['error']) && $_GET['error'] == 'email'){
            echo '<div class="mensaje-error" id="mensajeError">
                    <i class="bi bi-exclamation-circle-fill"></i> No existe una cuenta con ese correo.
                    </div>';
        }
        ?>

        <form action="funciones/recuperar_password_logica.php" method="POST" autocomplete="off" data-loader="Enviando código...">

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

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
<script src="js/padel.js?v=6"></script>
</body>
</html>