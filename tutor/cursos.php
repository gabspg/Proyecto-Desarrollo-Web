
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
$error = (string)($_SESSION["error_cursos_tutor"] ?? $error);
unset($_SESSION["error_cursos_tutor"]);
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
       AND d.estado <> 'CANCELADO'
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
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

    <a href="notificaciones.php">
        <i class="bi bi-bell-fill"></i> Notificaciones
    </a>

    <form action="../apis/logout.php" method="POST" class="logout-form">
        <input type="hidden" name="token" value="<?= escapar($_SESSION["csrf_logout"]) ?>">
        <button type="submit" class="salir">
            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
        </button>
    </form>
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

                <form method="POST" action="../apis/tutor_cursos.php">
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
                                        <form method="POST" action="../apis/tutor_cursos.php"
                                              data-confirm="¿Deseas quitar este curso?">
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

                    <form method="POST" action="../apis/tutor_cursos.php">
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
                                   class="form-control" min="<?= $tipoTutoria === "GRUPAL" ? 2 : 1 ?>"
                                   value="<?= (int)$cupoMaximo ?>" required>
                            <div id="ayudaCupo" class="form-text">
                                <?= $tipoTutoria === "GRUPAL"
                                    ? "Las tutorías grupales requieren al menos 2 participantes."
                                    : "Las tutorías individuales tienen un solo cupo." ?>
                            </div>
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
                                                          action="../apis/tutor_cursos.php"
                                                          data-confirm="¿Confirmas la cancelación de esta tutoría y el envío del motivo a los estudiantes inscritos?">
                                                        <input type="hidden" name="modulo" value="horarios">
                                                        <input type="hidden" name="token" value="<?= escapar($tokenHorarios) ?>">
                                                        <input type="hidden" name="accion" value="cancelar">
                                                        <input type="hidden" name="horario_id" value="<?= (int)$horario["id"] ?>">
                                                        <label class="form-label small mb-1" for="motivo-cancelacion-<?= (int)$horario["id"] ?>">
                                                            Motivo obligatorio
                                                        </label>
                                                        <textarea
                                                            class="form-control form-control-sm mb-2"
                                                            id="motivo-cancelacion-<?= (int)$horario["id"] ?>"
                                                            name="motivo_cancelacion"
                                                            rows="2"
                                                            maxlength="1000"
                                                            required
                                                        ></textarea>
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

<script src="../js/tutor-cursos.js" defer></script>
<script src="../js/form-confirm.js" defer></script>

</body>
</html>