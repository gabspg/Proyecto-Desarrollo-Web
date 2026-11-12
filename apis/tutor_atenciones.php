<?php
require_once __DIR__ . "/bootstrap.php";
apiRequerirPost("token_atenciones_tutor");
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../seguridad/verificar_sesion.php";
$tutorId = apiRequerirRol($conexion, "TUTOR");
$token = apiToken("token_atenciones_tutor");
$error = "";
$mensaje = "";
$solicitudId = 0;
$asistencia = "1";
$observaciones = "";
$resultado = "";
$recomendaciones = "";
$seccion = "lista";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tokenRecibido = $_POST["token"] ?? "";

    if (
        !is_string($tokenRecibido) ||
        !hash_equals($token, $tokenRecibido)
    ) {
        exit("Solicitud no válida.");
    }

    $accion = $_POST["accion"] ?? "";

    if (in_array($accion, ["registrar", "editar"], true)) {

        $seccion = $accion;

        $solicitudId = filter_input(
            INPUT_POST,
            "solicitud_id",
            FILTER_VALIDATE_INT
        ) ?: 0;

        $asistencia = $_POST["asistencia"] ?? "";
        $observaciones = trim($_POST["observaciones"] ?? "");
        $resultado = trim($_POST["resultado"] ?? "");
        $recomendaciones = trim($_POST["recomendaciones"] ?? "");

        if (!$solicitudId) {
            $error = "Selecciona una solicitud válida.";
        }

        if (
            $error === "" &&
            !in_array($asistencia, ["0", "1"], true)
        ) {
            $error = "Selecciona si el estudiante asistió.";
        }

        if ($error === "" && $resultado === "") {
            $error = "Ingresa el resultado de la tutoría.";
        }

        /* VERIFICAR SOLICITUD DEL TUTOR */

        if ($error === "") {

            $stmt = $conexion->prepare(
                "SELECT
                    s.id,
                    s.estado,
                    d.fecha,
                    d.hora_fin
                 FROM solicitudes_tutoria s
                 INNER JOIN disponibilidades d
                    ON d.id = s.disponibilidad_id
                 WHERE s.id = :solicitud
                   AND d.tutor_id = :tutor"
            );

            $stmt->execute([
                "solicitud" => $solicitudId,
                "tutor" => $tutorId
            ]);

            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {

                $error = "La solicitud no pertenece a este tutor.";

            } elseif (
                !in_array(
                    $solicitud["estado"],
                    ["ACEPTADA", "ATENDIDA"],
                    true
                )
            ) {

                $error = "Solo puedes registrar atenciones de solicitudes aceptadas.";

            } elseif (
                strtotime(
                    $solicitud["fecha"] . " " .
                    $solicitud["hora_fin"]
                ) > time()
            ) {

                $error = "La tutoría todavía no ha finalizado.";
            }
        }

        /* GUARDAR ATENCIÓN */

        if ($error === "") {

            try {

                $conexion->beginTransaction();

                $stmt = $conexion->prepare(
                    "SELECT id
                     FROM atenciones
                     WHERE solicitud_id = :solicitud"
                );

                $stmt->execute([
                    "solicitud" => $solicitudId
                ]);

                $atencionExistente = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    $accion === "registrar" &&
                    $atencionExistente
                ) {

                    throw new RuntimeException(
                        "Esta solicitud ya tiene una atención registrada."
                    );
                }

                if (
                    $accion === "editar" &&
                    !$atencionExistente
                ) {

                    throw new RuntimeException(
                        "No existe una atención para editar."
                    );
                }

                if ($accion === "registrar") {

                    $stmt = $conexion->prepare(
                        "INSERT INTO atenciones
                        (
                            solicitud_id,
                            registrado_por,
                            asistencia,
                            observaciones,
                            resultado,
                            recomendaciones
                        )
                        VALUES
                        (
                            :solicitud,
                            :tutor,
                            :asistencia,
                            :observaciones,
                            :resultado,
                            :recomendaciones
                        )"
                    );

                    $stmt->execute([
                        "solicitud" => $solicitudId,
                        "tutor" => $tutorId,
                        "asistencia" => (int)$asistencia,
                        "observaciones" => $observaciones,
                        "resultado" => $resultado,
                        "recomendaciones" => $recomendaciones
                    ]);

                    /* ACTUALIZAR SOLICITUD A ATENDIDA */

                    $stmt = $conexion->prepare(
                        "UPDATE solicitudes_tutoria
                         SET estado = 'ATENDIDA'
                         WHERE id = :solicitud
                           AND estado = 'ACEPTADA'"
                    );

                    $stmt->execute([
                        "solicitud" => $solicitudId
                    ]);

                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException(
                            "No se pudo actualizar el estado de la solicitud."
                        );
                    }

                    /* GUARDAR HISTORIAL */

                    $stmt = $conexion->prepare(
                        "INSERT INTO historial_estados
                        (
                            solicitud_id,
                            estado_anterior,
                            estado_nuevo,
                            cambiado_por,
                            comentario
                        )
                        VALUES
                        (
                            :solicitud,
                            'ACEPTADA',
                            'ATENDIDA',
                            :tutor,
                            'Se registró la atención de la tutoría.'
                        )"
                    );

                    $stmt->execute([
                        "solicitud" => $solicitudId,
                        "tutor" => $tutorId
                    ]);

                    $_SESSION["mensaje_atenciones"] =
                        "Atención registrada correctamente.";

                } else {

                    $stmt = $conexion->prepare(
                        "UPDATE atenciones
                         SET asistencia = :asistencia,
                             observaciones = :observaciones,
                             resultado = :resultado,
                             recomendaciones = :recomendaciones
                         WHERE solicitud_id = :solicitud
                           AND registrado_por = :tutor"
                    );

                    $stmt->execute([
                        "asistencia" => (int)$asistencia,
                        "observaciones" => $observaciones,
                        "resultado" => $resultado,
                        "recomendaciones" => $recomendaciones,
                        "solicitud" => $solicitudId,
                        "tutor" => $tutorId
                    ]);

                    $_SESSION["mensaje_atenciones"] =
                        "Atención actualizada correctamente.";
                }

                $conexion->commit();

                header("Location: ../tutor/atenciones.php");
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

                $error = "No se pudo guardar la atención.";
            }
        }
    }
}
if ($error !== "") {
    $_SESSION["error_atenciones_tutor"] = $error;
    $destino = "../tutor/atenciones.php";
    if (in_array($seccion, ["registrar", "editar"], true)) {
        $destino .= "?seccion=" . urlencode($seccion);
        if ($seccion === "editar" && $solicitudId > 0) {
            $destino .= "&solicitud_id=" . (int)$solicitudId;
        }
    }
    header("Location: " . $destino);
    exit;
}
header("Location: ../tutor/atenciones.php");
exit;
