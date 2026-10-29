
<?php
require_once '../config/conexion.php';
require_once '../seguridad/verificar_sesion.php';

verificarAcceso('ESTUDIANTE');

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function meet($url) {
    return (bool)preg_match(
        '~^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}/?(?:\?[^\s]*)?$~i',
        trim((string)$url)
    );
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

$stmt = $conexion->prepare("
    SELECT u.id
    FROM usuarios u
    JOIN roles r ON r.id = u.rol_id
    WHERE u.id = ?
      AND r.nombre = 'ESTUDIANTE'
      AND u.estado = 'ACTIVO'
");

$stmt->execute([$id]);

if (!$stmt->fetchColumn()) {
    exit('Cuenta no activa.');
}

if (empty($_SESSION['csrf_solicitudes_est'])) {
    $_SESSION['csrf_solicitudes_est'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['csrf_solicitudes_est'];

$vista = (string)($_GET['vista'] ?? 'solicitudes');

if (!in_array($vista, ['solicitudes', 'tutorias'], true)) {
    $vista = 'solicitudes';
}

$error = '';
$flash = $_SESSION['flash_sol_est'] ?? '';
unset($_SESSION['flash_sol_est']);

/* ==========================================================
   CANCELAR SOLICITUD
   ========================================================== */

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
                SELECT id, disponibilidad_id, estado
                FROM solicitudes_tutoria
                WHERE id = ?
                  AND estudiante_id = ?
                FOR UPDATE
            ");

            $stmt->execute([$sid, $id]);
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

            $stmt->execute([$s['disponibilidad_id']]);
            $d = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$d ||
                strtotime($d['fecha'] . ' ' . $d['hora_inicio']) <= time()
            ) {
                throw new RuntimeException(
                    'No puedes cancelar una tutoría que ya comenzó.'
                );
            }

            $stmt = $conexion->prepare("
                UPDATE solicitudes_tutoria
                SET estado = 'CANCELADA',
                    reserva_activa = 0
                WHERE id = ?
                  AND estudiante_id = ?
            ");

            $stmt->execute([$sid, $id]);

            if ($s['estado'] === 'ACEPTADA') {

                $stmt = $conexion->prepare("
                    UPDATE disponibilidades
                    SET
                        cupo_actual = GREATEST(cupo_actual - 1, 0),
                        estado = CASE
                            WHEN estado = 'AGOTADO'
                            THEN 'DISPONIBLE'
                            ELSE estado
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
                    'Cancelación realizada por el estudiante'
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
                    'SOLICITUD',
                    'El estudiante canceló su solicitud de tutoría.'
                )
            ");

            $stmt->execute([
                $d['tutor_id'],
                $sid
            ]);

            $conexion->commit();

            $_SESSION['flash_sol_est'] =
                'Solicitud cancelada correctamente.';

            header(
                'Location: solicitudes.php?vista=' .
                urlencode($vista)
            );
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
            $error = 'No fue posible cancelar la solicitud.';
        }
    }
}

/* ==========================================================
   CONSULTAR SOLICITUDES
   ========================================================== */

$stmt = $conexion->prepare("
    SELECT
        s.id,
        s.motivo,
        s.estado,
        s.created_at,
        d.fecha,
        d.hora_inicio,
        d.hora_fin,
        d.modalidad,
        d.tipo_tutoria,
        d.lugar_link,
        d.estado AS disponibilidad_estado,
        c.codigo,
        c.nombre AS curso,
        t.nombre AS tutor,
        t.apellido
    FROM solicitudes_tutoria s
    JOIN disponibilidades d
        ON d.id = s.disponibilidad_id
    JOIN cursos c
        ON c.id = d.curso_id
    JOIN usuarios t
        ON t.id = d.tutor_id
    WHERE s.estudiante_id = ?
    ORDER BY s.created_at DESC
");

$stmt->execute([$id]);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   ESTADÍSTICAS
   ========================================================== */

$pendientes = 0;
$aceptadas = 0;
$atendidas = 0;

foreach ($solicitudes as $s) {

    if ($s['estado'] === 'PENDIENTE') {
        $pendientes++;
    }

    if ($s['estado'] === 'ACEPTADA') {
        $aceptadas++;
    }

    if ($s['estado'] === 'ATENDIDA') {
        $atendidas++;
    }
}

/* ==========================================================
   NOTIFICACIONES - SIN COLUMNA LEIDA
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

    <title>Mis solicitudes y tutorías - TutorLink</title>

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
            background: #fff;
            border: 1px solid #e8edf0;
            border-radius: 18px;
            padding: 23px;
            box-shadow: 0 7px 24px #102b3f0b;
        }

        .tl-space {
            margin-top: 28px;
        }

        .tl-small {
            color: #6b7d8a;
            font-size: 14px;
        }

        .tl-small i {
            color: #176b52;
        }

        .tl-pill {
            padding: 5px 10px;
            border-radius: 18px;
            font-size: 12px;
            font-weight: 700;
            background: #fff3d8;
            color: #98640c;
        }

        .tl-pill.ACEPTADA,
        .tl-pill.ATENDIDA {
            background: #e4f5ed;
            color: #176b52;
        }

        .tl-pill.RECHAZADA,
        .tl-pill.CANCELADA {
            background: #fce8e8;
            color: #b42318;
        }

        .tl-link {
            color: #176b52;
            text-decoration: none;
            font-weight: 600;
        }

        .tl-btn {
            background: #176b52;
            color: white;
            border: 0;
            border-radius: 9px;
            padding: 9px 15px;
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

    <a
        class="<?= $vista === 'solicitudes' ? 'activo' : '' ?>"
        href="solicitudes.php?vista=solicitudes"
        style="display:none"
    ></a>

    <a href="buscar.php">
        <i class="bi bi-search"></i>
        Buscar tutorías
    </a>

    <a
        class="<?= $vista === 'solicitudes' ? 'activo' : '' ?>"
        href="solicitudes.php?vista=solicitudes"
    >
        <i class="bi bi-envelope-paper-fill"></i>
        Mis solicitudes
    </a>

    <div class="menu-titulo">
        MIS TUTORÍAS
    </div>

    <a
        class="<?= $vista === 'tutorias' ? 'activo' : '' ?>"
        href="solicitudes.php?vista=tutorias"
    >
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
                <?= $vista === 'tutorias'
                    ? 'Mis tutorías'
                    : 'Mis solicitudes' ?>
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

            <h2>
                <?= $vista === 'tutorias'
                    ? 'Organiza tus sesiones'
                    : 'Seguimiento de solicitudes' ?>
            </h2>

            <p>
                <?= $vista === 'tutorias'
                    ? 'Consulta tus tutorías aceptadas, horarios y enlaces de reunión.'
                    : 'Revisa tus solicitudes y cancela las que todavía no hayan comenzado.' ?>
            </p>

        </div>

        <i class="bi bi-calendar-check bienvenida-icono"></i>

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
         CONTENIDO DE SOLICITUDES O TUTORÍAS
         SIN BOTONES DUPLICADOS
         ====================================================== -->

    <section class="tl-space">

        <!-- ESTADÍSTICAS -->

        <div class="row g-3 mb-4">

            <?php
            foreach ([
                ['Total de solicitudes', count($solicitudes)],
                ['Pendientes', $pendientes],
                ['Aceptadas', $aceptadas],
                ['Atendidas', $atendidas]
            ] as $n):
            ?>

                <div class="col-md-3 col-6">

                    <div class="tl-box">

                        <div
                            class="fs-2 fw-bold"
                            style="color:#102b3f"
                        >
                            <?= $n[1] ?>
                        </div>

                        <div class="tl-small">
                            <?= e($n[0]) ?>
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <!-- LISTADO -->

        <div class="row g-3">

            <?php
            $visibles = 0;

            foreach ($solicitudes as $s):

                if (
                    $vista === 'tutorias' &&
                    !in_array(
                        $s['estado'],
                        ['ACEPTADA', 'ATENDIDA'],
                        true
                    )
                ) {
                    continue;
                }

                $visibles++;

                $puedeCancelar =
                    in_array(
                        $s['estado'],
                        ['PENDIENTE', 'ACEPTADA'],
                        true
                    ) &&
                    strtotime(
                        $s['fecha'] . ' ' . $s['hora_inicio']
                    ) > time();
            ?>

                <div class="col-lg-6">

                    <div class="tl-box h-100">

                        <div class="d-flex justify-content-between gap-2 align-items-start">

                            <h5>
                                <?= e($s['curso']) ?>
                            </h5>

                            <span class="tl-pill <?= e($s['estado']) ?>">
                                <?= e($s['estado']) ?>
                            </span>

                        </div>

                        <div class="tl-small mb-2">
                            <?= e($s['codigo']) ?>
                        </div>

                        <div class="tl-small">

                            <i class="bi bi-person"></i>

                            Tutor:
                            <?= e(
                                $s['tutor'] . ' ' .
                                $s['apellido']
                            ) ?>

                        </div>

                        <div class="tl-small">

                            <i class="bi bi-calendar3"></i>

                            <?= e(
                                date(
                                    'd/m/Y',
                                    strtotime($s['fecha'])
                                )
                            ) ?>

                            ·

                            <?= e(
                                substr($s['hora_inicio'], 0, 5) .
                                ' - ' .
                                substr($s['hora_fin'], 0, 5)
                            ) ?>

                        </div>

                        <div class="tl-small">

                            <i class="bi bi-camera-video"></i>

                            <?= e(
                                $s['modalidad'] . ' · ' .
                                $s['tipo_tutoria']
                            ) ?>

                        </div>

                        <!-- GOOGLE MEET O UBICACIÓN -->

                        <?php if (
                            $s['estado'] === 'ACEPTADA' ||
                            $s['estado'] === 'ATENDIDA'
                        ): ?>

                            <?php if (
                                $s['modalidad'] === 'VIRTUAL' &&
                                meet($s['lugar_link'])
                            ): ?>

                                <div class="mt-2">

                                    <a
                                        class="tl-link"
                                        href="<?= e($s['lugar_link']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <i class="bi bi-camera-video-fill"></i>
                                        Unirse a Google Meet
                                    </a>

                                </div>

                            <?php elseif (
                                $s['modalidad'] === 'PRESENCIAL'
                            ): ?>

                                <div class="tl-small">

                                    <i class="bi bi-geo-alt"></i>

                                    <?= e($s['lugar_link']) ?>

                                </div>

                            <?php endif; ?>

                        <?php endif; ?>

                        <!-- MOTIVO DE SOLICITUD -->

                        <?php if ($vista === 'solicitudes'): ?>

                            <div class="tl-small mt-2">

                                <strong>Motivo:</strong>

                                <?= nl2br(e($s['motivo'])) ?>

                            </div>

                        <?php endif; ?>

                        <!-- HORARIO CANCELADO -->

                        <?php if (
                            $s['disponibilidad_estado'] === 'CANCELADO'
                        ): ?>

                            <div class="alert alert-warning mt-2 mb-0 py-2">
                                El tutor canceló este horario.
                            </div>

                        <?php endif; ?>

                        <!-- CANCELAR SOLICITUD -->

                        <?php if ($puedeCancelar): ?>

                            <form
                                method="post"
                                class="mt-3"
                                onsubmit="return confirm('¿Deseas cancelar esta solicitud?');"
                            >

                                <input
                                    type="hidden"
                                    name="token"
                                    value="<?= e($token) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="cancelar"
                                >

                                <input
                                    type="hidden"
                                    name="solicitud_id"
                                    value="<?= (int)$s['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-outline-danger btn-sm"
                                >
                                    <i class="bi bi-x-circle"></i>
                                    Cancelar solicitud
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

            <!-- SIN RESULTADOS -->

            <?php if (!$visibles): ?>

                <div class="col-12">

                    <div class="tl-box text-center text-muted py-5">

                        <?= $vista === 'tutorias'
                            ? 'Aún no tienes tutorías aceptadas o atendidas.'
                            : 'Aún no has enviado solicitudes.' ?>

                        <a class="tl-link" href="buscar.php">
                            Buscar tutorías
                        </a>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

</body>
</html>