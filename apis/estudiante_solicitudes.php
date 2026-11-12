<?php
require_once __DIR__ . "/bootstrap.php";
apiRequerirPost("csrf_solicitudes_est");
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../seguridad/verificar_sesion.php";
$id = apiRequerirRol($conexion, "ESTUDIANTE");
$token = apiToken("csrf_solicitudes_est");
$vista = in_array($_POST["vista"] ?? "solicitudes", ["solicitudes", "tutorias"], true) ? (string)($_POST["vista"] ?? "solicitudes") : "solicitudes";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($token, (string)($_POST['token'] ?? ''))) {
        exit('Solicitud no válida.');
    }

    $sid = filter_input(
        INPUT_POST,
        'solicitud_id',
        FILTER_VALIDATE_INT
    );

    if (!$sid || ($_POST['accion'] ?? '') !== 'cancelar') {

        $error = 'Acción inválida.';

    } else {

        try {
            $conexion->beginTransaction();

            $stmt = $conexion->prepare("
                SELECT disponibilidad_id
                FROM solicitudes_tutoria
                WHERE id = ?
                  AND estudiante_id = ?
            ");

            $stmt->execute([$sid, $id]);
            $disponibilidadId = $stmt->fetchColumn();

            if (!$disponibilidadId) {
                throw new RuntimeException(
                    'Esta solicitud ya no puede cancelarse.'
                );
            }

            $stmt = $conexion->prepare("
                SELECT
                    id,
                    tutor_id,
                    fecha,
                    hora_inicio,
                    cupo_actual,
                    cupo_maximo,
                    estado
                FROM disponibilidades
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$disponibilidadId]);
            $d = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$d ||
                $d['estado'] === 'CANCELADO' ||
                strtotime($d['fecha'] . ' ' . $d['hora_inicio']) <= time()
            ) {
                throw new RuntimeException(
                    'No puedes cancelar una tutoría que ya fue cancelada o comenzó.'
                );
            }

            $stmt = $conexion->prepare("
                SELECT id, disponibilidad_id, estado
                FROM solicitudes_tutoria
                WHERE id = ?
                  AND estudiante_id = ?
                  AND disponibilidad_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$sid, $id, $disponibilidadId]);
            $s = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$s ||
                !in_array($s['estado'], ['PENDIENTE', 'ACEPTADA'], true)
            ) {
                throw new RuntimeException(
                    'Esta solicitud ya no puede cancelarse.'
                );
            }

            $stmt = $conexion->prepare("
                UPDATE solicitudes_tutoria
                SET estado = 'CANCELADA'
                WHERE id = ?
                  AND estudiante_id = ?
            ");

            $stmt->execute([$sid, $id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'No se pudo cancelar la solicitud.'
                );
            }

            if ($s['estado'] === 'ACEPTADA') {

                $stmt = $conexion->prepare("
                    UPDATE disponibilidades
                    SET
                        cupo_actual = GREATEST(cupo_actual - 1, 0),
                        estado = CASE
                            WHEN cupo_actual - 1 < cupo_maximo
                            THEN 'DISPONIBLE'
                            ELSE 'AGOTADO'
                        END
                    WHERE id = ?
                      AND estado <> 'CANCELADO'
                ");

                $stmt->execute([$d['id']]);
            }

            $stmt = $conexion->prepare("
                INSERT INTO historial_estados
                (
                    solicitud_id,
                    estado_anterior,
                    estado_nuevo,
                    cambiado_por,
                    comentario
                )
                VALUES (
                    ?,
                    ?,
                    'CANCELADA',
                    ?,
                    'El estudiante canceló su participación en la tutoría.'
                )
            ");

            $stmt->execute([
                $sid,
                $s['estado'],
                $id
            ]);

            $stmt = $conexion->prepare("
                INSERT INTO notificaciones
                (
                    usuario_id,
                    solicitud_id,
                    tipo,
                    mensaje
                )
                VALUES (
                    ?,
                    ?,
                    'CANCELACION',
                    'El estudiante canceló su participación en la tutoría.'
                )
            ");

            $stmt->execute([
                $d['tutor_id'],
                $sid
            ]);

            $conexion->commit();

            $_SESSION['flash_sol_est'] =
                'Participación cancelada correctamente.';

            header(
                'Location: ../estudiante/solicitudes.php?vista=' .
                urlencode($vista)
            );
            exit;

        } catch (RuntimeException $ex) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            $error = $ex->getMessage();

        } catch (PDOException $ex) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            error_log($ex->getMessage());
            $error = 'No fue posible cancelar la solicitud.';
        }
    }
}
if ($error !== "") {
    $_SESSION["error_solicitudes_estudiante"] = $error;
    header(
        "Location: ../estudiante/solicitudes.php?vista=" .
        urlencode($vista)
    );
    exit;
}
header(
    "Location: ../estudiante/solicitudes.php?vista=" .
    urlencode($vista)
);
exit;
