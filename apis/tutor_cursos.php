<?php
require_once __DIR__ . "/bootstrap.php";
apiRequerirPost(
    ($_POST["modulo"] ?? "") === "horarios"
        ? "token_horarios"
        : "token_cursos_tutor"
);
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../seguridad/verificar_sesion.php";
$tutorId = apiRequerirRol($conexion, "TUTOR");
$tokenCursos = apiToken("token_cursos_tutor");
$tokenHorarios = apiToken("token_horarios");
$vista = "cursos";
$seccion = "lista";
$error = "";
$mensaje = "";
$horarioId = 0;
$tutorCursoId = 0;
$fecha = "";
$horaInicio = "";
$horaFin = "";
$modalidad = "VIRTUAL";
$tipoTutoria = "INDIVIDUAL";
$lugarLink = "";
$cupoMaximo = 1;
$stmt = $conexion->prepare("SELECT carrera_id FROM usuarios WHERE id = ? AND estado = 'ACTIVO'");
$stmt->execute([$tutorId]);
$carreraId = (int)$stmt->fetchColumn();
if ($carreraId < 1) { http_response_code(403); exit("Tutor sin carrera activa."); }
function esMeetValidoApi($enlace) { return (bool)preg_match('~^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}/?(?:\?[^\s]*)?$~i', trim((string)$enlace)); }
function esFechaValidaApi($fecha) { $obj = DateTimeImmutable::createFromFormat("!Y-m-d", (string)$fecha); return $obj && $obj->format("Y-m-d") === $fecha; }
function esHoraValidaApi($hora) { return (bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', (string)$hora); }

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $modulo = $_POST["modulo"] ?? "";
    $accion = $_POST["accion"] ?? "";

    if (!in_array($modulo, ["cursos", "horarios"], true)) {
        exit("Módulo inválido.");
    }

    $vista = $modulo;

    $tokenRecibido = $_POST["token"] ?? "";
    $tokenCorrecto = $modulo === "cursos" ? $tokenCursos : $tokenHorarios;

    if (!is_string($tokenRecibido) ||
        !hash_equals($tokenCorrecto, $tokenRecibido)) {
        exit("Solicitud no válida.");
    }

    /* CURSOS */

    if ($modulo === "cursos" && in_array($accion, ["agregar", "quitar"], true)) {

        $cursoId = filter_input(INPUT_POST, "curso_id", FILTER_VALIDATE_INT);

        if (!$cursoId) {
            $error = "Selecciona un curso válido.";
        }

        if ($error === "" && $accion === "agregar") {

            $stmt = $conexion->prepare(
                "SELECT id FROM cursos
                 WHERE id = :curso
                   AND carrera_id = :carrera
                   AND estado = 'ACTIVO'"
            );
            $stmt->execute([
                "curso" => $cursoId,
                "carrera" => $carreraId
            ]);

            if (!$stmt->fetch()) {
                $error = "El curso no está disponible para tu carrera.";
            }

            if ($error === "") {
                $stmt = $conexion->prepare(
                    "SELECT id FROM tutor_cursos
                     WHERE tutor_id = :tutor AND curso_id = :curso"
                );
                $stmt->execute([
                    "tutor" => $tutorId,
                    "curso" => $cursoId
                ]);

                if ($stmt->fetch()) {
                    $error = "Ya tienes asignado este curso.";
                }
            }
        }

        if ($error === "") {
            try {

                if ($accion === "agregar") {
                    $stmt = $conexion->prepare(
                        "INSERT INTO tutor_cursos (tutor_id, curso_id)
                         VALUES (:tutor, :curso)"
                    );
                } else {
                    $stmt = $conexion->prepare(
                        "DELETE FROM tutor_cursos
                         WHERE tutor_id = :tutor AND curso_id = :curso"
                    );
                }

                $stmt->execute([
                    "tutor" => $tutorId,
                    "curso" => $cursoId
                ]);

                if ($accion === "quitar" && $stmt->rowCount() === 0) {
                    $error = "El curso no estaba asignado.";
                } else {
                    $_SESSION["mensaje_cursos_tutor"] =
                        $accion === "agregar"
                        ? "Curso agregado correctamente."
                        : "Curso quitado correctamente.";

                    header("Location: ../tutor/cursos.php?vista=cursos");
                    exit;
                }

            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = $accion === "agregar"
                    ? "No se pudo agregar el curso."
                    : "No puedes quitar un curso con horarios relacionados.";
            }
        }
    }

    /* HORARIOS */

    if ($modulo === "horarios") {

        if (in_array($accion, ["registrar", "editar"], true)) {

            $seccion = $accion;
            $horarioId = filter_input(INPUT_POST, "horario_id", FILTER_VALIDATE_INT) ?: 0;
            $tutorCursoId = filter_input(INPUT_POST, "tutor_curso_id", FILTER_VALIDATE_INT) ?: 0;

            $fecha = trim($_POST["fecha"] ?? "");
            $horaInicio = trim($_POST["hora_inicio"] ?? "");
            $horaFin = trim($_POST["hora_fin"] ?? "");
            $modalidad = $_POST["modalidad"] ?? "";
            $tipoTutoria = $_POST["tipo_tutoria"] ?? "";
            $lugarLink = trim($_POST["lugar_link"] ?? "");
            $cupoMaximo = filter_input(INPUT_POST, "cupo_maximo", FILTER_VALIDATE_INT);

            $stmt = $conexion->prepare(
                "SELECT tc.id, tc.curso_id
                 FROM tutor_cursos tc
                 INNER JOIN cursos c ON c.id = tc.curso_id
                 WHERE tc.id = :id
                   AND tc.tutor_id = :tutor
                   AND c.estado = 'ACTIVO'"
            );
            $stmt->execute([
                "id" => $tutorCursoId,
                "tutor" => $tutorId
            ]);
            $cursoAsignado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cursoAsignado) {
                $error = "Selecciona un curso activo que tengas asignado.";
            }

            if ($error === "" && !esFechaValidaApi($fecha)) {
                $error = "Selecciona una fecha válida.";
            }

            if ($error === "" &&
                (!esHoraValidaApi($horaInicio) || !esHoraValidaApi($horaFin))) {
                $error = "Selecciona horas válidas.";
            }

            if ($error === "" && $horaInicio >= $horaFin) {
                $error = "La hora de inicio debe ser menor que la hora final.";
            }

            if ($error === "" &&
                !in_array($modalidad, ["VIRTUAL", "PRESENCIAL"], true)) {
                $error = "Selecciona una modalidad válida.";
            }

            if ($error === "" &&
                !in_array($tipoTutoria, ["INDIVIDUAL", "GRUPAL"], true)) {
                $error = "Selecciona un tipo de tutoría válido.";
            }

            if ($error === "" && (!$cupoMaximo || $cupoMaximo < 1)) {
                $error = "El cupo máximo debe ser mayor que cero.";
            }

            if ($error === "" &&
                $tipoTutoria === "INDIVIDUAL" &&
                $cupoMaximo !== 1) {
                $error = "Una tutoría individual solo puede tener un cupo.";
            }

            if ($error === "" &&
                $tipoTutoria === "GRUPAL" &&
                $cupoMaximo < 2) {
                $error = "Una tutoría grupal debe tener al menos dos participantes.";
            }

            if ($error === "" && $lugarLink === "") {
                $error = $modalidad === "VIRTUAL"
                    ? "Ingresa el enlace de Google Meet."
                    : "Ingresa el lugar de la tutoría.";
            }

            if ($error === "" && strlen($lugarLink) > 255) {
                $error = "El lugar o enlace es demasiado largo.";
            }

            if ($error === "" &&
                $modalidad === "VIRTUAL" &&
                !esMeetValidoApi($lugarLink)) {
                $error = "Ingresa un enlace válido de Google Meet.";
            }

            if ($error === "" && $accion === "editar") {

                $stmt = $conexion->prepare(
                    "SELECT * FROM disponibilidades
                     WHERE id = :id AND tutor_id = :tutor"
                );
                $stmt->execute([
                    "id" => $horarioId,
                    "tutor" => $tutorId
                ]);
                $existente = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existente) {
                    $error = "No se encontró el horario.";
                } elseif ($existente["estado"] === "CANCELADO") {
                    $error = "No puedes editar un horario cancelado.";
                }
            }

            if ($error === "" && $accion === "editar") {

                $stmt = $conexion->prepare(
                    "SELECT COUNT(*) FROM solicitudes_tutoria
                     WHERE disponibilidad_id = :id
                       AND estado IN ('PENDIENTE', 'ACEPTADA')"
                );
                $stmt->execute(["id" => $horarioId]);

                if ((int)$stmt->fetchColumn() > 0) {
                    $error = "No puedes editar un horario con solicitudes activas.";
                }
            }

            if ($error === "") {

                $stmt = $conexion->prepare(
                    "SELECT id FROM disponibilidades
                     WHERE tutor_id = :tutor
                       AND fecha = :fecha
                       AND hora_inicio < :fin
                       AND hora_fin > :inicio
                       AND estado <> 'CANCELADO'
                       AND id <> :id
                     LIMIT 1"
                );
                $stmt->execute([
                    "tutor" => $tutorId,
                    "fecha" => $fecha,
                    "fin" => $horaFin,
                    "inicio" => $horaInicio,
                    "id" => $horarioId
                ]);

                if ($stmt->fetch()) {
                    $error = "Ya tienes una tutoría en esa fecha y horario.";
                }
            }

            if ($error === "") {
                try {

                    $cursoId = (int)$cursoAsignado["curso_id"];

                    if ($accion === "registrar") {

                        $stmt = $conexion->prepare(
                            "INSERT INTO disponibilidades
                            (tutor_curso_id, tutor_id, curso_id,
                             fecha, hora_inicio, hora_fin, modalidad,
                             tipo_tutoria, lugar_link, cupo_maximo,
                             cupo_actual, estado)
                             VALUES
                            (:tutor_curso, :tutor, :curso,
                             :fecha, :inicio, :fin, :modalidad,
                             :tipo, :lugar, :cupo, 0, 'DISPONIBLE')"
                        );
                        $stmt->execute([
                            "tutor_curso" => $tutorCursoId,
                            "tutor" => $tutorId,
                            "curso" => $cursoId,
                            "fecha" => $fecha,
                            "inicio" => $horaInicio,
                            "fin" => $horaFin,
                            "modalidad" => $modalidad,
                            "tipo" => $tipoTutoria,
                            "lugar" => $lugarLink,
                            "cupo" => $cupoMaximo
                        ]);

                        $_SESSION["mensaje_horarios"] =
                            "Tutoría registrada correctamente.";

                    } else {

                        $stmt = $conexion->prepare(
                            "UPDATE disponibilidades
                             SET tutor_curso_id = :tutor_curso,
                                 curso_id = :curso,
                                 fecha = :fecha,
                                 hora_inicio = :inicio,
                                 hora_fin = :fin,
                                 modalidad = :modalidad,
                                 tipo_tutoria = :tipo,
                                 lugar_link = :lugar,
                                 cupo_maximo = :cupo
                             WHERE id = :id
                               AND tutor_id = :tutor
                               AND estado <> 'CANCELADO'"
                        );
                        $stmt->execute([
                            "tutor_curso" => $tutorCursoId,
                            "curso" => $cursoId,
                            "fecha" => $fecha,
                            "inicio" => $horaInicio,
                            "fin" => $horaFin,
                            "modalidad" => $modalidad,
                            "tipo" => $tipoTutoria,
                            "lugar" => $lugarLink,
                            "cupo" => $cupoMaximo,
                            "id" => $horarioId,
                            "tutor" => $tutorId
                        ]);

                        $_SESSION["mensaje_horarios"] =
                            "Tutoría actualizada correctamente.";
                    }

                    header("Location: ../tutor/cursos.php?vista=horarios");
                    exit;

                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = "No se pudo guardar la tutoría.";
                }
            }
        }

        if ($accion === "cancelar") {

            $horarioId = filter_input(INPUT_POST, "horario_id", FILTER_VALIDATE_INT);
            $motivoCancelacion = trim((string)($_POST["motivo_cancelacion"] ?? ""));
            $longitudMotivo = function_exists("mb_strlen")
                ? mb_strlen($motivoCancelacion, "UTF-8")
                : strlen($motivoCancelacion);

            if (!$horarioId) {
                $error = "Horario inválido.";
            } elseif ($motivoCancelacion === "") {
                $error = "Indica el motivo de la cancelación.";
            } elseif ($longitudMotivo > 1000) {
                $error = "El motivo no puede superar los 1000 caracteres.";
            } else {
                try {
                    $conexion->beginTransaction();

                    $stmt = $conexion->prepare(
                        "SELECT id, estado, fecha, hora_inicio FROM disponibilidades
                         WHERE id = :id AND tutor_id = :tutor
                         FOR UPDATE"
                    );
                    $stmt->execute([
                        "id" => $horarioId,
                        "tutor" => $tutorId
                    ]);
                    $horario = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$horario) {
                        $error = "No se encontró el horario.";
                    } elseif ($horario["estado"] === "CANCELADO") {
                        $error = "Este horario ya está cancelado.";
                    } else {
                        $stmt = $conexion->prepare(
                            "SELECT id, estudiante_id, estado
                             FROM solicitudes_tutoria
                             WHERE disponibilidad_id = :id
                               AND estado IN ('PENDIENTE', 'ACEPTADA')
                             ORDER BY id
                             FOR UPDATE"
                        );
                        $stmt->execute(["id" => $horarioId]);
                        $solicitudesActivas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $stmt = $conexion->prepare(
                            "UPDATE disponibilidades
                             SET estado = 'CANCELADO',
                                 cupo_actual = 0
                             WHERE id = :id
                               AND tutor_id = :tutor
                               AND estado <> 'CANCELADO'"
                        );
                        $stmt->execute([
                            "id" => $horarioId,
                            "tutor" => $tutorId
                        ]);

                        if ($stmt->rowCount() !== 1) {
                            throw new RuntimeException("No se pudo cancelar la tutoría.");
                        }

                        $stmtHistorial = $conexion->prepare(
                            "INSERT INTO historial_estados
                             (solicitud_id, estado_anterior, estado_nuevo,
                              cambiado_por, comentario)
                             VALUES
                             (:solicitud, :anterior, 'CANCELADA',
                              :tutor, :comentario)"
                        );
                        $stmtNotificacion = $conexion->prepare(
                            "INSERT INTO notificaciones
                             (usuario_id, solicitud_id, tipo, mensaje)
                             VALUES
                             (:estudiante, :solicitud, 'CANCELACION', :mensaje)"
                        );
                        $stmtCancelar = $conexion->prepare(
                            "UPDATE solicitudes_tutoria
                             SET estado = 'CANCELADA'
                             WHERE id = :id
                               AND estado IN ('PENDIENTE', 'ACEPTADA')"
                        );

                        $detalleHorario = date(
                            "d/m/Y",
                            strtotime($horario["fecha"])
                        ) . " a las " . substr($horario["hora_inicio"], 0, 5);

                        foreach ($solicitudesActivas as $solicitud) {
                            $stmtCancelar->execute(["id" => $solicitud["id"]]);
                            if ($stmtCancelar->rowCount() !== 1) {
                                throw new RuntimeException(
                                    "No se pudo actualizar una de las solicitudes."
                                );
                            }

                            $comentario = "Tutoría cancelada por el tutor. Motivo: "
                                . $motivoCancelacion;
                            $stmtHistorial->execute([
                                "solicitud" => $solicitud["id"],
                                "anterior" => $solicitud["estado"],
                                "tutor" => $tutorId,
                                "comentario" => $comentario
                            ]);
                            $stmtNotificacion->execute([
                                "estudiante" => $solicitud["estudiante_id"],
                                "solicitud" => $solicitud["id"],
                                "mensaje" => "El tutor canceló la tutoría del "
                                    . $detalleHorario . ". Motivo: "
                                    . $motivoCancelacion
                            ]);
                        }

                        $_SESSION["mensaje_horarios"] =
                            "Tutoría cancelada y estudiantes notificados.";
                    }

                    if ($error !== "") {
                        $conexion->rollBack();
                    } else {
                        $conexion->commit();
                        header("Location: ../tutor/cursos.php?vista=horarios");
                        exit;
                    }

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
                    $error = "No se pudo cancelar la tutoría.";
                }
            }
        }
    }
}
if ($error !== "") {
    $_SESSION["error_cursos_tutor"] = $error;
    $destino = "../tutor/cursos.php?vista=" . urlencode(
        in_array($vista, ["cursos", "horarios"], true) ? $vista : "cursos"
    );
    if ($vista === "horarios" &&
        in_array($seccion, ["registrar", "editar"], true)) {
        $destino .= "&seccion=" . urlencode($seccion);
        if ($seccion === "editar" && $horarioId > 0) {
            $destino .= "&id=" . (int)$horarioId;
        }
    }
    header("Location: " . $destino);
    exit;
}
header("Location: ../tutor/cursos.php");
exit;
