<?php

include("../config/no_cache.php");
include("../config/conexion.php");
require_once("csrf.php");


// ==========================================
// VERIFICAR QUE VENGA DEL FORMULARIO
// ==========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: ../registro_organizador.php");
    exit();

}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {

    header("Location: ../registro_organizador.php");
    exit();

}


// ==========================================
// DATOS DEL ORGANIZADOR
// ==========================================

$nombre =
    trim($_POST["nombre"] ?? "");

$apellido =
    trim($_POST["apellido"] ?? "");

$fecha_nacimiento =
    $_POST["fecha_nacimiento"] ?? "";

$telefono =
    trim($_POST["telefono"] ?? "");

$email =
    trim($_POST["email"] ?? "");

$password =
    $_POST["password"] ?? "";

$confirmar_password =
    $_POST["confirmar_password"] ?? "";


// ==========================================
// DATOS DEL CLUB
// ==========================================

$nombre_club = trim($_POST["nombre_club"] ?? "");
$direccion = trim($_POST["direccion"] ?? "");
$telefono_club = trim($_POST["telefono_club"] ?? "");

$manana_desde = trim(
    $_POST["horario_manana_desde"] ?? ""
);

$manana_hasta = trim(
    $_POST["horario_manana_hasta"] ?? ""
);

$tarde_desde = trim(
    $_POST["horario_tarde_desde"] ?? ""
);

$tarde_hasta = trim(
    $_POST["horario_tarde_hasta"] ?? ""
);

$horario_manana = $manana_desde . " - " . $manana_hasta;
$horario_tarde = $tarde_desde . " - " . $tarde_hasta;

$cantidad_canchas = intval(
    $_POST["cantidad_canchas"] ?? 0
);


// ==========================================
// DATOS DE LAS CANCHAS
// ==========================================

$nombres_canchas =
    $_POST["nombre_cancha"] ?? [];


// ==========================================
// VALIDAR CAMPOS OBLIGATORIOS
// ==========================================

if (
    empty($nombre) ||
    empty($apellido) ||
    empty($fecha_nacimiento) ||
    empty($telefono) ||
    empty($email) ||
    empty($password) ||
    empty($confirmar_password) ||
    empty($nombre_club) ||
    empty($direccion) ||
    empty($telefono_club) ||
empty($manana_desde) ||
empty($manana_hasta) ||
empty($tarde_desde) ||
empty($tarde_hasta)
    
) {

    die("Todos los campos son obligatorios.");

}


// ==========================================
// VALIDAR EMAIL
// ==========================================

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    die("El correo electrónico no es válido.");

}


// ==========================================
// VALIDAR FECHA DE NACIMIENTO
// ==========================================

$fechaNacimiento =
    DateTime::createFromFormat(
        "Y-m-d",
        $fecha_nacimiento
    );


if (
    !$fechaNacimiento ||
    $fechaNacimiento->format("Y-m-d")
    !== $fecha_nacimiento
) {

    die("La fecha de nacimiento no es válida.");

}


// ==========================================
// CALCULAR EDAD
// ==========================================

$hoy =
    new DateTime();

$edad =
    $hoy->diff($fechaNacimiento)->y;


if ($edad < 18) {

    die(
        "Debes tener 18 años o más para registrarte como organizador."
    );

}


// ==========================================
// VALIDAR TELEFONO
// ==========================================

if (!preg_match('/^[0-9]{10}$/', $telefono)) {

    die(
        "El teléfono debe tener exactamente 10 números y no debe incluir +54."
    );

}


// ==========================================
// VALIDAR TELEFONO DEL CLUB
// ==========================================

if (
    !preg_match(
        '/^[0-9]{10}$/',
        $telefono_club
    )
) {

    die(
        "El teléfono del club debe tener exactamente 10 números y no debe incluir +54."
    );

}


// ==========================================
// VALIDAR CONTRASEÑA
// ==========================================

// Mínimo 8 caracteres
if (strlen($password) < 8) {

    die(
        "La contraseña debe tener al menos 8 caracteres."
    );

}


// Al menos una mayúscula
if (!preg_match('/[A-Z]/', $password)) {

    die(
        "La contraseña debe tener al menos una letra mayúscula."
    );

}


// Al menos un número
if (!preg_match('/[0-9]/', $password)) {

    die(
        "La contraseña debe tener al menos un número."
    );

}


// Al menos un carácter especial
if (!preg_match('/[^A-Za-z0-9]/', $password)) {

    die(
        "La contraseña debe tener al menos un carácter especial."
    );

}


// ==========================================
// CONFIRMAR CONTRASEÑA
// ==========================================

if ($password !== $confirmar_password) {

    die(
        "Las contraseñas no coinciden."
    );

}


// ==========================================
// VALIDAR CANTIDAD DE CANCHAS
// ==========================================

if ($cantidad_canchas < 1) {

    die(
        "La cantidad de canchas no es válida."
    );

}


if ($cantidad_canchas > 20) {

    die(
        "La cantidad máxima de canchas es 20."
    );

}


// ==========================================
// COMPROBAR CANCHAS
// ==========================================

if (
    count($nombres_canchas)
    != $cantidad_canchas
) {

    die(
        "La cantidad de nombres de canchas no coincide."
    );

}


// ==========================================
// CONTRASEÑA HASH
// ==========================================

$pass_hash =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );


// ==========================================
// INICIAR TRANSACCIÓN
// ==========================================

mysqli_begin_transaction($conexion);


try {


    // ======================================
    // 1. GUARDAR SOLICITUD
    // ======================================

        $sql = "
    INSERT INTO solicitud_organizador
    (
        nombre,
        apellido,
        fecha_nacimiento,
        telefono,
        email,
        pass,
        nombre_club,
        direccion,
        telefono_club,
        horario_manana,
        horario_tarde,
        cantidad_canchas
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";


    $stmt =
        mysqli_prepare(
            $conexion,
            $sql
        );


    if (!$stmt) {

        throw new Exception(
            "Error al preparar la solicitud: "
            . mysqli_error($conexion)
        );

    }


        mysqli_stmt_bind_param(
    $stmt,
    "sssssssssssi",
    $nombre,
    $apellido,
    $fecha_nacimiento,
    $telefono,
    $email,
    $pass_hash,
    $nombre_club,
    $direccion,
    $telefono_club,
    $horario_manana,
    $horario_tarde,
    $cantidad_canchas
);


    if (
        !mysqli_stmt_execute($stmt)
    ) {

        throw new Exception(
            "Error al guardar la solicitud: "
            . mysqli_stmt_error($stmt)
        );

    }


    // ID de solicitud

    $id_solicitud =
        mysqli_insert_id($conexion);


    mysqli_stmt_close($stmt);


    // ======================================
    // 2. CARPETA DE IMÁGENES
    // ======================================

    $carpeta =
        "../img/canchas/";


    if (!is_dir($carpeta)) {

        if (
            !mkdir(
                $carpeta,
                0777,
                true
            )
        ) {

            throw new Exception(
                "No se pudo crear la carpeta de imágenes."
            );

        }

    }


    // ======================================
    // 3. PREPARAR INSERT DE CANCHAS
    // ======================================

    /*
        IMPORTANTE:

        Ya NO enviamos estado.

        La columna estado sigue existiendo
        en la base de datos.

        MySQL utilizará su valor
        predeterminado: Disponible.
    */

    $sqlCancha = "
        INSERT INTO solicitud_cancha
        (
            id_solicitud,
            nombre,
            imagen
        )
        VALUES (?, ?, ?)
    ";


    $stmtCancha =
        mysqli_prepare(
            $conexion,
            $sqlCancha
        );


    if (!$stmtCancha) {

        throw new Exception(
            "Error al preparar las canchas: "
            . mysqli_error($conexion)
        );

    }


    // ======================================
    // 4. GUARDAR CANCHAS
    // ======================================

    for (
        $i = 0;
        $i < $cantidad_canchas;
        $i++
    ) {


        $nombre_cancha =
            trim(
                $nombres_canchas[$i] ?? ""
            );


        // ------------------------------
        // VALIDAR NOMBRE
        // ------------------------------

        if (
            empty($nombre_cancha)
        ) {

            throw new Exception(
                "Falta el nombre de la cancha "
                . ($i + 1)
            );

        }


        if (
            strlen($nombre_cancha) > 50
        ) {

            throw new Exception(
                "El nombre de la cancha "
                . ($i + 1)
                . " es demasiado largo."
            );

        }


        // ==================================
        // IMAGEN
        // ==================================

        $imagen = null;


        if (
            isset(
                $_FILES["imagen_cancha"]["error"][$i]
            ) &&
            $_FILES["imagen_cancha"]["error"][$i]
            === UPLOAD_ERR_OK
        ) {


            $tmp_name =
                $_FILES["imagen_cancha"]["tmp_name"][$i];


            $nombre_original =
                $_FILES["imagen_cancha"]["name"][$i];


            // ------------------------------
            // TAMAÑO MÁXIMO
            // ------------------------------

            $tamano =
                $_FILES["imagen_cancha"]["size"][$i];


            if (
                $tamano > 5 * 1024 * 1024
            ) {

                throw new Exception(
                    "La imagen de la cancha "
                    . ($i + 1)
                    . " supera los 5 MB."
                );

            }


            // ------------------------------
            // VALIDAR MIME REAL
            // ------------------------------

            $finfo =
                finfo_open(FILEINFO_MIME_TYPE);


            $mime =
                finfo_file(
                    $finfo,
                    $tmp_name
                );


            finfo_close($finfo);


            $tiposPermitidos = [

                "image/jpeg" => "jpg",

                "image/png" => "png",

                "image/webp" => "webp"

            ];


            if (
                !isset(
                    $tiposPermitidos[$mime]
                )
            ) {

                throw new Exception(
                    "La imagen de la cancha "
                    . ($i + 1)
                    . " no tiene un formato válido."
                );

            }


            $extension =
                $tiposPermitidos[$mime];


            // ------------------------------
            // NOMBRE ÚNICO
            // ------------------------------

            $nombre_archivo =
                uniqid(
                    "cancha_",
                    true
                )
                . "."
                . $extension;


            $destino =
                $carpeta
                . $nombre_archivo;


            // ------------------------------
            // MOVER IMAGEN
            // ------------------------------

            if (
                !move_uploaded_file(
                    $tmp_name,
                    $destino
                )
            ) {

                throw new Exception(
                    "No se pudo guardar la imagen de la cancha "
                    . ($i + 1)
                );

            }


            // Ruta para BD

            $imagen =
                "img/canchas/"
                . $nombre_archivo;

        } else {

            throw new Exception(
                "Debes seleccionar una imagen para la cancha "
                . ($i + 1)
            );

        }


        // ==================================
        // INSERTAR CANCHA
        // ==================================

        mysqli_stmt_bind_param(
            $stmtCancha,
            "iss",
            $id_solicitud,
            $nombre_cancha,
            $imagen
        );


        if (
            !mysqli_stmt_execute(
                $stmtCancha
            )
        ) {

            throw new Exception(
                "Error al guardar la cancha "
                . ($i + 1)
                . ": "
                . mysqli_stmt_error(
                    $stmtCancha
                )
            );

        }

    }


    mysqli_stmt_close(
        $stmtCancha
    );


    // ======================================
    // CONFIRMAR
    // ======================================

    mysqli_commit(
        $conexion
    );


    // ======================================
    // MENSAJE
    // ======================================

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Solicitud enviada
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

</head>


<body>

<div class="contenedor">

    <div class="login-box">

        <div class="mensaje-exito">

            <h2>
                ¡Solicitud enviada!
            </h2>

            <p>
                Tu solicitud de registro como
                organizador fue enviada correctamente.
            </p>

            <p>
                Un administrador revisará tus datos
                y decidirá si acepta o rechaza la solicitud.
            </p>

            <a
                href="../login.php"
                class="btn-exito"
            >
                Volver al inicio
            </a>

        </div>

    </div>

</div>

</body>

</html>

<?php


} catch (Exception $e) {


    // ======================================
    // DESHACER TODO
    // ======================================

    mysqli_rollback(
        $conexion
    );

    error_log("Error al guardar solicitud de organizador: " . $e->getMessage());

    die(
        "Ocurrió un error al guardar la solicitud. Intentá nuevamente."
    );

}

?>