<?php

include("../config/no_cache.php");
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

function enviarMailInvitacion($correo, $asunto, $cuerpoHtml)
{
    $configMail = require __DIR__ . '/../config/mail.php';

    $mail = new PHPMailer(true);

    try {

        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        $mail->isSMTP();
        $mail->Host = $configMail['host'];
        $mail->SMTPAuth = true;

        $mail->Username = $configMail['username'];
        $mail->Password = $configMail['password'];

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $configMail['port'];

        $mail->setFrom($configMail['from_email'], $configMail['from_name']);

        $mail->addAddress($correo);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpoHtml;

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log("Error al enviar invitacion a $correo: " . $mail->ErrorInfo);

        return false;

    }
}

// Invitación a un compañero que YA tiene cuenta en la plataforma.
function enviarInvitacionRegistrado($correo, $nombreInvita, $nombreTorneo)
{
    $cuerpo = "
        <h2>¡Te invitaron a jugar!</h2>
        <p><strong>" . htmlspecialchars($nombreInvita) . "</strong> te invita a formar pareja
        para participar en el torneo <strong>" . htmlspecialchars($nombreTorneo) . "</strong>
        de The Bull Padel Club.</p>
        <p>Entrá a tu cuenta y revisá tus notificaciones para aceptar o rechazar la invitación.</p>
    ";

    return enviarMailInvitacion($correo, 'Invitación a formar pareja - The Bull Padel Club', $cuerpo);
}

// Invitación a un correo que todavía NO tiene cuenta.
function enviarInvitacionNuevoUsuario($correo, $nombreInvita, $nombreTorneo, $urlRegistro)
{
    $cuerpo = "
        <h2>¡Te invitaron a jugar!</h2>
        <p><strong>" . htmlspecialchars($nombreInvita) . "</strong> quiere formar pareja con vos
        para participar en el torneo <strong>" . htmlspecialchars($nombreTorneo) . "</strong>
        de The Bull Padel Club.</p>
        <p>Registrate en nuestra plataforma para poder recibir y responder a la invitación:</p>
        <p><a href=\"" . htmlspecialchars($urlRegistro) . "\">Registrarme en The Bull Padel Club</a></p>
    ";

    return enviarMailInvitacion($correo, 'Te invitaron a jugar - The Bull Padel Club', $cuerpo);
}

// Aviso al que invitó cuando el compañero responde.
function enviarAvisoRespuestaInvitacion($correo, $nombreCompanero, $nombreTorneo, $aceptada)
{
    $accion = $aceptada ? 'aceptó' : 'rechazó';

    $cuerpo = "
        <h2>Novedades de tu invitación</h2>
        <p><strong>" . htmlspecialchars($nombreCompanero) . "</strong> $accion tu invitación
        para formar pareja en el torneo <strong>" . htmlspecialchars($nombreTorneo) . "</strong>
        de The Bull Padel Club.</p>
    ";

    return enviarMailInvitacion($correo, 'Respuesta a tu invitación - The Bull Padel Club', $cuerpo);
}
