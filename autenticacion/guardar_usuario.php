
<?php

require_once "../config/conexion.php";

// Función para mostrar mensajes con diseño
function mostrarMensaje($titulo, $mensaje, $tipo = 'error', $textoBoton = 'Volver al registro', $enlace = 'registro.php')
{
    $titulo = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $mensaje = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
    $textoBoton = htmlspecialchars($textoBoton, ENT_QUOTES, 'UTF-8');
    $enlace = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');

    $clase = $tipo === 'exito' ? 'guardar-exito' : 'guardar-error';
    $icono = $tipo === 'exito' ? '&#10003;' : '!';

    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo} - TutorLink</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            padding: 12px;
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(135deg, #102B3F, #176B52);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* CONTENEDOR HORIZONTAL */

        .guardar-container {
            width: 100%;
            max-width: 900px;
            min-height: 390px;
            display: grid;
            grid-template-columns: 45% 55%;
            background: #244b3b;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.25);
        }

        /* PANEL IZQUIERDO */

        .guardar-visual {
            min-width: 0;
            padding: 30px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .guardar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #102B3F;
            font-size: 23px;
            font-weight: 700;
            margin-bottom: 22px;
        }

        .guardar-logo-icono {
            color: #176B52;
            font-size: 28px;
        }

        .guardar-visual h1 {
            font-size: 34px;
            font-weight: 700;
            color: #102B3F;
            margin: 0 0 12px;
        }

        .guardar-visual p {
            font-size: 14px;
            line-height: 1.6;
            color: #176B52;
            max-width: 280px;
            margin: 0;
        }

        /* PANEL DERECHO */

        .guardar-panel {
            min-width: 0;
            padding: 35px 30px;
            background: #244b3b;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        /* ICONO */

        .guardar-icono {
            width: 78px;
            height: 78px;
            margin-bottom: 20px;
            background: #376b58;
            border: 2px solid #69b4b1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8bd4cf;
            font-size: 40px;
            font-weight: 700;
        }

        .guardar-error .guardar-icono {
            background: #633c40;
            border-color: #e9a3a3;
            color: #ffd0d0;
        }

        /* MENSAJES */

        .guardar-panel h2 {
            font-size: 25px;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 12px;
        }

        .guardar-panel p {
            font-size: 14px;
            line-height: 1.7;
            color: #d1e3da;
            max-width: 350px;
            margin: 0 0 25px;
        }

        /* BOTÓN */

        .guardar-boton {
            width: 100%;
            max-width: 270px;
            min-height: 44px;
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            background: #69b4b1;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease,
                        transform 0.2s ease;
        }

        .guardar-boton:hover {
            background: #4b9e9a;
            transform: translateY(-2px);
        }

        .guardar-boton:focus-visible {
            outline: 2px solid #ffffff;
            outline-offset: 3px;
        }

        /* PIE */

        .guardar-footer {
            margin-top: 24px;
            font-size: 10px;
            color: #a9c5b7;
        }

        /* TABLETS */

        @media (max-width: 850px) {
            .guardar-container {
                grid-template-columns: 43% 57%;
            }

            .guardar-visual {
                padding: 20px;
            }

            .guardar-panel {
                padding: 25px 20px;
            }

            .guardar-visual h1 {
                font-size: 27px;
            }
        }

        /* CELULARES - SIEMPRE HORIZONTAL */

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .guardar-container {
                grid-template-columns: 40% 60%;
                min-height: 330px;
                border-radius: 20px;
            }

            .guardar-visual {
                padding: 12px;
            }

            .guardar-logo {
                font-size: 14px;
                gap: 4px;
                flex-wrap: wrap;
                margin-bottom: 15px;
            }

            .guardar-logo-icono {
                font-size: 19px;
            }

            .guardar-visual h1 {
                font-size: 19px;
            }

            .guardar-visual p {
                font-size: 10px;
            }

            .guardar-panel {
                padding: 20px 12px;
            }

            .guardar-icono {
                width: 55px;
                height: 55px;
                font-size: 28px;
                margin-bottom: 14px;
            }

            .guardar-panel h2 {
                font-size: 19px;
            }

            .guardar-panel p {
                font-size: 11px;
                margin-bottom: 18px;
            }

            .guardar-boton {
                font-size: 11px;
                padding: 10px;
            }

            .guardar-footer {
                font-size: 9px;
            }
        }

        /* CELULARES PEQUEÑOS */

        @media (max-width: 400px) {
            .guardar-container {
                grid-template-columns: 35% 65%;
            }

            .guardar-visual {
                padding: 8px;
            }

            .guardar-logo {
                font-size: 12px;
            }

            .guardar-visual h1 {
                font-size: 17px;
            }

            .guardar-visual p {
                font-size: 9px;
            }

            .guardar-panel {
                padding: 15px 9px;
            }

            .guardar-panel h2 {
                font-size: 17px;
            }
        }
    </style>
</head>

<body>

    <main class="guardar-container {$clase}">

        <!-- PANEL IZQUIERDO -->

        <section class="guardar-visual">

            <div class="guardar-logo">
                <span class="guardar-logo-icono">&#10022;</span>
                TutorLink
            </div>

            <h1>¡Bienvenido!</h1>

            <p>
                Tu espacio para aprender,
                compartir conocimientos
                y crecer académicamente.
            </p>

        </section>

        <!-- PANEL DERECHO -->

        <section class="guardar-panel">

            <div class="guardar-icono">
                {$icono}
            </div>

            <h2>{$titulo}</h2>

            <p>{$mensaje}</p>

            <a href="{$enlace}" class="guardar-boton">
                {$textoBoton}
            </a>

            <div class="guardar-footer">
                TutorLink | Sistema de Tutorías Académicas
            </div>

        </section>

    </main>

</body>
</html>
HTML;

    exit;
}


// Solo permitir datos enviados desde el formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: registro.php");
    exit;
}

// Recibir los datos del formulario
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$username = trim($_POST['username'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$contrasena = $_POST['contrasena'] ?? '';
$confirmar = $_POST['confirmar_contrasena'] ?? '';
$rol = $_POST['rol'] ?? '';
$carrera_id = $_POST['carrera_id'] ?? '';

// Comprobar que todos los campos estén completos
if (
    $nombre === '' ||
    $apellido === '' ||
    $username === '' ||
    $correo === '' ||
    $contrasena === '' ||
    $confirmar === '' ||
    $rol === '' ||
    $carrera_id === ''
) {
    mostrarMensaje(
        "Campos incompletos",
        "Debes completar todos los campos."
    );
}

// Validar correo electrónico
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    mostrarMensaje(
        "Correo inválido",
        "El correo electrónico no es válido."
    );
}

// Validar longitud de los campos
if (
    strlen($nombre) > 50 ||
    strlen($apellido) > 50 ||
    strlen($username) > 20 ||
    strlen($correo) > 150
) {
    mostrarMensaje(
        "Datos incorrectos",
        "Uno de los campos supera la longitud permitida."
    );
}

// Comprobar contraseña
if (strlen($contrasena) < 8) {
    mostrarMensaje(
        "Contraseña inválida",
        "La contraseña debe tener al menos 8 caracteres."
    );
}

if ($contrasena !== $confirmar) {
    mostrarMensaje(
        "Contraseñas diferentes",
        "Las contraseñas no coinciden."
    );
}

// Permitir únicamente estudiantes y tutores
if ($rol !== 'ESTUDIANTE' && $rol !== 'TUTOR') {
    mostrarMensaje(
        "Tipo de usuario inválido",
        "El tipo de usuario no es válido."
    );
}

// Comprobar que la carrera exista
$consulta = $conexion->prepare(
    "SELECT id FROM carreras WHERE id = :id"
);

$consulta->execute([
    'id' => $carrera_id
]);

if (!$consulta->fetch()) {
    mostrarMensaje(
        "Carrera inválida",
        "La carrera seleccionada no existe."
    );
}

// Obtener el ID del rol seleccionado
$consulta = $conexion->prepare(
    "SELECT id FROM roles WHERE nombre = :nombre"
);

$consulta->execute([
    'nombre' => $rol
]);

$datosRol = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$datosRol) {
    mostrarMensaje(
        "Rol inválido",
        "El rol seleccionado no existe."
    );
}

// Comprobar si el correo o usuario ya están registrados
$consulta = $conexion->prepare(
    "SELECT id
     FROM usuarios
     WHERE correo = :correo OR username = :username"
);

$consulta->execute([
    'correo' => $correo,
    'username' => $username
]);

if ($consulta->fetch()) {
    mostrarMensaje(
        "Usuario existente",
        "El correo o nombre de usuario ya está registrado."
    );
}

// Definir el estado según el rol
$estado = 'ACTIVO';

if ($rol === 'TUTOR') {
    $estado = 'PENDIENTE';
}

// Proteger la contraseña
$contrasenaSegura = password_hash(
    $contrasena,
    PASSWORD_DEFAULT
);

// Guardar el usuario
$instruccion = "INSERT INTO usuarios
    (rol_id, carrera_id, nombre, apellido,
     username, correo, contrasena, estado)
    VALUES
    (:rol_id, :carrera_id, :nombre, :apellido,
     :username, :correo, :contrasena, :estado)";

$consulta = $conexion->prepare($instruccion);

try {
    $consulta->execute([
        'rol_id' => $datosRol['id'],
        'carrera_id' => $carrera_id,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'username' => $username,
        'correo' => $correo,
        'contrasena' => $contrasenaSegura,
        'estado' => $estado
    ]);
} catch (PDOException $error) {
    // Evitar mostrar información interna de MySQL
    error_log($error->getMessage());

    mostrarMensaje(
        "Error al registrar",
        "No se pudo crear la cuenta. Verifica los datos e inténtalo nuevamente."
    );
}

// Mostrar confirmación
if ($rol === 'TUTOR') {
    mostrarMensaje(
        "¡Registro exitoso!",
        "Tu cuenta fue creada correctamente. Debes esperar la aprobación del administrador.",
        "exito",
        "Volver al inicio de sesión",
        "../index.php"
    );
}

mostrarMensaje(
    "¡Registro exitoso!",
    "Tu cuenta fue creada correctamente.",
    "exito",
    "Iniciar sesión",
    "../index.php"
);

?>