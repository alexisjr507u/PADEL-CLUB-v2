<?php

// ==========================================
// AGREGAR CANCHA
// ==========================================

include("../config/no_cache.php");
include("../config/conexion.php");


// ==========================================
// VERIFICAR SESIÓN
// ==========================================

if (!isset($_SESSION['id_usuario'])) {

    header("Location: ../login.php");
    exit();

}


// ==========================================
// VERIFICAR QUE SEA ORGANIZADOR
// ==========================================

if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 2) {

    header("Location: ../paneles.php");
    exit();

}


// ==========================================
// SOLO POST
// ==========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: ../paneles.php?seccion=canchas");
    exit();

}


// ==========================================
// DATOS
// ==========================================

$id_usuario = intval($_SESSION['id_usuario']);

$nombre = trim(
    $_POST['nombre_cancha'] ?? ""
);


// ==========================================
// VALIDAR NOMBRE
// ==========================================

if ($nombre === "") {

    header(
        "Location: ../paneles.php?seccion=canchas&error=nombre"
    );

    exit();

}


if (strlen($nombre) > 50) {

    header(
        "Location: ../paneles.php?seccion=canchas&error=nombre_largo"
    );

    exit();

}


// ==========================================
// BUSCAR CLUB DEL ORGANIZADOR
// ==========================================

$sqlClub = "
    SELECT id_club
    FROM club
    WHERE id_usuario = ?
    LIMIT 1
";


$stmtClub = mysqli_prepare(
    $conexion,
    $sqlClub
);


if (!$stmtClub) {

    die(
        "Error al preparar la consulta del club: "
        . mysqli_error($conexion)
    );

}


mysqli_stmt_bind_param(
    $stmtClub,
    "i",
    $id_usuario
);


mysqli_stmt_execute($stmtClub);


$resultadoClub =
    mysqli_stmt_get_result($stmtClub);


$club =
    mysqli_fetch_assoc($resultadoClub);


mysqli_stmt_close($stmtClub);


// ==========================================
// VERIFICAR QUE EL CLUB EXISTA
// ==========================================

if (!$club) {

    header(
        "Location: ../paneles.php?seccion=canchas&error=club"
    );

    exit();

}


// ==========================================
// OBTENER ID DEL CLUB
// ==========================================

$id_club = intval(
    $club['id_club']
);


// ==========================================
// COMPROBAR NOMBRE REPETIDO
// ==========================================

$sqlExisteCancha = "
    SELECT id_cancha
    FROM cancha
    WHERE id_club = ?
      AND nombre = ?
    LIMIT 1
";


$stmtExisteCancha = mysqli_prepare(
    $conexion,
    $sqlExisteCancha
);


if (!$stmtExisteCancha) {

    header(
        "Location: ../paneles.php?seccion=canchas&error=servidor"
    );

    exit();

}


mysqli_stmt_bind_param(
    $stmtExisteCancha,
    "is",
    $id_club,
    $nombre
);


mysqli_stmt_execute(
    $stmtExisteCancha
);


$resultadoExisteCancha =
    mysqli_stmt_get_result(
        $stmtExisteCancha
    );


$existeCancha =
    mysqli_fetch_assoc(
        $resultadoExisteCancha
    );


mysqli_stmt_close(
    $stmtExisteCancha
);


// ==========================================
// SI EL NOMBRE YA EXISTE
// ==========================================

if ($existeCancha) {
    header("Location: ../paneles.php?seccion=canchas&error=cancha_repetida");
    exit();
}


// ==========================================
// IMAGEN
// ==========================================

$imagen = null;


if (
    !isset($_FILES['imagen_cancha']) ||
    $_FILES['imagen_cancha']['error'] !== UPLOAD_ERR_OK
) {

    header(
        "Location: ../paneles.php?seccion=canchas&error=imagen"
    );

    exit();

}


// ==========================================
// DATOS DEL ARCHIVO
// ==========================================

$archivo = $_FILES['imagen_cancha'];

$tmp_name = $archivo['tmp_name'];

$tamano = $archivo['size'];


// ==========================================
// TAMAÑO MÁXIMO: 5 MB
// ==========================================

if ($tamano > 5 * 1024 * 1024) {

    header(
        "Location: ../paneles.php?seccion=canchas&error=imagen_peso"
    );

    exit();

}


// ==========================================
// VALIDAR IMAGEN REAL
// ==========================================

$finfo = finfo_open(
    FILEINFO_MIME_TYPE
);


$mime = finfo_file(
    $finfo,
    $tmp_name
);


finfo_close($finfo);


// ==========================================
// TIPOS PERMITIDOS
// ==========================================

$tiposPermitidos = [

    "image/jpeg" => "jpg",

    "image/png" => "png",

    "image/webp" => "webp"

];


if (!isset($tiposPermitidos[$mime])) {

    header(
        "Location: ../paneles.php?seccion=canchas&error=imagen_tipo"
    );

    exit();

}


$extension =
    $tiposPermitidos[$mime];


// ==========================================
// CREAR CARPETA
// ==========================================

$carpeta =
    "../img/canchas/";


if (!is_dir($carpeta)) {

    if (!mkdir($carpeta, 0777, true)) {

        die(
            "No se pudo crear la carpeta de imágenes."
        );

    }

}


// ==========================================
// NOMBRE ÚNICO
// ==========================================

$nombreArchivo =
    "cancha_"
    . uniqid()
    . "_"
    . time()
    . "."
    . $extension;


$rutaArchivo =
    $carpeta . $nombreArchivo;


// ==========================================
// MOVER IMAGEN
// ==========================================

if (
    !move_uploaded_file(
        $tmp_name,
        $rutaArchivo
    )
) {

    header(
        "Location: ../paneles.php?seccion=canchas&error=guardar_imagen"
    );

    exit();

}


// ==========================================
// RUTA QUE SE GUARDA EN MYSQL
// ==========================================

$imagen =
    "img/canchas/" . $nombreArchivo;


// ==========================================
// GUARDAR CANCHA
// ==========================================

$sqlCancha = "
    INSERT INTO cancha
    (
        nombre,
        estado,
        imagen,
        id_club
    )
    VALUES
    (
        ?,
        'Disponible',
        ?,
        ?
    )
";


$stmtCancha =
    mysqli_prepare(
        $conexion,
        $sqlCancha
    );


if (!$stmtCancha) {

    // Si falla MySQL, borrar imagen

    if (file_exists($rutaArchivo)) {

        unlink($rutaArchivo);

    }

    die(
        "Error al preparar la cancha: "
        . mysqli_error($conexion)
    );

}


mysqli_stmt_bind_param(
    $stmtCancha,
    "ssi",
    $nombre,
    $imagen,
    $id_club
);


// ==========================================
// EJECUTAR
// ==========================================

if (!mysqli_stmt_execute($stmtCancha)) {

    // Si falla, borrar imagen

    if (file_exists($rutaArchivo)) {

        unlink($rutaArchivo);

    }

    $error =
        mysqli_stmt_error($stmtCancha);

    mysqli_stmt_close($stmtCancha);

    die(
        "Error al guardar la cancha: "
        . $error
    );

}


mysqli_stmt_close($stmtCancha);


// ==========================================
// TODO CORRECTO
// ==========================================

header(
    "Location: ../paneles.php?seccion=canchas&ok=cancha_agregada"
);

exit();

?>