
<?php
require_once "../config/conexion.php";
require_once "../seguridad/verificar_sesion.php";
verificarAcceso("TUTOR");

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function esMeetValido($enlace) {
    return (bool)preg_match(
        '~^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}/?(?:\?[^\s]*)?$~i',
        trim($enlace)
    );
}

function esFechaValida($fecha) {
    $obj = DateTimeImmutable::createFromFormat("!Y-m-d", $fecha);
    return $obj && $obj->format("Y-m-d") === $fecha;
}

function esHoraValida($hora) {
    return (bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $hora);
}

$tutorId = (int)($_SESSION["usuario_id"] ?? 0);
$nombre = $_SESSION["nombre"] ?? "Tutor";

if ($tutorId <= 0) {
    exit("No se pudo identificar al tutor.");
}

if (empty($_SESSION["token_cursos_tutor"])) {
    $_SESSION["token_cursos_tutor"] = bin2hex(random_bytes(32));
}

if (empty($_SESSION["token_horarios"])) {
    $_SESSION["token_horarios"] = bin2hex(random_bytes(32));
}

$tokenCursos = $_SESSION["token_cursos_tutor"];
$tokenHorarios = $_SESSION["token_horarios"];

$vista = $_GET["vista"] ?? "cursos";
$seccion = $_GET["seccion"] ?? "lista";

if (!in_array($vista, ["cursos", "horarios"], true)) {
    $vista = "cursos";
}

if (!in_array($seccion, ["lista", "registrar", "editar"], true)) {
    $seccion = "lista";
}

$error = "";
$mensaje = "";

$stmt = $conexion->prepare(
    "SELECT u.id, u.carrera_id, u.estado
     FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE u.id = :id AND r.nombre = 'TUTOR'"
);
$stmt->execute(["id" => $tutorId]);
$tutor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tutor || $tutor["estado"] !== "ACTIVO") {
    exit("El tutor no está activo.");
}

$carreraId = (int)$tutor["carrera_id"];

$horarioId = 0;
$tutorCursoId = 0;
$fecha = "";
$horaInicio = "";
$horaFin = "";
$modalidad = "VIRTUAL";
$tipoTutoria = "INDIVIDUAL";
$lugarLink = "";
$cupoMaximo = 1;

/* ==========================================================
   PROCESAR FORMULARIOS
   ========================================================== */

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

                    header("Location: cursos.php?vista=cursos");
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

            if ($error === "" && !esFechaValida($fecha)) {
                $error = "Selecciona una fecha válida.";
            }

            if ($error === "" &&
                (!esHoraValida($horaInicio) || !esHoraValida($horaFin))) {
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
                !esMeetValido($lugarLink)) {
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

                    header("Location: cursos.php?vista=horarios");
                    exit;

                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = "No se pudo guardar la tutoría.";
                }
            }
        }

        if ($accion === "cancelar") {

            $horarioId = filter_input(INPUT_POST, "horario_id", FILTER_VALIDATE_INT);

            if (!$horarioId) {
                $error = "Horario inválido.";
            } else {
                try {
                    $conexion->beginTransaction();

                    $stmt = $conexion->prepare(
                        "SELECT id, estado FROM disponibilidades
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
                            "SELECT COUNT(*) FROM solicitudes_tutoria
                             WHERE disponibilidad_id = :id
                               AND estado IN ('PENDIENTE', 'ACEPTADA')"
                        );
                        $stmt->execute(["id" => $horarioId]);

                        if ((int)$stmt->fetchColumn() > 0) {
                            $error = "No puedes cancelar una tutoría con solicitudes activas.";
                        } else {
                            $stmt = $conexion->prepare(
                                "UPDATE disponibilidades
                                 SET estado = 'CANCELADO'
                                 WHERE id = :id AND tutor_id = :tutor"
                            );
                            $stmt->execute([
                                "id" => $horarioId,
                                "tutor" => $tutorId
                            ]);

                            $_SESSION["mensaje_horarios"] =
                                "Tutoría cancelada correctamente.";
                        }
                    }

                    if ($error !== "") {
                        $conexion->rollBack();
                    } else {
                        $conexion->commit();
                        header("Location: cursos.php?vista=horarios");
                        exit;
                    }

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

/* ==========================================================
   CARGAR HORARIO PARA EDITAR
   ========================================================== */

if ($_SERVER["REQUEST_METHOD"] === "GET" &&
    $vista === "horarios" &&
    $seccion === "editar") {

    $horarioId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

    if (!$horarioId) {
        exit("Horario inválido.");
    }

    $stmt = $conexion->prepare(
        "SELECT * FROM disponibilidades
         WHERE id = :id AND tutor_id = :tutor"
    );
    $stmt->execute([
        "id" => $horarioId,
        "tutor" => $tutorId
    ]);
    $editar = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editar) {
        exit("No se encontró el horario.");
    }

    if ($editar["estado"] === "CANCELADO") {
        exit("No puedes editar un horario cancelado.");
    }

    $tutorCursoId = (int)$editar["tutor_curso_id"];
    $fecha = $editar["fecha"];
    $horaInicio = substr($editar["hora_inicio"], 0, 5);
    $horaFin = substr($editar["hora_fin"], 0, 5);
    $modalidad = $editar["modalidad"];
    $tipoTutoria = $editar["tipo_tutoria"];
    $lugarLink = $editar["lugar_link"] ?? "";
    $cupoMaximo = (int)$editar["cupo_maximo"];
}

/* ==========================================================
   CONSULTAS
   ========================================================== */

$stmt = $conexion->prepare(
    "SELECT tc.id AS tutor_curso_id,
            c.id, c.codigo, c.nombre, c.descripcion, c.estado
     FROM tutor_cursos tc
     INNER JOIN cursos c ON c.id = tc.curso_id
     WHERE tc.tutor_id = :tutor
     ORDER BY c.nombre"
);
$stmt->execute(["tutor" => $tutorId]);
$cursosAsignados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conexion->prepare(
    "SELECT c.id, c.codigo, c.nombre
     FROM cursos c
     WHERE c.carrera_id = :carrera
       AND c.estado = 'ACTIVO'
       AND NOT EXISTS (
           SELECT 1 FROM tutor_cursos tc
           WHERE tc.curso_id = c.id
             AND tc.tutor_id = :tutor
       )
     ORDER BY c.nombre"
);
$stmt->execute([
    "carrera" => $carreraId,
    "tutor" => $tutorId
]);
$cursosDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conexion->prepare(
    "SELECT d.id, d.fecha, d.hora_inicio, d.hora_fin,
            d.modalidad, d.tipo_tutoria, d.lugar_link,
            d.cupo_maximo, d.cupo_actual, d.estado,
            c.codigo AS codigo_curso,
            c.nombre AS nombre_curso
     FROM disponibilidades d
     INNER JOIN cursos c ON c.id = d.curso_id
     WHERE d.tutor_id = :tutor
     ORDER BY d.fecha DESC, d.hora_inicio"
);
$stmt->execute(["tutor" => $tutorId]);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($vista === "cursos" && isset($_SESSION["mensaje_cursos_tutor"])) {
    $mensaje = $_SESSION["mensaje_cursos_tutor"];
    unset($_SESSION["mensaje_cursos_tutor"]);
}

if ($vista === "horarios" && isset($_SESSION["mensaje_horarios"])) {
    $mensaje = $_SESSION["mensaje_horarios"];
    unset($_SESSION["mensaje_horarios"]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos y Horarios - TutorLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/tutor.css">
</head>
<body>

<aside class="sidebar">
    <div class="logo">
        <i class="bi bi-mortarboard-fill"></i> TutorLink
    </div>

    <div class="menu-titulo">MENÚ PRINCIPAL</div>

    <a href="panel.php">
        <i class="bi bi-grid-1x2-fill"></i> Inicio
    </a>

    <a href="cursos.php?vista=cursos"
       class="<?= $vista === "cursos" ? "activo" : "" ?>">
        <i class="bi bi-journal-bookmark-fill"></i> Cursos
    </a>

    <a href="cursos.php?vista=horarios"
       class="<?= $vista === "horarios" ? "activo" : "" ?>">
        <i class="bi bi-calendar-week-fill"></i> Horarios
    </a>

    <div class="menu-titulo">GESTIÓN DE TUTORÍAS</div>

    <a href="solicitudes.php">
        <i class="bi bi-envelope-paper-fill"></i> Solicitudes recibidas
    </a>

    <a href="atenciones.php">
        <i class="bi bi-clipboard-check-fill"></i> Registro de atenciones
    </a>

    <a href="/tutorias/autenticacion/logout.php" class="salir">
        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
    </a>
</aside>

<main class="contenido">

    <header class="barra-superior">
        <div>
            <h1 class="titulo-pagina">
                <?= $vista === "cursos" ? "Mis Cursos" : "Mis Horarios" ?>
            </h1>
            <p class="subtitulo">
                TutorLink - Sistema de Gestión de Tutorías y Asesorías Académicas
            </p>
        </div>
        <div class="perfil">
            <div class="avatar">
                <?= escapar(strtoupper(substr($nombre, 0, 1))) ?>
            </div>
            <div>
                <strong><?= escapar($nombre) ?></strong>
                <div class="text-muted small">Tutor</div>
            </div>
        </div>
    </header>

    <section class="bienvenida">
        <div>
            <h2>
                <?= $vista === "cursos"
                    ? "Administrar cursos"
                    : "Administrar horarios" ?>
            </h2>
            <p>
                <?= $vista === "cursos"
                    ? "Consulta y administra los cursos que deseas impartir."
                    : "Registra tutorías presenciales o virtuales con Google Meet." ?>
            </p>
        </div>
        <i class="bi bi-mortarboard bienvenida-icono"></i>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="alert alert-success"><?= escapar($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger"><?= escapar($error) ?></div>
    <?php endif; ?>

    <?php if ($vista === "cursos"): ?>

        <h4 class="seccion-titulo">Seleccionar curso</h4>

        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <p>Selecciona los cursos de tu carrera que deseas impartir.</p>

                <form method="POST" action="cursos.php?vista=cursos">
                    <input type="hidden" name="modulo" value="cursos">
                    <input type="hidden" name="token" value="<?= escapar($tokenCursos) ?>">
                    <input type="hidden" name="accion" value="agregar">

                    <div class="mb-3">
                        <label class="form-label">Curso disponible</label>
                        <select name="curso_id" class="form-select" required>
                            <option value="">Selecciona un curso</option>
                            <?php foreach ($cursosDisponibles as $curso): ?>
                                <option value="<?= (int)$curso["id"] ?>">
                                    <?= escapar($curso["codigo"] . " - " . $curso["nombre"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success"
                        <?= empty($cursosDisponibles) ? "disabled" : "" ?>>
                        <i class="bi bi-plus-circle"></i> Agregar curso
                    </button>
                </form>
            </div>
        </div>

        <h4 class="seccion-titulo">Cursos asignados</h4>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Curso</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cursosAsignados as $curso): ?>
                                <tr>
                                    <td><?= escapar($curso["codigo"]) ?></td>
                                    <td><?= escapar($curso["nombre"]) ?></td>
                                    <td><?= escapar($curso["descripcion"] ?? "") ?></td>
                                    <td>
                                        <span class="badge <?= $curso["estado"] === "ACTIVO" ? "bg-success" : "bg-secondary" ?>">
                                            <?= escapar($curso["estado"]) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="cursos.php?vista=cursos"
                                              onsubmit="return confirm('¿Deseas quitar este curso?')">
                                            <input type="hidden" name="modulo" value="cursos">
                                            <input type="hidden" name="token" value="<?= escapar($tokenCursos) ?>">
                                            <input type="hidden" name="accion" value="quitar">
                                            <input type="hidden" name="curso_id" value="<?= (int)$curso["id"] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i> Quitar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($cursosAsignados)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        No tienes cursos asignados.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>

        <h4 class="seccion-titulo">Administrar horarios</h4>

        <div class="d-flex gap-2 flex-wrap mb-4">
            <a href="cursos.php?vista=horarios" class="btn btn-primary">
                <i class="bi bi-list-ul"></i> Consultar horarios
            </a>
            <a href="cursos.php?vista=horarios&amp;seccion=registrar" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Nueva tutoría
            </a>
        </div>

        <?php if ($seccion === "registrar" || $seccion === "editar"): ?>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-4">
                        <?= $seccion === "editar" ? "Editar tutoría" : "Registrar nueva tutoría" ?>
                    </h4>

                    <form method="POST" action="cursos.php?vista=horarios">
                        <input type="hidden" name="modulo" value="horarios">
                        <input type="hidden" name="token" value="<?= escapar($tokenHorarios) ?>">
                        <input type="hidden" name="accion" value="<?= escapar($seccion) ?>">
                        <input type="hidden" name="horario_id" value="<?= (int)$horarioId ?>">

                        <div class="mb-3">
                            <label class="form-label">Curso</label>
                            <select name="tutor_curso_id" class="form-select" required>
                                <option value="">Selecciona un curso</option>
                                <?php foreach ($cursosAsignados as $curso): ?>
                                    <?php if ($curso["estado"] === "ACTIVO"): ?>
                                        <option value="<?= (int)$curso["tutor_curso_id"] ?>"
                                            <?= $tutorCursoId === (int)$curso["tutor_curso_id"] ? "selected" : "" ?>>
                                            <?= escapar($curso["codigo"] . " - " . $curso["nombre"]) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fecha de la tutoría</label>
                            <input type="date" name="fecha" class="form-control"
                                   value="<?= escapar($fecha) ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hora de inicio</label>
                                <input type="time" name="hora_inicio" class="form-control"
                                       value="<?= escapar($horaInicio) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hora de finalización</label>
                                <input type="time" name="hora_fin" class="form-control"
                                       value="<?= escapar($horaFin) ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="modalidad" class="form-label">Modalidad</label>
                            <select name="modalidad" id="modalidad" class="form-select" required>
                                <option value="VIRTUAL" <?= $modalidad === "VIRTUAL" ? "selected" : "" ?>>
                                    Virtual - Google Meet
                                </option>
                                <option value="PRESENCIAL" <?= $modalidad === "PRESENCIAL" ? "selected" : "" ?>>
                                    Presencial
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="tipo_tutoria" class="form-label">Tipo de tutoría</label>
                            <select name="tipo_tutoria" id="tipo_tutoria" class="form-select" required>
                                <option value="INDIVIDUAL" <?= $tipoTutoria === "INDIVIDUAL" ? "selected" : "" ?>>
                                    Individual
                                </option>
                                <option value="GRUPAL" <?= $tipoTutoria === "GRUPAL" ? "selected" : "" ?>>
                                    Grupal
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="lugar_link" id="etiquetaLugar" class="form-label">
                                Enlace de Google Meet
                            </label>
                            <input type="text" name="lugar_link" id="lugar_link"
                                   class="form-control" maxlength="255"
                                   value="<?= escapar($lugarLink) ?>" required>
                            <div id="ayudaLugar" class="form-text">
                                Pega el enlace de Google Meet.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="cupo_maximo" class="form-label">Cupo máximo</label>
                            <input type="number" name="cupo_maximo" id="cupo_maximo"
                                   class="form-control" min="1"
                                   value="<?= (int)$cupoMaximo ?>" required>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i>
                                <?= $seccion === "editar" ? "Guardar cambios" : "Registrar tutoría" ?>
                            </button>
                            <a href="cursos.php?vista=horarios" class="btn btn-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        <?php else: ?>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-4">Tutorías registradas</h4>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Curso</th>
                                    <th>Fecha</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Modalidad</th>
                                    <th>Tipo</th>
                                    <th>Meet / Lugar</th>
                                    <th>Cupos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($horarios as $horario): ?>
                                    <tr>
                                        <td>
                                            <?= escapar($horario["codigo_curso"] . " - " . $horario["nombre_curso"]) ?>
                                        </td>
                                        <td><?= escapar($horario["fecha"]) ?></td>
                                        <td><?= escapar(substr($horario["hora_inicio"], 0, 5)) ?></td>
                                        <td><?= escapar(substr($horario["hora_fin"], 0, 5)) ?></td>
                                        <td><?= escapar($horario["modalidad"]) ?></td>
                                        <td><?= escapar($horario["tipo_tutoria"]) ?></td>

                                        <td>
                                            <?php
                                            $enlace = trim($horario["lugar_link"] ?? "");
                                            $mostrarMeet =
                                                $horario["modalidad"] === "VIRTUAL" &&
                                                $horario["estado"] !== "CANCELADO" &&
                                                esMeetValido($enlace);
                                            ?>

                                            <?php if ($mostrarMeet): ?>
                                                <a href="<?= escapar($enlace) ?>"
                                                   target="_blank"
                                                   rel="noopener noreferrer"
                                                   class="btn btn-success btn-sm">
                                                    <i class="bi bi-camera-video-fill"></i>
                                                    Unirse a Meet
                                                </a>
                                            <?php elseif ($horario["modalidad"] === "VIRTUAL"): ?>
                                                <span class="text-muted">Enlace no disponible</span>
                                            <?php else: ?>
                                                <i class="bi bi-geo-alt-fill text-success"></i>
                                                <?= escapar($enlace) ?>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= (int)$horario["cupo_actual"] ?> /
                                            <?= (int)$horario["cupo_maximo"] ?>
                                        </td>

                                        <td>
                                            <?php
                                            $clase = "bg-secondary";
                                            if ($horario["estado"] === "DISPONIBLE") {
                                                $clase = "bg-success";
                                            } elseif ($horario["estado"] === "AGOTADO") {
                                                $clase = "bg-warning text-dark";
                                            } elseif ($horario["estado"] === "CANCELADO") {
                                                $clase = "bg-danger";
                                            }
                                            ?>
                                            <span class="badge <?= $clase ?>">
                                                <?= escapar($horario["estado"]) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($horario["estado"] !== "CANCELADO"): ?>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <a href="cursos.php?vista=horarios&amp;seccion=editar&amp;id=<?= (int)$horario["id"] ?>"
                                                       class="btn btn-primary btn-sm">
                                                        <i class="bi bi-pencil-square"></i> Editar
                                                    </a>

                                                    <form method="POST"
                                                          action="cursos.php?vista=horarios"
                                                          onsubmit="return confirm('¿Deseas cancelar esta tutoría?')">
                                                        <input type="hidden" name="modulo" value="horarios">
                                                        <input type="hidden" name="token" value="<?= escapar($tokenHorarios) ?>">
                                                        <input type="hidden" name="accion" value="cancelar">
                                                        <input type="hidden" name="horario_id" value="<?= (int)$horario["id"] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-x-circle"></i> Cancelar
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Sin acciones</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (empty($horarios)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">
                                            No tienes tutorías registradas.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

    <a href="panel.php" class="btn btn-secondary mt-4">
        <i class="bi bi-arrow-left"></i> Volver al panel
    </a>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const modalidad = document.getElementById("modalidad");
    const etiqueta = document.getElementById("etiquetaLugar");
    const campo = document.getElementById("lugar_link");
    const ayuda = document.getElementById("ayudaLugar");

    function actualizarModalidad() {
        if (!modalidad || !etiqueta || !campo || !ayuda) return;

        if (modalidad.value === "VIRTUAL") {
            etiqueta.textContent = "Enlace de Google Meet";
            campo.type = "url";
            campo.placeholder = "https://meet.google.com/abc-defg-hij";
            ayuda.textContent = "Crea una reunión en Google Meet y pega aquí el enlace.";
        } else {
            etiqueta.textContent = "Lugar de la tutoría";
            campo.type = "text";
            campo.placeholder = "Ejemplo: Edificio A, aula 203";
            ayuda.textContent = "Indica dónde se realizará la tutoría presencial.";
        }
    }

    if (modalidad) {
        modalidad.addEventListener("change", actualizarModalidad);
        actualizarModalidad();
    }

    const tipo = document.getElementById("tipo_tutoria");
    const cupo = document.getElementById("cupo_maximo");

    function actualizarCupos() {
        if (!tipo || !cupo) return;

        if (tipo.value === "INDIVIDUAL") {
            cupo.value = 1;
            cupo.readOnly = true;
        } else {
            cupo.readOnly = false;
        }
    }

    if (tipo) {
        tipo.addEventListener("change", actualizarCupos);
        actualizarCupos();
    }
});
</script>

</body>
</html>