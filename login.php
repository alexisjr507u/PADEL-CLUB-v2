<?php
include("config/no_cache.php");
include("config/conexion.php");
require_once("funciones/csrf.php");

$csrf_token = generar_csrf_token();

// Torneo del que viene el visitante (consulta pública). Solo se acepta un id entero que exista;
// se usa únicamente para volver a ese torneo después de entrar. No da ningún permiso.
$idTorneoDestino = 0;
$nombreTorneoDestino = '';
$idTorneoGet = filter_input(INPUT_GET, 'torneo', FILTER_VALIDATE_INT);

if ($idTorneoGet && $idTorneoGet > 0) {

    $stmtDestino = mysqli_prepare($conexion, "SELECT nombre FROM torneos WHERE id_torneo = ?");
    mysqli_stmt_bind_param($stmtDestino, "i", $idTorneoGet);
    mysqli_stmt_execute($stmtDestino);
    $filaDestino = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtDestino));
    mysqli_stmt_close($stmtDestino);

    if ($filaDestino) {
        $idTorneoDestino = (int) $idTorneoGet;
        $nombreTorneoDestino = $filaDestino['nombre'];
    }

}

$paramTorneoRegistro = $idTorneoDestino ? '?torneo=' . $idTorneoDestino : '';
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>The Bull Padel Club</title>

    <link rel="stylesheet" href="css/style.css">

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

<?php
// Aviso cuando la sesión se cerró por el límite de inactividad o de duración (lo deja config/sesion.php).
$avisoSesion = $_COOKIE['PADEL_AVISO'] ?? '';

if ($avisoSesion === 'inactividad' || $avisoSesion === 'duracion') {

    $textoAvisoSesion = $avisoSesion === 'inactividad'
        ? 'Tu sesión se cerró por inactividad. Volvé a iniciar sesión.'
        : 'Tu sesión llegó a su duración máxima. Volvé a iniciar sesión.';
?>
<div class="mensaje-error">
    <?php echo htmlspecialchars($textoAvisoSesion); ?>
</div>
<?php } ?>

            <?php if ($idTorneoDestino) { ?>
            <div class="mensaje-exito" style="margin-bottom:15px;">
                <?php if (($_GET['registro'] ?? '') === 'ok') { ?>
                    <strong>¡Cuenta creada!</strong> Iniciá sesión para volver al torneo
                <?php } else { ?>
                    Iniciá sesión para volver al torneo
                <?php } ?>
                <strong>«<?php echo htmlspecialchars($nombreTorneoDestino, ENT_QUOTES, 'UTF-8'); ?>»</strong>
                y confirmar tu inscripción.
            </div>
            <?php } ?>

            <form action="funciones/login_logica.php"
                method="POST"autocomplete="off" data-loader="Iniciando sesión...">

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <?php if ($idTorneoDestino) { ?>
                <input type="hidden" name="torneo" value="<?php echo (int) $idTorneoDestino; ?>">
                <?php } ?>

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

                <a href="registro_participante.php<?php echo $paramTorneoRegistro; ?>" class="btn-registro">
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
<script src="js/padel.js?v=17"></script>
</body>

</html>