
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

/* ==========================================================
   VALIDAR ESTUDIANTE
   ========================================================== */

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

/* ==========================================================
   CONSULTAR NOTIFICACIONES
   CORREGIDO: SIN COLUMNA 'leida'
   ========================================================== */

$stmt = $conexion->prepare("
    SELECT
        id,
        solicitud_id,
        tipo,
        mensaje
    FROM notificaciones
    WHERE usuario_id = ?
    ORDER BY id DESC
");

$stmt->execute([$id]);

$notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalNotificaciones = count($notificaciones);
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Notificaciones - TutorLink</title>

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
            box-shadow: 0 8px 25px #102b3f0b;
        }

        .tl-space {
            margin-top: 28px;
        }

        .tl-notif {
            border-bottom: 1px solid #edf0f2;
            padding: 17px 0;
        }

        .tl-notif:last-child {
            border-bottom: 0;
        }

        .tl-link {
            color: #176b52;
            text-decoration: none;
            font-weight: 600;
        }

        .tl-link:hover {
            color: #12543f;
        }

        .tl-muted {
            font-size: 13px;
            color: #718292;
        }

        .tl-alert {
            background: #f5b942;
            color: #102b3f;
            border-radius: 12px;
            padding: 2px 7px;
            font-size: 11px;
        }

        .tl-icono {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #e5f5ef;
            color: #176b52;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .tl-notif-contenido {
            flex: 1;
            min-width: 0;
        }

        .tl-tipo {
            font-weight: 700;
            color: #102b3f;
            margin-bottom: 5px;
        }

        .tl-mensaje {
            color: #516675;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .tl-contador {
            background: #e5f5ef;
            color: #176b52;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 13px;
            font-weight: 600;
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

    <a href="buscar.php">
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

    <a class="activo" href="notificaciones.php">

        <i class="bi bi-bell-fill"></i>
        Notificaciones

        <?php if ($totalNotificaciones > 0): ?>

            <span class="tl-alert">
                <?= $totalNotificaciones ?>
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
                Notificaciones
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

                <strong>
                    <?= e($nombre) ?>
                </strong>

                <div class="text-muted small">
                    Estudiante
                </div>

            </div>

        </div>

    </header>

    <!-- BIENVENIDA -->

    <section class="bienvenida">

        <div>

            <h2>Mantente al tanto</h2>

            <p>
                Revisa las respuestas de tus tutores
                y los cambios de tus solicitudes.
            </p>

        </div>

        <i class="bi bi-bell bienvenida-icono"></i>

    </section>

    <!-- ======================================================
         LISTADO DE NOTIFICACIONES
         ====================================================== -->

    <section class="tl-space">

        <div class="tl-box">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">

                <div>

                    <h4 class="m-0">
                        Mis notificaciones
                    </h4>

                    <div class="tl-muted mt-1">
                        Consulta tus avisos y actualizaciones.
                    </div>

                </div>

                <span class="tl-contador">

                    <i class="bi bi-bell"></i>

                    <?= $totalNotificaciones ?>

                    <?= $totalNotificaciones === 1
                        ? 'notificación'
                        : 'notificaciones' ?>

                </span>

            </div>

            <!-- SIN NOTIFICACIONES -->

            <?php if (!$notificaciones): ?>

                <div class="text-center text-muted py-5">

                    <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>

                    <h5>No tienes notificaciones todavía</h5>

                    <p>
                        Cuando un tutor responda tus solicitudes,
                        podrás consultar los avisos aquí.
                    </p>

                    <a
                        class="tl-link"
                        href="solicitudes.php?vista=solicitudes"
                    >
                        Ver mis solicitudes
                        <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

            <?php endif; ?>

            <!-- NOTIFICACIONES RECIBIDAS -->

            <?php foreach ($notificaciones as $n): ?>

                <div class="tl-notif">

                    <div class="d-flex gap-3 align-items-start">

                        <div class="tl-icono">

                            <?php if ($n['tipo'] === 'ACEPTACION'): ?>

                                <i class="bi bi-check-circle-fill"></i>

                            <?php elseif ($n['tipo'] === 'RECHAZO'): ?>

                                <i class="bi bi-x-circle-fill"></i>

                            <?php else: ?>

                                <i class="bi bi-bell-fill"></i>

                            <?php endif; ?>

                        </div>

                        <div class="tl-notif-contenido">

                            <div class="tl-tipo">

                                <?php if ($n['tipo'] === 'ACEPTACION'): ?>

                                    Solicitud aceptada

                                <?php elseif ($n['tipo'] === 'RECHAZO'): ?>

                                    Solicitud rechazada

                                <?php elseif ($n['tipo'] === 'SOLICITUD'): ?>

                                    Actualización de solicitud

                                <?php else: ?>

                                    <?= e($n['tipo']) ?>

                                <?php endif; ?>

                            </div>

                            <p class="tl-mensaje">
                                <?= nl2br(e($n['mensaje'])) ?>
                            </p>

                            <?php if (!empty($n['solicitud_id'])): ?>

                                <a
                                    class="tl-link"
                                    href="solicitudes.php?vista=solicitudes"
                                >

                                    Ver mis solicitudes

                                    <i class="bi bi-arrow-right"></i>

                                </a>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </section>

</main>

</body>
</html>