<?php

require_once __DIR__ . '/../config/sesion.php';
require_once "../config/conexion.php";
require_once "../config/no_cache.php";
require_once "csrf.php";


/* =========================================================
   SEGURIDAD
   ========================================================= */

if (
    !isset($_SESSION['id_usuario']) ||
    !in_array((int) ($_SESSION['id_rol'] ?? 0), [1, 2], true)
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

$id_torneo = filter_input(
    INPUT_POST,
    'id_torneo',
    FILTER_VALIDATE_INT
);

$nombre = trim($_POST['nombre'] ?? '');

$fecha_inicio = $_POST['fecha_inicio'] ?? '';
$hora_inicio = $_POST['hora_inicio'] ?? '';

$fecha_fin = $_POST['fecha_fin'] ?? '';

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
   VALIDAR ID DE TORNEO
   ========================================================= */

if (
    $id_torneo === false ||
    $id_torneo === null ||
    $id_torneo <= 0
) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=torneo_invalido"
    );

    exit;

}


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

if ($hora_inicio === '') {

    header(
        "Location: ../paneles.php?seccion=torneos&error=horas"
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
    $max_participantes === null ||
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
   TORNEO A EDITAR Y SU CLUB
   - Organizador: el torneo tiene que ser de un club suyo (puede tener más de uno).
   - Administrador: puede editar cualquier torneo.
   El club y el lugar no cambian: salen del club del propio torneo.
   ========================================================= */

$stmtTorneoActual = mysqli_prepare(
    $conexion,
    "SELECT t.imagen, t.fecha_inicio, t.id_categoria, t.tipo, t.id_club,
            c.direccion, c.id_usuario AS id_dueno
     FROM torneos t
     INNER JOIN club c ON c.id_club = t.id_club
     WHERE t.id_torneo = ?
     LIMIT 1"
);

if (!$stmtTorneoActual) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=servidor"
    );

    exit;

}

mysqli_stmt_bind_param($stmtTorneoActual, "i", $id_torneo);
mysqli_stmt_execute($stmtTorneoActual);

$torneoActual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTorneoActual));

mysqli_stmt_close($stmtTorneoActual);


if (
    !$torneoActual ||
    ((int) $_SESSION['id_rol'] === 2 && (int) $torneoActual['id_dueno'] !== $id_usuario)
) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=torneo_no_encontrado"
    );

    exit;

}

$id_club = (int) $torneoActual['id_club'];

$lugar = trim($torneoActual['direccion'] ?? '');

/* =========================================================
   CATEGORÍA Y TIPO NO SE PUEDEN CAMBIAR CON PARTICIPANTES

   Si el torneo ya tiene gente inscripta (confirmada, esperando a su compañero o descalificada,
   que sigue figurando en el torneo), cambiar la categoría o el tipo dejaría inscripciones que no
   corresponden (otra categoría, o individuales en un torneo de parejas). Se vuelve a comprobar
   acá en el servidor, no solo en el formulario.
   ========================================================= */

$stmtInscriptos = mysqli_prepare(
    $conexion,
    "SELECT COUNT(*) AS n FROM inscripcion
     WHERE id_torneo = ? AND estado IN ('Confirmada', 'Pendiente', 'Descalificada')"
);
mysqli_stmt_bind_param($stmtInscriptos, "i", $id_torneo);
mysqli_stmt_execute($stmtInscriptos);
$hayInscriptos = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmtInscriptos))['n'] > 0;
mysqli_stmt_close($stmtInscriptos);

if (
    $hayInscriptos &&
    (
        (int) $torneoActual['id_categoria'] !== $id_categoria ||
        $torneoActual['tipo'] !== $tipo
    )
) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=categoria_tipo_bloqueado"
    );

    exit;

}

/* =========================================================
   LA FECHA DE INICIO NO PUEDE SER ANTERIOR A HOY
   Solo se exige si la fecha cambió: un torneo que ya empezó puede
   seguir editándose (estado, categoría...) sin tocar su fecha.
   "Hoy" sale de MySQL (hora de este equipo); PHP trabaja en UTC.
   ========================================================= */

$fechaInicioValida = DateTime::createFromFormat('!Y-m-d', $fecha_inicio);

if (!$fechaInicioValida || $fechaInicioValida->format('Y-m-d') !== $fecha_inicio) {

    header(
        "Location: ../paneles.php?seccion=torneos&error=fechas"
    );

    exit;

}

if ($fecha_inicio !== $torneoActual['fecha_inicio']) {

    $hoy = mysqli_fetch_row(mysqli_query($conexion, "SELECT CURDATE()"))[0];

    if ($fecha_inicio < $hoy) {

        header(
            "Location: ../paneles.php?seccion=torneos&error=fecha_pasada"
        );

        exit;

    }

}

$imagenAnterior = $torneoActual['imagen'];

$rutaImagen = $imagenAnterior;

$imagenAnteriorAEliminar = null;


/* =========================================================
   IMAGEN NUEVA (OPCIONAL)
   ========================================================= */

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

    /* La imagen vieja se borra recién si el UPDATE sale bien */
    $imagenAnteriorAEliminar = $imagenAnterior;

}


/* =========================================================
   ACTUALIZAR TORNEO
   ========================================================= */

$sql = "
    UPDATE torneos
    SET
        nombre = ?,
        imagen = ?,
        fecha_inicio = ?,
        hora_inicio = ?,
        fecha_fin = ?,
        lugar = ?,
        id_categoria = ?,
        tipo = ?,
        max_participantes = ?,
        estado = ?
    WHERE id_torneo = ?
      AND id_club = ?
";

$stmt = mysqli_prepare(
    $conexion,
    $sql
);

if (!$stmt) {

    if (
        $imagenAnteriorAEliminar === null &&
        $rutaImagen !== $imagenAnterior &&
        $rutaImagen !== null
    ) {

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
    "ssssssisisii",
    $nombre,
    $rutaImagen,
    $fecha_inicio,
    $hora_inicio,
    $fecha_fin,
    $lugar,
    $id_categoria,
    $tipo,
    $max_participantes,
    $estado,
    $id_torneo,
    $id_club
);


/* =========================================================
   EJECUTAR
   ========================================================= */

if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    actividadTorneoModificado($conexion, $id_torneo);

    /* Si se subió una imagen nueva, ahora sí se borra la vieja */

    if (
        $imagenAnteriorAEliminar !== null &&
        $imagenAnteriorAEliminar !== ''
    ) {

        $archivoViejo = "../" . $imagenAnteriorAEliminar;

        if (file_exists($archivoViejo)) {
            unlink($archivoViejo);
        }

    }

    header(
        "Location: ../paneles.php?seccion=torneos&ok=torneo_actualizado"
    );

    exit;

}


/* =========================================================
   ERROR
   ========================================================= */

mysqli_stmt_close($stmt);

if (
    $imagenAnteriorAEliminar !== null &&
    $rutaImagen !== $imagenAnterior
) {

    $archivo = "../" . $rutaImagen;

    if (file_exists($archivo)) {
        unlink($archivo);
    }

}

header(
    "Location: ../paneles.php?seccion=torneos&error=no_se_pudo_actualizar"
);

exit;
