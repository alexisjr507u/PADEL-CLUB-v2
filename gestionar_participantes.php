<?php

include("config/no_cache.php");
include("config/conexion.php");
require_once("funciones/csrf.php");


/* =========================================================
   SEGURIDAD: solo organizador (de su propio torneo) o admin
   ========================================================= */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['id_rol'] != 1 && $_SESSION['id_rol'] != 2) {
    header("Location: paneles.php");
    exit;
}

$id_torneo = filter_input(INPUT_GET, 'id_torneo', FILTER_VALIDATE_INT);

if (!$id_torneo) {
    header("Location: paneles.php?seccion=torneos");
    exit;
}

$csrf_token = generar_csrf_token();

$esAdmin = ($_SESSION['id_rol'] == 1);


/* =========================================================
   TORNEO + VERIFICAR PERTENENCIA (si es organizador)
   ========================================================= */

$sqlTorneo = "
    SELECT torneos.id_torneo, torneos.nombre, torneos.tipo, torneos.estado,
           torneos.max_participantes, torneos.id_club,
           categoria.nombre AS categoria, club.nombre AS nombre_club, club.id_usuario AS id_organizador
    FROM torneos
    INNER JOIN categoria ON categoria.id_categoria = torneos.id_categoria
    INNER JOIN club ON club.id_club = torneos.id_club
    WHERE torneos.id_torneo = ?
    LIMIT 1
";

$stmtTorneo = mysqli_prepare($conexion, $sqlTorneo);
mysqli_stmt_bind_param($stmtTorneo, "i", $id_torneo);
mysqli_stmt_execute($stmtTorneo);
$torneo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTorneo));

if (!$torneo) {
    header("Location: paneles.php?seccion=torneos");
    exit;
}

if (!$esAdmin && (int) $torneo['id_organizador'] !== (int) $_SESSION['id_usuario']) {
    // Es organizador, pero este torneo no es de su club.
    header("Location: paneles.php?seccion=torneos&error=sin_permiso");
    exit;
}


/* =========================================================
   PARTICIPANTES / PAREJAS CONFIRMADOS
   ========================================================= */

$confirmados = [];

if ($torneo['tipo'] === 'Individual') {

    $stmtConfirmados = mysqli_prepare(
        $conexion,
        "SELECT insc.id_inscripcion, u.nombre, u.apellido, u.email
         FROM inscripcion insc
         INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
         WHERE insc.id_torneo = ? AND insc.estado = 'Confirmada'
         ORDER BY insc.id_inscripcion ASC"
    );
    mysqli_stmt_bind_param($stmtConfirmados, "i", $id_torneo);
    mysqli_stmt_execute($stmtConfirmados);
    $confirmados = mysqli_fetch_all(mysqli_stmt_get_result($stmtConfirmados), MYSQLI_ASSOC);

} else {

    $stmtConfirmados = mysqli_prepare(
        $conexion,
        "SELECT p.id_pareja,
                u1.nombre AS nombre1, u1.apellido AS apellido1, u1.email AS email1,
                u2.nombre AS nombre2, u2.apellido AS apellido2, u2.email AS email2
         FROM pareja p
         INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
         INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
         INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
         INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
         WHERE p.id_torneo = ?
         ORDER BY p.id_pareja ASC"
    );
    mysqli_stmt_bind_param($stmtConfirmados, "i", $id_torneo);
    mysqli_stmt_execute($stmtConfirmados);
    $confirmados = mysqli_fetch_all(mysqli_stmt_get_result($stmtConfirmados), MYSQLI_ASSOC);

}

$totalPersonasConfirmadas = $torneo['tipo'] === 'Individual'
    ? count($confirmados)
    : count($confirmados) * 2;


/* =========================================================
   PENDIENTES Y CANCELADAS (solo informativo)
   ========================================================= */

$stmtOtros = mysqli_prepare(
    $conexion,
    "SELECT insc.estado, u.nombre, u.apellido
     FROM inscripcion insc
     INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
     WHERE insc.id_torneo = ? AND insc.estado != 'Confirmada'
     ORDER BY insc.id_inscripcion ASC"
);
mysqli_stmt_bind_param($stmtOtros, "i", $id_torneo);
mysqli_stmt_execute($stmtOtros);
$otrasInscripciones = mysqli_fetch_all(mysqli_stmt_get_result($stmtOtros), MYSQLI_ASSOC);


$mensajesAccion = [
    'cancelada'        => ['tipo' => 'exito', 'texto' => 'La inscripción se canceló correctamente.'],
    'pareja_cancelada' => ['tipo' => 'exito', 'texto' => 'La pareja se canceló correctamente.'],
    'token'            => ['tipo' => 'error', 'texto' => 'Tu sesión expiró, volvé a intentarlo.'],
    'sin_permiso'      => ['tipo' => 'error', 'texto' => 'No tenés permiso sobre ese torneo.'],
    'no_existe'        => ['tipo' => 'error', 'texto' => 'Ese registro no existe o ya fue cancelado.'],
    'error_bd'         => ['tipo' => 'error', 'texto' => 'Ocurrió un error al cancelar. Intentá nuevamente.'],
];

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Participantes - <?php echo htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/ver_torneo.css">

</head>

<body class="panel-body">

    <div class="panel vt-panel-oscuro">

        <main class="contenido" style="width:100%; padding: 32px;">

            <a href="paneles.php?seccion=torneos" class="vt-volver">
                <i class="bi bi-arrow-left"></i> Volver a Torneos
            </a>

            <div class="vt-topbar" style="margin-top: 16px;">

                <div>

                    <span class="vt-estado-badge"><?php echo htmlspecialchars($torneo['estado'], ENT_QUOTES, 'UTF-8'); ?></span>

                    <h1><?php echo htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></h1>

                    <p>
                        <?php echo htmlspecialchars($torneo['tipo'], ENT_QUOTES, 'UTF-8'); ?> ·
                        <?php echo htmlspecialchars($torneo['categoria'], ENT_QUOTES, 'UTF-8'); ?> ·
                        <?php echo htmlspecialchars($torneo['nombre_club'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                </div>

                <div>
                    <strong><?php echo $totalPersonasConfirmadas; ?> / <?php echo (int) $torneo['max_participantes']; ?></strong>
                    <span>participantes confirmados</span>
                </div>

            </div>

            <?php if (isset($_GET['accion']) && isset($mensajesAccion[$_GET['accion']])) {
                $m = $mensajesAccion[$_GET['accion']];
                $icono = $m['tipo'] === 'exito' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
                $clase = $m['tipo'] === 'exito' ? 'mensaje-exito' : 'mensaje-error';
            ?>
                <div class="<?php echo $clase; ?> mensaje-error-compacto">
                    <i class="bi <?php echo $icono; ?>"></i> <?php echo htmlspecialchars($m['texto']); ?>
                </div>
            <?php } ?>


            <!-- ========================================= -->
            <!-- CONFIRMADOS -->
            <!-- ========================================= -->

            <div class="vt-seccion-titulo" style="margin-top: 24px;">
                <span class="vt-eyebrow">GESTIÓN</span>
                <h2><?php echo $torneo['tipo'] === 'Individual' ? 'Participantes confirmados' : 'Parejas confirmadas'; ?></h2>
            </div>

            <?php if (empty($confirmados)) { ?>

                <div class="vt-card vt-estado-vacio">
                    <i class="bi bi-people"></i>
                    <p>Todavía no hay <?php echo $torneo['tipo'] === 'Individual' ? 'participantes' : 'parejas'; ?> confirmados.</p>
                </div>

            <?php } else { ?>

                <div class="lista-gestion-participantes">

                    <?php foreach ($confirmados as $c) { ?>

                        <div class="tarjeta-gestion-participante">

                            <div>

                                <?php if ($torneo['tipo'] === 'Individual') { ?>

                                    <strong><?php echo htmlspecialchars($c['nombre'] . ' ' . $c['apellido']); ?></strong>
                                    <div class="detalle-invitacion-recibida"><?php echo htmlspecialchars($c['email']); ?></div>

                                <?php } else { ?>

                                    <strong><?php echo htmlspecialchars($c['nombre1'] . ' ' . $c['apellido1']); ?></strong>
                                    <span> + </span>
                                    <strong><?php echo htmlspecialchars($c['nombre2'] . ' ' . $c['apellido2']); ?></strong>

                                <?php } ?>

                            </div>

                            <button
                                type="button"
                                class="btn-cancelar-inscripcion btn-cancelar-gestion"
                                data-id="<?php echo $torneo['tipo'] === 'Individual' ? (int) $c['id_inscripcion'] : (int) $c['id_pareja']; ?>"
                                data-nombre="<?php echo $torneo['tipo'] === 'Individual'
                                    ? htmlspecialchars($c['nombre'] . ' ' . $c['apellido'], ENT_QUOTES, 'UTF-8')
                                    : htmlspecialchars($c['nombre1'] . ' ' . $c['apellido1'] . ' / ' . $c['nombre2'] . ' ' . $c['apellido2'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <i class="bi bi-x-circle"></i>
                                <?php echo $torneo['tipo'] === 'Individual' ? 'Cancelar inscripción' : 'Cancelar pareja'; ?>
                            </button>

                        </div>

                    <?php } ?>

                </div>

            <?php } ?>


            <!-- ========================================= -->
            <!-- OTRAS (informativo) -->
            <!-- ========================================= -->

            <?php if (!empty($otrasInscripciones)) { ?>

                <div class="vt-seccion-titulo" style="margin-top: 28px;">
                    <span class="vt-eyebrow">HISTORIAL</span>
                    <h2>Pendientes y canceladas</h2>
                </div>

                <div class="lista-gestion-participantes">

                    <?php foreach ($otrasInscripciones as $o) { ?>

                        <div class="tarjeta-gestion-participante">

                            <div>
                                <strong><?php echo htmlspecialchars($o['nombre'] . ' ' . $o['apellido']); ?></strong>
                            </div>

                            <span class="estado-mi-inscripcion estado-<?php echo strtolower($o['estado']); ?>">
                                <?php echo htmlspecialchars($o['estado']); ?>
                            </span>

                        </div>

                    <?php } ?>

                </div>

            <?php } ?>

        </main>

    </div>


    <!-- MODAL DE CONFIRMACIÓN -->

    <div class="modal-mensaje-exito" id="modalCancelarParticipante">

        <div class="modal-mensaje-exito-contenido">

            <button type="button" class="modal-mensaje-exito-cerrar" id="cerrarCancelarParticipante">×</button>

            <div class="modal-mensaje-exito-icono">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:32px;"></i>
            </div>

            <h2>¿Cancelar participación?</h2>

            <p id="textoCancelarParticipante">¿Seguro que querés cancelar esta participación?</p>

            <form
                action="<?php echo $torneo['tipo'] === 'Individual' ? 'funciones/cancelar_inscripcion.php' : 'funciones/cancelar_pareja.php'; ?>"
                method="POST"
                id="formCancelarParticipante"
                data-loader="Cancelando..."
            >

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                <input
                    type="hidden"
                    name="<?php echo $torneo['tipo'] === 'Individual' ? 'id_inscripcion' : 'id_pareja'; ?>"
                    id="idElementoCancelar"
                    value=""
                >

                <div class="modal-inscripcion-botones">

                    <button type="button" class="btn-cancelar-inscripcion" id="noCancelarParticipante">
                        No, volver
                    </button>

                    <button type="submit" class="btn-confirmar-inscripcion btn-confirmar-cancelacion">
                        Sí, cancelar
                    </button>

                </div>

            </form>

        </div>

    </div>


    <script src="js/padel.js?v=6"></script>

</body>

</html>
