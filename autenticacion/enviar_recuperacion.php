
<?php

require_once "../config/conexion.php";

// Permitir únicamente solicitudes del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: recuperar_contrasena.php");
    exit;
}

// Recibir el correo electrónico
$correo = trim($_POST['correo'] ?? '');

// Validar el correo
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    header("Location: recuperar_contrasena.php?mensaje=error");
    exit;
}

// Buscar al usuario
$consulta = $conexion->prepare(
    "SELECT id, correo
     FROM usuarios
     WHERE correo = :correo
     LIMIT 1"
);

$consulta->execute([
    'correo' => $correo
]);

$usuario = $consulta->fetch(PDO::FETCH_ASSOC);

// Mostrar siempre el mismo mensaje, exista o no el correo
if (!$usuario) {
    header("Location: recuperar_contrasena.php?mensaje=enviado");
    exit;
}

// Crear un token aleatorio seguro
$token = bin2hex(random_bytes(32));

// Guardar solamente el hash del token
$tokenHash = hash('sha256', $token);

// El enlace vencerá en 30 minutos
$fechaVencimiento = date(
    'Y-m-d H:i:s',
    time() + 1800
);

try {

    // Eliminar solicitudes anteriores de este usuario
    $consulta = $conexion->prepare(
        "DELETE FROM recuperacion_contrasenas
         WHERE usuario_id = :usuario_id"
    );

    $consulta->execute([
        'usuario_id' => $usuario['id']
    ]);

    // Guardar la nueva solicitud
    $consulta = $conexion->prepare(
        "INSERT INTO recuperacion_contrasenas
         (usuario_id, token_hash, fecha_vencimiento)
         VALUES
         (:usuario_id, :token_hash, :fecha_vencimiento)"
    );

    $consulta->execute([
        'usuario_id' => $usuario['id'],
        'token_hash' => $tokenHash,
        'fecha_vencimiento' => $fechaVencimiento
    ]);

    // Preparar el enlace de recuperación
    $enlace = "http://localhost/tutorias/autenticacion/restablecer_contrasena.php?token="
        . urlencode($token);

    // El enlace se enviará por correo en el siguiente paso.
    // No mostrarlo en pantalla ni guardarlo en registros.

} catch (PDOException $error) {

    error_log($error->getMessage());

    header("Location: recuperar_contrasena.php?mensaje=error");
    exit;
}

// Regresar al formulario
header("Location: recuperar_contrasena.php?mensaje=enviado");
exit;

?>