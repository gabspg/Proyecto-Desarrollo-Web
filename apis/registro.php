
<?php
require_once __DIR__ . "/bootstrap.php";
apiRequerirPost("csrf_registro");

require_once __DIR__ . "/../config/conexion.php";

// Función para mostrar mensajes con diseño
function apiRegistroResultado($titulo, $mensaje, $tipo = 'error', $textoBoton = 'Volver al registro', $enlace = '../autenticacion/registro.php')
{
    $_SESSION["registro_resultado"] = [
        "titulo" => $titulo,
        "mensaje" => $mensaje,
        "tipo" => $tipo,
        "texto_boton" => $textoBoton,
        "enlace" => $enlace
    ];
    header("Location: ../autenticacion/registro_resultado.php");
    exit;
}

// Solo permitir datos enviados desde el formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../autenticacion/registro.php");
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
    apiRegistroResultado(
        "Campos incompletos",
        "Debes completar todos los campos."
    );
}

// Validar correo electrónico
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    apiRegistroResultado(
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
    apiRegistroResultado(
        "Datos incorrectos",
        "Uno de los campos supera la longitud permitida."
    );
}

// Comprobar contraseña
if (strlen($contrasena) < 8) {
    apiRegistroResultado(
        "Contraseña inválida",
        "La contraseña debe tener al menos 8 caracteres."
    );
}

if ($contrasena !== $confirmar) {
    apiRegistroResultado(
        "Contraseñas diferentes",
        "Las contraseñas no coinciden."
    );
}

// Permitir únicamente estudiantes y tutores
if ($rol !== 'ESTUDIANTE' && $rol !== 'TUTOR') {
    apiRegistroResultado(
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
    apiRegistroResultado(
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
    apiRegistroResultado(
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
    apiRegistroResultado(
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

    apiRegistroResultado(
        "Error al registrar",
        "No se pudo crear la cuenta. Verifica los datos e inténtalo nuevamente."
    );
}

// Mostrar confirmación
if ($rol === 'TUTOR') {
    apiRegistroResultado(
        "¡Registro exitoso!",
        "Tu cuenta fue creada correctamente. Debes esperar la aprobación del administrador.",
        "exito",
        "Volver al inicio de sesión",
        "../index.php"
    );
}

apiRegistroResultado(
    "¡Registro exitoso!",
    "Tu cuenta fue creada correctamente.",
    "exito",
    "Iniciar sesión",
    "../index.php"
);

?>