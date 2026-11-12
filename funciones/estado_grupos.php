<?php

/* =========================================================
   QUÉ SE PUEDE HACER CON LOS GRUPOS SEGÚN EL ESTADO DEL TORNEO

   El estado se lee SIEMPRE de la base (con el torneo ya bloqueado por quien llama), nunca del
   navegador. Lo usan todos los endpoints de funciones/grupos_*.php y la pantalla gestionar_grupos.php.

   - Abierto / Cerrado (todavía no empezó): se pueden crear grupos, asignar y mover participantes,
     reordenar, borrar y generar los partidos de fase de grupos (como siempre).
   - En curso:
       · Si ya se generaron los partidos de fase de grupos: NO se pueden crear grupos ni cambiar la
         distribución (asignar, mover, quitar, borrar, reordenar), y los partidos de grupos no se
         generan de nuevo (nunca se borra nada). Siguen disponibles: cargar y corregir resultados,
         generar las fases siguientes (cruces) y renombrar un grupo.
       · Si todavía no se generaron esos partidos, la preparación sigue siendo posible.
   - Finalizado: solo consulta. Nada de grupos, partidos, resultados ni rondas se puede modificar.

   «Grupos generados» = el torneo tiene al menos un partido de fase de grupos.
   ========================================================= */

const ESTADOS_ANTES_DE_EMPEZAR = ['Abierto', 'Cerrado'];


/** Estado actual del torneo (de la base). null si no existe. */
function estadoActualDeTorneo($conexion, $idTorneo) {

    $idTorneo = (int) $idTorneo;
    $stmt = mysqli_prepare($conexion, "SELECT estado FROM torneos WHERE id_torneo = ?");
    mysqli_stmt_bind_param($stmt, "i", $idTorneo);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return $fila ? $fila['estado'] : null;

}


/** ¿El torneo ya tiene partidos de fase de grupos generados (de cualquier estado)? */
function tienePartidosDeGrupos($conexion, $idTorneo) {

    $idTorneo = (int) $idTorneo;
    $stmt = mysqli_prepare(
        $conexion,
        "SELECT (SELECT COUNT(*) FROM partido_individual p INNER JOIN grupo g ON g.id_grupo = p.id_grupo
                 WHERE g.id_torneo = ? AND p.fase = 'Grupos')
              + (SELECT COUNT(*) FROM partido_pareja p INNER JOIN grupo g ON g.id_grupo = p.id_grupo
                 WHERE g.id_torneo = ? AND p.fase = 'Grupos') AS n"
    );
    mysqli_stmt_bind_param($stmt, "ii", $idTorneo, $idTorneo);
    mysqli_stmt_execute($stmt);

    return (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'] > 0;

}


/**
 * Código de error si el estado del torneo NO permite la acción, o null si se puede.
 *
 * Acciones: 'estructura' (crear grupos, asignar, mover, quitar, borrar, reordenar), 'renombrar',
 *           'partidos' (generar partidos de fase de grupos), 'resultados', 'rondas' (cruces).
 */
function bloqueoDeGruposPorEstado($estado, $hayPartidosDeGrupos, $accion) {

    if ($estado === 'Finalizado') {
        return 'torneo_finalizado_grupos';
    }

    if ($estado === 'En curso' && $hayPartidosDeGrupos && $accion === 'estructura') {
        return 'grupos_ya_generados';
    }

    // 'partidos' en un torneo En curso lo resuelve cada endpoint (nunca se regenera ni se borra nada).
    return null;

}


/**
 * Para los endpoints: lanza una excepción con el código de error si el estado actual del torneo
 * (leído de la base) no permite la acción. Si se puede, devuelve el estado.
 */
function exigirEstadoParaGrupos($conexion, $idTorneo, $accion) {

    $estado = estadoActualDeTorneo($conexion, $idTorneo);

    if ($estado === null) {
        throw new Exception('no_existe');
    }

    $hay = ($accion === 'estructura' && $estado === 'En curso') ? tienePartidosDeGrupos($conexion, $idTorneo) : false;
    $bloqueo = bloqueoDeGruposPorEstado($estado, $hay, $accion);

    if ($bloqueo !== null) {
        throw new Exception($bloqueo);
    }

    return $estado;

}
