<?php

include("../config/no_cache.php");
include("../config/conexion.php");

if (!isset($_SESSION['password'])) {
    die("Error: no se encontró la contraseña.");
}

// Defensa en profundidad: aunque crear_password.php ya lo exige,
// este es el punto final que inserta en la BD con verificado = 1,
// así que vuelve a comprobar que el código fue validado de verdad.
if (
    !isset($_SESSION['verificacion_ok']) || $_SESSION['verificacion_ok'] !== true ||
    !isset($_SESSION['tipo_verificacion']) || $_SESSION['tipo_verificacion'] !== 'registro' ||
    !isset($_SESSION['email_verificado']) || !isset($_SESSION['email']) ||
    $_SESSION['email_verificado'] !== $_SESSION['email']
) {
    die("Error: el correo no fue verificado correctamente.");
}

$password = $_SESSION['password'];

$password_hash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$nombre = $_SESSION['nombre'];
$apellido = $_SESSION['apellido'];
$fecha_nacimiento = $_SESSION['fecha_nacimiento'];
$telefono = $_SESSION['telefono'];
$email = $_SESSION['email'];
$codigo_verificacion = $_SESSION['codigo'];

$id_rol = 3;
$id_categoria = $_SESSION['id_categoria'];

$sql = "INSERT INTO usuario
(
nombre,
apellido,
fecha_nacimiento,
telefono,
email,
pass,
codigo_verificacion,
verificado,
id_rol,
id_categoria
)
VALUES
(?, ?, ?, ?, ?, ?, ?, 1, ?, ?)";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "sssssssii",
    $nombre,
    $apellido,
    $fecha_nacimiento,
    $telefono,
    $email,
    $password_hash,
    $codigo_verificacion,
    $id_rol,
    $id_categoria
);

if (mysqli_stmt_execute($stmt)) {

    $id_usuario_nuevo = mysqli_insert_id($conexion);

    // Vincular invitaciones pendientes que esperaban este correo.
    // El email ya pasó por el flujo de verificación con código,
    // así que esto es seguro: nadie puede "robar" una invitación
    // con un correo que no controla.
    $stmtInvitacionesPendientes = mysqli_prepare(
        $conexion,
        "SELECT i.id_invitacion, i.id_torneo, i.id_inscripcion_invita, t.nombre AS nombre_torneo,
                u.nombre AS nombre_invita, u.apellido AS apellido_invita
         FROM invitacion i
         INNER JOIN torneos t ON t.id_torneo = i.id_torneo
         INNER JOIN inscripcion insc ON insc.id_inscripcion = i.id_inscripcion_invita
         INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
         WHERE i.email_invitado = ?
           AND i.estado = 'Pendiente'
           AND i.id_usuario_invitado IS NULL"
    );
    mysqli_stmt_bind_param($stmtInvitacionesPendientes, "s", $email);
    mysqli_stmt_execute($stmtInvitacionesPendientes);
    $invitacionesPendientes = mysqli_stmt_get_result($stmtInvitacionesPendientes);

    while ($invitacionPendiente = mysqli_fetch_assoc($invitacionesPendientes)) {

        $stmtVincular = mysqli_prepare(
            $conexion,
            "UPDATE invitacion SET id_usuario_invitado = ? WHERE id_invitacion = ?"
        );
        mysqli_stmt_bind_param(
            $stmtVincular,
            "ii",
            $id_usuario_nuevo,
            $invitacionPendiente['id_invitacion']
        );
        mysqli_stmt_execute($stmtVincular);

        $mensajeNotificacion =
            $invitacionPendiente['nombre_invita'] . ' ' . $invitacionPendiente['apellido_invita'] .
            ' te invita a formar pareja para participar en el torneo ' .
            $invitacionPendiente['nombre_torneo'] . ' de The Bull Padel Club.';

        $stmtNotifNueva = mysqli_prepare(
            $conexion,
            "INSERT INTO notificacion (id_usuario_destino, tipo, mensaje, id_torneo, id_invitacion)
             VALUES (?, 'invitacion_pendiente', ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmtNotifNueva,
            "isii",
            $id_usuario_nuevo,
            $mensajeNotificacion,
            $invitacionPendiente['id_torneo'],
            $invitacionPendiente['id_invitacion']
        );
        mysqli_stmt_execute($stmtNotifNueva);

    }

    session_unset();
    session_destroy();

    ?>

    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <title>Cuenta creada</title>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="../css/style.css">

        <meta http-equiv="refresh" content="3;url=../login.php">
    </head>

    <body>

        <div class="contenedor">

            <div class="login-box">

                <img src="../img/ChatGPT Image 29 may 2026, 17_54_13.png"
                    alt="Logo"
                    class="logo">

                <div class="mensaje-exito">

                    <i class="bi bi-check-circle-fill"></i> Cuenta creada correctamente.

                    <br><br>

                    Serás redirigido al login en unos segundos...

                </div>

            </div>

        </div>

    </body>

    </html>

    <?php

} else {

    error_log("Error al crear usuario: " . mysqli_stmt_error($stmt));
    echo "Ha ocurrido un error al crear la cuenta. Intenta nuevamente.";

}
