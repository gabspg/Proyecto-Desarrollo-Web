
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

            /* EVITAR SOLICITUDES DUPLICADAS */

            $stmt = $conexion->prepare("
                SELECT COUNT(*)
                FROM solicitudes_tutoria
                WHERE estudiante_id = ?
                  AND disponibilidad_id = ?
                  AND estado IN (
                      'PENDIENTE',
                      'ACEPTADA',
                      'ATENDIDA'
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

            $conexion->commit();

            $_SESSION['flash_estudiante'] =
                'Solicitud enviada. Espera la respuesta del tutor.';

            header('Location: buscar.php');
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

            $error = 'No se pudo guardar la solicitud. Detalle: ' .
                $ex->getMessage();
        }
    }
}

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

/* ==========================================================
   NOTIFICACIONES
   CORREGIDO: SIN COLUMNA 'leida'
   ========================================================== */

$stmt = $conexion->prepare("
    SELECT COUNT(*)
    FROM notificaciones
    WHERE usuario_id = ?
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
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../estilos/tutor.css">

    <style>
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

    <a
        class="salir"
        href="/tutorias/autenticacion/logout.php"
    >
        <i class="bi bi-box-arrow-right"></i>
        Cerrar sesión
    </a>

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

                No se encontraron tutorías disponibles
                con esos filtros.

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
                                data-bs-toggle="modal"
                                data-bs-target="#sol<?= (int)$t['id'] ?>"
                            >
                                Solicitar tutoría
                            </button>

                        </div>

                    </div>

                    <!-- ======================================
                         MODAL SOLICITAR TUTORÍA
                         ====================================== -->

                    <div
                        class="modal fade"
                        id="sol<?= (int)$t['id'] ?>"
                        tabindex="-1"
                        aria-hidden="true"
                    >

                        <div class="modal-dialog modal-dialog-centered">

                            <div class="modal-content">

                                <form method="post">

                                    <div class="modal-header">

                                        <h5 class="modal-title">
                                            Solicitar tutoría
                                        </h5>

                                        <button
                                            class="btn-close"
                                            type="button"
                                            data-bs-dismiss="modal"
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
                                            data-bs-dismiss="modal"
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

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>
</html>