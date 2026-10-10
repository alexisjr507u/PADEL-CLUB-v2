<?php
include("../config/no_cache.php");
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

function enviarCodigo($correo, $codigo)
{
    $configMail = require __DIR__ . '/../config/mail.php';

    $mail = new PHPMailer(true);

    try {

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
        $mail->Subject = 'Codigo de verificacion';

        $mail->Body = "
            <h2>Verificacion de correo</h2>
            <p>Tu codigo es:</p>
            <h1>$codigo</h1>
        ";

        $mail->send();

        return true;

    } catch (Exception $e) {

        return false;

    }
}