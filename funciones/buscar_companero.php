<?php

// Endpoint de solo lectura (no crea nada) para que el modal de
// invitación muestre si el correo ingresado pertenece a una cuenta
// registrada, antes de que el participante confirme el envío.

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");
require_once("club_categoria.php");

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
    "SELECT tipo, estado, id_categoria, id_club, max_participantes FROM torneos WHERE id_torneo = ?"
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

// Sin lugar para una pareja nueva (2 jugadores), no tiene sentido buscar compañero. El servidor lo vuelve a comprobar al invitar.
$stmtCupoBusqueda = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM inscripcion WHERE id_torneo = ? AND estado = 'Confirmada'");
mysqli_stmt_bind_param($stmtCupoBusqueda, "i", $id_torneo);
mysqli_stmt_execute($stmtCupoBusqueda);
$confirmadosBusqueda = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCupoBusqueda))['n'];

if ($confirmadosBusqueda + 2 > (int) $torneo['max_participantes']) {
    responder(['ok' => false, 'mensaje' => 'Este torneo ya alcanzó su capacidad máxima: no quedan lugares disponibles para una pareja nueva.']);
}

// ¿El titular ya tiene una invitación pendiente (vigente) en este torneo? Sirve para avisar que enviar otra la reemplaza.
$stmtPendienteBusqueda = mysqli_prepare(
    $conexion,
    "SELECT v.email_invitado, v.id_usuario_invitado
     FROM invitacion v
     INNER JOIN inscripcion i ON i.id_inscripcion = v.id_inscripcion_invita
     WHERE v.id_torneo = ? AND i.id_usuario = ? AND v.estado = 'Pendiente' AND v.fecha_vencimiento >= NOW()
     LIMIT 1"
);
mysqli_stmt_bind_param($stmtPendienteBusqueda, "ii", $id_torneo, $id_usuario_titular);
mysqli_stmt_execute($stmtPendienteBusqueda);
$pendienteBusqueda = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPendienteBusqueda));
$avisoReemplazo = ' Tu invitación pendiente anterior se cancelará al enviar esta.';

$stmtPropio = mysqli_prepare($conexion, "SELECT email FROM usuario WHERE id_usuario = ?");
mysqli_stmt_bind_param($stmtPropio, "i", $id_usuario_titular);
mysqli_stmt_execute($stmtPropio);
$propio = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPropio));

// Tu categoría EN EL CLUB DEL TORNEO.
$motivoPropio = motivoSinCategoriaEnClub(membresiaEnClub($conexion, $id_usuario_titular, (int) $torneo['id_club']), (int) $torneo['id_categoria']);

if ($motivoPropio !== '') {
    responder(['ok' => false, 'mensaje' => [
        'no_pertenece_club' => 'Todavía no pertenecés al club de este torneo: usá «Unirme al club» antes de formar pareja.',
        'sin_categoria' => 'Todavía no tenés una categoría asignada en este club: todavía no podés formar pareja.',
        'categoria_incompatible' => 'Tu categoría en este club no coincide con la de este torneo.',
    ][$motivoPropio]]);
}

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
    "SELECT id_usuario, nombre, apellido FROM usuario WHERE email = ?"
);
mysqli_stmt_bind_param($stmtUsuario, "s", $email);
mysqli_stmt_execute($stmtUsuario);
$usuarioEncontrado = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUsuario));

if ($pendienteBusqueda && strcasecmp($pendienteBusqueda['email_invitado'], $email) === 0) {

    responder([
        'ok' => true,
        'registrado' => $usuarioEncontrado ? true : false,
        'puede_invitar' => false,
        'mensaje' => 'Ya tenés una invitación pendiente para este compañero.',
    ]);

}

if (!$usuarioEncontrado) {

    responder([
        'ok' => true,
        'registrado' => false,
        'puede_invitar' => true,
        'mensaje' => 'Ese correo todavía no tiene cuenta. Si enviás la invitación, le vamos a mandar un mail para que se registre.' . ($pendienteBusqueda ? $avisoReemplazo : ''),
    ]);

}

// La categoría de esa persona EN EL CLUB DEL TORNEO.
$motivoOtro = motivoSinCategoriaEnClub(
    membresiaEnClub($conexion, (int) $usuarioEncontrado['id_usuario'], (int) $torneo['id_club']),
    (int) $torneo['id_categoria']
);

if ($motivoOtro !== '') {

    responder([
        'ok' => true,
        'registrado' => true,
        'puede_invitar' => false,
        'nombre' => $usuarioEncontrado['nombre'] . ' ' . $usuarioEncontrado['apellido'],
        'mensaje' => [
            'no_pertenece_club' => 'Esa persona todavía no pertenece al club de este torneo.',
            'sin_categoria' => 'Esa persona todavía no tiene una categoría asignada en este club.',
            'categoria_incompatible' => 'Esa persona no juega en la categoría de este torneo.',
        ][$motivoOtro],
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
    'mensaje' => 'Se encontró la cuenta de ' . $usuarioEncontrado['nombre'] . ' ' . $usuarioEncontrado['apellido'] . '.' . ($pendienteBusqueda ? $avisoReemplazo : ''),
]);
