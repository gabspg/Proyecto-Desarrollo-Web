
<?php
/* ==========================================================
   TUTORLINK - PANEL PRINCIPAL DEL TUTOR
   ========================================================== */

require_once "../config/conexion.php";
require_once "../seguridad/verificar_sesion.php";

verificarAcceso("TUTOR");

/* ==========================================================
   FUNCIONES
   ========================================================== */

function escapar($valor)
{
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

function esMeetValido($enlace)
{
    return (bool)preg_match(
        '~^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}/?(?:\?[^\s]*)?$~i',
        trim((string)$enlace)
    );
}

/* ==========================================================
   DATOS DE SESIÓN
   ========================================================== */

$tutorId = (int)($_SESSION["usuario_id"] ?? 0);
$nombre = $_SESSION["nombre"] ?? "Tutor";

if ($tutorId <= 0) {
    exit("No se pudo identificar al tutor.");
}

$inicial = function_exists("mb_substr")
    ? mb_substr($nombre, 0, 1, "UTF-8")
    : substr($nombre, 0, 1);

$inicial = strtoupper($inicial);

/* ==========================================================
   VERIFICAR TUTOR
   ========================================================== */

$stmt = $conexion->prepare(
    "SELECT u.id
     FROM usuarios u
     INNER JOIN roles r
        ON r.id = u.rol_id
     WHERE u.id = :tutor
       AND r.nombre = 'TUTOR'
       AND u.estado = 'ACTIVO'"
);

$stmt->execute([
    "tutor" => $tutorId
]);

if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    exit("El tutor no está activo.");
}

/* ==========================================================
   ESTADÍSTICAS
   ========================================================== */

/* CURSOS ASIGNADOS */

$stmt = $conexion->prepare(
    "SELECT COUNT(*)
     FROM tutor_cursos
     WHERE tutor_id = :tutor"
);

$stmt->execute([
    "tutor" => $tutorId
]);

$totalCursos = (int)$stmt->fetchColumn();

/* TUTORÍAS PROGRAMADAS */

$stmt = $conexion->prepare(
    "SELECT COUNT(*)
     FROM disponibilidades
     WHERE tutor_id = :tutor
       AND estado <> 'CANCELADO'
       AND TIMESTAMP(fecha, hora_fin) >= NOW()"
);

$stmt->execute([
    "tutor" => $tutorId
]);

$totalHorarios = (int)$stmt->fetchColumn();

/* SOLICITUDES PENDIENTES */

$stmt = $conexion->prepare(
    "SELECT COUNT(*)
     FROM solicitudes_tutoria s
     INNER JOIN disponibilidades d
        ON d.id = s.disponibilidad_id
     WHERE d.tutor_id = :tutor
       AND s.estado = 'PENDIENTE'"
);

$stmt->execute([
    "tutor" => $tutorId
]);

$totalPendientes = (int)$stmt->fetchColumn();

/* TUTORÍAS ATENDIDAS */

$stmt = $conexion->prepare(
    "SELECT COUNT(*)
     FROM atenciones a
     INNER JOIN solicitudes_tutoria s
        ON s.id = a.solicitud_id
     INNER JOIN disponibilidades d
        ON d.id = s.disponibilidad_id
     WHERE d.tutor_id = :tutor"
);

$stmt->execute([
    "tutor" => $tutorId
]);

$totalAtendidas = (int)$stmt->fetchColumn();

/* ==========================================================
   PRÓXIMAS TUTORÍAS
   ========================================================== */

$stmt = $conexion->prepare(
    "SELECT
        d.id,
        d.fecha,
        d.hora_inicio,
        d.hora_fin,
        d.modalidad,
        d.tipo_tutoria,
        d.lugar_link,
        d.estado,
        d.cupo_actual,
        d.cupo_maximo,
        c.nombre AS curso_nombre,
        c.codigo AS curso_codigo
     FROM disponibilidades d
     INNER JOIN cursos c
        ON c.id = d.curso_id
     WHERE d.tutor_id = :tutor
       AND d.estado <> 'CANCELADO'
       AND TIMESTAMP(d.fecha, d.hora_fin) >= NOW()
     ORDER BY
        d.fecha ASC,
        d.hora_inicio ASC
     LIMIT 5"
);

$stmt->execute([
    "tutor" => $tutorId
]);

$proximasTutorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   SOLICITUDES PENDIENTES RECIENTES
   ========================================================== */

$stmt = $conexion->prepare(
    "SELECT
        s.id,
        s.motivo,
        s.created_at,
        e.nombre AS estudiante_nombre,
        e.apellido AS estudiante_apellido,
        c.nombre AS curso_nombre,
        d.fecha,
        d.hora_inicio
     FROM solicitudes_tutoria s
     INNER JOIN disponibilidades d
        ON d.id = s.disponibilidad_id
     INNER JOIN usuarios e
        ON e.id = s.estudiante_id
     INNER JOIN cursos c
        ON c.id = d.curso_id
     WHERE d.tutor_id = :tutor
       AND s.estado = 'PENDIENTE'
     ORDER BY s.created_at DESC
     LIMIT 5"
);

$stmt->execute([
    "tutor" => $tutorId
]);

$solicitudesRecientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Panel del Tutor - TutorLink</title>

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

        /* ESTADÍSTICAS */

        .estadistica-tutor {
            background: #ffffff;
            border: 1px solid #e9edf0;
            border-radius: 18px;
            padding: 23px;
            height: 100%;
            box-shadow: 0 8px 25px rgba(16, 43, 63, 0.05);
            transition: transform 0.2s ease;
        }

        .estadistica-tutor:hover {
            transform: translateY(-3px);
        }

        .estadistica-encabezado {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .estadistica-icono {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: #e5f5ef;
            color: #176b52;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 23px;
            flex-shrink: 0;
        }

        .estadistica-icono.pendientes {
            background: #fff3d8;
            color: #b77912;
        }

        .estadistica-numero {
            color: #102b3f;
            font-size: 34px;
            font-weight: 750;
            margin: 16px 0 4px;
        }

        .estadistica-texto {
            color: #667b8e;
            font-size: 14px;
            margin: 0;
        }

        /* RESUMEN */

        .resumen-tutor {
            background: #ffffff;
            border: 1px solid #e9edf0;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 8px 25px rgba(16, 43, 63, 0.05);
            height: 100%;
        }

        .resumen-titulo {
            font-size: 19px;
            font-weight: 700;
            color: #102b3f;
            margin: 0;
        }

        .resumen-encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .resumen-enlace {
            color: #176b52;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .resumen-enlace:hover {
            text-decoration: underline;
        }

        .tutoria-resumen {
            border: 1px solid #e8edf0;
            border-radius: 13px;
            padding: 16px;
            margin-bottom: 12px;
        }

        .tutoria-resumen:last-child {
            margin-bottom: 0;
        }

        .tutoria-nombre {
            font-weight: 700;
            color: #102b3f;
            margin-bottom: 5px;
        }

        .tutoria-detalle {
            color: #6c7e8e;
            font-size: 13px;
            margin-top: 7px;
        }

        .tutoria-detalle i {
            color: #176b52;
            margin-right: 5px;
        }

        .etiqueta-tutoria {
            display: inline-block;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
        }

        .etiqueta-virtual {
            background: #e8f1ff;
            color: #2360a5;
        }

        .etiqueta-presencial {
            background: #e4f5ed;
            color: #176b52;
        }

        .solicitud-resumen {
            padding: 15px 0;
            border-bottom: 1px solid #edf0f2;
        }

        .solicitud-resumen:first-of-type {
            padding-top: 0;
        }

        .solicitud-resumen:last-child {
            border-bottom: none;
        }

        .solicitud-estudiante {
            color: #102b3f;
            font-weight: 700;
        }

        .solicitud-curso {
            color: #6c7e8e;
            font-size: 13px;
            margin-top: 4px;
        }

        .estado-pendiente {
            display: inline-block;
            background: #fff3d8;
            color: #98640c;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
        }

        .resumen-vacio {
            text-align: center;
            padding: 30px 12px;
            color: #718292;
        }

        .resumen-vacio i {
            font-size: 34px;
            color: #9cb0bd;
            display: block;
            margin-bottom: 10px;
        }

        .resumen-vacio p {
            margin: 0;
            font-size: 14px;
        }

        .boton-meet-resumen {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #176b52;
            color: white;
            padding: 7px 11px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 10px;
        }

        .boton-meet-resumen:hover {
            background: #12543f;
            color: white;
        }

        .seccion-dashboard {
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .estadistica-tutor {
                padding: 18px;
            }

            .resumen-tutor {
                padding: 18px;
            }
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

    <a href="panel.php" class="activo">

        <i class="bi bi-grid-1x2-fill"></i>

        Inicio

    </a>

    <a href="cursos.php?vista=cursos">

        <i class="bi bi-journal-bookmark-fill"></i>

        Cursos

    </a>

    <a href="cursos.php?vista=horarios">

        <i class="bi bi-calendar-week-fill"></i>

        Horarios

    </a>

    <div class="menu-titulo">
        GESTIÓN DE TUTORÍAS
    </div>

    <a href="solicitudes.php">

        <i class="bi bi-envelope-paper-fill"></i>

        Solicitudes recibidas

    </a>

    <a href="atenciones.php">

        <i class="bi bi-clipboard-check-fill"></i>

        Registro de atenciones

    </a>

    <a
        href="/tutorias/autenticacion/logout.php"
        class="salir"
    >

        <i class="bi bi-box-arrow-right"></i>

        Cerrar sesión

    </a>

</aside>

<!-- ==========================================================
     CONTENIDO PRINCIPAL
     ========================================================== -->

<main class="contenido">

    <!-- ENCABEZADO -->

    <header class="barra-superior">

        <div>

            <h1 class="titulo-pagina">
                Panel del Tutor
            </h1>

            <p class="subtitulo">
                TutorLink - Sistema de Gestión de Tutorías y Asesorías Académicas
            </p>

        </div>

        <div class="perfil">

            <div class="avatar">
                <?= escapar($inicial) ?>
            </div>

            <div>

                <strong>
                    <?= escapar($nombre) ?>
                </strong>

                <div class="text-muted small">
                    Tutor
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
                ¡Bienvenido a TutorLink!
            </h2>

            <p>
                Consulta el resumen de tu actividad académica,
                administra tus solicitudes y organiza tus próximas tutorías.
            </p>

        </div>

        <i class="bi bi-mortarboard bienvenida-icono"></i>

    </section>

    <!-- ======================================================
         ESTADÍSTICAS
         ====================================================== -->

    <section class="seccion-dashboard">

        <h4 class="seccion-titulo">
            Resumen general
        </h4>

        <div class="row g-3">

            <!-- CURSOS -->

            <div class="col-xl-3 col-md-6">

                <div class="estadistica-tutor">

                    <div class="estadistica-encabezado">

                        <div class="estadistica-icono">
                            <i class="bi bi-journal-bookmark-fill"></i>
                        </div>

                    </div>

                    <div class="estadistica-numero">
                        <?= $totalCursos ?>
                    </div>

                    <p class="estadistica-texto">
                        Cursos asignados
                    </p>

                </div>

            </div>

            <!-- HORARIOS -->

            <div class="col-xl-3 col-md-6">

                <div class="estadistica-tutor">

                    <div class="estadistica-encabezado">

                        <div class="estadistica-icono">
                            <i class="bi bi-calendar-week-fill"></i>
                        </div>

                    </div>

                    <div class="estadistica-numero">
                        <?= $totalHorarios ?>
                    </div>

                    <p class="estadistica-texto">
                        Tutorías programadas
                    </p>

                </div>

            </div>

            <!-- SOLICITUDES -->

            <div class="col-xl-3 col-md-6">

                <div class="estadistica-tutor">

                    <div class="estadistica-encabezado">

                        <div class="estadistica-icono pendientes">
                            <i class="bi bi-envelope-paper-fill"></i>
                        </div>

                    </div>

                    <div class="estadistica-numero">
                        <?= $totalPendientes ?>
                    </div>

                    <p class="estadistica-texto">
                        Solicitudes pendientes
                    </p>

                </div>

            </div>

            <!-- ATENCIONES -->

            <div class="col-xl-3 col-md-6">

                <div class="estadistica-tutor">

                    <div class="estadistica-encabezado">

                        <div class="estadistica-icono">
                            <i class="bi bi-clipboard-check-fill"></i>
                        </div>

                    </div>

                    <div class="estadistica-numero">
                        <?= $totalAtendidas ?>
                    </div>

                    <p class="estadistica-texto">
                        Tutorías atendidas
                    </p>

                </div>

            </div>

        </div>

    </section>

    <!-- ======================================================
         PRÓXIMAS TUTORÍAS Y SOLICITUDES
         ====================================================== -->

    <section class="seccion-dashboard">

        <div class="row g-4">

            <!-- PRÓXIMAS TUTORÍAS -->

            <div class="col-lg-7">

                <div class="resumen-tutor">

                    <div class="resumen-encabezado">

                        <h4 class="resumen-titulo">

                            <i class="bi bi-calendar-event text-success"></i>

                            Próximas tutorías

                        </h4>

                        <a
                            href="cursos.php?vista=horarios"
                            class="resumen-enlace"
                        >
                            Ver horarios
                            <i class="bi bi-arrow-right"></i>
                        </a>

                    </div>

                    <?php if (empty($proximasTutorias)): ?>

                        <div class="resumen-vacio">

                            <i class="bi bi-calendar-x"></i>

                            <p>
                                No tienes tutorías próximas programadas.
                            </p>

                        </div>

                    <?php else: ?>

                        <?php foreach ($proximasTutorias as $tutoria): ?>

                            <div class="tutoria-resumen">

                                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">

                                    <div>

                                        <div class="tutoria-nombre">
                                            <?= escapar($tutoria["curso_nombre"]) ?>
                                        </div>

                                        <div class="text-muted small">
                                            <?= escapar($tutoria["curso_codigo"]) ?>
                                        </div>

                                    </div>

                                    <?php if ($tutoria["modalidad"] === "VIRTUAL"): ?>

                                        <span class="etiqueta-tutoria etiqueta-virtual">
                                            <i class="bi bi-camera-video"></i>
                                            Virtual
                                        </span>

                                    <?php else: ?>

                                        <span class="etiqueta-tutoria etiqueta-presencial">
                                            <i class="bi bi-geo-alt"></i>
                                            Presencial
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <div class="tutoria-detalle">

                                    <i class="bi bi-calendar3"></i>

                                    <?= escapar(
                                        date(
                                            "d/m/Y",
                                            strtotime($tutoria["fecha"])
                                        )
                                    ) ?>

                                </div>

                                <div class="tutoria-detalle">

                                    <i class="bi bi-clock"></i>

                                    <?= escapar(
                                        substr($tutoria["hora_inicio"], 0, 5)
                                    ) ?>

                                    -

                                    <?= escapar(
                                        substr($tutoria["hora_fin"], 0, 5)
                                    ) ?>

                                </div>

                                <div class="tutoria-detalle">

                                    <i class="bi bi-people"></i>

                                    <?= (int)$tutoria["cupo_actual"] ?>
                                    /
                                    <?= (int)$tutoria["cupo_maximo"] ?>

                                    cupos ocupados

                                </div>

                                <?php if (
                                    $tutoria["modalidad"] === "VIRTUAL" &&
                                    esMeetValido($tutoria["lugar_link"] ?? "")
                                ): ?>

                                    <a
                                        href="<?= escapar($tutoria["lugar_link"]) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="boton-meet-resumen"
                                    >

                                        <i class="bi bi-camera-video-fill"></i>

                                        Unirse a Meet

                                    </a>

                                <?php elseif (
                                    $tutoria["modalidad"] === "PRESENCIAL"
                                ): ?>

                                    <div class="tutoria-detalle">

                                        <i class="bi bi-geo-alt"></i>

                                        <?= escapar(
                                            $tutoria["lugar_link"] ?? ""
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

            <!-- SOLICITUDES RECIENTES -->

            <div class="col-lg-5">

                <div class="resumen-tutor">

                    <div class="resumen-encabezado">

                        <h4 class="resumen-titulo">

                            <i class="bi bi-envelope-paper text-success"></i>

                            Solicitudes pendientes

                        </h4>

                        <a
                            href="solicitudes.php"
                            class="resumen-enlace"
                        >

                            Ver todas

                            <i class="bi bi-arrow-right"></i>

                        </a>

                    </div>

                    <?php if (empty($solicitudesRecientes)): ?>

                        <div class="resumen-vacio">

                            <i class="bi bi-inbox"></i>

                            <p>
                                No tienes solicitudes pendientes.
                            </p>

                        </div>

                    <?php else: ?>

                        <?php foreach ($solicitudesRecientes as $solicitud): ?>

                            <div class="solicitud-resumen">

                                <div class="d-flex justify-content-between align-items-start gap-2">

                                    <div>

                                        <div class="solicitud-estudiante">

                                            <?= escapar(
                                                $solicitud["estudiante_nombre"]
                                                . " " .
                                                $solicitud["estudiante_apellido"]
                                            ) ?>

                                        </div>

                                        <div class="solicitud-curso">

                                            <?= escapar(
                                                $solicitud["curso_nombre"]
                                            ) ?>

                                        </div>

                                    </div>

                                    <span class="estado-pendiente">
                                        Pendiente
                                    </span>

                                </div>

                                <div class="tutoria-detalle">

                                    <i class="bi bi-calendar3"></i>

                                    <?= escapar(
                                        date(
                                            "d/m/Y",
                                            strtotime($solicitud["fecha"])
                                        )
                                    ) ?>

                                    ·

                                    <?= escapar(
                                        substr($solicitud["hora_inicio"], 0, 5)
                                    ) ?>

                                </div>

                                <?php if (
                                    trim((string)$solicitud["motivo"]) !== ""
                                ): ?>

                                    <div class="tutoria-detalle">

                                        <i class="bi bi-chat-left-text"></i>

                                        <?= escapar(
                                            $solicitud["motivo"]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>

    <!-- ======================================================
         GESTIÓN ACADÉMICA
         ====================================================== -->

    <section class="seccion-dashboard">

        <h4 class="seccion-titulo">
            Gestión académica
        </h4>

        <div class="row g-4">

            <!-- CURSOS -->

            <div class="col-lg-6 col-md-6">

                <div class="tarjeta-accion h-100">

                    <div class="icono-accion">
                        <i class="bi bi-journal-bookmark-fill"></i>
                    </div>

                    <h4>Mis cursos</h4>

                    <p>
                        Consulta los cursos disponibles de tu carrera,
                        agrega los que deseas impartir y administra
                        tus cursos asignados.
                    </p>

                    <a
                        href="cursos.php?vista=cursos"
                        class="boton-accion"
                    >

                        <i class="bi bi-arrow-right-circle"></i>

                        Gestionar cursos

                    </a>

                </div>

            </div>

            <!-- HORARIOS -->

            <div class="col-lg-6 col-md-6">

                <div class="tarjeta-accion h-100">

                    <div class="icono-accion">
                        <i class="bi bi-calendar-week-fill"></i>
                    </div>

                    <h4>Mis horarios</h4>

                    <p>
                        Registra tus tutorías, selecciona fechas
                        y horarios disponibles y configura
                        sesiones presenciales o virtuales.
                    </p>

                    <a
                        href="cursos.php?vista=horarios"
                        class="boton-accion"
                    >

                        <i class="bi bi-arrow-right-circle"></i>

                        Gestionar horarios

                    </a>

                </div>

            </div>

            <!-- SOLICITUDES -->

            <div class="col-lg-6 col-md-6">

                <div class="tarjeta-accion h-100">

                    <div class="icono-accion">
                        <i class="bi bi-envelope-paper-fill"></i>
                    </div>

                    <h4>Solicitudes recibidas</h4>

                    <p>
                        Consulta las solicitudes enviadas
                        por los estudiantes y acepta o rechaza
                        las solicitudes pendientes.
                    </p>

                    <a
                        href="solicitudes.php"
                        class="boton-accion"
                    >

                        <i class="bi bi-arrow-right-circle"></i>

                        Ver solicitudes

                    </a>

                </div>

            </div>

            <!-- ATENCIONES -->

            <div class="col-lg-6 col-md-6">

                <div class="tarjeta-accion h-100">

                    <div class="icono-accion">
                        <i class="bi bi-clipboard-check-fill"></i>
                    </div>

                    <h4>Registro de atenciones</h4>

                    <p>
                        Registra las tutorías realizadas,
                        asistencia, observaciones, resultados
                        y recomendaciones.
                    </p>

                    <a
                        href="atenciones.php"
                        class="boton-accion"
                    >

                        <i class="bi bi-arrow-right-circle"></i>

                        Gestionar atenciones

                    </a>

                </div>

            </div>

        </div>

    </section>

</main>

</body>
</html>