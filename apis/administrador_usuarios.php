<?php
require_once __DIR__ . "/bootstrap.php";
apiRequerirPost("token_usuarios");
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../seguridad/verificar_sesion.php";
require_once __DIR__ . "/../seguridad/eliminar_usuario.php";
$usuarioSesionId = apiRequerirRol($conexion, "ADMIN");
apiToken("token_usuarios");
$error = "";
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";
    $token = $_POST["token"] ?? "";
    $tokenSesion = $_SESSION["token_usuarios"];

    // Validar token de seguridad
    if (
        !is_string($token) ||
        !hash_equals($tokenSesion, $token)
    ) {
        exit("Solicitud no válida.");
    }

    // Validar ID del usuario
    $usuarioId = filter_input(
        INPUT_POST,
        "usuario_id",
        FILTER_VALIDATE_INT
    );

    if (!$usuarioId) {
        exit("Usuario no válido.");
    }

    if ($accion === "eliminar") {
        try {
            eliminarUsuarioPermanentemente($conexion, (int)$usuarioId);
            $mensaje = "El perfil y sus datos relacionados se eliminaron permanentemente.";
        } catch (DomainException $error) {
            $error = $error->getMessage();
        } catch (Throwable $error) {
            error_log($error->getMessage());
            $error = "No se pudo eliminar el perfil. Inténtalo nuevamente.";
        }
    }


    /* ========================================================
       5. APROBAR O RECHAZAR TUTORES
       ======================================================== */

    if ($accion === "aprobar" || $accion === "rechazar") {

        $nuevoEstado = "ACTIVO";

        if ($accion === "rechazar") {
            $nuevoEstado = "INACTIVO";
        }

        $consulta = $conexion->prepare(
            "UPDATE usuarios
             INNER JOIN roles
                ON usuarios.rol_id = roles.id
             SET usuarios.estado = :estado
             WHERE usuarios.id = :usuario_id
             AND roles.nombre = 'TUTOR'
             AND usuarios.estado = 'PENDIENTE'"
        );

        $consulta->execute([
            "estado" => $nuevoEstado,
            "usuario_id" => $usuarioId
        ]);

        if ($consulta->rowCount() === 0) {
            $error = "No se encontró un tutor pendiente.";
        } else {
            $mensaje = "Estado del tutor actualizado correctamente.";
        }
    }


    /* ========================================================
       6. ACTIVAR O DESACTIVAR USUARIOS
       ======================================================== */

    if ($accion === "activar" || $accion === "desactivar") {

        $nuevoEstado = "ACTIVO";

        if ($accion === "desactivar") {
            $nuevoEstado = "INACTIVO";
        }

        $consulta = $conexion->prepare(
            "UPDATE usuarios
             INNER JOIN roles
                ON usuarios.rol_id = roles.id
             SET usuarios.estado = :estado
             WHERE usuarios.id = :usuario_id
             AND roles.nombre IN ('ESTUDIANTE', 'TUTOR')
             AND usuarios.estado IN ('ACTIVO', 'INACTIVO')"
        );

        $consulta->execute([
            "estado" => $nuevoEstado,
            "usuario_id" => $usuarioId
        ]);

        if ($consulta->rowCount() === 0) {
            $error = "No se pudo cambiar el estado del usuario.";
        } else {
            $mensaje = "Estado del usuario actualizado correctamente.";
        }
    }


    /* ========================================================
       7. REDIRECCIÓN DESPUÉS DE GUARDAR
       ======================================================== */

    if ($mensaje !== "") {

        $_SESSION["mensaje_usuarios"] = $mensaje;

        $destino = "usuarios";

        if ($accion === "aprobar" || $accion === "rechazar") {
            $destino = "tutores";
        }

        header("Location: ../administrador/usuarios.php?seccion=" . $destino);
        exit;
    }
}
if ($error !== "") {
    $_SESSION["error_usuarios"] = $error;
    header("Location: ../administrador/usuarios.php");
    exit;
}
header("Location: ../administrador/usuarios.php");
exit;
