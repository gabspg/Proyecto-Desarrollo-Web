
<?php
require_once '../config/conexion.php';
require_once '../seguridad/verificar_sesion.php';
verificarAcceso('ESTUDIANTE');

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id = (int)($_SESSION['usuario_id'] ?? 0);
$nombre = $_SESSION['nombre'] ?? 'Estudiante';

if ($id < 1) exit('Sesión inválida.');

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

function contar($db, $sql, $id) {
    $s = $db->prepare($sql);
    $s->execute([$id]);
    return (int)$s->fetchColumn();
}

$total = contar(
    $conexion,
    'SELECT COUNT(*) FROM solicitudes_tutoria WHERE estudiante_id=?',
    $id
);

$pendientes = contar(
    $conexion,
    "SELECT COUNT(*) FROM solicitudes_tutoria
     WHERE estudiante_id=? AND estado='PENDIENTE'",
    $id
);

$proximas = contar(
    $conexion,
    "SELECT COUNT(*)
     FROM solicitudes_tutoria s
     JOIN disponibilidades d ON d.id=s.disponibilidad_id
     WHERE s.estudiante_id=?
       AND s.estado='ACEPTADA'
       AND d.estado<>'CANCELADO'
       AND TIMESTAMP(d.fecha,d.hora_fin)>=NOW()",
    $id
);

$atendidas = contar(
    $conexion,
    "SELECT COUNT(*) FROM solicitudes_tutoria
     WHERE estudiante_id=? AND estado='ATENDIDA'",
    $id
);

/* ==========================================================
   NOTIFICACIONES
   CORREGIDO: SE ELIMINÓ LA COLUMNA INEXISTENTE 'leida'
   ========================================================== */

$noLeidas = contar(
    $conexion,
    "SELECT COUNT(*)
     FROM notificaciones
     WHERE usuario_id=?",
    $id
);

/* ==========================================================
   PRÓXIMAS TUTORÍAS
   ========================================================== */

$stmt = $conexion->prepare("
    SELECT
        c.nombre curso,
        t.nombre tutor,
        t.apellido,
        d.fecha,
        d.hora_inicio,
        d.hora_fin,
        d.modalidad,
        d.lugar_link
    FROM solicitudes_tutoria s
    JOIN disponibilidades d ON d.id=s.disponibilidad_id
    JOIN cursos c ON c.id=d.curso_id
    JOIN usuarios t ON t.id=d.tutor_id
    WHERE s.estudiante_id=?
      AND s.estado='ACEPTADA'
      AND d.estado<>'CANCELADO'
      AND TIMESTAMP(d.fecha,d.hora_fin)>=NOW()
    ORDER BY d.fecha,d.hora_inicio
    LIMIT 5
");
$stmt->execute([$id]);
$agenda = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   SOLICITUDES RECIENTES
   ========================================================== */

$stmt = $conexion->prepare("
    SELECT
        s.estado,
        c.nombre curso,
        d.fecha,
        d.hora_inicio
    FROM solicitudes_tutoria s
    JOIN disponibilidades d ON d.id=s.disponibilidad_id
    JOIN cursos c ON c.id=d.curso_id
    WHERE s.estudiante_id=?
    ORDER BY s.created_at DESC
    LIMIT 5
");
$stmt->execute([$id]);
$recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   VALIDAR ENLACE DE GOOGLE MEET
   ========================================================== */

function meet($url) {
    return (bool)preg_match(
        '~^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}/?(?:\?[^\s]*)?$~i',
        trim((string)$url)
    );
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <title>Inicio - TutorLink</title>

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
            border: 1px solid #e7ecef;
            border-radius: 18px;
            padding: 23px;
            height: 100%;
            box-shadow: 0 7px 22px #102b3f0b;
        }

        .tl-num {
            font-size: 34px;
            font-weight: 750;
            color: #102b3f;
        }

        .tl-muted {
            color: #718292;
            font-size: 14px;
        }

        .tl-item {
            padding: 13px 0;
            border-bottom: 1px solid #edf0f2;
        }

        .tl-item:last-child {
            border: 0;
        }

        .tl-link {
            color: #176b52;
            text-decoration: none;
            font-weight: 600;
        }

        .tl-space {
            margin-top: 28px;
        }

        .tl-pill {
            font-size: 12px;
            background: #e5f5ef;
            color: #176b52;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .tl-alert {
            background: #f5b942;
            color: #102b3f;
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

    <div class="menu-titulo">MENÚ PRINCIPAL</div>

    <a class="activo" href="panel.php">
        <i class="bi bi-grid-1x2-fill"></i>
        Inicio
    </a>

    <a href="buscar.php">
        <i class="bi bi-search"></i>
        Buscar tutorías
    </a>

    <a href="solicitudes.php?vista=solicitudes">
        <i class="bi bi-envelope-paper-fill"></i>
        Mis solicitudes
    </a>

    <div class="menu-titulo">MIS TUTORÍAS</div>

    <a href="solicitudes.php?vista=tutorias">
        <i class="bi bi-calendar-check-fill"></i>
        Mis tutorías
    </a>

    <a href="notificaciones.php">
        <i class="bi bi-bell-fill"></i>
        Notificaciones

        <?php if ($noLeidas): ?>
            <span class="tl-alert"><?= $noLeidas ?></span>
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
            <h1 class="titulo-pagina">Panel del Estudiante</h1>

            <p class="subtitulo">
                TutorLink - Sistema de Gestión de Tutorías y Asesorías Académicas
            </p>
        </div>

        <div class="perfil">
            <div class="avatar"><?= e($inicial) ?></div>

            <div>
                <strong><?= e($nombre) ?></strong>
                <div class="text-muted small">Estudiante</div>
            </div>
        </div>

    </header>

    <!-- BIENVENIDA -->

    <section class="bienvenida">

        <div>
            <h2>¡Bienvenido a TutorLink!</h2>

            <p>
                Encuentra tutorías, consulta tus solicitudes
                y organiza tus próximas sesiones.
            </p>
        </div>

        <i class="bi bi-mortarboard bienvenida-icono"></i>

    </section>

    <!-- ======================================================
         ESTADÍSTICAS
         ====================================================== -->

    <section class="tl-space">

        <h4 class="seccion-titulo">Resumen general</h4>

        <div class="row g-3">

            <?php
            foreach ([
                ['bi-envelope-paper-fill', 'Solicitudes enviadas', $total],
                ['bi-hourglass-split', 'Solicitudes pendientes', $pendientes],
                ['bi-calendar-check-fill', 'Tutorías próximas', $proximas],
                ['bi-clipboard-check-fill', 'Tutorías atendidas', $atendidas]
            ] as $item):
            ?>

                <div class="col-xl-3 col-md-6">
                    <div class="tl-box">

                        <i class="bi <?= e($item[0]) ?> fs-3 text-success"></i>

                        <div class="tl-num">
                            <?= $item[2] ?>
                        </div>

                        <div class="tl-muted">
                            <?= e($item[1]) ?>
                        </div>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    </section>

    <!-- ======================================================
         PRÓXIMAS TUTORÍAS Y SOLICITUDES RECIENTES
         ====================================================== -->

    <section class="tl-space">

        <div class="row g-4">

            <!-- PRÓXIMAS TUTORÍAS -->

            <div class="col-lg-7">

                <div class="tl-box">

                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">

                        <h5 class="m-0">Próximas tutorías</h5>

                        <a
                            class="tl-link"
                            href="solicitudes.php?vista=tutorias"
                        >
                            Ver todas
                            <i class="bi bi-arrow-right"></i>
                        </a>

                    </div>

                    <?php if (!$agenda): ?>

                        <p class="tl-muted">
                            Todavía no tienes tutorías próximas aceptadas.
                        </p>

                    <?php endif; ?>

                    <?php foreach ($agenda as $a): ?>

                        <div class="tl-item">

                            <strong><?= e($a['curso']) ?></strong>

                            <span class="tl-pill">
                                <?= e($a['modalidad']) ?>
                            </span>

                            <div class="tl-muted">
                                Tutor:
                                <?= e($a['tutor'] . ' ' . $a['apellido']) ?>
                            </div>

                            <div class="tl-muted">

                                <i class="bi bi-calendar3"></i>

                                <?= e(date('d/m/Y', strtotime($a['fecha']))) ?>

                                ·

                                <?= e(substr($a['hora_inicio'], 0, 5)) ?>
                                –
                                <?= e(substr($a['hora_fin'], 0, 5)) ?>

                            </div>

                            <?php if (
                                $a['modalidad'] === 'VIRTUAL' &&
                                meet($a['lugar_link'])
                            ): ?>

                                <a
                                    class="tl-link"
                                    href="<?= e($a['lugar_link']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Unirse a Meet
                                </a>

                            <?php elseif ($a['modalidad'] === 'PRESENCIAL'): ?>

                                <div class="tl-muted">
                                    Lugar: <?= e($a['lugar_link']) ?>
                                </div>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

            <!-- SOLICITUDES RECIENTES -->

            <div class="col-lg-5">

                <div class="tl-box">

                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">

                        <h5 class="m-0">Solicitudes recientes</h5>

                        <a
                            class="tl-link"
                            href="solicitudes.php?vista=solicitudes"
                        >
                            Ver todas
                            <i class="bi bi-arrow-right"></i>
                        </a>

                    </div>

                    <?php if (!$recientes): ?>

                        <p class="tl-muted">
                            No has enviado solicitudes.
                        </p>

                    <?php endif; ?>

                    <?php foreach ($recientes as $r): ?>

                        <div class="tl-item">

                            <strong><?= e($r['curso']) ?></strong>

                            <div class="tl-muted">

                                <?= e(date('d/m/Y', strtotime($r['fecha']))) ?>

                                ·

                                <?= e(substr($r['hora_inicio'], 0, 5)) ?>

                            </div>

                            <span class="tl-pill">
                                <?= e($r['estado']) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

    </section>

    <!-- ======================================================
         ACCESOS RÁPIDOS
         ====================================================== -->

    <section class="tl-space">

        <h4 class="seccion-titulo">Gestión de tutorías</h4>

        <div class="row g-4">

            <?php
            foreach ([
                [
                    'bi-search',
                    'Buscar tutorías',
                    'Consulta horarios y solicita una tutoría.',
                    'buscar.php'
                ],
                [
                    'bi-envelope-paper-fill',
                    'Mis solicitudes',
                    'Revisa estados y cancela cuando corresponda.',
                    'solicitudes.php?vista=solicitudes'
                ],
                [
                    'bi-calendar-check-fill',
                    'Mis tutorías',
                    'Consulta sesiones aceptadas y enlaces de Meet.',
                    'solicitudes.php?vista=tutorias'
                ],
                [
                    'bi-bell-fill',
                    'Notificaciones',
                    'Consulta respuestas y novedades.',
                    'notificaciones.php'
                ]
            ] as $c):
            ?>

                <div class="col-md-6">

                    <div class="tarjeta-accion h-100">

                        <div class="icono-accion">
                            <i class="bi <?= e($c[0]) ?>"></i>
                        </div>

                        <h4><?= e($c[1]) ?></h4>

                        <p><?= e($c[2]) ?></p>

                        <a
                            class="boton-accion"
                            href="<?= e($c[3]) ?>"
                        >
                            <i class="bi bi-arrow-right-circle"></i>
                            Abrir
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </section>

</main>

</body>
</html>