/* =========================================================
   PÁGINAS PÚBLICAS (solo lectura)

   - «Inscribirme»: abre el aviso «Necesitás una cuenta» con Iniciar sesión / Registrarme.
     Los dos enlaces llevan el id del torneo para volver a él después de entrar.
   - No envía ningún dato: solo muestra el aviso y arma enlaces.
   ========================================================= */

(function () {

    var modal = document.getElementById('modalInscripcionPublica');

    if (!modal) {
        return;
    }

    var nombre = document.getElementById('nombreTorneoInscripcion');
    var enlaceLogin = document.getElementById('enlaceLoginInscripcion');
    var enlaceRegistro = document.getElementById('enlaceRegistroInscripcion');
    var ultimoBoton = null;

    function abrir(boton) {

        var id = parseInt(boton.getAttribute('data-torneo-id'), 10);

        if (!id || id < 1) {
            return;
        }

        ultimoBoton = boton;

        nombre.textContent = boton.getAttribute('data-torneo-nombre') || 'este torneo';
        enlaceLogin.setAttribute('href', 'login.php?torneo=' + id);
        enlaceRegistro.setAttribute('href', 'registro_participante.php?torneo=' + id);

        modal.hidden = false;
        document.body.classList.add('pub-modal-abierto');
        enlaceLogin.focus();

    }

    function cerrar() {

        modal.hidden = true;
        document.body.classList.remove('pub-modal-abierto');

        if (ultimoBoton) {
            ultimoBoton.focus();
        }

    }

    document.addEventListener('click', function (e) {

        var boton = e.target.closest('.pub-abrir-inscripcion');

        if (boton) {
            e.preventDefault();
            abrir(boton);
            return;
        }

        if (e.target.closest('[data-cerrar-modal]') || e.target === modal) {
            cerrar();
        }

    });

    document.addEventListener('keydown', function (e) {

        if (e.key === 'Escape' && !modal.hidden) {
            cerrar();
        }

    });

})();
