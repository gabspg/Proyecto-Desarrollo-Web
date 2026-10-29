
<?php
session_start();

require_once "../config/conexion.php";

// Verificar que los datos vengan del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

// Recibir correo o nombre de usuario
$usuarioIngresado = trim($_POST['usuario'] ?? '');
$contrasena = $_POST['contrasena'] ?? '';

// Comprobar que los campos estén completos
if ($usuarioIngresado === '' || $contrasena === '') {
    header("Location: ../index.php?error=2");
    exit;
}

// Buscar al usuario por correo o nombre de usuario
$instruccion = "
    SELECT
        usuarios.id,
        usuarios.nombre,
        usuarios.apellido,
        usuarios.contrasena,
        usuarios.estado,
        roles.nombre AS rol
    FROM usuarios
    INNER JOIN roles
        ON usuarios.rol_id = roles.id
    WHERE usuarios.correo = :correo
       OR usuarios.username = :username
    LIMIT 1
";

$consulta = $conexion->prepare($instruccion);

$consulta->execute([
    'correo' => $usuarioIngresado,
    'username' => $usuarioIngresado
]);

$usuario = $consulta->fetch(PDO::FETCH_ASSOC);

// Comprobar que el usuario exista
if (!$usuario) {
    header("Location: ../index.php?error=1");
    exit;
}

// Verificar la contraseña mediante hash
if (!password_verify($contrasena, $usuario['contrasena'])) {
    header("Location: ../index.php?error=1");
    exit;
}

// Comprobar que la cuenta esté activa
if ($usuario['estado'] !== 'ACTIVO') {
    header("Location: ../index.php?error=1");
    exit;
}

// Definir las rutas según el rol
$rutas = [
    'ADMIN' => '../administrador/panel.php',
    'TUTOR' => '../tutor/panel.php',
    'ESTUDIANTE' => '../estudiante/panel.php'
];

// Verificar que el rol sea válido
if (!isset($rutas[$usuario['rol']])) {
    header("Location: ../index.php?error=1");
    exit;
}

// Proteger la sesión
session_regenerate_id(true);

// Guardar los datos de la sesión
$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['nombre'] = $usuario['nombre'];
$_SESSION['apellido'] = $usuario['apellido'];
$_SESSION['rol'] = $usuario['rol'];

// Redirigir al panel correspondiente
header("Location: " . $rutas[$usuario['rol']]);
exit;
?>