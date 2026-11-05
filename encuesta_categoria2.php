<?php
session_start();
require_once("funciones/csrf.php");
require_once("funciones/clasificacion_categoria.php");

// La parte 2 solo tiene sentido después de completar la parte 1.
if (empty($_SESSION['encuesta_parte1']) || !is_array($_SESSION['encuesta_parte1'])) {
    header("Location: encuesta_categoria.php");
    exit();
}

$preguntas = preguntasEncuesta();

$csrf_token = generar_csrf_token();
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

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <?php foreach ($preguntas as $numero => $pregunta) {
                if ($numero < 6 || $numero > 10) { continue; } ?>

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

            <?php if ($numero < 10) { ?>
            <div class="separador"></div>
            <?php } ?>

            <?php } ?>

            <button class="btn-encuesta" type="submit">
                Finalizar encuesta 
            </button>

        </form>

    </div>

</div>

</body>
</html>