<?php

require_once "../config/no_cache.php";
require_once "../config/conexion.php";

session_start();

/* ==========================================
   VERIFICAR SESIÓN
   ========================================== */

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit;
}


/* ==========================================
   VERIFICAR ROL
   ========================================== */

if ($_SESSION['id_rol'] != 2) {
    header("Location: ../paneles.php");
    exit;
}


/* ==========================================
   VERIFICAR MÉTODO
   ========================================== */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paneles.php?seccion=canchas");
    exit;
}


/* ==========================================
   RECIBIR DATOS
   ========================================== */

$idCancha = isset($_POST['id_cancha'])
    ? (int) $_POST['id_cancha']
    : 0;

$nombre = trim($_POST['nombre_cancha'] ?? '');


/* ==========================================
   VALIDAR ID
   ========================================== */

if ($idCancha <= 0) {
    header("Location: ../paneles.php?seccion=canchas&error=cancha_invalida");
    exit;
}


/* ==========================================
   VALIDAR NOMBRE
   ========================================== */

if ($nombre === '') {
    header("Location: ../paneles.php?seccion=canchas&error=nombre_vacio");
    exit;
}

if (mb_strlen($nombre) > 50) {
    header("Location: ../paneles.php?seccion=canchas&error=nombre_largo");
    exit;
}


/* ==========================================
   VERIFICAR QUE LA CANCHA SEA DEL ORGANIZADOR
   ========================================== */

$sqlVerificar = "
    SELECT
    cancha.id_cancha,
    cancha.id_club,
    cancha.imagen
FROM cancha
    INNER JOIN club
        ON cancha.id_club = club.id_club
    WHERE cancha.id_cancha = ?
      AND club.id_usuario = ?
";

$stmtVerificar = mysqli_prepare($conexion, $sqlVerificar);

mysqli_stmt_bind_param(
    $stmtVerificar,
    "ii",
    $idCancha,
    $_SESSION['id_usuario']
);

mysqli_stmt_execute($stmtVerificar);

$resultado = mysqli_stmt_get_result($stmtVerificar);

$cancha = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmtVerificar);


/* ==========================================
   CANCHA NO ENCONTRADA
   ========================================== */

if (!$cancha) {
    header("Location: ../paneles.php?seccion=canchas&error=cancha_no_autorizada");
    exit;
}
/* ==========================================
   VERIFICAR NOMBRE REPETIDO
   ========================================== */

$sqlExisteCancha = "
    SELECT id_cancha
    FROM cancha
    WHERE id_club = ?
      AND nombre = ?
      AND id_cancha != ?
    LIMIT 1
";

$stmtExisteCancha = mysqli_prepare(
    $conexion,
    $sqlExisteCancha
);

mysqli_stmt_bind_param(
    $stmtExisteCancha,
    "isi",
    $cancha['id_club'],
    $nombre,
    $idCancha
);

mysqli_stmt_execute($stmtExisteCancha);

$resultadoExisteCancha =
    mysqli_stmt_get_result($stmtExisteCancha);

if (mysqli_num_rows($resultadoExisteCancha) > 0) {

    mysqli_stmt_close($stmtExisteCancha);

    header(
        "Location: ../paneles.php?seccion=canchas&error=cancha_repetida"
    );

    exit;
}

mysqli_stmt_close($stmtExisteCancha);
/* ==========================================
   VERIFICAR NOMBRE REPETIDO
   ========================================== */

$sqlExisteCancha = "
    SELECT id_cancha        
    FROM cancha
    WHERE id_club = ?
      AND nombre = ?
      AND id_cancha != ?
    LIMIT 1
";

$stmtExisteCancha = mysqli_prepare(
    $conexion,
    $sqlExisteCancha
);

mysqli_stmt_bind_param(
    $stmtExisteCancha,
    "isi",
    $cancha['id_club'],
    $nombre,
    $idCancha
);

mysqli_stmt_execute($stmtExisteCancha);

$resultadoExisteCancha =
    mysqli_stmt_get_result($stmtExisteCancha);

if (mysqli_num_rows($resultadoExisteCancha) > 0) {

    mysqli_stmt_close($stmtExisteCancha);

    header(
        "Location: ../paneles.php?seccion=canchas&error=cancha_repetida"
    );

    exit;
}

mysqli_stmt_close($stmtExisteCancha);


/* ==========================================
   IMAGEN ACTUAL
   ========================================== */

$imagenActual = $cancha['imagen'] ?? '';

$imagenNueva = $imagenActual;


/* ==========================================
   SI SE SUBIÓ UNA NUEVA IMAGEN
   ========================================== */

if (
    isset($_FILES['imagen_cancha']) &&
    $_FILES['imagen_cancha']['error'] !== UPLOAD_ERR_NO_FILE
) {

    if ($_FILES['imagen_cancha']['error'] !== UPLOAD_ERR_OK) {
        header("Location: ../paneles.php?seccion=canchas&error=error_imagen");
        exit;
    }


    /* Tamaño máximo: 5 MB */

    if ($_FILES['imagen_cancha']['size'] > 5 * 1024 * 1024) {
        header("Location: ../paneles.php?seccion=canchas&error=imagen_grande");
        exit;
    }


    /* Verificar tipo */

    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $tipo = mime_content_type(
        $_FILES['imagen_cancha']['tmp_name']
    );

    if (!isset($tiposPermitidos[$tipo])) {
        header("Location: ../paneles.php?seccion=canchas&error=tipo_imagen");
        exit;
    }


    /* Crear carpeta si no existe */

    $carpeta = "../img/canchas/";

    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }


    /* Nombre único */

    $extension = $tiposPermitidos[$tipo];

    $nombreArchivo = "cancha_" .
        $_SESSION['id_usuario'] . "_" .
        time() . "_" .
        bin2hex(random_bytes(4)) .
        "." . $extension;

    $rutaFisica = $carpeta . $nombreArchivo;

    $rutaBD = "img/canchas/" . $nombreArchivo;


    /* Mover imagen */

    if (!move_uploaded_file(
        $_FILES['imagen_cancha']['tmp_name'],
        $rutaFisica
    )) {
        header("Location: ../paneles.php?seccion=canchas&error=error_subir_imagen");
        exit;
    }

    $imagenNueva = $rutaBD;
}


/* ==========================================
   ACTUALIZAR CANCHA
   ========================================== */

$sqlActualizar = "
    UPDATE cancha
    SET nombre = ?, imagen = ?
    WHERE id_cancha = ?
";

$stmtActualizar = mysqli_prepare(
    $conexion,
    $sqlActualizar
);

mysqli_stmt_bind_param(
    $stmtActualizar,
    "ssi",
    $nombre,
    $imagenNueva,
    $idCancha
);


/* ==========================================
   EJECUTAR
   ========================================== */

if (mysqli_stmt_execute($stmtActualizar)) {

    mysqli_stmt_close($stmtActualizar);

    /* Si había una imagen nueva,
       eliminar la imagen anterior */

    if (
        $imagenNueva !== $imagenActual &&
        !empty($imagenActual)
    ) {

        $rutaImagenAnterior = "../" . $imagenActual;

        if (file_exists($rutaImagenAnterior)) {
            unlink($rutaImagenAnterior);
        }
    }

    header(
        "Location: ../paneles.php?seccion=canchas&ok=cancha_editada"
    );

    exit;
}


/* ==========================================
   ERROR
   ========================================== */

mysqli_stmt_close($stmtActualizar);

header(
    "Location: ../paneles.php?seccion=canchas&error=error_editar_cancha"
);

exit;