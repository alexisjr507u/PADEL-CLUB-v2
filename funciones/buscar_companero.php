<?php

// Endpoint de solo lectura (no crea nada) para que el modal de
// invitación muestre si el correo ingresado pertenece a una cuenta
// registrada, antes de que el participante confirme el envío.

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");

header('Content-Type: application/json; charset=utf-8');

function responder($datos)
{
    echo json_encode($datos);
    exit;
}

if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 3) {
    http_response_code(401);
    responder(['ok' => false, 'mensaje' => 'Sesión no válida.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(['ok' => false, 'mensaje' => 'Método no permitido.']);
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    responder(['ok' => false, 'mensaje' => 'Token inválido, recargá la página.']);
}

$id_torneo = intval($_POST['id_torneo'] ?? 0);
$email = trim($_POST['email'] ?? '');
$id_usuario_titular = (int) $_SESSION['id_usuario'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(['ok' => false, 'mensaje' => 'Ingresá un correo válido.']);
}

$stmtTorneo = mysqli_prepare(
    $conexion,
    "SELECT tipo, estado, id_categoria FROM torneos WHERE id_torneo = ?"
);
mysqli_stmt_bind_param($stmtTorneo, "i", $id_torneo);
mysqli_stmt_execute($stmtTorneo);
$torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTorneo));

if (!$torneo) {
    responder(['ok' => false, 'mensaje' => 'El torneo no existe.']);
}

if ($torneo['estado'] !== 'Abierto') {
    responder(['ok' => false, 'mensaje' => 'Ese torneo ya no admite inscripciones.']);
}

if (!in_array($torneo['tipo'], ['Parejas', 'Mixto'], true)) {
    responder(['ok' => false, 'mensaje' => 'Ese torneo no es de parejas ni mixto.']);
}

$stmtPropio = mysqli_prepare($conexion, "SELECT email FROM usuario WHERE id_usuario = ?");
mysqli_stmt_bind_param($stmtPropio, "i", $id_usuario_titular);
mysqli_stmt_execute($stmtPropio);
$propio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPropio));

if ($propio && strcasecmp($propio['email'], $email) === 0) {
    responder([
        'ok' => true,
        'registrado' => false,
        'puede_invitar' => false,
        'mensaje' => 'No podés invitarte a vos mismo.',
    ]);
}

$stmtUsuario = mysqli_prepare(
    $conexion,
    "SELECT id_usuario, nombre, apellido, id_categoria FROM usuario WHERE email = ?"
);
mysqli_stmt_bind_param($stmtUsuario, "s", $email);
mysqli_stmt_execute($stmtUsuario);
$usuarioEncontrado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUsuario));

if (!$usuarioEncontrado) {

    responder([
        'ok' => true,
        'registrado' => false,
        'puede_invitar' => true,
        'mensaje' => 'Ese correo todavía no tiene cuenta. Si enviás la invitación, le vamos a mandar un mail para que se registre.',
    ]);

}

if ((int) $usuarioEncontrado['id_categoria'] !== (int) $torneo['id_categoria']) {

    responder([
        'ok' => true,
        'registrado' => true,
        'puede_invitar' => false,
        'nombre' => $usuarioEncontrado['nombre'] . ' ' . $usuarioEncontrado['apellido'],
        'mensaje' => 'Esa persona no juega en la categoría de este torneo.',
    ]);

}

$stmtYaInscripto = mysqli_prepare(
    $conexion,
    "SELECT id_inscripcion FROM inscripcion WHERE id_torneo = ? AND id_usuario = ?"
);
mysqli_stmt_bind_param($stmtYaInscripto, "ii", $id_torneo, $usuarioEncontrado['id_usuario']);
mysqli_stmt_execute($stmtYaInscripto);

if (mysqli_num_rows(mysqli_stmt_get_result($stmtYaInscripto)) > 0) {

    responder([
        'ok' => true,
        'registrado' => true,
        'puede_invitar' => false,
        'nombre' => $usuarioEncontrado['nombre'] . ' ' . $usuarioEncontrado['apellido'],
        'mensaje' => 'Esa persona ya tiene una inscripción relacionada con este torneo.',
    ]);

}

responder([
    'ok' => true,
    'registrado' => true,
    'puede_invitar' => true,
    'nombre' => $usuarioEncontrado['nombre'] . ' ' . $usuarioEncontrado['apellido'],
    'mensaje' => 'Se encontró la cuenta de ' . $usuarioEncontrado['nombre'] . ' ' . $usuarioEncontrado['apellido'] . '.',
]);
