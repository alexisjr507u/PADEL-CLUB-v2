<?php

session_start();

require_once "../config/conexion.php";
require_once "../config/no_cache.php";
require_once "csrf.php";


/* =========================================================
   SEGURIDAD
   ========================================================= */

if (
    !isset($_SESSION['id_usuario']) ||
    $_SESSION['id_rol'] != 2
) {
    header("Location: ../login.php");
    exit;
}


/* =========================================================
   SOLO POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: ../paneles.php?seccion=torneos");
    exit;
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../paneles.php?seccion=torneos");
    exit;

}


/* =========================================================
   DATOS RECIBIDOS
   ========================================================= */

$nombre = trim($_POST['nombre'] ?? '');
$lugar = trim($_POST['lugar'] ?? '');

$fecha_inicio = $_POST['fecha_inicio'] ?? '';
$hora_inicio = $_POST['hora_inicio'] ?? '';

$fecha_fin = $_POST['fecha_fin'] ?? '';
$hora_fin = $_POST['hora_fin'] ?? '';

$categoria = trim($_POST['categoria'] ?? '');
$tipo = trim($_POST['tipo'] ?? '');

$max_participantes = filter_input(
    INPUT_POST,
    'max_participantes',
    FILTER_VALIDATE_INT
);

$estado = trim($_POST['estado'] ?? '');

$id_usuario = intval($_SESSION['id_usuario']);


/* =========================================================
   VALIDAR NOMBRE
   ========================================================= */

if ($nombre === '') {

    header(
        "Location: ../paneles.php?seccion=torneos&error=nombre"
    );

    exit;

}

if (mb_strlen($nombre) > 100) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=nombre_largo"
    );

    exit;

}




/* =========================================================
   VALIDAR FECHAS
   ========================================================= */

if ($fecha_inicio === '' || $fecha_fin === '') {

    header(
        "Location: ../paneles.php?seccion=torneos&error=fechas"
    );

    exit;

}

if ($fecha_fin < $fecha_inicio) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=fecha_invalida"
    );

    exit;

}
/* =========================================================
   VALIDAR HORAS
   ========================================================= */

if ($hora_inicio === '' || $hora_fin === '') {

    header(
        "Location: ../paneles.php?seccion=torneos&error=horas"
    );

    exit;

}


/* =========================================================
   SI ES EL MISMO DÍA
   LA HORA FINAL NO PUEDE SER ANTERIOR
   ========================================================= */

if (
    $fecha_inicio === $fecha_fin &&
    $hora_fin <= $hora_inicio
) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=hora_invalida"
    );

    exit;

}


/* =========================================================
   VALIDAR CATEGORÍA
   ========================================================= */

$categoriasPermitidas = [
    'Principiante',
    '7ta',
    '6ta',
    '5ta',
    '4ta',
    '3ra',
    '2da',
    '1ra'
];

if (!in_array($categoria, $categoriasPermitidas, true)) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=categoria"
    );

    exit;

}


/* =========================================================
   BUSCAR ID DE CATEGORÍA
   ========================================================= */

$sqlCategoria = "
    SELECT id_categoria
    FROM categoria
    WHERE nombre = ?
    LIMIT 1
";

$stmtCategoria = mysqli_prepare(
    $conexion,
    $sqlCategoria
);

if (!$stmtCategoria) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=servidor"
    );

    exit;

}

mysqli_stmt_bind_param(
    $stmtCategoria,
    "s",
    $categoria
);

mysqli_stmt_execute($stmtCategoria);

$resultadoCategoria =
    mysqli_stmt_get_result($stmtCategoria);

$datosCategoria =
    mysqli_fetch_assoc($resultadoCategoria);

mysqli_stmt_close($stmtCategoria);


if (!$datosCategoria) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=categoria_no_encontrada"
    );

    exit;

}

$id_categoria =
    intval($datosCategoria['id_categoria']);


/* =========================================================
   VALIDAR TIPO
   ========================================================= */

$tiposPermitidos = [
    'Individual',
    'Parejas',
    'Mixto'
];

if (!in_array($tipo, $tiposPermitidos, true)) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=tipo"
    );

    exit;

}


/* =========================================================
   VALIDAR PARTICIPANTES
   ========================================================= */

if (
    $max_participantes === false ||
    $max_participantes < 2 ||
    $max_participantes > 1000
) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=participantes"
    );

    exit;

}


/* =========================================================
   VALIDAR ESTADO
   ========================================================= */

$estadosPermitidos = [
    'Abierto',
    'Cerrado',
    'En curso',
    'Finalizado'
];

if (!in_array($estado, $estadosPermitidos, true)) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=estado"
    );

    exit;

}


/* =========================================================
   BUSCAR CLUB DEL ORGANIZADOR
   ========================================================= */

$sqlClub = "
    SELECT
        id_club,
        direccion
    FROM club
    WHERE id_usuario = ?
    LIMIT 1
";

$stmtClub = mysqli_prepare(
    $conexion,
    $sqlClub
);

if (!$stmtClub) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=servidor"
    );

    exit;

}

mysqli_stmt_bind_param(
    $stmtClub,
    "i",
    $id_usuario
);

mysqli_stmt_execute($stmtClub);

$resultadoClub =
    mysqli_stmt_get_result($stmtClub);

$datosClub =
    mysqli_fetch_assoc($resultadoClub);

mysqli_stmt_close($stmtClub);


if (!$datosClub) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=club_no_encontrado"
    );

    exit;

}

$id_club =
    intval($datosClub['id_club']);

$lugar =
    trim($datosClub['direccion'] ?? '');


/* =========================================================
   IMAGEN
   ========================================================= */

$rutaImagen = null;

if (
    isset($_FILES['imagen']) &&
    $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE
) {

    if (
        $_FILES['imagen']['error'] !== UPLOAD_ERR_OK
    ) {

        header(
            "Location: ../paneles.php?seccion=torneos&error=imagen"
        );

        exit;

    }


    /* ---------------------------------------------
       TAMAÑO
       --------------------------------------------- */

    if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {

        header(
            "Location: ../paneles.php?seccion=torneos&error=imagen_peso"
        );

        exit;

    }


    /* ---------------------------------------------
       TIPO MIME
       --------------------------------------------- */

    $tiposImagen = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    $mime = finfo_file(
        $finfo,
        $_FILES['imagen']['tmp_name']
    );

    finfo_close($finfo);


    if (!isset($tiposImagen[$mime])) {

        header(
            "Location: ../paneles.php?seccion=torneos&error=imagen_tipo"
        );

        exit;

    }


    /* ---------------------------------------------
       CREAR CARPETA
       --------------------------------------------- */

    $carpetaImagenes = "../img/torneos/";

    if (!is_dir($carpetaImagenes)) {

        if (!mkdir($carpetaImagenes, 0755, true)) {

            header(
                "Location: ../paneles.php?seccion=torneos&error=carpeta_imagen"
            );

            exit;

        }

    }


    /* ---------------------------------------------
       NOMBRE ÚNICO
       --------------------------------------------- */

    $extension = $tiposImagen[$mime];

    $nombreArchivo =
        'torneo_' .
        bin2hex(random_bytes(8)) .
        '.' .
        $extension;

    $rutaFisica =
        $carpetaImagenes .
        $nombreArchivo;


    /* ---------------------------------------------
       MOVER IMAGEN
       --------------------------------------------- */

    if (
        !move_uploaded_file(
            $_FILES['imagen']['tmp_name'],
            $rutaFisica
        )
    ) {

        header(
            "Location: ../paneles.php?seccion=torneos&error=imagen_guardar"
        );

        exit;

    }


    $rutaImagen =
        "img/torneos/" .
        $nombreArchivo;

}


/* =========================================================
   INSERTAR TORNEO
   ========================================================= */

$sql = "
   INSERT INTO torneos (
    nombre,
    imagen,
    fecha_inicio,
    hora_inicio,
    fecha_fin,
    hora_fin,
    lugar,
    id_club,
    id_categoria,
    tipo,
    max_participantes,
    estado
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = mysqli_prepare(
    $conexion,
    $sql
);

if (!$stmt) {

    if ($rutaImagen !== null) {

        $archivo = "../" . $rutaImagen;

        if (file_exists($archivo)) {
            unlink($archivo);
        }

    }

    header(
        "Location: ../paneles.php?seccion=torneos&error=servidor"
    );

    exit;

}


mysqli_stmt_bind_param(
    $stmt,
    "sssssssiisis",
    $nombre,
    $rutaImagen,
    $fecha_inicio,
    $hora_inicio,
    $fecha_fin,
    $hora_fin,
    $lugar,
    $id_club,
    $id_categoria,
    $tipo,
    $max_participantes,
    $estado
);
/* =========================================================
   EJECUTAR
   ========================================================= */

if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    header(
        "Location: ../paneles.php?seccion=torneos&ok=torneo_creado"
    );

    exit;

}


/* =========================================================
   ERROR
   ========================================================= */

mysqli_stmt_close($stmt);


if ($rutaImagen !== null) {

    $archivo = "../" . $rutaImagen;

    if (file_exists($archivo)) {
        unlink($archivo);
    }

}


header(
    "Location: ../paneles.php?seccion=torneos&error=no_se_pudo_crear"
);

exit;