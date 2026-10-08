<?php
include("config/no_cache.php");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Código</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="CSS/style.css">
</head>

<body>

    <div class="contenedor">

        <div class="login-box">

            <img src="img/ChatGPT Image 29 may 2026, 17_54_13.png"
                alt="Logo"
                class="logo">

            <h2>Verificar Email</h2>
    <?php if(isset($_GET['error']) && $_GET['error'] == 'incorrecto'){ ?>

<div id="mensajeError" class="mensaje-error">
     <i class="bi bi-exclamation-circle-fill"></i> Código incorrecto. Intente nuevamente.
</div>

<?php } ?>

<?php if(isset($_GET['error']) && $_GET['error'] == 'expirado'){ ?>

<div id="mensajeError" class="mensaje-error">
     <i class="bi bi-exclamation-circle-fill"></i> El código ha expirado. Solicita uno nuevo.
</div>

<?php } ?>
            <form action="funciones/verificar_codigo_logica.php" method="POST" autocomplete="off">

                <div class="input-group">

                    <label>Código de verificación</label>

                    <input type="text"
                        name="codigo"
                        placeholder="Ingrese el código"
                        maxlength="6"
                        required>

                </div>

                <button type="submit">
                    Verificar
                </button>
                <div class="links">

    <a href="funciones/reenviar_codigo.php">
        Reenviar código
    </a>

</div>


            </form>

        </div>

    </div>

</body>
<script src="js/padel.js?v=2"></script>
</html>