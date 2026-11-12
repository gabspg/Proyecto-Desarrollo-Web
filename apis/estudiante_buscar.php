<?php
require_once __DIR__ . "/bootstrap.php";
apiRequerirPost("csrf_buscar");
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../seguridad/verificar_sesion.php";
$id = apiRequerirRol($conexion, "ESTUDIANTE");
$token = apiToken("csrf_buscar");
$error = "";
$stmt = $conexion->prepare("SELECT carrera_id FROM usuarios WHERE id = ? AND estado = 'ACTIVO'");
$stmt->execute([$id]);
$carrera = (int)$stmt->fetchColumn();
if ($carrera < 1) { http_response_code(403); exit("Cuenta sin carrera activa."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($token, (string)($_POST['token'] ?? ''))) {
        exit('Solicitud no válida.');
    }

    $disponibilidad = filter_input(
        INPUT_POST,
        'disponibilidad_id',
        FILTER_VALIDATE_INT
    );

    $motivo = trim((string)($_POST['motivo'] ?? ''));

    $longitudMotivo = function_exists('mb_strlen')
        ? mb_strlen($motivo, 'UTF-8')
        : strlen($motivo);

    if (
        !$disponibilidad ||
        $motivo === '' ||
        $longitudMotivo > 1000
    ) {
        $error = 'Selecciona una tutoría y escribe un motivo válido (máximo 1000 caracteres).';

    } else {

        try {
            $conexion->beginTransaction();

            /* CONSULTAR DISPONIBILIDAD */

            $stmt = $conexion->prepare("
                SELECT
                    d.id,
                    d.tutor_id,
                    d.fecha,
                    d.hora_inicio,
                    d.hora_fin,
                    d.cupo_actual,
                    d.cupo_maximo,
                    d.estado
                FROM disponibilidades d
                JOIN cursos c
                    ON c.id = d.curso_id
                JOIN usuarios t
                    ON t.id = d.tutor_id
                JOIN tutor_cursos tc
                    ON tc.id = d.tutor_curso_id
                   AND tc.tutor_id = d.tutor_id
                   AND tc.curso_id = d.curso_id
                WHERE d.id = ?
                  AND c.carrera_id = ?
                  AND c.estado = 'ACTIVO'
                  AND t.estado = 'ACTIVO'
                FOR UPDATE
            ");

            $stmt->execute([
                $disponibilidad,
                $carrera
            ]);

            $d = $stmt->fetch(PDO::FETCH_ASSOC);

            /* VALIDAR ESTADO Y CUPOS */

            if (
                !$d ||
                $d['estado'] !== 'DISPONIBLE' ||
                (int)$d['cupo_actual'] >= (int)$d['cupo_maximo']
            ) {
                throw new RuntimeException(
                    'Este horario ya no está disponible.'
                );
            }

            /* ==================================================
               VALIDAR FECHA Y HORA CON MYSQL
               ================================================== */

            $stmtHora = $conexion->prepare("
                SELECT TIMESTAMP(?, ?) > NOW()
            ");

            $stmtHora->execute([
                $d['fecha'],
                $d['hora_inicio']
            ]);

            if (!(int)$stmtHora->fetchColumn()) {
                throw new RuntimeException(
                    'La tutoría ya comenzó o su horario ha pasado.'
                );
            }

            /* EVITAR SOLICITAR UNA TUTORÍA PROPIA */

            if ((int)$d['tutor_id'] === $id) {
                throw new RuntimeException(
                    'No puedes reservar tu propia tutoría.'
                );
            }

            /* Evitar duplicar una solicitud que sigue activa */

            $stmt = $conexion->prepare("
                SELECT COUNT(*)
                FROM solicitudes_tutoria
                WHERE estudiante_id = ?
                  AND disponibilidad_id = ?
                  AND estado IN (
                      'PENDIENTE',
                      'ACEPTADA'
                  )
            ");

            $stmt->execute([
                $id,
                $disponibilidad
            ]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Ya tienes una solicitud para este horario.'
                );
            }

            /* EVITAR CRUCES DE HORARIOS */

            $stmt = $conexion->prepare("
                SELECT COUNT(*)
                FROM solicitudes_tutoria s
                JOIN disponibilidades d
                    ON d.id = s.disponibilidad_id
                WHERE s.estudiante_id = ?
                  AND s.estado = 'ACEPTADA'
                  AND d.estado <> 'CANCELADO'
                  AND d.fecha = ?
                  AND d.hora_inicio < ?
                  AND d.hora_fin > ?
            ");

            $stmt->execute([
                $id,
                $d['fecha'],
                $d['hora_fin'],
                $d['hora_inicio']
            ]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Ya tienes una tutoría aceptada que coincide con este horario.'
                );
            }

            /* ==================================================
               GUARDAR SOLICITUD
               CORREGIDO: reserva_activa ES GENERADA POR MYSQL
               ================================================== */

            $stmt = $conexion->prepare("
                INSERT INTO solicitudes_tutoria
                (
                    estudiante_id,
                    disponibilidad_id,
                    motivo,
                    estado
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    'PENDIENTE'
                )
            ");

            $stmt->execute([
                $id,
                $disponibilidad,
                $motivo
            ]);

            $sid = (int)$conexion->lastInsertId();

            /* REGISTRAR HISTORIAL */

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
                    NULL,
                    'PENDIENTE',
                    ?,
                    'Solicitud enviada por el estudiante'
                )
            ");

            $stmt->execute([
                $sid,
                $id
            ]);

            /* NOTIFICAR AL TUTOR */

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
                    'SOLICITUD',
                    'Has recibido una nueva solicitud de tutoría.'
                )
            ");

            $stmt->execute([
                $d['tutor_id'],
                $sid
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
                    'SOLICITUD',
                    'Tu solicitud fue enviada y está pendiente de respuesta del tutor.'
                )
            ");

            $stmt->execute([
                $id,
                $sid
            ]);

            $conexion->commit();

            $_SESSION['flash_estudiante'] =
                'Solicitud enviada. Espera la respuesta del tutor.';

            header('Location: ../estudiante/buscar.php');
            exit;

        } catch (RuntimeException $ex) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            $error = $ex->getMessage();

        } catch (Throwable $ex) {

            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }

            error_log($ex->getMessage());

            $error = 'No se pudo guardar la solicitud. Inténtalo nuevamente.';
        }
    }
}
if ($error !== "") {
    $_SESSION["error_buscar_estudiante"] = $error;
    header("Location: ../estudiante/buscar.php");
    exit;
}
header("Location: ../estudiante/buscar.php");
exit;
