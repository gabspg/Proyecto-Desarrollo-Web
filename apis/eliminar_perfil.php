<?php
require_once __DIR__ . "/bootstrap.php";
apiRequerirPost("csrf_eliminar_perfil");
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../seguridad/verificar_sesion.php";
require_once __DIR__ . "/../seguridad/eliminar_usuario.php";

$rol = $_SESSION["rol"] ?? "";
if (!in_array($rol, ["ESTUDIANTE", "TUTOR"], true)) {
    http_response_code(403);
    exit("No tienes permiso para eliminar este perfil.");
}

$usuarioId = apiRequerirRol($conexion, $rol);
$contrasena = $_POST["contrasena_actual"] ?? "";

$consulta = $conexion->prepare(
    "SELECT contrasena FROM usuarios WHERE id = :id"
);
$consulta->execute(["id" => $usuarioId]);
$hashContrasena = $consulta->fetchColumn();

if (!is_string($contrasena) || !is_string($hashContrasena) || !password_verify($contrasena, $hashContrasena)) {
    $_SESSION["error_eliminar_perfil"] = "La contraseña actual no es correcta. El perfil sigue activo.";
    header("Location: ../" . strtolower($rol) . "/panel.php");
    exit;
}

try {
    eliminarUsuarioPermanentemente($conexion, $usuarioId);
} catch (DomainException $error) {
    $_SESSION["error_eliminar_perfil"] = $error->getMessage();
    header("Location: ../" . strtolower($rol) . "/panel.php");
    exit;
} catch (Throwable $error) {
    error_log($error->getMessage());
    $_SESSION["error_eliminar_perfil"] = "No se pudo eliminar el perfil. Inténtalo nuevamente.";
    header("Location: ../" . strtolower($rol) . "/panel.php");
    exit;
}

$_SESSION = [];
$datosCookie = session_get_cookie_params();
if (ini_get("session.use_cookies")) {
    setcookie(session_name(), "", [
        "expires" => time() - 42000,
        "path" => $datosCookie["path"],
        "domain" => $datosCookie["domain"],
        "secure" => $datosCookie["secure"],
        "httponly" => $datosCookie["httponly"],
        "samesite" => $datosCookie["samesite"] ?? "Lax"
    ]);
}
session_destroy();

header("Location: ../index.php?perfil=eliminado");
exit;
