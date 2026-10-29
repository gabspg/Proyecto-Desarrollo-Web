
<?php

require_once "../config/conexion.php";

// Permitir únicamente datos enviados desde el formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: recuperar_contrasena.php");
    exit;
}

// Recibir los datos
$token = $_POST['token'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';
$confirmar = $_POST['confirmar_contrasena'] ?? '';

// Verificar el token
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    exit("El enlace de recuperación no es válido.");
}

// Verificar que las contraseñas estén completas
if ($contrasena === '' || $confirmar === '') {
    exit("Debes completar ambos campos.");
}

// Comprobar la longitud de la contraseña
if (strlen($contrasena) < 8) {
    exit("La contraseña debe tener al menos 8 caracteres.");
}

// Comprobar que las contraseñas coincidan
if ($contrasena !== $confirmar) {
    exit("Las contraseñas no coinciden.");
}

// Crear el hash del token
$tokenHash = hash('sha256', $token);

// Proteger la nueva contraseña
$contrasenaSegura = password_hash(
    $contrasena,
    PASSWORD_DEFAULT
);

try {

    // Iniciar transacción para evitar reutilizar el enlace
    $conexion->beginTransaction();

    // Buscar y bloquear la solicitud de recuperación
    $consulta = $conexion->prepare(
        "SELECT id, usuario_id
         FROM recuperacion_contrasenas
         WHERE token_hash = :token_hash
           AND utilizado = 0
           AND fecha_vencimiento > NOW()
         LIMIT 1
         FOR UPDATE"
    );

    $consulta->execute([
        'token_hash' => $tokenHash
    ]);

    $recuperacion = $consulta->fetch(PDO::FETCH_ASSOC);

    // Comprobar que el enlace siga siendo válido
    if (!$recuperacion) {
        $conexion->rollBack();
        exit("El enlace ha vencido o ya fue utilizado.");
    }

    // Actualizar la contraseña del usuario
    $consulta = $conexion->prepare(
        "UPDATE usuarios
         SET contrasena = :contrasena
         WHERE id = :usuario_id"
    );

    $consulta->execute([
        'contrasena' => $contrasenaSegura,
        'usuario_id' => $recuperacion['usuario_id']
    ]);

    // Invalidar todos los enlaces de recuperación del usuario
    $consulta = $conexion->prepare(
        "UPDATE recuperacion_contrasenas
         SET utilizado = 1
         WHERE usuario_id = :usuario_id"
    );

    $consulta->execute([
        'usuario_id' => $recuperacion['usuario_id']
    ]);

    // Confirmar los cambios
    $conexion->commit();

} catch (PDOException $error) {

    // Deshacer los cambios si ocurre un error
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log($error->getMessage());

    exit("No se pudo actualizar la contraseña. Inténtalo nuevamente.");
}

// Mostrar confirmación
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contraseña actualizada</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container min-vh-100 d-flex justify-content-center align-items-center">

        <div class="card shadow-sm p-4 text-center"
             style="width: 100%; max-width: 420px;">

            <h3 class="text-success mb-3">
                Contraseña actualizada
            </h3>

            <p>
                Tu contraseña se ha cambiado correctamente.
                Ahora puedes iniciar sesión con tu nueva contraseña.
            </p>

            <a href="../index.php" class="btn btn-primary">
                Iniciar sesión
            </a>

        </div>

    </div>

</body>
</html>