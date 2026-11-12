<?php

/* =========================================================
   HORA DE ARGENTINA

   Todas las fechas de la base (NOW(), CURDATE(), fecha_inscripcion, fecha_vencimiento...) las genera MySQL con la
   hora del servidor, que es la de Argentina. PHP, en cambio, trabaja en UTC: después de las 21:00 su «hoy» ya es
   el día siguiente, y comparar un texto de la base con time() corre la hora 3 horas.

   Por eso, para saber «hoy» o si algo ya venció, se le pregunta a MySQL (la misma hora con la que se guardó el dato).
   Estas funciones no modifican ningún dato ni ninguna fecha existente.
   ========================================================= */

/** «Hoy» (Y-m-d) según MySQL. */
function fechaHoyServidor() {

    static $hoy = null;

    if ($hoy === null) {
        global $conexion;
        $hoy = mysqli_fetch_row(mysqli_query($conexion, "SELECT CURDATE()"))[0];
    }

    return $hoy;

}
