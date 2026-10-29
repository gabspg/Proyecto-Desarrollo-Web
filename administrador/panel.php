
<?php

/* ============================================================
   SISTEMA DE GESTIÓN DE TUTORÍAS Y ASESORÍAS ACADÉMICAS
   EMPRESA: TUTORLINK
   MÓDULO: ADMINISTRADOR
   FORMULARIO: PANEL PRINCIPAL
   ============================================================ */

require_once "../seguridad/verificar_sesion.php";
require_once "../config/conexion.php";


/* ============================================================
   1. VERIFICAR ACCESO DEL ADMINISTRADOR
   ============================================================ */

// Permitir el acceso únicamente a administradores
verificarAcceso("ADMIN");

// Obtener el nombre del administrador
$nombre = htmlspecialchars(
    $_SESSION["nombre"] ?? "Administrador",
    ENT_QUOTES,
    "UTF-8"
);


/* ============================================================
   2. INICIALIZAR ESTADÍSTICAS
   ============================================================ */

$totalEstudiantes = 0;
$totalTutores = 0;
$totalCursos = 0;
$totalPendientes = 0;


/* ============================================================
   3. CONSULTAR ESTUDIANTES REGISTRADOS
   ============================================================ */

$consultaEstudiantes = $conexion->query(
    "SELECT COUNT(*)
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE r.nombre = 'ESTUDIANTE'"
);

$totalEstudiantes = (int) $consultaEstudiantes->fetchColumn();


/* ============================================================
   4. CONSULTAR TUTORES ACTIVOS
   ============================================================ */

$consultaTutores = $conexion->query(
    "SELECT COUNT(*)
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE r.nombre = 'TUTOR'
     AND u.estado = 'ACTIVO'"
);

$totalTutores = (int) $consultaTutores->fetchColumn();


/* ============================================================
   5. CONSULTAR CURSOS ACTIVOS
   ============================================================ */

$consultaCursos = $conexion->query(
    "SELECT COUNT(*)
     FROM cursos
     WHERE estado = 'ACTIVO'"
);

$totalCursos = (int) $consultaCursos->fetchColumn();


/* ============================================================
   6. CONSULTAR TUTORES PENDIENTES
   ============================================================ */

$consultaPendientes = $conexion->query(
    "SELECT COUNT(*)
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE r.nombre = 'TUTOR'
     AND u.estado = 'PENDIENTE'"
);

$totalPendientes = (int) $consultaPendientes->fetchColumn();

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Panel del Administrador - TutorLink</title>


    <!-- ========================================================
         BOOTSTRAP
         ======================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- ========================================================
         ICONOS DE BOOTSTRAP
         ======================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- ========================================================
         ESTILOS DEL ADMINISTRADOR
         ======================================================== -->

    <link
        href="../estilos/administrador.css"
        rel="stylesheet"
    >

</head>

<body>


<!-- ============================================================
     7. MENÚ LATERAL DEL ADMINISTRADOR
     ============================================================ -->

<aside class="sidebar">

    <!-- LOGOTIPO DEL SISTEMA -->

    <div class="logo">

        <i class="bi bi-mortarboard-fill"></i>

        TutorLink

    </div>


    <!-- TÍTULO DEL MENÚ -->

    <div class="menu-titulo">
        MENÚ PRINCIPAL
    </div>


    <!-- INICIO -->

    <a href="panel.php" class="activo">

        <i class="bi bi-grid-1x2-fill"></i>

        Inicio

    </a>


    <!-- ADMINISTRAR USUARIOS -->

    <a href="usuarios.php?seccion=usuarios">

        <i class="bi bi-people-fill"></i>

        Usuarios

    </a>


    <!-- APROBAR TUTORES -->

    <a href="usuarios.php?seccion=tutores">

        <i class="bi bi-person-check-fill"></i>

        Aprobar tutores

    </a>


    <!-- ADMINISTRAR CURSOS -->

    <a href="cursos.php">

        <i class="bi bi-journal-bookmark-fill"></i>

        Cursos

    </a>


    <!-- ASIGNAR CURSOS -->

    <a href="cursos.php?seccion=asignar">

        <i class="bi bi-bookmark-check-fill"></i>

        Asignar cursos

    </a>


    <!-- CERRAR SESIÓN -->

    <a href="../autenticacion/logout.php" class="salir">

        <i class="bi bi-box-arrow-right"></i>

        Cerrar sesión

    </a>

</aside>



<!-- ============================================================
     8. CONTENIDO PRINCIPAL
     ============================================================ -->

<main class="contenido">


    <!-- ========================================================
         9. ENCABEZADO SUPERIOR
         ======================================================== -->

    <header class="barra-superior">

        <div>

            <h1 class="titulo-pagina">
                Panel del Administrador
            </h1>

            <p class="subtitulo">
                TutorLink - Sistema de Gestión de Tutorías y Asesorías Académicas
            </p>

        </div>


        <!-- PERFIL DEL ADMINISTRADOR -->

        <div class="perfil">

            <div class="avatar">

                <?= strtoupper(substr($nombre, 0, 1)) ?>

            </div>

            <div>

                <strong>
                    <?= $nombre ?>
                </strong>

                <div class="text-muted small">
                    Administrador
                </div>

            </div>

        </div>

    </header>



    <!-- ========================================================
         10. BANNER DE BIENVENIDA
         ======================================================== -->

    <section class="bienvenida">

        <div>

            <h2>
                ¡Bienvenido, <?= $nombre ?>!
            </h2>

            <p>
                Administra los usuarios, tutores y cursos
                de TutorLink, tu sistema de gestión de tutorías
                y asesorías académicas, desde un solo lugar.
            </p>

        </div>


        <!-- ICONO DECORATIVO -->

        <i class="bi bi-mortarboard bienvenida-icono"></i>

    </section>



    <!-- ========================================================
         11. RESUMEN GENERAL DEL SISTEMA
         ======================================================== -->

    <h4 class="seccion-titulo">
        Resumen general
    </h4>


    <div class="row g-3 mb-5">


        <!-- ====================================================
             TOTAL DE ESTUDIANTES
             ==================================================== -->

        <div class="col-xl-3 col-md-6">

            <div class="estadistica azul">

                <div>

                    <h3>
                        <?= $totalEstudiantes ?>
                    </h3>

                    <p>
                        Estudiantes registrados
                    </p>

                </div>

                <i class="bi bi-people-fill"></i>

            </div>

        </div>



        <!-- ====================================================
             TOTAL DE TUTORES ACTIVOS
             ==================================================== -->

        <div class="col-xl-3 col-md-6">

            <div class="estadistica verde">

                <div>

                    <h3>
                        <?= $totalTutores ?>
                    </h3>

                    <p>
                        Tutores activos
                    </p>

                </div>

                <i class="bi bi-person-video3"></i>

            </div>

        </div>



        <!-- ====================================================
             TOTAL DE CURSOS ACTIVOS
             ==================================================== -->

        <div class="col-xl-3 col-md-6">

            <div class="estadistica petroleo">

                <div>

                    <h3>
                        <?= $totalCursos ?>
                    </h3>

                    <p>
                        Cursos activos
                    </p>

                </div>

                <i class="bi bi-journal-bookmark-fill"></i>

            </div>

        </div>



        <!-- ====================================================
             TOTAL DE TUTORES PENDIENTES
             ==================================================== -->

        <div class="col-xl-3 col-md-6">

            <div class="estadistica marino">

                <div>

                    <h3>
                        <?= $totalPendientes ?>
                    </h3>

                    <p>
                        Tutores pendientes
                    </p>

                </div>

                <i class="bi bi-clock-history"></i>

            </div>

        </div>

    </div>



    <!-- ========================================================
         12. ACCESOS RÁPIDOS DEL ADMINISTRADOR
         ======================================================== -->

    <h4 class="seccion-titulo">
        Accesos rápidos
    </h4>


    <div class="row g-3">


        <!-- ====================================================
             ADMINISTRAR USUARIOS
             ==================================================== -->

        <div class="col-lg-6">

            <div class="tarjeta-accion">

                <div class="icono-accion">

                    <i class="bi bi-people"></i>

                </div>

                <h5>
                    Administrar usuarios
                </h5>

                <p>
                    Consulta los usuarios registrados,
                    revisa sus estados y administra
                    la activación o desactivación de cuentas.
                </p>

                <a
                    href="usuarios.php?seccion=usuarios"
                    class="boton-accion"
                >
                    Administrar usuarios
                </a>

            </div>

        </div>



        <!-- ====================================================
             APROBAR TUTORES
             ==================================================== -->

        <div class="col-lg-6">

            <div class="tarjeta-accion">

                <div class="icono-accion">

                    <i class="bi bi-person-check"></i>

                </div>

                <h5>
                    Aprobar tutores
                </h5>

                <p>
                    Revisa las solicitudes de registro
                    de los tutores y aprueba o rechaza
                    sus cuentas.
                </p>

                <a
                    href="usuarios.php?seccion=tutores"
                    class="boton-accion"
                >
                    Aprobar tutores
                </a>

            </div>

        </div>



        <!-- ====================================================
             ADMINISTRAR CURSOS
             ==================================================== -->

        <div class="col-lg-6">

            <div class="tarjeta-accion">

                <div class="icono-accion">

                    <i class="bi bi-journal-text"></i>

                </div>

                <h5>
                    Administrar cursos
                </h5>

                <p>
                    Registra, consulta, modifica,
                    activa y desactiva los cursos
                    disponibles en el sistema.
                </p>

                <a
                    href="cursos.php"
                    class="boton-accion"
                >
                    Administrar cursos
                </a>

            </div>

        </div>



        <!-- ====================================================
             ASIGNAR CURSOS A TUTORES
             ==================================================== -->

        <div class="col-lg-6">

            <div class="tarjeta-accion">

                <div class="icono-accion">

                    <i class="bi bi-bookmark-check"></i>

                </div>

                <h5>
                    Asignar cursos a tutores
                </h5>

                <p>
                    Selecciona los cursos que puede
                    impartir cada tutor, de acuerdo
                    con su carrera.
                </p>

                <a
                    href="cursos.php?seccion=asignar"
                    class="boton-accion"
                >
                    Asignar cursos
                </a>

            </div>

        </div>

    </div>

</main>

</body>
</html>