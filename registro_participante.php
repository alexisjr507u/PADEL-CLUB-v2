<?php
include("config/no_cache.php");
include("config/conexion.php");
require_once("funciones/csrf.php");

$csrf_token = generar_csrf_token();

// Torneo de origen (consulta pública): se guarda en la sesión de registro para volver a él al terminar.
// Una visita sin ?torneo= (y sin error de validación) lo borra; así un valor viejo no se arrastra.
$idTorneoGet = filter_input(INPUT_GET, 'torneo', FILTER_VALIDATE_INT);

if ($idTorneoGet && $idTorneoGet > 0) {
    $_SESSION['torneo_destino'] = (int) $idTorneoGet;
} elseif (!isset($_GET['error'])) {
    unset($_SESSION['torneo_destino']);
}

$paramTorneoLogin = !empty($_SESSION['torneo_destino']) ? '?torneo=' . (int) $_SESSION['torneo_destino'] : '';
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registro - The Bull Padel Club</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">

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
              <?php if(isset($_GET['error']) && $_GET['error'] == 'nombre'){ ?>

<div id="mensajeError" class="mensaje-error">
     <i class="bi bi-exclamation-circle-fill"></i> El nombre y el apellido solo pueden tener letras, espacios, apóstrofes o guiones (entre 2 y 50 caracteres).
</div>

<?php } ?>
              <?php if(isset($_GET['error']) && $_GET['error'] == 'telefono'){ ?>

<div id="mensajeError" class="mensaje-error">
     <i class="bi bi-exclamation-circle-fill"></i> Este número de teléfono ya está registrado.
</div>

<?php } ?>

            <form action="funciones/registro_logica.php"
                method="POST" autocomplete="off" data-loader="Procesando...">

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

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

                <a href="login.php<?php echo $paramTorneoLogin; ?>">
                    ¿Ya tienes cuenta? Iniciar sesión
                </a>

            </div>

        </div>

    </div>
<script src="js/padel.js?v=17"></script>
</body>

</html>
