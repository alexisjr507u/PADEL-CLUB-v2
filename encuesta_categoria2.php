<?php
session_start();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encuesta de Categoría - Parte 2</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="contenedor">

    <div class="encuesta-box">

        <div class="encuesta-titulo">

            <h2>Encuesta de Categoría</h2>

            <p>Parte 2 de 2</p>

        </div>

        <div class="progreso">
            <div class="barra" style="width:100%;"></div>
        </div>

        <form action="funciones/calcular_categoria.php" method="POST">

            <!-- Pregunta 6 -->

            <div class="pregunta">

                <h3>6. Si la pelota golpea primero el vidrio de tu lado sin haber botado antes en el suelo, ¿qué sucede?</h3>

                <div class="opciones">

                    <label><input type="radio" name="p6" value="0" required> El punto continúa.</label>

                    <label><input type="radio" name="p6" value="0"> Es punto para tu pareja.</label>

                    <label><input type="radio" name="p6" value="0"> Se repite el punto.</label>

                    <label><input type="radio" name="p6" value="5"> Pierdes el punto.</label>

                </div>

            </div>

            <div class="separador"></div>

            <!-- Pregunta 7 -->

            <div class="pregunta">

                <h3>7. En un partido oficial, ¿cuántos intentos de saque tiene un jugador?</h3>

                <div class="opciones">

                    <label><input type="radio" name="p7" value="0" required> Uno.</label>

                    <label><input type="radio" name="p7" value="5"> Dos.</label>

                    <label><input type="radio" name="p7" value="0"> Tres.</label>

                    <label><input type="radio" name="p7" value="0"> Ilimitados.</label>

                </div>

            </div>

            <div class="separador"></div>

            <!-- Pregunta 8 -->

            <div class="pregunta">

                <h3>8. ¿Dónde debe picar correctamente el saque?</h3>

                <div class="opciones">

                    <label><input type="radio" name="p8" value="0" required> En cualquier parte de la cancha.</label>

                    <label><input type="radio" name="p8" value="0"> En el cuadro del mismo lado.</label>

                    <label><input type="radio" name="p8" value="5"> En el cuadro de saque diagonal del rival.</label>

                    <label><input type="radio" name="p8" value="0"> Directamente contra el vidrio.</label>

                </div>

            </div>

            <div class="separador"></div>

            <!-- Pregunta 9 -->

            <div class="pregunta">

                <h3>9. ¿Cuándo cambia de lado la pareja durante un set?</h3>

                <div class="opciones">

                    <label><input type="radio" name="p9" value="0" required> Cada dos juegos.</label>

                    <label><input type="radio" name="p9" value="0"> Cada tres juegos.</label>

                    <label><input type="radio" name="p9" value="5"> Cuando la suma de los juegos disputados es impar.</label>

                    <label><input type="radio" name="p9" value="0"> Solo al finalizar el set.</label>

                </div>

            </div>

            <div class="separador"></div>

            <!-- Pregunta 10 -->

            <div class="pregunta">

                <h3>10. Si un jugador toca la red con la pala o con el cuerpo mientras la pelota está en juego, ¿qué sucede?</h3>

                <div class="opciones">

                    <label><input type="radio" name="p10" value="0" required> El punto continúa.</label>

                    <label><input type="radio" name="p10" value="0"> Se repite el punto.</label>

                    <label><input type="radio" name="p10" value="5"> Pierde el punto.</label>

                    <label><input type="radio" name="p10" value="0"> Depende del árbitro.</label>

                </div>

            </div>

            <button class="btn-encuesta" type="submit">
                Finalizar encuesta 
            </button>

        </form>

    </div>

</div>

</body>
</html>