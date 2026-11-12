<?php
require_once __DIR__ . "/bootstrap.php";
apiRequerirPost("token_solicitudes_tutor");
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../seguridad/verificar_sesion.php";
$tutorId = apiRequerirRol($conexion, "TUTOR");
$token = apiToken("token_solicitudes_tutor");
$error = "";
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tokenRecibido = $_POST["token"] ?? "";

    if (!is_string($tokenRecibido) ||
        !hash_equals($token, $tokenRecibido)) {
        exit("Solicitud no válida.");
    }

    $solicitudId = filter_input(
        INPUT_POST,
        "solicitud_id",
        FILTER_VALIDATE_INT
    );

    $accion = $_POST["accion"] ?? "";

    if (!$solicitudId) {
        $error = "La solicitud seleccionada no es válida.";
    } elseif (!in_array($accion, ["aceptar", "rechazar"], true)) {
        $error = "Acción no permitida.";
    } else {

        try {
            $conexion->beginTransaction();

            $stmt = $conexion->prepare(
                "SELECT
                    s.id,
                    s.estudiante_id,
                    s.estado,
                    s.disponibilidad_id,
                    d.estado AS estado_disponibilidad,
                    d.cupo_actual,
                    d.cupo_maximo
                 FROM solicitudes_tutoria s
                 INNER JOIN disponibilidades d
                    ON d.id = s.disponibilidad_id
                 WHERE s.id = :solicitud
                   AND d.tutor_id = :tutor
                 FOR UPDATE"
            );
            $stmt->execute([
                "solicitud" => $solicitudId,
                "tutor" => $tutorId
            ]);

            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                throw new RuntimeException("No se encontró la solicitud.");
            }

            if ($solicitud["estado"] !== "PENDIENTE") {
                throw new RuntimeException("Esta solicitud ya fue procesada.");
            }

            $estadoNuevo = $accion === "aceptar"
                ? "ACEPTADA"
                : "RECHAZADA";

            if ($accion === "aceptar") {

                if ($solicitud["estado_disponibilidad"] !== "DISPONIBLE") {
                    throw new RuntimeException("La tutoría ya no está disponible.");
                }

                $stmt = $conexion->prepare(
                    "UPDATE disponibilidades
                     SET cupo_actual = cupo_actual + 1,
                         estado = CASE
                             WHEN cupo_actual + 1 >= cupo_maximo
                             THEN 'AGOTADO'
                             ELSE 'DISPONIBLE'
                         END
                     WHERE id = :disponibilidad
                       AND estado = 'DISPONIBLE'
                       AND cupo_actual < cupo_maximo"
                );
                $stmt->execute([
                    "disponibilidad" => $solicitud["disponibilidad_id"]
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException("No hay cupos disponibles.");
                }
            }

            $stmt = $conexion->prepare(
                "UPDATE solicitudes_tutoria
                 SET estado = :estado
                 WHERE id = :solicitud
                   AND estado = 'PENDIENTE'"
            );
            $stmt->execute([
                "estado" => $estadoNuevo,
                "solicitud" => $solicitudId
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException("No se pudo actualizar la solicitud.");
            }

            $comentario = $accion === "aceptar"
                ? "El tutor aceptó la solicitud."
                : "El tutor rechazó la solicitud.";

            $stmt = $conexion->prepare(
                "INSERT INTO historial_estados
                (solicitud_id, estado_anterior, estado_nuevo,
                 cambiado_por, comentario)
                 VALUES
                (:solicitud, 'PENDIENTE', :nuevo,
                 :tutor, :comentario)"
            );
            $stmt->execute([
                "solicitud" => $solicitudId,
                "nuevo" => $estadoNuevo,
                "tutor" => $tutorId,
                "comentario" => $comentario
            ]);

            $tipoNotificacion = $accion === "aceptar"
                ? "ACEPTACION"
                : "RECHAZO";

            $textoNotificacion = $accion === "aceptar"
                ? "Tu solicitud de tutoría fue aceptada."
                : "Tu solicitud de tutoría fue rechazada.";

            $stmt = $conexion->prepare(
                "INSERT INTO notificaciones
                (usuario_id, solicitud_id, tipo, mensaje)
                 VALUES
                (:estudiante, :solicitud, :tipo, :mensaje)"
            );
            $stmt->execute([
                "estudiante" => $solicitud["estudiante_id"],
                "solicitud" => $solicitudId,
                "tipo" => $tipoNotificacion,
                "mensaje" => $textoNotificacion
            ]);

            $conexion->commit();

            $_SESSION["mensaje_solicitudes_tutor"] =
                $accion === "aceptar"
                ? "Solicitud aceptada correctamente."
                : "Solicitud rechazada correctamente.";

            header("Location: ../tutor/solicitudes.php");
            exit;

        } catch (RuntimeException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $error = $e->getMessage();

        } catch (PDOException $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            error_log($e->getMessage());
            $error = "No se pudo procesar la solicitud.";
        }
    }
}
if ($error !== "") {
    $_SESSION["error_solicitudes_tutor"] = $error;
    header("Location: ../tutor/solicitudes.php");
    exit;
}
header("Location: ../tutor/solicitudes.php");
exit;
