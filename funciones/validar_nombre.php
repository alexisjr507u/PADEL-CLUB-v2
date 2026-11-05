<?php

/**
 * Normaliza y valida un nombre o apellido de persona.
 *
 * Acepta letras de cualquier idioma (con tildes y ñ), espacios, apóstrofes,
 * guiones y puntos ("María José", "O'Connor", "Jean-Pierre", "De la Cruz").
 * Rechaza dígitos, "<", ">", "&", comillas y cualquier otro símbolo, para que
 * un nombre nunca pueda llevar HTML o script a las pantallas que lo muestran.
 *
 * @return string|null  El nombre normalizado, o null si no es válido.
 */
function normalizar_nombre_persona($valor)
{
    if (!is_string($valor)) {
        return null;
    }

    $valor = preg_replace('/\s+/u', ' ', $valor);

    // null si el texto no es UTF-8 válido.
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);

    // Largo medido en caracteres (no bytes), sin depender de mbstring.
    if (!preg_match('/^.{2,50}$/us', $valor)) {
        return null;
    }

    if (!preg_match("/^\p{L}[\p{L}\p{M}'’.\- ]*$/u", $valor)) {
        return null;
    }

    return $valor;
}
