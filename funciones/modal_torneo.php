<?php
/* Formulario (en ventana) para crear o editar un torneo. Lo usan el panel del organizador (crear y editar)
   y el del administrador (editar). Necesita $csrf_token. El comportamiento está en js/padel.js. */
?>
<!-- =========================================================
     MODAL CREAR TORNEO
     ========================================================= -->

<div class="modal-torneo" id="modalCrearTorneo">

    <div class="modal-torneo-contenido">

        <!-- CERRAR -->

        <button
            type="button"
            class="modal-torneo-cerrar"
            id="cerrarCrearTorneo"
        >
            ×
        </button>


        <!-- ENCABEZADO -->

        <div class="modal-torneo-header">

            <div class="modal-torneo-icono">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >

                    <path
                        d="M6 3H18V7C18 10.3137 15.3137 13 12 13C8.68629 13 6 10.3137 6 7V3Z"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linejoin="round"
                    />

                    <path
                        d="M6 5H3C3 9.41828 5.23858 12 8.5 12"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M18 5H21C21 9.41828 18.7614 12 15.5 12"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M12 13V18"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                    <path
                        d="M8 21H16"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                    />

                </svg>

            </div>

            <div>

                <h2 id="modalTorneoTitulo">Crear torneo</h2>

                <p id="modalTorneoSubtitulo">
                    Completá los datos del nuevo torneo.
                </p>

            </div>

        </div>


        <!-- FORMULARIO -->

        <form
            action="funciones/crear_torneo.php"
            method="POST"
            enctype="multipart/form-data"
            id="formCrearTorneo"
        >
        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($csrf_token); ?>"
        >
        <input
            type="hidden"
            id="idTorneoEditar"
            name="id_torneo"
            value=""
        >

        <!-- NOMBRE DEL TORNEO -->

<div class="campo-torneo">
    <label for="nombreTorneo">
        Nombre del torneo
    </label>

    <input
        type="text"
        id="nombreTorneo"
        name="nombre"
        maxlength="100"
        placeholder="Ej. Copa The Bull"
        required
    >
</div>


            <!-- FILA 1 -->

           <!-- =========================================
     FECHA Y HORA
     ========================================= -->

<div class="form-torneo-fila">

    <div class="campo-torneo">

        <label for="fechaInicioTorneo">
            Fecha de inicio
        </label>

        <input
            type="date"
            id="fechaInicioTorneo"
            name="fecha_inicio"
            required
        >

    </div>


    <div class="campo-torneo">

        <label for="horaInicioTorneo">
            Hora de inicio
        </label>

        <input
            type="time"
            id="horaInicioTorneo"
            name="hora_inicio"
            required
        >

    </div>

</div>


<div class="form-torneo-fila form-torneo-fila-unica">

    <div class="campo-torneo">

        <label for="fechaFinTorneo">
            Fecha de finalización
        </label>

        <input
            type="date"
            id="fechaFinTorneo"
            name="fecha_fin"
            required
        >

    </div>

</div>  

            <!-- IMAGEN -->

            <div class="campo-torneo">

                <label for="imagenTorneo">
                    Imagen del torneo
                </label>

                <div
                    class="subir-imagen-torneo"
                    id="zonaImagenTorneo"
                >

                    <div class="subir-imagen-torneo-icono">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >

                            <rect
                                x="3"
                                y="4"
                                width="18"
                                height="16"
                                rx="2"
                                stroke="currentColor"
                                stroke-width="1.8"
                            />

                            <circle
                                cx="8.5"
                                cy="9"
                                r="1.5"
                                stroke="currentColor"
                                stroke-width="1.6"
                            />

                            <path
                                d="M3 17L8 12L12 16L15 13L21 19"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                        </svg>

                    </div>

                    <strong>
                        Seleccionar imagen
                    </strong>

                    <span>
                        JPG, PNG o WEBP · Máximo 5 MB
                    </span>

                    <input
                        type="file"
                        id="imagenTorneo"
                        name="imagen"
                        accept="image/jpeg,image/png,image/webp"
                    >

                </div>

                <div
                    class="preview-torneo"
                    id="previewTorneo"
                ></div>

            </div>


            


            <!-- CATEGORÍA / TIPO -->

            <div class="form-torneo-fila">

                <div class="campo-torneo">

                    <label for="categoriaTorneo">
                        Categoría
                    </label>

                    <select
                        id="categoriaTorneo"
                        name="categoria"
                        required
                    >

                        <option value="">
                            Seleccionar categoría
                        </option>

                        <option value="Principiante">
                            Principiante
                        </option>

                        <option value="7ta">
                            7ta
                        </option>

                        <option value="6ta">
                            6ta
                        </option>

                        <option value="5ta">
                            5ta
                        </option>

                        <option value="4ta">
                            4ta
                        </option>

                        <option value="3ra">
                            3ra
                        </option>

                        <option value="2da">
                            2da
                        </option>

                        <option value="1ra">
                            1ra
                        </option>

                    </select>

                    <p class="campo-torneo-nota" id="notaCategoriaBloqueada" role="alert" hidden></p>

                </div>


                <div class="campo-torneo">

                    <label for="tipoTorneo">
                        Tipo de torneo
                    </label>

                    <select
                        id="tipoTorneo"
                        name="tipo"
                        required
                    >

                        <option value="">
                            Seleccionar tipo
                        </option>

                        <option value="Individual">
                            Individual
                        </option>

                        <option value="Parejas">
                            Parejas
                        </option>

                        <option value="Mixto">
                            Mixto
                        </option>

                    </select>

                    <p class="campo-torneo-nota" id="notaTipoBloqueado" role="alert" hidden></p>

                </div>

            </div>


            <!-- PARTICIPANTES / ESTADO -->

            <div class="form-torneo-fila">

                <div class="campo-torneo">

                    <label for="maxParticipantes">
                        Máximo de participantes
                    </label>

                    <input
                        type="number"
                        id="maxParticipantes"
                        name="max_participantes"
                        min="2"
                        max="1000"
                        placeholder="Ej. 32"
                        required
                    >

                </div>


                <div class="campo-torneo">

                    <label for="estadoTorneo">
                        Estado
                    </label>

                    <select
                        id="estadoTorneo"
                        name="estado"
                        required
                    >

                        <option value="Abierto">
                            Abierto
                        </option>

                        <option value="Cerrado">
                            Cerrado
                        </option>

                        <option value="En curso">
                            En curso
                        </option>

                        <option value="Finalizado">
                            Finalizado
                        </option>

                    </select>

                </div>

            </div>


            <!-- BOTONES -->

            <div class="modal-torneo-acciones">

                <button
                    type="button"
                    class="btn-cancelar-torneo"
                    id="cancelarCrearTorneo"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-guardar-torneo"
                >

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >

                        <path
                            d="M5 12L10 17L19 7"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                    </svg>

                    <span id="textoBotonGuardarTorneo">Crear torneo</span>

                </button>

            </div>

        </form>

    </div>

</div>


