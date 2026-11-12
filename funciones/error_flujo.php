<?php

/* =========================================================
   MANEJO SEGURO DE ERRORES DE LOS FLUJOS DE INSCRIPCIÓN E INVITACIONES

   Problema que resuelve: mysqli lanza una excepción ante cualquier error de SQL (PHP 8.1+) y los flujos hacían
   `error=` + urlencode($e->getMessage()) en la redirección. Así el texto de MySQL (tablas, índices, constraints,
   nombres de la base, textos de triggers) terminaba en la URL, el usuario no veía ningún mensaje y el error real
   no quedaba registrado.

   Regla: al navegador solo viajan CÓDIGOS PROPIOS de una lista blanca. Todo lo demás se registra completo
   (error_log) y se traduce a un código genérico.
   ========================================================= */

/**
 * Convierte cualquier Throwable en un código seguro para usar en la URL.
 *
 *  - Excepción PROPIA del flujo (no es de MySQL) cuyo mensaje está en $permitidos: se devuelve tal cual.
 *  - Error de MySQL 1213 (interbloqueo) o 1205 (tiempo de espera de bloqueo): 'conflicto_temporal' (reintentar).
 *  - Cualquier otro error (SQL, Error de PHP, excepción con un mensaje que no está permitido): 'error_bd'.
 *
 * Todo lo que no sea una excepción propia permitida se registra con error_log, con el contexto, el código SQL
 * (errno / SQLSTATE) cuando existe, y el archivo y la línea. El mensaje original NUNCA se devuelve.
 *
 * @param Throwable $e          lo capturado
 * @param string[]  $permitidos códigos propios que este flujo puede mostrar (lista blanca)
 * @param string    $contexto   texto corto para el registro, p. ej. 'inscribir_torneo torneo=5 usuario=9'
 */
function codigoDeErrorSeguro(Throwable $e, array $permitidos, string $contexto): string {

    if ($e instanceof mysqli_sql_exception) {

        $errno = (int) $e->getCode();

        error_log(
            "[$contexto] Error SQL $errno (SQLSTATE " . $e->getSqlState() . "): " . $e->getMessage()
            . ' en ' . basename($e->getFile()) . ':' . $e->getLine()
        );

        return ($errno === 1213 || $errno === 1205) ? 'conflicto_temporal' : 'error_bd';

    }

    if ($e instanceof Exception) {

        $mensaje = $e->getMessage();

        if (in_array($mensaje, $permitidos, true)) {

            // Estos dos ya se registraban antes: se mantiene.
            if ($mensaje === 'error_bd' || $mensaje === 'no_existe') {
                error_log("[$contexto] $mensaje");
            }

            return $mensaje;

        }

    }

    error_log(
        "[$contexto] Error inesperado " . get_class($e) . ': ' . $e->getMessage()
        . ' en ' . basename($e->getFile()) . ':' . $e->getLine()
    );

    return 'error_bd';

}


/**
 * Rollback que nunca interrumpe el manejo del error (si la conexión se perdió, solo queda registrado).
 */
function rollbackSeguro($conexion, string $contexto): void {

    try {
        mysqli_rollback($conexion);
    } catch (Throwable $e) {
        error_log("[$contexto] No se pudo hacer rollback: " . $e->getMessage());
    }

}
