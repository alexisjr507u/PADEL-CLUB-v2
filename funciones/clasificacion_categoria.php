<?php

/* =========================================================
   ENCUESTA DE CATEGORÍA — preguntas, puntajes y clasificación
   Única fuente de verdad: las pantallas de la encuesta, el cálculo
   del servidor y la revisión del administrador usan este archivo.

   Los puntos NUNCA viajan al navegador: el formulario solo envía la
   letra de la opción elegida y el servidor calcula el puntaje.

   Áreas y pesos (máximo 100):
     Experiencia 12 · Competición 16 · Técnica 36 · Táctica 26 · Reglas 10

   Los umbrales son VALORES INICIALES propuestos, no oficiales: deben
   calibrarse con jugadores de categoría conocida antes de darlos por buenos.
   ========================================================= */

const AREAS_ENCUESTA = [
    'experiencia' => 'Experiencia',
    'competicion' => 'Competición',
    'tecnica' => 'Técnica',
    'tactica' => 'Táctica',
    'reglas' => 'Reglas',
];

const PESO_AREAS_ENCUESTA = [
    'experiencia' => 12,
    'competicion' => 16,
    'tecnica' => 36,
    'tactica' => 26,
    'reglas' => 10,
];

// Puntaje total mínimo (en %) para 7ma, 6ta, 5ta, 4ta, 3ra, 2da y 1ra.
// Por debajo del primero: Principiante. Los ids de categoria son 1..8 en ese orden.
const UMBRALES_CATEGORIA = [20, 35, 50, 63, 75, 85, 93];

// A partir de este índice (4 = 4ta) el resultado exige revisión del administrador.
const INDICE_PRIMERA_CATEGORIA_A_REVISAR = 4;

// Habilidad máxima (preguntas 5 a 9).
const HABILIDAD_MAXIMA_ENCUESTA = 62;


/* ---------------------------------------------------------
   VERSIONES DE LA ENCUESTA
   1 = original: 10 preguntas de experiencia y reglas, 0 o 5 puntos cada una
       (máximo 50). Es la que se usa hoy para registrar participantes.
   2 = la encuesta con técnica y táctica (áreas y pesos de arriba). Ya no se
       muestra, pero queda definida porque hay clasificaciones guardadas con
       ella y la revisión del administrador debe mostrarlas con sus textos.
   Cada fila de clasificacion_categoria guarda su versión (version_encuesta).
   --------------------------------------------------------- */
const VERSION_ENCUESTA_ORIGINAL = 1;
const VERSION_ENCUESTA_HABILIDAD = 2;
const VERSION_ENCUESTA_ACTIVA = VERSION_ENCUESTA_ORIGINAL;

// Encuesta original: puntaje máximo y límite superior (en %) de cada categoría,
// de Principiante a 3ra; por encima del último límite es 1ra.
const PUNTAJE_MAXIMO_ENCUESTA_ORIGINAL = 50;
const LIMITES_CATEGORIA_ENCUESTA_ORIGINAL = [19, 34, 49, 64, 79, 89, 95];


function preguntasEncuesta($version = VERSION_ENCUESTA_ACTIVA) {

    return (int) $version === VERSION_ENCUESTA_ORIGINAL
        ? preguntasEncuestaOriginal()
        : preguntasEncuestaHabilidad();

}


/**
 * Áreas que se muestran al revisar una clasificación: clave => [nombre, máximo].
 * Solo las que existen en esa versión de la encuesta.
 */
function areasEncuesta($version = VERSION_ENCUESTA_ACTIVA) {

    if ((int) $version === VERSION_ENCUESTA_ORIGINAL) {

        return [
            'experiencia' => ['Experiencia', 10],
            'reglas' => ['Reglas', 40],
        ];

    }

    $areas = [];

    foreach (AREAS_ENCUESTA as $clave => $nombre) {
        $areas[$clave] = [$nombre, PESO_AREAS_ENCUESTA[$clave]];
    }

    return $areas;

}


/* Encuesta original, tal como estaba en el commit 278caf6.
   Las opciones se identifican por letra (a, b, c...) en vez de por su puntaje:
   el navegador nunca recibe cuántos puntos vale cada una. 'nunca' solo existe
   en la pregunta 1. */
function preguntasEncuestaOriginal() {

    return [

        1 => ['area' => 'experiencia', 'texto' => '¿Hace cuánto tiempo juegas al pádel?', 'opciones' => [
            'nunca' => ['Nunca jugué', 0],
            'a' => ['He jugado antes', 1],
            'b' => ['Menos de 6 meses', 2],
            'c' => ['Entre 6 meses y 1 año', 3],
            'd' => ['Entre 1 y 3 años', 4],
            'e' => ['Más de 3 años', 5],
        ]],

        2 => ['area' => 'experiencia', 'texto' => '¿Con qué frecuencia juegas?', 'opciones' => [
            'a' => ['Menos de una vez por semana', 2],
            'b' => ['Una vez por semana', 3],
            'c' => ['Dos veces por semana', 4],
            'd' => ['Tres o más veces por semana', 5],
        ]],

        3 => ['area' => 'reglas', 'texto' => '¿Cómo debe realizarse correctamente el saque en pádel?', 'opciones' => [
            'a' => ['Golpeando la pelota por encima del hombro.', 0],
            'b' => ['Dejando botar la pelota y golpeándola a la altura o por debajo de la cintura.', 5],
            'c' => ['Lanzando la pelota al aire.', 0],
            'd' => ['Saltando para golpear la pelota.', 0],
        ]],

        4 => ['area' => 'reglas', 'texto' => 'Después del saque, la pelota bota en el campo rival y luego golpea directamente la malla metálica. ¿Qué ocurre?', 'opciones' => [
            'a' => ['El punto continúa.', 0],
            'b' => ['Es punto válido.', 0],
            'c' => ['Es mala y el punto es para el rival.', 5],
            'd' => ['Se repite el saque.', 0],
        ]],

        5 => ['area' => 'reglas', 'texto' => '¿Cuál es la puntuación después de ganar el primer punto?', 'opciones' => [
            'a' => ['1 - 0', 0],
            'b' => ['10 - 0', 0],
            'c' => ['15 - 0', 5],
            'd' => ['30 - 0', 0],
        ]],

        6 => ['area' => 'reglas', 'texto' => 'Si la pelota golpea primero el vidrio de tu lado sin haber botado antes en el suelo, ¿qué sucede?', 'opciones' => [
            'a' => ['El punto continúa.', 0],
            'b' => ['Es punto para tu pareja.', 0],
            'c' => ['Se repite el punto.', 0],
            'd' => ['Pierdes el punto.', 5],
        ]],

        7 => ['area' => 'reglas', 'texto' => 'En un partido oficial, ¿cuántos intentos de saque tiene un jugador?', 'opciones' => [
            'a' => ['Uno.', 0],
            'b' => ['Dos.', 5],
            'c' => ['Tres.', 0],
            'd' => ['Ilimitados.', 0],
        ]],

        8 => ['area' => 'reglas', 'texto' => '¿Dónde debe picar correctamente el saque?', 'opciones' => [
            'a' => ['En cualquier parte de la cancha.', 0],
            'b' => ['En el cuadro del mismo lado.', 0],
            'c' => ['En el cuadro de saque diagonal del rival.', 5],
            'd' => ['Directamente contra el vidrio.', 0],
        ]],

        9 => ['area' => 'reglas', 'texto' => '¿Cuándo cambia de lado la pareja durante un set?', 'opciones' => [
            'a' => ['Cada dos juegos.', 0],
            'b' => ['Cada tres juegos.', 0],
            'c' => ['Cuando la suma de los juegos disputados es impar.', 5],
            'd' => ['Solo al finalizar el set.', 0],
        ]],

        10 => ['area' => 'reglas', 'texto' => 'Si un jugador toca la red con la pala o con el cuerpo mientras la pelota está en juego, ¿qué sucede?', 'opciones' => [
            'a' => ['El punto continúa.', 0],
            'b' => ['Se repite el punto.', 0],
            'c' => ['Pierde el punto.', 5],
            'd' => ['Depende del árbitro.', 0],
        ]],

    ];

}


function preguntasEncuestaHabilidad() {

    return [

        1 => ['area' => 'experiencia', 'texto' => '¿Desde hace cuánto tiempo jugás al pádel con cierta regularidad?', 'opciones' => [
            'nunca' => ['Nunca jugué', 0],
            'a' => ['Menos de 1 año', 2],
            'b' => ['Más de 3 años', 6],
            'c' => ['Solo jugué algunas veces, sin regularidad', 1],
            'd' => ['Entre 1 y 3 años', 4],
        ]],

        2 => ['area' => 'experiencia', 'texto' => 'En los últimos 3 meses, ¿con qué frecuencia jugaste?', 'opciones' => [
            'a' => ['Una vez por semana', 4],
            'b' => ['Menos de 2 veces por mes', 0],
            'c' => ['Dos o más veces por semana', 6],
            'd' => ['Entre 2 y 4 veces por mes', 2],
        ]],

        3 => ['area' => 'competicion', 'texto' => '¿Qué experiencia tenés en competencias?', 'opciones' => [
            'a' => ['Torneos de club o ligas locales', 5],
            'b' => ['Nunca competí', 0],
            'c' => ['Torneos federados o regionales con ranking', 10],
            'd' => ['Solo americanos o partidos sociales entre amigos', 2],
        ]],

        4 => ['area' => 'competicion', 'texto' => '¿Con qué tipo de rivales sentís que jugás parejo?', 'opciones' => [
            'a' => ['Con jugadores de club habituales', 4],
            'b' => ['Con personas que recién empiezan', 0],
            'c' => ['Con jugadores que compiten regularmente', 6],
            'd' => ['Con aficionados que juegan de vez en cuando', 2],
        ]],

        5 => ['area' => 'tecnica', 'texto' => 'Cuando la pelota rebota en la pared de fondo, ¿qué te pasa normalmente?', 'opciones' => [
            'a' => ['La devuelvo cuando rebota despacio, sin elegir hacia dónde', 4],
            'b' => ['La devuelvo con control y la uso a propósito para cambiar el ritmo o la dirección del juego', 12],
            'c' => ['Casi siempre la dejo pasar o no llego a devolverla', 0],
            'd' => ['La devuelvo con control y casi siempre elijo hacia dónde mandarla', 8],
        ]],

        6 => ['area' => 'tecnica', 'texto' => 'Cuando jugás cerca de la red, ¿cómo te sentís con las voleas?', 'opciones' => [
            'a' => ['Hago voleas con control y dirección la mayoría de las veces', 8],
            'b' => ['Evito subir a la red; casi no hago voleas', 0],
            'c' => ['Resuelvo voleas altas y bajas con control y suelo definir el punto', 12],
            'd' => ['Hago voleas para devolver, pero fallo seguido y sin dirección', 4],
        ]],

        7 => ['area' => 'tecnica', 'texto' => 'En el saque y en los golpes por encima de la cabeza, ¿cómo te desempeñás?', 'opciones' => [
            'a' => ['Varío el saque y remato con potencia y colocación (bandeja, víbora, remate)', 12],
            'b' => ['Pongo la pelota en juego con dificultad; casi no remato', 0],
            'c' => ['Saco con regularidad y remato con dirección la mayoría de las veces', 8],
            'd' => ['Saco para poner la pelota en juego y remato sin dirección fija', 4],
        ]],

        8 => ['area' => 'tactica', 'texto' => '¿Cómo te movés con tu compañero o compañera durante un punto?', 'opciones' => [
            'a' => ['Subimos y bajamos juntos casi siempre', 9],
            'b' => ['No pienso en la posición; voy hacia la pelota', 0],
            'c' => ['Nos movemos como bloque, cubrimos los huecos y ajustamos la posición según el rival', 13],
            'd' => ['Trato de no chocar con mi compañero, pero a veces quedamos mal ubicados', 4],
        ]],

        9 => ['area' => 'tactica', 'texto' => 'Tus rivales están en la red y vos en el fondo con una pelota alta: ¿qué hacés?', 'opciones' => [
            'a' => ['Elijo entre globo y pasada según cómo estén parados', 9],
            'b' => ['Pego fuerte hacia cualquier lado', 0],
            'c' => ['Intento pasarla por los costados', 4],
            'd' => ['Uso el globo para recuperar la red y varío según su posición y la mía', 13],
        ]],

        10 => ['area' => 'reglas', 'texto' => 'El saque bota dentro del cuadro correcto y luego toca directamente la reja metálica. ¿Qué pasa?', 'opciones' => [
            'a' => ['El punto sigue normalmente', 0],
            'b' => ['Es falta: si era el primer saque se saca de nuevo; si era el segundo, se pierde el punto', 10],
            'c' => ['Es punto para quien sacó', 0],
            'd' => ['Se repite el saque sin ninguna consecuencia', 0],
        ]],

    ];

}


function indiceCategoriaPorPorcentaje($porcentaje) {

    $indice = 0;

    foreach (UMBRALES_CATEGORIA as $posicion => $umbral) {
        if ($porcentaje >= $umbral) {
            $indice = $posicion + 1;
        }
    }

    return $indice;

}


/**
 * Calcula la clasificación a partir de las letras elegidas ([1 => 'a', ...]).
 * Devuelve ['valido' => false] si falta una respuesta o una letra no existe.
 *
 * Reglas:
 *  - Categoría por puntaje total (T).
 *  - Tope por habilidad: no puede superar en más de un escalón a la categoría
 *    que corresponde solo a técnica + táctica (preguntas 5 a 9). Así las reglas,
 *    los años de juego o la competición no compensan una habilidad baja.
 *  - 2da y 1ra exigen la respuesta máxima de competición (pregunta 3); si no, 3ra.
 *  - Exige revisión: resultado de 4ta o superior, o incoherencia fuerte entre
 *    el puntaje total y la habilidad (2 escalones o más).
 */
function calcularClasificacionEncuesta(array $respuestas, $version = VERSION_ENCUESTA_ACTIVA) {

    if ((int) $version === VERSION_ENCUESTA_ORIGINAL) {
        return calcularClasificacionEncuestaOriginal($respuestas);
    }

    $preguntas = preguntasEncuestaHabilidad();
    $puntosPorArea = array_fill_keys(array_keys(AREAS_ENCUESTA), 0);
    $letras = [];

    foreach ($preguntas as $numero => $pregunta) {

        $letra = $respuestas[$numero] ?? null;

        if (!is_string($letra) || $letra === 'nunca' || !isset($pregunta['opciones'][$letra])) {
            return ['valido' => false];
        }

        $letras[$numero] = $letra;
        $puntosPorArea[$pregunta['area']] += $pregunta['opciones'][$letra][1];

    }

    $total = array_sum($puntosPorArea);
    $habilidad = $puntosPorArea['tecnica'] + $puntosPorArea['tactica'];
    $habilidadPct = $habilidad / HABILIDAD_MAXIMA_ENCUESTA * 100;

    $indiceTotal = indiceCategoriaPorPorcentaje($total);
    $indiceHabilidad = indiceCategoriaPorPorcentaje($habilidadPct);

    $indice = min($indiceTotal, $indiceHabilidad + 1);
    $motivos = [];

    if ($indice < $indiceTotal) {
        $motivos[] = 'Tope por habilidad técnica/táctica';
    }

    $maxCompeticion = max(array_column($preguntas[3]['opciones'], 1));

    if ($indice >= 6 && $preguntas[3]['opciones'][$letras[3]][1] < $maxCompeticion) {
        $indice = 5;
        $motivos[] = '2da/1ra requieren experiencia competitiva alta';
    }

    $incoherente = abs($indiceTotal - $indiceHabilidad) >= 2;

    if ($incoherente) {
        $motivos[] = 'Respuestas incoherentes entre experiencia y habilidad';
    }

    $requiereRevision = $incoherente || $indice >= INDICE_PRIMERA_CATEGORIA_A_REVISAR;

    if ($indice >= INDICE_PRIMERA_CATEGORIA_A_REVISAR) {
        $motivos[] = 'Categoría estimada de 4ta o superior';
    }

    return [
        'valido' => true,
        'version' => VERSION_ENCUESTA_HABILIDAD,
        'letras' => $letras,
        'puntos' => $puntosPorArea,
        'total' => $total,
        'habilidad_pct' => (int) round($habilidadPct),
        'id_categoria_estimada' => $indice + 1,
        'requiere_revision' => $requiereRevision,
        'motivos' => implode('; ', $motivos),
    ];

}


/**
 * Clasificación de la encuesta original: suma de puntos sobre 50 y los mismos
 * cortes que tenía funciones/calcular_categoria.php (<=19 Principiante, <=34 7ma,
 * <=49 6ta, <=64 5ta, <=79 4ta, <=89 3ra, <=95 2da, más 1ra).
 *
 * Revisión del administrador: igual que en la encuesta actual, todo resultado de
 * 4ta o superior queda pendiente. Las reglas de tope por habilidad y de competición
 * no se pueden aplicar porque esta encuesta no pregunta técnica, táctica ni
 * competición; por eso tampoco hay "incoherencia" que detectar.
 *
 * puntos_total se guarda como porcentaje (0-100), la misma escala que usa la
 * pantalla de revisión.
 */
function calcularClasificacionEncuestaOriginal(array $respuestas) {

    $preguntas = preguntasEncuestaOriginal();
    $puntosPorArea = array_fill_keys(array_keys(AREAS_ENCUESTA), 0);
    $letras = [];

    foreach ($preguntas as $numero => $pregunta) {

        $letra = $respuestas[$numero] ?? null;

        if (!is_string($letra) || $letra === 'nunca' || !isset($pregunta['opciones'][$letra])) {
            return ['valido' => false];
        }

        $letras[$numero] = $letra;
        $puntosPorArea[$pregunta['area']] += $pregunta['opciones'][$letra][1];

    }

    $total = array_sum($puntosPorArea);
    $porcentaje = $total / PUNTAJE_MAXIMO_ENCUESTA_ORIGINAL * 100;

    $indice = 0;

    foreach (LIMITES_CATEGORIA_ENCUESTA_ORIGINAL as $posicion => $limite) {
        if ($porcentaje > $limite) {
            $indice = $posicion + 1;
        }
    }

    $requiereRevision = $indice >= INDICE_PRIMERA_CATEGORIA_A_REVISAR;

    return [
        'valido' => true,
        'version' => VERSION_ENCUESTA_ORIGINAL,
        'letras' => $letras,
        'puntos' => $puntosPorArea,
        'total' => (int) round($porcentaje),
        'puntos_brutos' => $total,
        'id_categoria_estimada' => $indice + 1,
        'requiere_revision' => $requiereRevision,
        'motivos' => $requiereRevision
            ? 'Categoría estimada de 4ta o superior (encuesta original: mide experiencia y reglas, no técnica ni táctica)'
            : '',
    ];

}
