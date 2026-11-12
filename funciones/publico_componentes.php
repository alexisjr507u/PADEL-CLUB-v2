<?php

/* =========================================================
   COMPONENTES DE LAS PÁGINAS PÚBLICAS (sin iniciar sesión)

   Las páginas públicas son de SOLO LECTURA: no contienen formularios que escriban datos ni
   enlaces a herramientas del organizador. Inscribirse exige tener cuenta: el botón «Inscribirme»
   abre un aviso con «Iniciar sesión» y «Registrarme», y recién después de entrar el participante
   confirma su inscripción (nunca se inscribe solo).
   ========================================================= */

/** Id de torneo válido (entero positivo) o 0. */
function publicoIdTorneo($valor) {

    $id = filter_var($valor, FILTER_VALIDATE_INT);

    return ($id !== false && $id > 0) ? (int) $id : 0;

}


/** Barra de navegación pública (misma identidad que la portada). $activo: 'inicio' | 'torneos'. */
function renderNavbarPublica($activo = 'torneos') {

    ob_start();
    ?>
    <nav class="pub-navbar">

        <a href="panel_principal.php" class="pub-marca">
            <img src="img/ChatGPT Image 29 may 2026, 17_54_13.png" alt="Logo The Bull">
            <span>THE BULL</span>
        </a>

        <ul class="pub-menu">
            <li><a href="panel_principal.php" class="<?php echo $activo === 'inicio' ? 'activo' : ''; ?>"><i class="bi bi-house-door-fill"></i> Inicio</a></li>
            <li><a href="torneos_publicos.php" class="<?php echo $activo === 'torneos' ? 'activo' : ''; ?>"><i class="bi bi-trophy-fill"></i> Torneos</a></li>
            <li><a href="ranking_publico.php" class="<?php echo $activo === 'ranking' ? 'activo' : ''; ?>"><i class="bi bi-bar-chart-fill"></i> Ranking</a></li>
        </ul>

        <div class="pub-acceso">
            <a href="registro_participante.php" class="pub-btn pub-btn-borde"><i class="bi bi-person-plus-fill"></i> Registrarme</a>
            <a href="login.php" class="pub-btn pub-btn-primario"><i class="bi bi-box-arrow-in-right"></i> Iniciar sesión</a>
        </div>

    </nav>
    <?php
    return ob_get_clean();

}


/**
 * Aviso que se abre al pulsar «Inscribirme» sin haber iniciado sesión. Los enlaces llevan el id del
 * torneo; js/publico.js los completa según el botón que se pulsó. Sin JavaScript, el botón
 * «Inscribirme» es un enlace directo al inicio de sesión (ver publicoBotonInscribirme()).
 */
function renderModalInscripcionPublica() {

    ob_start();
    ?>
    <div class="pub-modal" id="modalInscripcionPublica" role="dialog" aria-modal="true" aria-labelledby="tituloInscripcionPublica" hidden>

        <div class="pub-modal-contenido">

            <button type="button" class="pub-modal-cerrar" data-cerrar-modal aria-label="Cerrar">×</button>

            <div class="pub-modal-icono"><i class="bi bi-person-lock"></i></div>

            <h2 id="tituloInscripcionPublica">Necesitás una cuenta para inscribirte</h2>

            <p>
                Para inscribirte a <strong id="nombreTorneoInscripcion">este torneo</strong> tenés que iniciar sesión o crear una cuenta.
                Cuando termines, volvés a este torneo y confirmás tu inscripción.
            </p>

            <div class="pub-modal-acciones">
                <a href="login.php" id="enlaceLoginInscripcion" class="pub-btn pub-btn-primario"><i class="bi bi-box-arrow-in-right"></i> Iniciar sesión</a>
                <a href="registro_participante.php" id="enlaceRegistroInscripcion" class="pub-btn pub-btn-borde"><i class="bi bi-person-plus-fill"></i> Registrarme</a>
            </div>

            <button type="button" class="pub-modal-no" data-cerrar-modal>Ahora no</button>

        </div>

    </div>
    <?php
    return ob_get_clean();

}


/**
 * Botón «Inscribirme» para visitantes (solo torneos Abiertos). Funciona sin JavaScript: es un enlace al
 * inicio de sesión recordando el torneo; con JavaScript abre antes el aviso con las dos opciones.
 */
function publicoBotonInscribirme($idTorneo, $nombreTorneo, $clase = 'pub-btn pub-btn-primario') {

    $idTorneo = (int) $idTorneo;

    return '<a href="login.php?torneo=' . $idTorneo . '" class="' . htmlspecialchars($clase, ENT_QUOTES, 'UTF-8') . ' pub-abrir-inscripcion"'
        . ' data-torneo-id="' . $idTorneo . '" data-torneo-nombre="' . htmlspecialchars($nombreTorneo, ENT_QUOTES, 'UTF-8') . '">'
        . '<i class="bi bi-pencil-square"></i> Inscribirme</a>';

}
