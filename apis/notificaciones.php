<?php
require_once __DIR__ . "/bootstrap.php";

$rol = $_SESSION["rol"] ?? "";
if (!in_array($rol, ["ESTUDIANTE", "TUTOR"], true)) {
    http_response_code(403);
    exit("No tienes permiso para realizar esta acción.");
}

$claveCsrf = $rol === "TUTOR"
    ? "csrf_notificaciones_tutor"
    : "csrf_notificaciones_est";
apiRequerirPost($claveCsrf);

require_once __DIR__ . "/../config/conexion.php";
$usuarioId = apiRequerirRol($conexion, $rol);

$notificacionId = filter_input(
    INPUT_POST,
    "notificacion_id",
    FILTER_VALIDATE_INT
);
$destino = $rol === "TUTOR"
    ? "../tutor/notificaciones.php"
    : "../estudiante/notificaciones.php";

if (!$notificacionId) {
    $_SESSION["error_notificaciones"] = "La notificación seleccionada no es válida.";
    header("Location: " . $destino);
    exit;
}

try {
    $stmt = $conexion->prepare(
        "DELETE FROM notificaciones
         WHERE id = :notificacion
           AND usuario_id = :usuario"
    );
    $stmt->execute([
        "notificacion" => $notificacionId,
        "usuario" => $usuarioId
    ]);

    $_SESSION[$stmt->rowCount() === 1
        ? "mensaje_notificaciones"
        : "error_notificaciones"] = $stmt->rowCount() === 1
            ? "Notificación eliminada correctamente."
            : "No se encontró la notificación seleccionada.";
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION["error_notificaciones"] = "No se pudo eliminar la notificación.";
}

header("Location: " . $destino);
exit;
