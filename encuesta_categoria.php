<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/config/animacion.php';   // animación de entrada
require_once("funciones/csrf.php");
require_once("funciones/clasificacion_categoria.php");

$preguntas = preguntasEncuesta();

$csrf_token = generar_csrf_token();
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

        <?php if (($_GET['error'] ?? '') === 'incoherente') { ?>
            <div class="mensaje-error">
                Tus respuestas resultaron incoherentes entre sí y no se pudo calcular tu categoría.
                Por favor, volvé a completar la encuesta.
            </div>
        <?php } ?>

        <div class="progreso">
            <div class="barra"></div>
        </div>

        <form action="funciones/guardar_encuesta1.php" method="POST" id="formEncuesta">

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <?php foreach ($preguntas as $numero => $pregunta) {
                if ($numero < 1 || $numero > 5) { continue; } ?>

            <div class="pregunta" id="pregunta<?php echo $numero; ?>">

                <h3><?php echo $numero; ?>. <?php echo htmlspecialchars($pregunta['texto']); ?></h3>

                <div class="opciones">

                    <?php foreach ($pregunta['opciones'] as $letra => $opcion) { ?>
                        <label>
                            <input type="radio" name="p<?php echo $numero; ?>" value="<?php echo htmlspecialchars($letra); ?>" required>
                            <?php echo htmlspecialchars($opcion[0]); ?>
                        </label>
                    <?php } ?>

                </div>

            </div>

            <?php if ($numero < 5) { ?>
            <div class="separador"></div>
            <?php } ?>

            <?php } ?>

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
<script src="js/padel.js?v=17"></script>
</body>
</html>