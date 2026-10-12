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
    header("Location: paneles.php?seccion=torneos&error=sin_permiso");
    exit;
}

$esIndividual = ($torneo['tipo'] === 'Individual');


/* =========================================================
   GRUPOS EXISTENTES + SUS INTEGRANTES
   ========================================================= */

$stmtGrupos = mysqli_prepare(
    $conexion,
    "SELECT id_grupo, nombre, orden FROM grupo WHERE id_torneo = ? ORDER BY orden ASC, id_grupo ASC"
);
mysqli_stmt_bind_param($stmtGrupos, "i", $id_torneo);
mysqli_stmt_execute($stmtGrupos);
$grupos = mysqli_fetch_all(mysqli_stmt_get_result($stmtGrupos), MYSQLI_ASSOC);

foreach ($grupos as &$grupo) {

    if ($esIndividual) {

        $stmtMiembros = mysqli_prepare(
            $conexion,
            "SELECT insc.id_inscripcion, u.nombre, u.apellido, gi.orden
             FROM grupo_inscripcion gi
             INNER JOIN inscripcion insc ON insc.id_inscripcion = gi.id_inscripcion
             INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
             WHERE gi.id_grupo = ? AND insc.estado = 'Confirmada'
             ORDER BY gi.orden ASC, gi.id_grupo_inscripcion ASC"
        );
        mysqli_stmt_bind_param($stmtMiembros, "i", $grupo['id_grupo']);
        mysqli_stmt_execute($stmtMiembros);
        $grupo['miembros'] = mysqli_fetch_all(mysqli_stmt_get_result($stmtMiembros), MYSQLI_ASSOC);

    } else {

        $stmtMiembros = mysqli_prepare(
            $conexion,
            "SELECT p.id_pareja,
                    u1.nombre AS nombre1, u1.apellido AS apellido1,
                    u2.nombre AS nombre2, u2.apellido AS apellido2,
                    gp.orden
             FROM grupo_pareja gp
             INNER JOIN pareja p ON p.id_pareja = gp.id_pareja
             INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
             INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
             INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
             INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
             WHERE gp.id_grupo = ?
             ORDER BY gp.orden ASC, gp.id_grupo_pareja ASC"
        );
        mysqli_stmt_bind_param($stmtMiembros, "i", $grupo['id_grupo']);
        mysqli_stmt_execute($stmtMiembros);
        $grupo['miembros'] = mysqli_fetch_all(mysqli_stmt_get_result($stmtMiembros), MYSQLI_ASSOC);

    }

}
unset($grupo);


/* =========================================================
   CONFIRMADOS SIN ASIGNAR A NINGÚN GRUPO
   ========================================================= */

$sinAsignar = [];

if ($esIndividual) {

    $stmtSinAsignar = mysqli_prepare(
        $conexion,
        "SELECT insc.id_inscripcion, u.nombre, u.apellido
         FROM inscripcion insc
         INNER JOIN usuario u ON u.id_usuario = insc.id_usuario
         LEFT JOIN grupo_inscripcion gi ON gi.id_inscripcion = insc.id_inscripcion
         WHERE insc.id_torneo = ? AND insc.estado = 'Confirmada' AND gi.id_grupo_inscripcion IS NULL
         ORDER BY insc.id_inscripcion ASC"
    );

} else {

    $stmtSinAsignar = mysqli_prepare(
        $conexion,
        "SELECT p.id_pareja,
                u1.nombre AS nombre1, u1.apellido AS apellido1,
                u2.nombre AS nombre2, u2.apellido AS apellido2
         FROM pareja p
         INNER JOIN inscripcion i1 ON i1.id_inscripcion = p.id_inscripcion_1
         INNER JOIN inscripcion i2 ON i2.id_inscripcion = p.id_inscripcion_2
         INNER JOIN usuario u1 ON u1.id_usuario = i1.id_usuario
         INNER JOIN usuario u2 ON u2.id_usuario = i2.id_usuario
         LEFT JOIN grupo_pareja gp ON gp.id_pareja = p.id_pareja
         WHERE p.id_torneo = ? AND gp.id_grupo_pareja IS NULL
         ORDER BY p.id_pareja ASC"
    );

}

mysqli_stmt_bind_param($stmtSinAsignar, "i", $id_torneo);
mysqli_stmt_execute($stmtSinAsignar);
$sinAsignar = mysqli_fetch_all(mysqli_stmt_get_result($stmtSinAsignar), MYSQLI_ASSOC);


$mensajesAccion = [
    'grupo_creado'        => ['tipo' => 'exito', 'texto' => 'El grupo se creó correctamente.'],
    'grupo_renombrado'    => ['tipo' => 'exito', 'texto' => 'El nombre del grupo se actualizó.'],
    'grupo_reordenado'    => ['tipo' => 'exito', 'texto' => 'El orden de los grupos se actualizó.'],
    'grupo_eliminado'     => ['tipo' => 'exito', 'texto' => 'El grupo se eliminó. Los participantes y parejas del torneo no se vieron afectados.'],
    'asignado'            => ['tipo' => 'exito', 'texto' => 'Se asignó correctamente al grupo.'],
    'movido'              => ['tipo' => 'exito', 'texto' => 'Se movió correctamente de grupo.'],
    'quitado'             => ['tipo' => 'exito', 'texto' => 'Se quitó del grupo. Su inscripción al torneo no se vio afectada.'],
    'token'               => ['tipo' => 'error', 'texto' => 'Tu sesión expiró, volvé a intentarlo.'],
    'sin_permiso'         => ['tipo' => 'error', 'texto' => 'No tenés permiso sobre ese torneo.'],
    'no_existe'           => ['tipo' => 'error', 'texto' => 'Ese torneo no existe.'],
    'grupo_no_existe'     => ['tipo' => 'error', 'texto' => 'Ese grupo no existe en este torneo.'],
    'datos_invalidos'     => ['tipo' => 'error', 'texto' => 'Los datos enviados no son válidos.'],
    'modalidad_incompatible' => ['tipo' => 'error', 'texto' => 'No se pueden mezclar inscripciones individuales con parejas.'],
    'elemento_no_disponible' => ['tipo' => 'error', 'texto' => 'Ese participante o pareja no está confirmado en este torneo.'],
    'elemento_no_asignado'   => ['tipo' => 'error', 'texto' => 'Ese participante o pareja no está asignado a ningún grupo de este torneo.'],
    'ya_asignado'         => ['tipo' => 'error', 'texto' => 'Ya está asignado a un grupo. Quitalo primero si querés moverlo.'],
    'error_bd'            => ['tipo' => 'error', 'texto' => 'Ocurrió un error al guardar. Intentá nuevamente.'],
];

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Grupos - <?php echo htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/ver_torneo.css">
    <link rel="stylesheet" href="css/grupos.css">

</head>

<body class="panel-body">

    <div class="panel vt-panel-oscuro">

        <main class="contenido" style="width:100%; padding: 32px;">

            <a href="gestionar_participantes.php?id_torneo=<?php echo (int) $id_torneo; ?>" class="vt-volver">
                <i class="bi bi-arrow-left"></i> Volver a Participantes
            </a>

            <div class="vt-topbar" style="margin-top: 16px;">

                <div>

                    <span class="vt-estado-badge"><?php echo htmlspecialchars($torneo['estado'], ENT_QUOTES, 'UTF-8'); ?></span>

                    <h1>Grupos de <?php echo htmlspecialchars($torneo['nombre'], ENT_QUOTES, 'UTF-8'); ?></h1>

                    <p>
                        <?php echo htmlspecialchars($torneo['tipo'], ENT_QUOTES, 'UTF-8'); ?> ·
                        <?php echo htmlspecialchars($torneo['categoria'], ENT_QUOTES, 'UTF-8'); ?> ·
                        <?php echo htmlspecialchars($torneo['nombre_club'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>

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
            <!-- CREAR GRUPO -->
            <!-- ========================================= -->

            <div class="vt-seccion-titulo" style="margin-top: 24px;">
                <span class="vt-eyebrow">GRUPOS</span>
                <h2>Crear grupo</h2>
            </div>

            <form
                action="funciones/grupos_crear.php"
                method="POST"
                class="grupos-form-crear"
            >
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">

                <input
                    type="text"
                    name="nombre"
                    placeholder="Ej: Grupo A"
                    maxlength="50"
                    required
                >

                <button type="submit" class="btn-torneo editar">
                    <i class="bi bi-plus-lg"></i>
                    <span>Crear</span>
                </button>
            </form>


            <!-- ========================================= -->
            <!-- LISTADO DE GRUPOS -->
            <!-- ========================================= -->

            <div class="vt-seccion-titulo" style="margin-top: 28px;">
                <span class="vt-eyebrow">DISTRIBUCIÓN</span>
                <h2>Grupos del torneo</h2>
            </div>

            <?php if (empty($grupos)) { ?>

                <div class="vt-card vt-estado-vacio">
                    <i class="bi bi-diagram-3"></i>
                    <p>Todavía no creaste ningún grupo para este torneo.</p>
                </div>

            <?php } else { ?>

                <div class="grupos-grid">

                    <?php foreach ($grupos as $indiceGrupo => $grupo) { ?>

                        <div class="grupos-tarjeta">

                            <div class="grupos-tarjeta-header">

                                <form action="funciones/grupos_renombrar.php" method="POST" class="grupos-form-renombrar">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                    <input type="hidden" name="id_grupo" value="<?php echo (int) $grupo['id_grupo']; ?>">

                                    <input
                                        type="text"
                                        name="nombre"
                                        value="<?php echo htmlspecialchars($grupo['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                        maxlength="50"
                                        required
                                    >

                                    <button type="submit" title="Guardar nombre">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>

                                <div class="grupos-tarjeta-acciones">

                                    <form action="funciones/grupos_reordenar.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                        <input type="hidden" name="id_grupo" value="<?php echo (int) $grupo['id_grupo']; ?>">
                                        <input type="hidden" name="direccion" value="subir">
                                        <button type="submit" title="Subir" <?php echo $indiceGrupo === 0 ? 'disabled' : ''; ?>>
                                            <i class="bi bi-arrow-up"></i>
                                        </button>
                                    </form>

                                    <form action="funciones/grupos_reordenar.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                        <input type="hidden" name="id_grupo" value="<?php echo (int) $grupo['id_grupo']; ?>">
                                        <input type="hidden" name="direccion" value="bajar">
                                        <button type="submit" title="Bajar" <?php echo $indiceGrupo === count($grupos) - 1 ? 'disabled' : ''; ?>>
                                            <i class="bi bi-arrow-down"></i>
                                        </button>
                                    </form>

                                    <button
                                        type="button"
                                        class="btn-eliminar-grupo"
                                        data-id="<?php echo (int) $grupo['id_grupo']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($grupo['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                        title="Eliminar grupo"
                                    >
                                        <i class="bi bi-trash-fill"></i>
                                    </button>

                                </div>

                            </div>

                            <?php if (empty($grupo['miembros'])) { ?>

                                <p class="grupos-tarjeta-vacio">Todavía no hay nadie en este grupo.</p>

                            <?php } else { ?>

                                <ul class="grupos-miembros">

                                    <?php foreach ($grupo['miembros'] as $miembro) {

                                        $idElemento = $esIndividual ? (int) $miembro['id_inscripcion'] : (int) $miembro['id_pareja'];
                                        $nombreMiembro = $esIndividual
                                            ? trim($miembro['nombre'] . ' ' . $miembro['apellido'])
                                            : trim($miembro['nombre1'] . ' ' . $miembro['apellido1']) . ' / ' . trim($miembro['nombre2'] . ' ' . $miembro['apellido2']);
                                    ?>

                                        <li class="grupos-miembro">

                                            <span><?php echo htmlspecialchars($nombreMiembro, ENT_QUOTES, 'UTF-8'); ?></span>

                                            <div class="grupos-miembro-acciones">

                                                <?php if (count($grupos) > 1) { ?>

                                                    <form action="funciones/grupos_mover.php" method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                        <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                                        <input type="hidden" name="tipo" value="<?php echo $esIndividual ? 'inscripcion' : 'pareja'; ?>">
                                                        <input type="hidden" name="id_elemento" value="<?php echo $idElemento; ?>">

                                                        <select name="id_grupo_destino">
                                                            <?php foreach ($grupos as $otroGrupo) {
                                                                if ((int) $otroGrupo['id_grupo'] === (int) $grupo['id_grupo']) {
                                                                    continue;
                                                                }
                                                            ?>
                                                                <option value="<?php echo (int) $otroGrupo['id_grupo']; ?>">
                                                                    <?php echo htmlspecialchars($otroGrupo['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                                                </option>
                                                            <?php } ?>
                                                        </select>

                                                        <button type="submit" title="Mover a otro grupo">
                                                            <i class="bi bi-arrow-left-right"></i>
                                                        </button>
                                                    </form>

                                                <?php } ?>

                                                <form action="funciones/grupos_quitar.php" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                                    <input type="hidden" name="tipo" value="<?php echo $esIndividual ? 'inscripcion' : 'pareja'; ?>">
                                                    <input type="hidden" name="id_elemento" value="<?php echo $idElemento; ?>">

                                                    <button type="submit" title="Quitar del grupo">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>

                                            </div>

                                        </li>

                                    <?php } ?>

                                </ul>

                            <?php } ?>

                        </div>

                    <?php } ?>

                </div>

            <?php } ?>


            <!-- ========================================= -->
            <!-- SIN ASIGNAR -->
            <!-- ========================================= -->

            <div class="vt-seccion-titulo" style="margin-top: 28px;">
                <span class="vt-eyebrow"><?php echo $esIndividual ? 'PARTICIPANTES' : 'PAREJAS'; ?></span>
                <h2>Confirmados sin asignar</h2>
            </div>

            <?php if (empty($sinAsignar)) { ?>

                <div class="vt-card vt-estado-vacio">
                    <i class="bi bi-people"></i>
                    <p>
                        <?php echo empty($grupos)
                            ? 'Todavía no hay ' . ($esIndividual ? 'participantes' : 'parejas') . ' confirmados en este torneo.'
                            : 'Todos los confirmados ya están asignados a un grupo.'; ?>
                    </p>
                </div>

            <?php } else { ?>

                <ul class="grupos-miembros grupos-sin-asignar">

                    <?php foreach ($sinAsignar as $elemento) {

                        $idElemento = $esIndividual ? (int) $elemento['id_inscripcion'] : (int) $elemento['id_pareja'];
                        $nombreElemento = $esIndividual
                            ? trim($elemento['nombre'] . ' ' . $elemento['apellido'])
                            : trim($elemento['nombre1'] . ' ' . $elemento['apellido1']) . ' / ' . trim($elemento['nombre2'] . ' ' . $elemento['apellido2']);
                    ?>

                        <li class="grupos-miembro">

                            <span><?php echo htmlspecialchars($nombreElemento, ENT_QUOTES, 'UTF-8'); ?></span>

                            <?php if (!empty($grupos)) { ?>

                                <form action="funciones/grupos_asignar.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                                    <input type="hidden" name="tipo" value="<?php echo $esIndividual ? 'inscripcion' : 'pareja'; ?>">
                                    <input type="hidden" name="id_elemento" value="<?php echo $idElemento; ?>">

                                    <select name="id_grupo" required>
                                        <option value="">Asignar a...</option>
                                        <?php foreach ($grupos as $grupoDisponible) { ?>
                                            <option value="<?php echo (int) $grupoDisponible['id_grupo']; ?>">
                                                <?php echo htmlspecialchars($grupoDisponible['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php } ?>
                                    </select>

                                    <button type="submit" title="Asignar al grupo">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </form>

                            <?php } ?>

                        </li>

                    <?php } ?>

                </ul>

            <?php } ?>

        </main>

    </div>


    <!-- MODAL ELIMINAR GRUPO -->

    <div class="modal-mensaje-exito" id="modalEliminarGrupo">

        <div class="modal-mensaje-exito-contenido">

            <button type="button" class="modal-mensaje-exito-cerrar" id="cerrarEliminarGrupo">×</button>

            <div class="modal-mensaje-exito-icono">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:32px;"></i>
            </div>

            <h2>¿Eliminar grupo?</h2>

            <p id="textoEliminarGrupo">¿Seguro que querés eliminar este grupo?</p>

            <form
                action="funciones/grupos_eliminar.php"
                method="POST"
                id="formEliminarGrupo"
            >

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="id_torneo" value="<?php echo (int) $id_torneo; ?>">
                <input type="hidden" name="id_grupo" id="idGrupoEliminar" value="">

                <div class="modal-inscripcion-botones">

                    <button type="button" class="btn-cancelar-inscripcion" id="noEliminarGrupo">
                        No, volver
                    </button>

                    <button type="submit" class="btn-confirmar-inscripcion btn-confirmar-cancelacion">
                        Sí, eliminar
                    </button>

                </div>

            </form>

        </div>

    </div>


    <script>

        document.addEventListener("DOMContentLoaded", function () {

            var botonesEliminar = document.querySelectorAll(".btn-eliminar-grupo");

            if (!botonesEliminar.length) {
                return;
            }

            var modal = document.getElementById("modalEliminarGrupo");
            var texto = document.getElementById("textoEliminarGrupo");
            var idOculto = document.getElementById("idGrupoEliminar");
            var cerrar = document.getElementById("cerrarEliminarGrupo");
            var noEliminar = document.getElementById("noEliminarGrupo");

            function cerrarModalEliminar() {
                modal.classList.remove("mostrar");
                document.documentElement.style.overflow = "";
                document.body.style.overflow = "";
            }

            botonesEliminar.forEach(function (boton) {

                boton.addEventListener("click", function () {

                    var nombre = boton.dataset.nombre || "este grupo";

                    texto.textContent = "¿Seguro que querés eliminar \"" + nombre + "\"? Los participantes y parejas del torneo no se verán afectados.";

                    idOculto.value = boton.dataset.id || "";

                    modal.classList.add("mostrar");

                    document.documentElement.style.overflow = "hidden";
                    document.body.style.overflow = "hidden";

                });

            });

            if (cerrar) {
                cerrar.addEventListener("click", cerrarModalEliminar);
            }

            if (noEliminar) {
                noEliminar.addEventListener("click", cerrarModalEliminar);
            }

        });

    </script>

    <script src="js/padel.js?v=6"></script>

</body>

</html>
