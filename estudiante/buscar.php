
<?php
require_once '../config/conexion.php';
require_once '../seguridad/verificar_sesion.php';

verificarAcceso('ESTUDIANTE');

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id = (int)($_SESSION['usuario_id'] ?? 0);
$nombre = $_SESSION['nombre'] ?? 'Estudiante';

if ($id < 1) {
    exit('Sesión inválida.');
}

$inicial = strtoupper(
    function_exists('mb_substr')
        ? mb_substr($nombre, 0, 1, 'UTF-8')
        : substr($nombre, 0, 1)
);

if (empty($_SESSION['csrf_buscar'])) {
    $_SESSION['csrf_buscar'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['csrf_buscar'];
$error = '';
$error = (string)($_SESSION['error_buscar_estudiante'] ?? $error);
unset($_SESSION['error_buscar_estudiante']);

/* ==========================================================
   VALIDAR ESTUDIANTE
   ========================================================== */

$stmt = $conexion->prepare("
    SELECT u.carrera_id
    FROM usuarios u
    JOIN roles r ON r.id = u.rol_id
    WHERE u.id = ?
      AND r.nombre = 'ESTUDIANTE'
      AND u.estado = 'ACTIVO'
");

$stmt->execute([$id]);
$carrera = $stmt->fetchColumn();

if (!$carrera) {
    exit('Tu cuenta debe estar activa y tener una carrera asignada.');
}

$carrera = (int)$carrera;

/* ==========================================================
   ENVIAR SOLICITUD DE TUTORÍA
   ========================================================== */



/* ==========================================================
   MENSAJES
   ========================================================== */

$flash = $_SESSION['flash_estudiante'] ?? '';
unset($_SESSION['flash_estudiante']);

/* ==========================================================
   FILTROS
   ========================================================== */

$curso = filter_input(
    INPUT_GET,
    'curso',
    FILTER_VALIDATE_INT
) ?: 0;

$modalidad = (string)($_GET['modalidad'] ?? '');

if (!in_array($modalidad, ['', 'VIRTUAL', 'PRESENCIAL'], true)) {
    $modalidad = '';
}

/* ==========================================================
   CONSULTAR CURSOS
   ========================================================== */

$stmt = $conexion->prepare("
    SELECT DISTINCT
        c.id,
        c.codigo,
        c.nombre
    FROM cursos c
    JOIN tutor_cursos tc
        ON tc.curso_id = c.id
    JOIN usuarios t
        ON t.id = tc.tutor_id
    WHERE c.carrera_id = ?
      AND c.estado = 'ACTIVO'
      AND t.estado = 'ACTIVO'
    ORDER BY c.nombre
");

$stmt->execute([$carrera]);
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   CONSULTAR TUTORÍAS DISPONIBLES
   ========================================================== */

$sql = "
    SELECT
        d.id,
        d.fecha,
        d.hora_inicio,
        d.hora_fin,
        d.modalidad,
        d.tipo_tutoria,
        d.lugar_link,
        d.cupo_maximo,
        d.cupo_actual,
        c.codigo,
        c.nombre AS curso,
        t.nombre AS tutor,
        t.apellido
    FROM disponibilidades d
    JOIN cursos c
        ON c.id = d.curso_id
    JOIN usuarios t
        ON t.id = d.tutor_id
    JOIN tutor_cursos tc
        ON tc.id = d.tutor_curso_id
       AND tc.tutor_id = d.tutor_id
       AND tc.curso_id = d.curso_id
    WHERE c.carrera_id = :carrera
      AND c.estado = 'ACTIVO'
      AND t.estado = 'ACTIVO'
      AND d.estado = 'DISPONIBLE'
      AND d.cupo_actual < d.cupo_maximo
      AND TIMESTAMP(d.fecha, d.hora_inicio) > NOW()
";

$args = [
    'carrera' => $carrera
];

if ($curso) {

    $sql .= " AND c.id = :curso";
    $args['curso'] = $curso;
}

if ($modalidad) {

    $sql .= " AND d.modalidad = :modalidad";
    $args['modalidad'] = $modalidad;
}

$sql .= " ORDER BY d.fecha, d.hora_inicio";

$stmt = $conexion->prepare($sql);
$stmt->execute($args);

$tutorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conexion->prepare("
    SELECT COUNT(*)
    FROM notificaciones
    WHERE usuario_id = ?
      AND leido = 0
");

$stmt->execute([$id]);

$noLeidas = (int)$stmt->fetchColumn();
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Buscar tutorías - TutorLink</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
        integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD"
        crossorigin="anonymous"
    >

    <link rel="stylesheet" href="../estilos/tutor.css">

    <style nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, "UTF-8") ?>">
        .tl-box {
            background: white;
            border: 1px solid #e8edf0;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 8px 25px #102b3f0b;
        }

        .tl-info {
            color: #687d8b;
            font-size: 14px;
            margin: 8px 0;
        }

        .tl-info i {
            color: #176b52;
        }

        .tl-badge {
            border-radius: 25px;
            padding: 5px 10px;
            background: #e5f5ef;
            color: #176b52;
            font-size: 12px;
        }

        .tl-space {
            margin-top: 28px;
        }

        .tl-btn {
            background: #176b52;
            border: 0;
            color: white;
            border-radius: 10px;
            padding: 10px 15px;
        }

        .tl-btn:hover {
            background: #12543f;
            color: white;
        }

        .tl-alert {
            background: #f5b942;
            border-radius: 12px;
            padding: 2px 7px;
            font-size: 11px;
        }
    </style>

</head>

<body>

<!-- ==========================================================
     MENÚ LATERAL
     ========================================================== -->

<aside class="sidebar">

    <div class="logo">
        <i class="bi bi-mortarboard-fill"></i>
        TutorLink
    </div>

    <div class="menu-titulo">
        MENÚ PRINCIPAL
    </div>

    <a href="panel.php">
        <i class="bi bi-grid-1x2-fill"></i>
        Inicio
    </a>

    <a class="activo" href="buscar.php">
        <i class="bi bi-search"></i>
        Buscar tutorías
    </a>

    <a href="solicitudes.php?vista=solicitudes">
        <i class="bi bi-envelope-paper-fill"></i>
        Mis solicitudes
    </a>

    <div class="menu-titulo">
        MIS TUTORÍAS
    </div>

    <a href="solicitudes.php?vista=tutorias">
        <i class="bi bi-calendar-check-fill"></i>
        Mis tutorías
    </a>

    <a href="notificaciones.php">

        <i class="bi bi-bell-fill"></i>
        Notificaciones

        <?php if ($noLeidas): ?>

            <span class="tl-alert">
                <?= $noLeidas ?>
            </span>

        <?php endif; ?>

    </a>

    <form action="../apis/logout.php" method="POST" class="logout-form">
        <input type="hidden" name="token" value="<?= e($_SESSION["csrf_logout"]) ?>">
        <button type="submit" class="salir">
        <i class="bi bi-box-arrow-right"></i>
        Cerrar sesión
        </button>
    </form>

</aside>

<!-- ==========================================================
     CONTENIDO PRINCIPAL
     ========================================================== -->

<main class="contenido">

    <header class="barra-superior">

        <div>

            <h1 class="titulo-pagina">
                Buscar tutorías
            </h1>

            <p class="subtitulo">
                TutorLink - Sistema de Gestión de Tutorías y Asesorías Académicas
            </p>

        </div>

        <div class="perfil">

            <div class="avatar">
                <?= e($inicial) ?>
            </div>

            <div>

                <strong><?= e($nombre) ?></strong>

                <div class="text-muted small">
                    Estudiante
                </div>

            </div>

        </div>

    </header>

    <!-- ======================================================
         BIENVENIDA
         ====================================================== -->

    <section class="bienvenida">

        <div>

            <h2>Encuentra tu próxima tutoría</h2>

            <p>
                Elige un curso, consulta horarios disponibles
                y envía tu solicitud.
            </p>

        </div>

        <i class="bi bi-search bienvenida-icono"></i>

    </section>

    <!-- MENSAJES -->

    <?php if ($flash): ?>

        <div class="alert alert-success mt-3">
            <?= e($flash) ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert alert-danger mt-3">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <!-- ======================================================
         FILTROS
         ====================================================== -->

    <section class="tl-space">

        <h4 class="seccion-titulo">
            Filtrar tutorías
        </h4>

        <div class="tl-box">

            <form
                method="get"
                class="row g-3 align-items-end"
            >

                <div class="col-md-6">

                    <label class="form-label" for="curso">
                        Curso
                    </label>

                    <select
                        id="curso"
                        name="curso"
                        class="form-select"
                    >

                        <option value="">
                            Todos los cursos
                        </option>

                        <?php foreach ($cursos as $c): ?>

                            <option
                                value="<?= (int)$c['id'] ?>"
                                <?= $curso === (int)$c['id'] ? 'selected' : '' ?>
                            >
                                <?= e(
                                    $c['codigo'] . ' - ' .
                                    $c['nombre']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-4">

                    <label class="form-label" for="modalidad">
                        Modalidad
                    </label>

                    <select
                        id="modalidad"
                        name="modalidad"
                        class="form-select"
                    >

                        <option value="">
                            Todas
                        </option>

                        <option
                            value="VIRTUAL"
                            <?= $modalidad === 'VIRTUAL' ? 'selected' : '' ?>
                        >
                            Virtual
                        </option>

                        <option
                            value="PRESENCIAL"
                            <?= $modalidad === 'PRESENCIAL' ? 'selected' : '' ?>
                        >
                            Presencial
                        </option>

                    </select>

                </div>

                <div class="col-md-2">

                    <button class="btn tl-btn w-100">

                        <i class="bi bi-search"></i>
                        Buscar

                    </button>

                </div>

            </form>

        </div>

    </section>

    <!-- ======================================================
         HORARIOS DISPONIBLES
         ====================================================== -->

    <section class="tl-space">

        <h4 class="seccion-titulo">

            Horarios disponibles
            (<?= count($tutorias) ?>)

        </h4>

        <?php if (!$tutorias): ?>

            <div class="tl-box text-center text-muted">

                <p class="mb-2">
                    No se encontraron tutorías que coincidan con los filtros.
                </p>
                <p class="mb-0">
                    Solo aparecen horarios futuros de cursos de tu carrera.
                    Si no hay ninguno, pide a un tutor que publique una nueva
                    disponibilidad.
                </p>

            </div>

        <?php else: ?>

            <div class="row g-4">

                <?php foreach ($tutorias as $t): ?>

                    <div class="col-xl-4 col-md-6">

                        <div class="tl-box h-100">

                            <div class="d-flex justify-content-between gap-2">

                                <h5>
                                    <?= e($t['curso']) ?>
                                </h5>

                                <span class="tl-badge align-self-start">
                                    <?= e($t['modalidad']) ?>
                                </span>

                            </div>

                            <div class="tl-info">

                                <i class="bi bi-book"></i>

                                <?= e($t['codigo']) ?>

                            </div>

                            <div class="tl-info">

                                <i class="bi bi-person"></i>

                                Tutor:
                                <?= e(
                                    $t['tutor'] . ' ' .
                                    $t['apellido']
                                ) ?>

                            </div>

                            <div class="tl-info">

                                <i class="bi bi-calendar3"></i>

                                <?= e(
                                    date(
                                        'd/m/Y',
                                        strtotime($t['fecha'])
                                    )
                                ) ?>

                            </div>

                            <div class="tl-info">

                                <i class="bi bi-clock"></i>

                                <?= e(
                                    substr($t['hora_inicio'], 0, 5) .
                                    ' - ' .
                                    substr($t['hora_fin'], 0, 5)
                                ) ?>

                            </div>

                            <div class="tl-info">

                                <i class="bi bi-people"></i>

                                <?= e($t['tipo_tutoria']) ?>

                                · Cupos:

                                <?= (int)$t['cupo_maximo'] -
                                    (int)$t['cupo_actual'] ?>

                            </div>

                            <?php if ($t['modalidad'] === 'PRESENCIAL'): ?>

                                <div class="tl-info">

                                    <i class="bi bi-geo-alt"></i>

                                    <?= e($t['lugar_link']) ?>

                                </div>

                            <?php else: ?>

                                <div class="tl-info">

                                    <i class="bi bi-camera-video"></i>

                                    Meet visible tras la aceptación

                                </div>

                            <?php endif; ?>

                            <button
                                class="btn tl-btn w-100 mt-3"
                                type="button"
                                data-dialog-target="sol<?= (int)$t['id'] ?>"
                            >
                                Solicitar tutoría
                            </button>

                        </div>

                    </div>

                    <!-- ======================================
                         MODAL SOLICITAR TUTORÍA
                         ====================================== -->

                    <dialog
                        class="modal fade"
                        id="sol<?= (int)$t['id'] ?>"
                        aria-hidden="true"
                    >

                        <div class="modal-dialog modal-dialog-centered">

                            <div class="modal-content">

                                <form method="post" action="../apis/estudiante_buscar.php">

                                    <div class="modal-header">

                                        <h5 class="modal-title">
                                            Solicitar tutoría
                                        </h5>

                                        <button
                                            class="btn-close"
                                            type="button"
                                            data-dialog-dismiss
                                            aria-label="Cerrar"
                                        ></button>

                                    </div>

                                    <div class="modal-body">

                                        <p>

                                            <strong>
                                                <?= e($t['curso']) ?>
                                            </strong>

                                            <br>

                                            <?= e(
                                                $t['tutor'] . ' ' .
                                                $t['apellido']
                                            ) ?>

                                            <br>

                                            <?= e(
                                                date(
                                                    'd/m/Y',
                                                    strtotime($t['fecha'])
                                                ) .
                                                ' · ' .
                                                substr($t['hora_inicio'], 0, 5)
                                            ) ?>

                                        </p>

                                        <input
                                            type="hidden"
                                            name="token"
                                            value="<?= e($token) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="disponibilidad_id"
                                            value="<?= (int)$t['id'] ?>"
                                        >

                                        <label
                                            class="form-label"
                                            for="motivo<?= (int)$t['id'] ?>"
                                        >
                                            ¿En qué necesitas ayuda?
                                        </label>

                                        <textarea
                                            class="form-control"
                                            id="motivo<?= (int)$t['id'] ?>"
                                            name="motivo"
                                            rows="4"
                                            maxlength="1000"
                                            required
                                        ></textarea>

                                    </div>

                                    <div class="modal-footer">

                                        <button
                                            type="button"
                                            class="btn btn-secondary"
                                            data-dialog-dismiss
                                        >
                                            Volver
                                        </button>

                                        <button
                                            type="submit"
                                            class="btn tl-btn"
                                        >
                                            Enviar solicitud
                                        </button>

                                    </div>
                                </form>

                            </div>

                        </div>

                    </dialog>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

<script src="../js/student-booking-dialog.js" defer></script>

</body>
</html>