<?php

require_once __DIR__ . "/../seguridad/sesion.php";

function apiRequerirPost(
    string $claveCsrf,
    string $campoToken = "token"
): void
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        header("Allow: POST");
        exit("Método no permitido.");
    }

    foreach ($_POST as $valor) {
        if (!is_string($valor)) {
            http_response_code(400);
            exit("Datos no válidos.");
        }
    }

    $token = $_POST[$campoToken] ?? "";
    $tokenSesion = $_SESSION[$claveCsrf] ?? "";

    if (
        !is_string($token) ||
        !is_string($tokenSesion) ||
        $tokenSesion === "" ||
        !hash_equals($tokenSesion, $token)
    ) {
        http_response_code(403);
        exit("Solicitud no válida.");
    }
}

function apiRequerirRol(PDO $conexion, string $rol): int
{
    $usuarioId = filter_var(
        $_SESSION["usuario_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$usuarioId || empty($_SESSION["rol"])) {
        http_response_code(401);
        exit("Debes iniciar sesión.");
    }

    $consulta = $conexion->prepare(
        "SELECT u.contrasena
         FROM usuarios u
         INNER JOIN roles r ON r.id = u.rol_id
         WHERE u.id = :id
           AND r.nombre = :rol
           AND u.estado = 'ACTIVO'"
    );
    $consulta->execute([
        "id" => $usuarioId,
        "rol" => $rol
    ]);
    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if (
        !$usuario ||
        ($_SESSION["rol"] ?? "") !== $rol ||
        !hash_equals(
            hash("sha256", (string)($usuario["contrasena"] ?? "")),
            (string)($_SESSION["usuario_auth_version"] ?? "")
        )
    ) {
        http_response_code(403);
        exit("No tienes permiso para realizar esta acción.");
    }

    return (int)$usuarioId;
}

function apiToken(string $clave): string
{
    if (empty($_SESSION[$clave])) {
        $_SESSION[$clave] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$clave];
}
