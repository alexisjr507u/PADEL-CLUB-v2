<?php

include("config/no_cache.php");
include("config/conexion.php");
require_once("funciones/csrf.php");
require_once("funciones/descalificacion.php");
require_once("funciones/retiro.php");


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
        "SELECT insc.id_inscripcion, insc.id_usuario, u.nombre, u.apellido, u.email
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
        "SELECT p.id_pareja, i1.id_usuario AS id_usuario1,
                u1.nombre AS nombre1, u1.apellido AS apellido1, u1.email AS email1,
                u2.nombre AS nombre2, u2.apellido AS apellido2, u2.email AS email2
         FROM pareja p
         INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
         INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
         INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
         INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
         WHERE p.id_torneo = ? AND i1.estado = 'Confirmada' AND i2.estado = 'Confirmada'
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
     WHERE insc.id_torneo = ? AND insc.estado NOT IN ('Confirmada', 'Descalificada', 'Retirada')
       AND NOT (insc.estado = 'Pendiente' AND ? = 'Individual')
     ORDER BY insc.id_inscripcion ASC"
);
mysqli_stmt_bind_param($stmtOtros, "is", $id_torneo, $torneo['tipo']);
mysqli_stmt_execute($stmtOtros);
$otrasInscripciones = mysqli_fetch_all(mysqli_stmt_get_result($stmtOtros), MYSQLI_ASSOC);


/* =========================================================
   DESCALIFICADOS (con quién, cuándo, por qué y los partidos que jugaron)
   ========================================================= */

$descalificados = [];

if ($torneo['tipo'] === 'Individual') {

    $stmtDesc = mysqli_prepare(
        $conexion,
        "SELECT insc.id_inscripcion AS clave, u.id_usuario AS id1, NULL AS id2,
                CONCAT(u.nombre, ' ', u.apellido) AS nombre,
                insc.motivo_descalificacion AS motivo, insc.fecha_descalificacion AS fecha,
                CONCAT(d.nombre, ' ', d.apellido) AS descalificador
         FROM inscripcion insc
         INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
         LEFT JOIN usuario d ON d.id_usuario = insc.id_descalificador
         WHERE insc.id_torneo = ? AND insc.estado = 'Descalificada'
         ORDER BY insc.fecha_descalificacion DESC, insc.id_inscripcion DESC"
    );

} else {

    $stmtDesc = mysqli_prepare(
        $conexion,
        "SELECT p.id_pareja AS clave, u1.id_usuario AS id1, u2.id_usuario AS id2,
                CONCAT(u1.nombre, ' ', u1.apellido, ' / ', u2.nombre, ' ', u2.apellido) AS nombre,
                i1.motivo_descalificacion AS motivo, i1.fecha_descalificacion AS fecha,
                CONCAT(d.nombre, ' ', d.apellido) AS descalificador
         FROM pareja p
         INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
         INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
         INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
         INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
         LEFT JOIN usuario d ON d.id_usuario = i1.id_descalificador
         WHERE p.id_torneo = ? AND i1.estado = 'Descalificada' AND i2.estado = 'Descalificada'
         ORDER BY i1.fecha_descalificacion DESC, p.id_pareja DESC"
    );

}

mysqli_stmt_bind_param($stmtDesc, "i", $id_torneo);
mysqli_stmt_execute($stmtDesc);

foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmtDesc), MYSQLI_ASSOC) as $d) {

    $ids = array_values(array_filter([(int) $d['id1'], $d['id2'] !== null ? (int) $d['id2'] : null]));
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmtPts = mysqli_prepare(
        $conexion,
        "SELECT COALESCE(SUM(puntos_grupos + puntos_cruces), 0) AS pts FROM ranking_puntos
         WHERE id_torneo = ? AND anulado = 1 AND id_usuario IN ($marcas)"
    );
    $paramsPts = array_merge([$id_torneo], $ids);
    mysqli_stmt_bind_param($stmtPts, str_repeat('i', count($paramsPts)), ...$paramsPts);
    mysqli_stmt_execute($stmtPts);
    $d['puntos_anulados'] = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPts))['pts'];

    $d['partidos'] = partidosDeDescalificado(
        $conexion, $id_torneo, (int) $d['clave'], $torneo['tipo'] === 'Individual', $d['fecha']
    );

    $descalificados[] = $d;

}


/* =========================================================
   QUÉ SE PUEDE HACER SEGÚN EL ESTADO DEL TORNEO (el servidor lo vuelve a comprobar al ejecutar)
   - Abierto / Cerrado (sin empezar): «Retirar participante». No es una descalificación.
   - En curso: «Descalificar participante».
   - Finalizado: ninguna acción; solo consulta.
   ========================================================= */

$accionPermitida = null;

if (in_array($torneo['estado'], ['Abierto', 'Cerrado'], true)) {
    $accionPermitida = 'retirar';
} elseif ($torneo['estado'] === 'En curso') {
    $accionPermitida = 'descalificar';
}

$esIndividualTorneo = ($torneo['tipo'] === 'Individual');


/* Retirados (quién, cuándo y por qué). Se listan por persona: al retirar una pareja se elimina su registro de pareja. */

$stmtRetirados = mysqli_prepare(
    $conexion,
    "SELECT CONCAT(u.nombre, ' ', u.apellido) AS nombre, insc.motivo_retiro AS motivo, insc.fecha_retiro AS fecha,
            CONCAT(r.nombre, ' ', r.apellido) AS retirador
     FROM inscripcion insc
     INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
     LEFT JOIN usuario r ON r.id_usuario = insc.id_retirador
     WHERE insc.id_torneo = ? AND insc.estado = 'Retirada'
     ORDER BY insc.fecha_retiro DESC, insc.id_inscripcion DESC"
);
mysqli_stmt_bind_param($stmtRetirados, "i", $id_torneo);
mysqli_stmt_execute($stmtRetirados);
$retirados = mysqli_fetch_all(mysqli_stmt_get_result($stmtRetirados), MYSQLI_ASSOC);


/* Torneo finalizado: los participantes se muestran solo para consulta, con sus partidos y puntos. */

$puntosPorUsuario = [];

if ($torneo['estado'] === 'Finalizado') {

    $stmtPuntosTorneo = mysqli_prepare(
        $conexion,
        "SELECT id_usuario, puntos_grupos + puntos_cruces AS pts FROM ranking_puntos WHERE id_torneo = ? AND anulado = 0"
    );
    mysqli_stmt_bind_param($stmtPuntosTorneo, "i", $id_torneo);
    mysqli_stmt_execute($stmtPuntosTorneo);

    foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmtPuntosTorneo), MYSQLI_ASSOC) as $fp) {
        $puntosPorUsuario[(int) $fp['id_usuario']] = (int) $fp['pts'];
    }

}


$mensajesAccion = [
    'retirado'         => ['tipo' => 'exito', 'texto' => 'Se retiró al participante: ya no ocupa cupo y puede volver a inscribirse mientras el torneo esté abierto. No se generó ningún punto ni se tocó el ranking.'],
    'ya_retirado'      => ['tipo' => 'error', 'texto' => 'Ese participante ya estaba retirado.'],
    'estado_no_permite' => ['tipo' => 'error', 'texto' => 'Esa acción no está disponible con el torneo en este estado: se retira con el torneo Abierto y se descalifica con el torneo En curso. Con el torneo Finalizado no se puede modificar a los participantes.'],
    'tiene_partidos_jugados' => ['tipo' => 'error', 'texto' => 'No se puede retirar: ya tiene partidos jugados o puntos en este torneo. Si el torneo ya empezó, corresponde descalificar (con el torneo En curso).'],
    'descalificado'    => ['tipo' => 'exito', 'texto' => 'Se descalificó correctamente. No recibirá puntos de este torneo en ningún ranking; sus partidos jugados se conservan.'],
    'descalificado_ascenso' => ['tipo' => 'exito', 'texto' => 'Se descalificó correctamente y sus puntos de este torneo se anularon. Atención: esos puntos le habían dado un ascenso de categoría, que NO se revierte solo: si corresponde, corregilo con un cambio administrativo de categoría.'],
    'ya_descalificado' => ['tipo' => 'error', 'texto' => 'Ese participante ya estaba descalificado.'],
    'motivo_largo'     => ['tipo' => 'error', 'texto' => 'El motivo es demasiado largo (máximo ' . DESCALIFICACION_MOTIVO_MAX . ' caracteres).'],
    'token'            => ['tipo' => 'error', 'texto' => 'Tu sesión expiró, volvé a intentarlo.'],
    'sin_permiso'      => ['tipo' => 'error', 'texto' => 'No tenés permiso sobre ese torneo.'],
    'no_existe'        => ['tipo' => 'error', 'texto' => 'Ese registro no existe o ya no está confirmado.'],
    'error_bd'         => ['tipo' => 'error', 'texto' => 'Ocurrió un error al guardar la decisión. Intentá nuevamente.'],
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
    <link rel="stylesheet" href="css/ver_torneo.css?v=15">
    <link rel="stylesheet" href="css/dashboard.css?v=7">
    <link rel="stylesheet" href="css/descalificacion.css?v=2">
    <link rel="stylesheet" href="css/confirmar.css?v=1">

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
                    <?php if ($_GET['accion'] === 'retirado' && (int) ($_GET['pe'] ?? 0) > 0) {
                        $pe = (int) $_GET['pe'];
                        echo htmlspecialchars(' Se quitó de su grupo y se eliminaron ' . $pe . ' partido' . ($pe === 1 ? '' : 's') . ' sin jugar: conviene regenerar los partidos del grupo.');
                    } ?>
                    <?php if (in_array($_GET['accion'], ['descalificado', 'descalificado_ascenso'], true)) {
                        $ptsAnulados = (int) ($_GET['pts'] ?? 0);
                        $partidosWo = (int) ($_GET['wo'] ?? 0);
                        if ($ptsAnulados > 0) { echo htmlspecialchars(' Puntos anulados de este torneo: ' . $ptsAnulados . '.'); }
                        if ($partidosWo > 0) { echo htmlspecialchars(' Partidos pendientes dados por ganados (W.O.) a sus rivales: ' . $partidosWo . '.'); }
                    } ?>
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

                                <?php if ($torneo['estado'] === 'Finalizado') {
                                    // Solo consulta: sin botones. Se muestran los puntos y los partidos que disputó.
                                    $claveConsulta = $esIndividualTorneo ? (int) $c['id_inscripcion'] : (int) $c['id_pareja'];
                                    $partidosConsulta = partidosDeDescalificado($conexion, $id_torneo, $claveConsulta, $esIndividualTorneo, null);
                                    $puntosConsulta = $esIndividualTorneo
                                        ? ($puntosPorUsuario[(int) $c['id_usuario']] ?? 0)
                                        : ($puntosPorUsuario[(int) $c['id_usuario1']] ?? 0);
                                ?>
                                    <div class="detalle-invitacion-recibida">Puntos de este torneo: <strong><?php echo (int) $puntosConsulta; ?></strong></div>
                                    <details class="descalificado-partidos">
                                        <summary>Partidos disputados (<?php echo count($partidosConsulta); ?>)</summary>
                                        <?php if (empty($partidosConsulta)) { ?>
                                            <p class="detalle-invitacion-recibida">No tiene partidos registrados.</p>
                                        <?php } else { ?>
                                            <ul>
                                                <?php foreach ($partidosConsulta as $pc) { ?>
                                                    <li>
                                                        <span class="descalificado-fase"><?php echo htmlspecialchars($pc['fase']); ?></span>
                                                        vs <?php echo htmlspecialchars($pc['rival']); ?> —
                                                        <strong><?php echo htmlspecialchars($pc['resultado']); ?></strong>
                                                        <?php if ($pc['wo']) { ?>(W.O.)<?php } elseif ($pc['marcador'] !== '') { ?>(<?php echo htmlspecialchars($pc['marcador']); ?>)<?php } ?>
                                                    </li>
                                                <?php } ?>
                                            </ul>
                                        <?php } ?>
                                    </details>
                                <?php } ?>

                            </div>

                            <?php if ($accionPermitida !== null) { ?>
                            <?php // Retirar con el torneo Abierto/Cerrado; descalificar con el torneo En curso; nada si está Finalizado. ?>
                            <button
                                type="button"
                                class="btn-cancelar-inscripcion btn-cancelar-gestion"
                                data-verbo="<?php echo $accionPermitida === 'retirar' ? 'retirar' : 'descalificar'; ?>"
                                data-id="<?php echo $torneo['tipo'] === 'Individual' ? (int) $c['id_inscripcion'] : (int) $c['id_pareja']; ?>"
                                data-nombre="<?php echo $torneo['tipo'] === 'Individual'
                                    ? htmlspecialchars($c['nombre'] . ' ' . $c['apellido'], ENT_QUOTES, 'UTF-8')
                                    : htmlspecialchars($c['nombre1'] . ' ' . $c['apellido1'] . ' / ' . $c['nombre2'] . ' ' . $c['apellido2'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <i class="bi <?php echo $accionPermitida === 'retirar' ? 'bi-box-arrow-right' : 'bi-person-x-fill'; ?>"></i>
                                <?php
                                if ($accionPermitida === 'retirar') {
                                    echo $torneo['tipo'] === 'Individual' ? 'Retirar participante' : 'Retirar pareja';
                                } else {
                                    echo $torneo['tipo'] === 'Individual' ? 'Descalificar participante' : 'Descalificar pareja';
                                }
                                ?>
                            </button>
                            <?php } ?>

                        </div>

                    <?php } ?>

                </div>

            <?php } ?>


            <!-- ========================================= -->
            <!-- DESCALIFICADOS -->
            <!-- ========================================= -->

            <?php if (!empty($descalificados)) { ?>

                <div class="vt-seccion-titulo" style="margin-top: 28px;">
                    <span class="vt-eyebrow">HISTORIAL</span>
                    <h2>Descalificados</h2>
                </div>

                <div class="lista-gestion-participantes">

                    <?php foreach ($descalificados as $d) { ?>

                        <div class="tarjeta-gestion-participante tarjeta-descalificado">

                            <div class="descalificado-datos">

                                <strong><?php echo htmlspecialchars($d['nombre']); ?></strong>

                                <div class="detalle-invitacion-recibida">
                                    Descalificado
                                    <?php if ($d['fecha']) { ?>el <?php echo date('d/m/Y H:i', strtotime($d['fecha'])); ?><?php } ?>
                                    <?php if (trim((string) $d['descalificador']) !== '') { ?>por <?php echo htmlspecialchars(trim($d['descalificador'])); ?><?php } ?>
                                </div>

                                <div class="detalle-invitacion-recibida">
                                    Motivo: <?php echo ($d['motivo'] !== null && $d['motivo'] !== '') ? htmlspecialchars($d['motivo']) : 'sin motivo indicado'; ?>
                                </div>

                                <div class="detalle-invitacion-recibida">
                                    Puntos de este torneo: <strong>0</strong>
                                    <?php if ($d['puntos_anulados'] > 0) { ?>(se anularon <?php echo (int) $d['puntos_anulados']; ?>)<?php } ?>
                                </div>

                                <details class="descalificado-partidos">
                                    <summary>Partidos disputados (<?php echo count($d['partidos']); ?>)</summary>

                                    <?php if (empty($d['partidos'])) { ?>
                                        <p class="detalle-invitacion-recibida">No tenía partidos registrados.</p>
                                    <?php } else { ?>
                                        <ul>
                                            <?php foreach ($d['partidos'] as $p) { ?>
                                                <li>
                                                    <span class="descalificado-fase"><?php echo htmlspecialchars($p['fase']); ?></span>
                                                    vs <?php echo htmlspecialchars($p['rival']); ?> —
                                                    <strong><?php echo htmlspecialchars($p['resultado']); ?></strong>
                                                    <?php if ($p['por_descalificacion']) { ?>
                                                        (W.O. por la descalificación: no lo jugó)
                                                    <?php } elseif ($p['wo']) { ?>
                                                        (W.O.)
                                                    <?php } elseif ($p['marcador'] !== '') { ?>
                                                        (<?php echo htmlspecialchars($p['marcador']); ?>)
                                                    <?php } ?>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                </details>

                            </div>

                            <span class="estado-mi-inscripcion estado-descalificada">Descalificado</span>

                        </div>

                    <?php } ?>

                </div>

            <?php } ?>


            <!-- ========================================= -->
            <!-- RETIRADOS -->
            <!-- ========================================= -->

            <?php if (!empty($retirados)) { ?>

                <div class="vt-seccion-titulo" style="margin-top: 28px;">
                    <span class="vt-eyebrow">HISTORIAL</span>
                    <h2>Retirados</h2>
                </div>

                <div class="lista-gestion-participantes">

                    <?php foreach ($retirados as $rt) { ?>

                        <div class="tarjeta-gestion-participante tarjeta-descalificado">

                            <div class="descalificado-datos">

                                <strong><?php echo htmlspecialchars($rt['nombre']); ?></strong>

                                <div class="detalle-invitacion-recibida">
                                    Retirado
                                    <?php if ($rt['fecha']) { ?>el <?php echo date('d/m/Y H:i', strtotime($rt['fecha'])); ?><?php } ?>
                                    <?php if (trim((string) $rt['retirador']) !== '') { ?>por <?php echo htmlspecialchars(trim($rt['retirador'])); ?><?php } ?>
                                </div>

                                <div class="detalle-invitacion-recibida">
                                    Motivo: <?php echo ($rt['motivo'] !== null && $rt['motivo'] !== '') ? htmlspecialchars($rt['motivo']) : 'sin motivo indicado'; ?>
                                </div>

                            </div>

                            <span class="estado-mi-inscripcion estado-retirada">Retirado</span>

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


    <?php if ($accionPermitida !== null) { ?>

    <!-- MODAL DE CONFIRMACIÓN (retirar o descalificar, según el estado del torneo) -->

    <?php $esRetiro = ($accionPermitida === 'retirar'); ?>

    <div class="modal-mensaje-exito" id="modalCancelarParticipante">

        <div class="modal-mensaje-exito-contenido">

            <button type="button" class="modal-mensaje-exito-cerrar" id="cerrarCancelarParticipante">×</button>

            <div class="modal-mensaje-exito-icono">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:32px;"></i>
            </div>

            <h2><?php echo $esRetiro ? '¿Retirar participante?' : '¿Descalificar participante?'; ?></h2>

            <p id="textoCancelarParticipante">¿Seguro que querés <?php echo $esRetiro ? 'retirar' : 'descalificar'; ?> esta participación?</p>

            <?php if ($esRetiro) { ?>

            <ul class="descalificar-consecuencias">
                <li>Deja de ocupar un <strong>cupo</strong> del torneo y <strong>puede volver a inscribirse</strong> mientras esté abierto.</li>
                <li><strong>No es una descalificación:</strong> no genera ni quita puntos y no afecta ningún ranking.</li>
                <li>Si ya estaba en un grupo, se lo quita y se eliminan sus partidos sin jugar (después conviene regenerar los partidos del grupo).</li>
                <?php if (!$esIndividualTorneo) { ?>
                    <li>Se retira a la <strong>pareja completa</strong> (los dos integrantes).</li>
                <?php } ?>
            </ul>

            <?php } else { ?>

            <ul class="descalificar-consecuencias">
                <li>No recibirá <strong>ningún punto de este torneo</strong>, ni en el ranking de su categoría ni en el general, hasta donde haya llegado.</li>
                <li>Sus puntos de <strong>otros torneos no se modifican</strong>.</li>
                <li>Sus partidos ya jugados se conservan y no cambian los resultados de los demás.</li>
                <li>No pasará de fase y sus partidos pendientes se darán por ganados (W.O.) a sus rivales.</li>
                <?php if (!$esIndividualTorneo) { ?>
                    <li>Se descalifica a la <strong>pareja completa</strong> (los dos integrantes).</li>
                <?php } ?>
            </ul>

            <?php } ?>

            <form
                action="<?php echo $esRetiro ? 'funciones/retirar_participante.php' : 'funciones/descalificar_participante.php'; ?>"
                method="POST"
                id="formCancelarParticipante"
                data-loader="<?php echo $esRetiro ? 'Retirando...' : 'Descalificando...'; ?>"
            >

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                <input
                    type="hidden"
                    name="<?php echo $torneo['tipo'] === 'Individual' ? 'id_inscripcion' : 'id_pareja'; ?>"
                    id="idElementoCancelar"
                    value=""
                >

                <label class="descalificar-etiqueta" for="motivoDescalificacion">Motivo (opcional)</label>
                <textarea
                    name="motivo"
                    id="motivoDescalificacion"
                    class="descalificar-motivo"
                    maxlength="<?php echo DESCALIFICACION_MOTIVO_MAX; ?>"
                    rows="3"
                    placeholder="<?php echo $esRetiro ? 'Ej.: pidió retirarse, inscripción duplicada...' : 'Ej.: no se presentó, conducta antideportiva...'; ?>"
                ></textarea>

                <div class="modal-inscripcion-botones">

                    <button type="button" class="btn-cancelar-inscripcion" id="noCancelarParticipante">
                        No, volver
                    </button>

                    <button type="submit" class="btn-confirmar-inscripcion btn-confirmar-cancelacion">
                        <?php echo $esRetiro ? 'Sí, retirar' : 'Sí, descalificar'; ?>
                    </button>

                </div>

            </form>

        </div>

    </div>

    <?php } ?>

    <script src="js/padel.js?v=17"></script>


</body>

</html>
