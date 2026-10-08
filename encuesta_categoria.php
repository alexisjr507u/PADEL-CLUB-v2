<?php
session_start();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuesta de Categoría</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="contenedor">

    <div class="encuesta-box">

        <div class="encuesta-titulo">

            <h2>Encuesta de Categoría</h2>

            <p>Parte 1 de 2</p>

        </div>

        <div class="progreso">
            <div class="barra"></div>
        </div>

        <form action="funciones/guardar_encuesta1.php" method="POST" id="formEncuesta">

            <!-- Pregunta 1 -->

            <div class="pregunta" id="pregunta1">

                <h3>1. ¿Hace cuánto tiempo juegas al pádel?</h3>

                <div class="opciones">

                    <label>
                        <input type="radio" name="p1" value="nunca" required>
                        Nunca jugué
                    </label>

                    <label>
                        <input type="radio" name="p1" value="1">
                        He jugado antes
                    </label>

                    <label>
                        <input type="radio" name="p1" value="2">
                        Menos de 6 meses
                    </label>

                    <label>
                        <input type="radio" name="p1" value="3">
                        Entre 6 meses y 1 año
                    </label>

                    <label>
                        <input type="radio" name="p1" value="4">
                        Entre 1 y 3 años
                    </label>

                    <label>
                        <input type="radio" name="p1" value="5">
                        Más de 3 años
                    </label>

                </div>

            </div>

            <div class="separador"></div>

            <!-- Pregunta 2 -->

            <div class="pregunta" id="pregunta2">

                <h3>2. ¿Con qué frecuencia juegas?</h3>

                <div class="opciones">

                    <label>
                        <input type="radio" name="p2" value="2" required>
                        Menos de una vez por semana
                    </label>

                    <label>
                        <input type="radio" name="p2" value="3">
                        Una vez por semana
                    </label>

                    <label>
                        <input type="radio" name="p2" value="4">
                        Dos veces por semana
                    </label>

                    <label>
                        <input type="radio" name="p2" value="5">
                        Tres o más veces por semana
                    </label>

                </div>

            </div>

            <div class="separador"></div>

            <!-- Pregunta 3 -->

            <div class="pregunta" id="pregunta3">

                <h3>3. ¿Cómo debe realizarse correctamente el saque en pádel?</h3>

                <div class="opciones">

                    <label>
                        <input type="radio" name="p3" value="0" required>
                        Golpeando la pelota por encima del hombro.
                    </label>

                    <label>
                        <input type="radio" name="p3" value="5">
                        Dejando botar la pelota y golpeándola a la altura o por debajo de la cintura.
                    </label>

                    <label>
                        <input type="radio" name="p3" value="0">
                        Lanzando la pelota al aire.
                    </label>

                    <label>
                        <input type="radio" name="p3" value="0">
                        Saltando para golpear la pelota.
                    </label>

                </div>

            </div>

            <div class="separador"></div>

            <!-- Pregunta 4 -->

            <div class="pregunta" id="pregunta4">

                <h3>4. Después del saque, la pelota bota en el campo rival y luego golpea directamente la malla metálica. ¿Qué ocurre?</h3>

                <div class="opciones">

                    <label>
                        <input type="radio" name="p4" value="0" required>
                        El punto continúa.
                    </label>

                    <label>
                        <input type="radio" name="p4" value="0">
                        Es punto válido.
                    </label>

                    <label>
                        <input type="radio" name="p4" value="5">
                        Es mala y el punto es para el rival.
                    </label>

                    <label>
                        <input type="radio" name="p4" value="0">
                        Se repite el saque.
                    </label>

                </div>

            </div>

            <div class="separador"></div>

            <!-- Pregunta 5 -->

            <div class="pregunta" id="pregunta5">

                <h3>5. ¿Cuál es la puntuación después de ganar el primer punto?</h3>

                <div class="opciones">

                    <label>
                        <input type="radio" name="p5" value="0" required>
                        1 - 0
                    </label>

                    <label>
                        <input type="radio" name="p5" value="0">
                        10 - 0
                    </label>

                    <label>
                        <input type="radio" name="p5" value="5">
                        15 - 0
                    </label>

                    <label>
                        <input type="radio" name="p5" value="0">
                        30 - 0
                    </label>

                </div>

            </div>
            <input type="hidden" name="principiante" id="principiante" value="0">
            <button class="btn-encuesta" type="submit" id="btnContinuar">
                Continuar encuesta →
            </button>

        </form>
        <!-- Modal Confirmación -->

<div id="modalConfirmacion" class="modal">

    <div class="modal-contenido">

        <div class="modal-icono">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>

        <h2>Confirmar selección</h2>

        <p>
            ¿Confirmás que nunca jugaste al pádel?
        </p>

        <p class="texto-secundario">
            Se te asignará automáticamente la categoría
            <strong>Principiante</strong>.
        </p>

        <div class="modal-botones">

            <button
                type="button"
                id="btnCancelar"
                class="btn-cancelar">

                Cancelar

            </button>

            <button
                type="button"
                id="btnAceptar"
                class="btn-aceptar">

                Sí, continuar

            </button>

        </div>

    </div>

</div>
    </div>

</div>
<script src="js/padel.js"></script>
</body>
</html>