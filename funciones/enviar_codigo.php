<?php
include("../config/no_cache.php");
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

function enviarCodigo($correo, $codigo)
{
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // TU CORREO GMAIL
        $mail->Username = 'padelweb2026@gmail.com';
        $mail->Password = 'kzbhndxdvxvnheeh';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('padelweb2026@gmail.com', 'Padel');

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