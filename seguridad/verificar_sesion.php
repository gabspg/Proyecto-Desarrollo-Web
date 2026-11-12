
<?php
require_once __DIR__ . "/sesion.php";
require_once __DIR__ . "/../config/conexion.php";

// Función para verificar el acceso de los usuarios
function verificarAcceso($rolNecesario)
{
    global $conexion;
    global $cspNonce;

    // Verificar si el usuario ha iniciado sesión
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: ../index.php");
        exit;
    }

    $usuarioId = filter_var(
        $_SESSION['usuario_id'],
        FILTER_VALIDATE_INT
    );

    $consulta = $conexion->prepare(
        "SELECT u.contrasena, r.nombre AS rol
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE u.id = :id
           AND u.estado = 'ACTIVO'"
    );
    $consulta->execute([
        "id" => $usuarioId ?: 0
    ]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (
        !$usuario ||
        !hash_equals(
            hash('sha256', (string)$usuario['contrasena']),
            (string)($_SESSION['usuario_auth_version'] ?? '')
        )
    ) {
        $_SESSION = [];
        session_destroy();
        header("Location: ../index.php?error=1");
        exit;
    }

    if (
        $usuario['rol'] !== $rolNecesario ||
        ($_SESSION['rol'] ?? '') !== $rolNecesario
    ) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Acceso denegado - TutorLink</title>

            <!-- CSS DEL INICIO DE SESIÓN -->
            <link rel="stylesheet" href="/tutorias/estilos/login.css">

            <style nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, "UTF-8") ?>">
                body {
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0;
                    font-family: 'Segoe UI', Arial, sans-serif;
                }

                .acceso-contenedor {
                    width: 90%;
                    max-width: 430px;
                    padding: 40px 30px;
                    text-align: center;
                    border-radius: 18px;
                    background: white;
                    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
                }

                .acceso-icono {
                    font-size: 55px;
                    margin-bottom: 15px;
                    color: #176b52;
                }

                .acceso-contenedor h1 {
                    font-size: 27px;
                    margin-bottom: 15px;
                    color: #176b52;
                }

                .acceso-contenedor p {
                    font-size: 15px;
                    line-height: 1.7;
                    margin-bottom: 25px;
                    color: #666;
                }

                .acceso-boton {
                    display: inline-block;
                    padding: 12px 25px;
                    border-radius: 10px;
                    background: #176b52;
                    color: white;
                    text-decoration: none;
                    font-weight: 600;
                }

                .acceso-boton:hover {
                    background: #12543f;
                    color: white;
                }
            </style>
        </head>

        <body>
            <div class="acceso-contenedor">

                <div class="acceso-icono">
                    &#128274;
                </div>

                <h1>Acceso denegado</h1>

                <p>
                    No tienes permiso para acceder a esta página.
                    Inicia sesión con una cuenta que tenga
                    los permisos correspondientes.
                </p>

                <a href="/tutorias/index.php" class="acceso-boton">
                    Volver al inicio
                </a>

            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>