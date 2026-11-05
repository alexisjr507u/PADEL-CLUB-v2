/* Pestañas: las tres secciones ya están en la página; solo se
   muestra una a la vez (atributo hidden). Nada se elimina del DOM,
   así los formularios mantienen lo que se escribió y siguen
   enviándose igual. Sin JavaScript se ven todas las secciones. */
(function () {

    var nav = document.getElementById("gruposTabs");

    if (!nav) {
        return;
    }

    var tabs = [].slice.call(nav.querySelectorAll("[data-tab]"));
    var paneles = {};

    tabs.forEach(function (tab) {
        var panel = document.getElementById(tab.getAttribute("data-tab"));
        if (panel) {
            paneles[tab.getAttribute("data-tab")] = panel;
            panel.setAttribute("role", "tabpanel");
        }
    });

    var clave = "gruposTab:" + nav.getAttribute("data-torneo");

    function leerGuardado() {
        try { return sessionStorage.getItem(clave); } catch (e) { return null; }
    }

    function guardar(nombre) {
        try { sessionStorage.setItem(clave, nombre); } catch (e) { }
    }

    // Pestaña a la que apunta un hash (#fase-final, #administrar-grupos
    // o un ancla interna como #partido-12).
    function pestanaDeHash(hash) {

        var id = decodeURIComponent((hash || "").replace(/^#/, ""));

        if (paneles[id]) {
            return id;
        }

        var elemento = id ? document.getElementById(id) : null;
        var panel = elemento ? elemento.closest(".grupos-tab-panel") : null;

        return panel && paneles[panel.id] ? panel.id : null;

    }

    // Pestaña predeterminada: data-default del nav, o la primera.
    var predeterminada = nav.getAttribute("data-default") || (tabs[0] && tabs[0].getAttribute("data-tab"));

    function activar(nombre) {

        if (!paneles[nombre]) {
            nombre = paneles[predeterminada] ? predeterminada : Object.keys(paneles)[0];
        }

        Object.keys(paneles).forEach(function (clavePanel) {
            paneles[clavePanel].hidden = (clavePanel !== nombre);
        });

        tabs.forEach(function (tab) {
            var activa = tab.getAttribute("data-tab") === nombre;
            tab.classList.toggle("activa", activa);
            tab.setAttribute("aria-selected", activa ? "true" : "false");
            tab.setAttribute("tabindex", activa ? "0" : "-1");
        });

        guardar(nombre);

    }

    var inicial = pestanaDeHash(location.hash) || leerGuardado();
    activar(inicial);

    // Si se llegó con un ancla interna (ej. el partido recién guardado),
    // se la lleva a la vista ahora que su panel está visible.
    if (location.hash && !paneles[location.hash.slice(1)]) {
        var destino = document.getElementById(decodeURIComponent(location.hash.slice(1)));
        if (destino && !destino.closest("[hidden]")) {
            destino.scrollIntoView();
        }
    }

    document.addEventListener("click", function (evento) {

        var enlace = evento.target.closest ? evento.target.closest('a[href^="#"]') : null;

        if (!enlace) {
            return;
        }

        // Solo los enlaces que apuntan a una pestaña; el resto de las
        // anclas (#partido-12, etc.) conservan su comportamiento normal.
        var nombre = enlace.getAttribute("href").replace(/^#/, "");

        if (!paneles[nombre]) {
            return;
        }

        evento.preventDefault();
        activar(nombre);
        history.pushState(null, "", "#" + nombre);
        window.scrollTo(0, 0);

    });

    window.addEventListener("popstate", function () {
        activar(pestanaDeHash(location.hash));
    });

    nav.addEventListener("keydown", function (evento) {

        if (evento.key !== "ArrowRight" && evento.key !== "ArrowLeft") {
            return;
        }

        var indice = tabs.indexOf(document.activeElement);

        if (indice < 0) {
            return;
        }

        var siguiente = tabs[(indice + (evento.key === "ArrowRight" ? 1 : tabs.length - 1)) % tabs.length];

        siguiente.focus();
        siguiente.click();

    });

})();

