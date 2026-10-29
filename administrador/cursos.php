
<?php

/* ============================================================
   SISTEMA DE GESTIÓN DE TUTORÍAS Y ASESORÍAS ACADÉMICAS
   MÓDULO: ADMINISTRADOR
   ARCHIVO: cursos.php
   ============================================================ */

require_once "../config/conexion.php";
require_once "../seguridad/verificar_sesion.php";


/* ============================================================
   1. VERIFICAR ACCESO DEL ADMINISTRADOR
   ============================================================ */

verificarAcceso("ADMIN");


/* ============================================================
   2. TOKEN DE SEGURIDAD Y VARIABLES
   ============================================================ */

if (empty($_SESSION["token_cursos"])) {
    $_SESSION["token_cursos"] = bin2hex(random_bytes(32));
}

$tokenSesion = $_SESSION["token_cursos"];

$error = "";
$mensaje = "";

$seccion = $_GET["seccion"] ?? "lista";

$secciones = ["lista", "registrar", "editar", "asignar"];

if (!in_array($seccion, $secciones, true)) {
    $seccion = "lista";
}

// Datos de cursos
$cursoId = 0;
$codigo = "";
$nombre = "";
$carreraId = "";
$descripcion = "";

// Datos de asignación
$tutorId = "";
$cursoAsignarId = "";


/* ============================================================
   3. CONSULTAR CARRERAS
   ============================================================ */

$consulta = $conexion->prepare(
    "SELECT id, nombre
     FROM carreras
     ORDER BY nombre ASC"
);

$consulta->execute();

$carreras = $consulta->fetchAll(PDO::FETCH_ASSOC);


/* ============================================================
   4. CARGAR DATOS PARA EDITAR UN CURSO
   ============================================================ */

if (
    $_SERVER["REQUEST_METHOD"] === "GET" &&
    $seccion === "editar"
) {

    $cursoId = filter_input(
        INPUT_GET,
        "id",
        FILTER_VALIDATE_INT
    );

    if (!$cursoId) {
        exit("Curso no válido.");
    }

    $consulta = $conexion->prepare(
        "SELECT id, codigo, nombre, carrera_id, descripcion
         FROM cursos
         WHERE id = :id"
    );

    $consulta->execute([
        "id" => $cursoId
    ]);

    $curso = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$curso) {
        exit("No se encontró el curso.");
    }

    $codigo = $curso["codigo"];
    $nombre = $curso["nombre"];
    $carreraId = $curso["carrera_id"];
    $descripcion = $curso["descripcion"] ?? "";
}


/* ============================================================
   5. PROCESAR FORMULARIOS
   ============================================================ */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["token"] ?? "";
    $accion = $_POST["accion"] ?? "";

    // Validar token de seguridad
    if (
        !is_string($token) ||
        !hash_equals($tokenSesion, $token)
    ) {
        exit("Solicitud no válida.");
    }


    /* ========================================================
       6. ACTIVAR Y DESACTIVAR CURSOS
       ======================================================== */

    if ($accion === "activar" || $accion === "desactivar") {

        $cursoId = filter_input(
            INPUT_POST,
            "curso_id",
            FILTER_VALIDATE_INT
        );

        if (!$cursoId) {
            exit("Curso no válido.");
        }

        $nuevoEstado = "ACTIVO";

        if ($accion === "desactivar") {
            $nuevoEstado = "INACTIVO";
        }

        $consulta = $conexion->prepare(
            "UPDATE cursos
             SET estado = :estado
             WHERE id = :id"
        );

        $consulta->execute([
            "estado" => $nuevoEstado,
            "id" => $cursoId
        ]);

        $_SESSION["mensaje_cursos"] =
            "Estado del curso actualizado correctamente.";

        header("Location: cursos.php");
        exit;
    }


    /* ========================================================
       7. REGISTRAR Y EDITAR CURSOS
       ======================================================== */

    if ($accion === "registrar" || $accion === "editar") {

        $seccion = $accion;

        $codigo = trim($_POST["codigo"] ?? "");
        $nombre = trim($_POST["nombre"] ?? "");
        $carreraId = $_POST["carrera_id"] ?? "";
        $descripcion = trim($_POST["descripcion"] ?? "");

        $cursoId = 0;

        if ($accion === "editar") {

            $cursoId = filter_input(
                INPUT_POST,
                "curso_id",
                FILTER_VALIDATE_INT
            );

            if (!$cursoId) {
                exit("Curso no válido.");
            }

            $consulta = $conexion->prepare(
                "SELECT id FROM cursos WHERE id = :id"
            );

            $consulta->execute([
                "id" => $cursoId
            ]);

            if (!$consulta->fetch()) {
                exit("No se encontró el curso.");
            }
        }

        // Validar campos obligatorios
        if ($codigo === "" || $nombre === "" || $carreraId === "") {
            $error = "Completa los campos obligatorios.";
        }

        // Validar carrera
        if ($error === "" && !ctype_digit((string)$carreraId)) {
            $error = "Selecciona una carrera válida.";
        }

        // Comprobar que la carrera exista
        if ($error === "") {

            $consulta = $conexion->prepare(
                "SELECT id FROM carreras WHERE id = :id"
            );

            $consulta->execute([
                "id" => $carreraId
            ]);

            if (!$consulta->fetch()) {
                $error = "La carrera seleccionada no existe.";
            }
        }

        // Comprobar código duplicado
        if ($error === "") {

            $consulta = $conexion->prepare(
                "SELECT id
                 FROM cursos
                 WHERE codigo = :codigo
                 AND id <> :id"
            );

            $consulta->execute([
                "codigo" => $codigo,
                "id" => $cursoId
            ]);

            if ($consulta->fetch()) {
                $error = "Ya existe otro curso con ese código.";
            }
        }


        /* ====================================================
           8. GUARDAR NUEVO CURSO
           ==================================================== */

        if ($error === "" && $accion === "registrar") {

            try {

                $consulta = $conexion->prepare(
                    "INSERT INTO cursos
                     (carrera_id, codigo, nombre, descripcion, estado)
                     VALUES
                     (:carrera_id, :codigo, :nombre, :descripcion, 'ACTIVO')"
                );

                $consulta->execute([
                    "carrera_id" => $carreraId,
                    "codigo" => $codigo,
                    "nombre" => $nombre,
                    "descripcion" => $descripcion
                ]);

                $_SESSION["mensaje_cursos"] =
                    "Curso registrado correctamente.";

                header("Location: cursos.php");
                exit;

            } catch (PDOException $errorBD) {

                error_log($errorBD->getMessage());

                $error = "No se pudo registrar el curso.";
            }
        }


        /* ====================================================
           9. GUARDAR MODIFICACIONES DEL CURSO
           ==================================================== */

        if ($error === "" && $accion === "editar") {

            try {

                $consulta = $conexion->prepare(
                    "UPDATE cursos
                     SET codigo = :codigo,
                         nombre = :nombre,
                         carrera_id = :carrera_id,
                         descripcion = :descripcion
                     WHERE id = :id"
                );

                $consulta->execute([
                    "codigo" => $codigo,
                    "nombre" => $nombre,
                    "carrera_id" => $carreraId,
                    "descripcion" => $descripcion,
                    "id" => $cursoId
                ]);

                $_SESSION["mensaje_cursos"] =
                    "Curso actualizado correctamente.";

                header("Location: cursos.php");
                exit;

            } catch (PDOException $errorBD) {

                error_log($errorBD->getMessage());

                $error = "No se pudo actualizar el curso.";
            }
        }
    }


    /* ========================================================
       10. ASIGNAR CURSOS A TUTORES
       ======================================================== */

    if ($accion === "asignar") {

        $seccion = "asignar";

        $tutorId = $_POST["tutor_id"] ?? "";
        $cursoAsignarId = $_POST["curso_id"] ?? "";

        if (
            !ctype_digit((string)$tutorId) ||
            !ctype_digit((string)$cursoAsignarId) ||
            (int)$tutorId <= 0 ||
            (int)$cursoAsignarId <= 0
        ) {
            $error = "Selecciona un tutor y un curso válidos.";
        }

        // Verificar tutor, curso y carrera
        if ($error === "") {

            $consulta = $conexion->prepare(
                "SELECT usuarios.id
                 FROM usuarios
                 INNER JOIN roles
                    ON usuarios.rol_id = roles.id
                 INNER JOIN cursos
                    ON usuarios.carrera_id = cursos.carrera_id
                 WHERE usuarios.id = :tutor_id
                 AND cursos.id = :curso_id
                 AND roles.nombre = 'TUTOR'
                 AND usuarios.estado = 'ACTIVO'
                 AND cursos.estado = 'ACTIVO'"
            );

            $consulta->execute([
                "tutor_id" => $tutorId,
                "curso_id" => $cursoAsignarId
            ]);

            if (!$consulta->fetch()) {
                $error = "El tutor y el curso deben estar activos y pertenecer a la misma carrera.";
            }
        }

        // Comprobar asignación duplicada
        if ($error === "") {

            $consulta = $conexion->prepare(
                "SELECT id
                 FROM tutor_cursos
                 WHERE tutor_id = :tutor_id
                 AND curso_id = :curso_id"
            );

            $consulta->execute([
                "tutor_id" => $tutorId,
                "curso_id" => $cursoAsignarId
            ]);

            if ($consulta->fetch()) {
                $error = "Este curso ya está asignado al tutor.";
            }
        }

        // Guardar asignación
        if ($error === "") {

            try {

                $consulta = $conexion->prepare(
                    "INSERT INTO tutor_cursos
                     (tutor_id, curso_id)
                     VALUES (:tutor_id, :curso_id)"
                );

                $consulta->execute([
                    "tutor_id" => $tutorId,
                    "curso_id" => $cursoAsignarId
                ]);

                $_SESSION["mensaje_cursos"] =
                    "Curso asignado correctamente al tutor.";

                header("Location: cursos.php?seccion=asignar");
                exit;

            } catch (PDOException $errorBD) {

                error_log($errorBD->getMessage());

                $error = "No se pudo guardar la asignación.";
            }
        }
    }


    /* ========================================================
       11. QUITAR ASIGNACIONES
       ======================================================== */

    if ($accion === "quitar_asignacion") {

        $seccion = "asignar";

        $asignacionId = filter_input(
            INPUT_POST,
            "asignacion_id",
            FILTER_VALIDATE_INT
        );

        if (!$asignacionId) {
            exit("Asignación no válida.");
        }

        try {

            $consulta = $conexion->prepare(
                "DELETE FROM tutor_cursos
                 WHERE id = :id"
            );

            $consulta->execute([
                "id" => $asignacionId
            ]);

            if ($consulta->rowCount() === 0) {

                $error = "No se encontró la asignación.";

            } else {

                $_SESSION["mensaje_cursos"] =
                    "Asignación eliminada correctamente.";

                header("Location: cursos.php?seccion=asignar");
                exit;
            }

        } catch (PDOException $errorBD) {

            error_log($errorBD->getMessage());

            $error = "No se puede quitar esta asignación porque puede tener tutorías relacionadas.";
        }
    }
}


/* ============================================================
   12. CONSULTAR CURSOS
   ============================================================ */

$consulta = $conexion->prepare(
    "SELECT
        cursos.id,
        cursos.codigo,
        cursos.nombre,
        cursos.descripcion,
        cursos.estado,
        carreras.nombre AS carrera
     FROM cursos
     INNER JOIN carreras
        ON cursos.carrera_id = carreras.id
     ORDER BY cursos.nombre ASC"
);

$consulta->execute();

$cursos = $consulta->fetchAll(PDO::FETCH_ASSOC);


/* ============================================================
   13. CONSULTAR TUTORES ACTIVOS
   ============================================================ */

$consulta = $conexion->prepare(
    "SELECT
        usuarios.id,
        usuarios.nombre,
        usuarios.apellido,
        usuarios.carrera_id,
        carreras.nombre AS carrera
     FROM usuarios
     INNER JOIN roles
        ON usuarios.rol_id = roles.id
     INNER JOIN carreras
        ON usuarios.carrera_id = carreras.id
     WHERE roles.nombre = 'TUTOR'
     AND usuarios.estado = 'ACTIVO'
     ORDER BY usuarios.nombre ASC"
);

$consulta->execute();

$tutores = $consulta->fetchAll(PDO::FETCH_ASSOC);


/* ============================================================
   14. CONSULTAR ASIGNACIONES
   ============================================================ */

$consulta = $conexion->prepare(
    "SELECT
        tutor_cursos.id,
        usuarios.nombre,
        usuarios.apellido,
        cursos.codigo,
        cursos.nombre AS curso,
        carreras.nombre AS carrera
     FROM tutor_cursos
     INNER JOIN usuarios
        ON tutor_cursos.tutor_id = usuarios.id
     INNER JOIN cursos
        ON tutor_cursos.curso_id = cursos.id
     INNER JOIN carreras
        ON cursos.carrera_id = carreras.id
     ORDER BY usuarios.nombre ASC, cursos.nombre ASC"
);

$consulta->execute();

$asignaciones = $consulta->fetchAll(PDO::FETCH_ASSOC);


/* ============================================================
   15. RECUPERAR MENSAJES
   ============================================================ */

if (isset($_SESSION["mensaje_cursos"])) {

    $mensaje = $_SESSION["mensaje_cursos"];

    unset($_SESSION["mensaje_cursos"]);
}


/* ============================================================
   16. DATOS DEL ENCABEZADO
   ============================================================ */

$tituloPagina = "Administración de cursos";
$descripcionPagina = "Consulta, registra y administra los cursos del sistema.";

if ($seccion === "registrar") {
    $tituloPagina = "Registrar curso";
    $descripcionPagina = "Agrega un nuevo curso a una carrera.";
}

if ($seccion === "editar") {
    $tituloPagina = "Editar curso";
    $descripcionPagina = "Modifica la información del curso seleccionado.";
}

if ($seccion === "asignar") {
    $tituloPagina = "Asignar cursos a tutores";
    $descripcionPagina = "Administra los cursos asignados a los tutores.";
}

$nombreAdministrador = htmlspecialchars(
    $_SESSION["nombre"] ?? "Administrador",
    ENT_QUOTES,
    "UTF-8"
);

$totalCursos = count($cursos);
$totalTutores = count($tutores);
$totalAsignaciones = count($asignaciones);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($tituloPagina) ?> - Sistema de Tutorías</title>


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
        href="/tutorias/estilos/administrador.css?v=2"
        rel="stylesheet"
    >

</head>

<body>


<!-- ============================================================
     17. MENÚ LATERAL DEL ADMINISTRADOR
     ============================================================ -->

<aside class="sidebar">

    <div class="logo">
        <i class="bi bi-mortarboard-fill"></i>
        Tutorías
    </div>


    <div class="menu-titulo">
        MENÚ PRINCIPAL
    </div>


    <!-- INICIO -->

    <a href="panel.php">
        <i class="bi bi-grid-1x2-fill"></i>
        Inicio
    </a>


    <!-- USUARIOS -->

    <a href="usuarios.php?seccion=usuarios">
        <i class="bi bi-people-fill"></i>
        Usuarios
    </a>


    <!-- APROBAR TUTORES -->

    <a href="usuarios.php?seccion=tutores">
        <i class="bi bi-person-check-fill"></i>
        Aprobar tutores
    </a>


    <!-- CURSOS -->

    <a
        href="cursos.php"
        class="<?= $seccion !== 'asignar' ? 'activo' : '' ?>"
    >
        <i class="bi bi-journal-bookmark-fill"></i>
        Cursos
    </a>


    <!-- ASIGNAR CURSOS -->

    <a
        href="cursos.php?seccion=asignar"
        class="<?= $seccion === 'asignar' ? 'activo' : '' ?>"
    >
        <i class="bi bi-bookmark-check-fill"></i>
        Asignar cursos
    </a>


    <!-- CERRAR SESIÓN -->

    <a href="../logout.php" class="salir">
        <i class="bi bi-box-arrow-right"></i>
        Cerrar sesión
    </a>

</aside>



<!-- ============================================================
     18. CONTENIDO PRINCIPAL
     ============================================================ -->

<main class="contenido">


    <!-- ========================================================
         19. BARRA SUPERIOR
         ======================================================== -->

    <header class="barra-superior">

        <div>

            <h1 class="titulo-pagina">
                <?= htmlspecialchars($tituloPagina) ?>
            </h1>

            <p class="subtitulo">
                Sistema de Gestión de Tutorías y Asesorías Académicas
            </p>

        </div>


        <!-- PERFIL DEL ADMINISTRADOR -->

        <div class="perfil">

            <div class="avatar">

                <?= htmlspecialchars(
                    strtoupper(substr($_SESSION["nombre"] ?? "A", 0, 1))
                ) ?>

            </div>

            <div>

                <strong>
                    <?= $nombreAdministrador ?>
                </strong>

                <div class="text-muted small">
                    Administrador
                </div>

            </div>

        </div>

    </header>



    <!-- ========================================================
         20. BANNER DE LA SECCIÓN
         ======================================================== -->

    <section class="bienvenida">

        <div>

            <h2>
                <?= htmlspecialchars($tituloPagina) ?>
            </h2>

            <p>
                <?= htmlspecialchars($descripcionPagina) ?>
            </p>

        </div>

        <i class="bi bi-journal-bookmark bienvenida-icono"></i>

    </section>



    <!-- ========================================================
         21. RESUMEN DE CURSOS
         ======================================================== -->

    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="estadistica azul">

                <div>

                    <h3>
                        <?= $totalCursos ?>
                    </h3>

                    <p>
                        Cursos registrados
                    </p>

                </div>

                <i class="bi bi-journal-bookmark-fill"></i>

            </div>

        </div>


        <div class="col-md-4">

            <div class="estadistica verde">

                <div>

                    <h3>
                        <?= $totalTutores ?>
                    </h3>

                    <p>
                        Tutores activos
                    </p>

                </div>

                <i class="bi bi-people-fill"></i>

            </div>

        </div>


        <div class="col-md-4">

            <div class="estadistica petroleo">

                <div>

                    <h3>
                        <?= $totalAsignaciones ?>
                    </h3>

                    <p>
                        Cursos asignados
                    </p>

                </div>

                <i class="bi bi-bookmark-check-fill"></i>

            </div>

        </div>

    </div>



    <!-- ========================================================
         22. MENSAJES DEL SISTEMA
         ======================================================== -->

    <?php if ($mensaje !== ""): ?>

        <div class="alert alert-success">

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= htmlspecialchars($mensaje) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">

            <i class="bi bi-exclamation-circle-fill me-2"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!-- ========================================================
         23. REGISTRAR Y EDITAR CURSOS
         ======================================================== -->

    <?php if ($seccion === "registrar" || $seccion === "editar"): ?>

        <h4 class="seccion-titulo">
            <?= $seccion === "editar"
                ? "Editar curso"
                : "Registrar nuevo curso" ?>
        </h4>


        <div class="card">

            <div class="card-body">

                <form method="POST" action="cursos.php">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= htmlspecialchars($tokenSesion) ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="<?= htmlspecialchars($seccion) ?>"
                    >

                    <input
                        type="hidden"
                        name="curso_id"
                        value="<?= (int)$cursoId ?>"
                    >


                    <!-- CÓDIGO DEL CURSO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Código del curso
                        </label>

                        <input
                            type="text"
                            name="codigo"
                            class="form-control"
                            value="<?= htmlspecialchars($codigo) ?>"
                            required
                        >

                    </div>


                    <!-- NOMBRE DEL CURSO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Nombre del curso
                        </label>

                        <input
                            type="text"
                            name="nombre"
                            class="form-control"
                            value="<?= htmlspecialchars($nombre) ?>"
                            required
                        >

                    </div>


                    <!-- CARRERA -->

                    <div class="mb-3">

                        <label class="form-label">
                            Carrera
                        </label>

                        <select
                            name="carrera_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Selecciona una carrera
                            </option>

                            <?php foreach ($carreras as $carrera): ?>

                                <option
                                    value="<?= (int)$carrera["id"] ?>"
                                    <?= (string)$carreraId === (string)$carrera["id"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= htmlspecialchars($carrera["nombre"]) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- DESCRIPCIÓN -->

                    <div class="mb-3">

                        <label class="form-label">
                            Descripción
                        </label>

                        <textarea
                            name="descripcion"
                            class="form-control"
                            rows="3"
                        ><?= htmlspecialchars($descripcion) ?></textarea>

                    </div>


                    <!-- BOTONES -->

                    <button type="submit" class="btn btn-success">

                        <i class="bi bi-check-circle me-1"></i>

                        <?= $seccion === "editar"
                            ? "Guardar cambios"
                            : "Guardar curso" ?>

                    </button>


                    <a href="cursos.php" class="btn btn-secondary">

                        <i class="bi bi-x-circle me-1"></i>

                        Cancelar

                    </a>

                </form>

            </div>

        </div>



    <!-- ========================================================
         24. ASIGNAR CURSOS A TUTORES
         ======================================================== -->

    <?php elseif ($seccion === "asignar"): ?>

        <h4 class="seccion-titulo">
            Asignar cursos a tutores
        </h4>

        <div class="card mb-4">

            <div class="card-body">

                <p class="text-muted mb-4">
                    Selecciona un tutor activo y un curso de su carrera.
                </p>


                <form method="POST" action="cursos.php">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= htmlspecialchars($tokenSesion) ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="asignar"
                    >


                    <!-- SELECCIONAR TUTOR -->

                    <div class="mb-3">

                        <label class="form-label">
                            Tutor
                        </label>

                        <select
                            name="tutor_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Selecciona un tutor
                            </option>

                            <?php foreach ($tutores as $tutor): ?>

                                <option
                                    value="<?= (int)$tutor["id"] ?>"
                                    <?= (string)$tutorId === (string)$tutor["id"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= htmlspecialchars(
                                        $tutor["nombre"] . " " .
                                        $tutor["apellido"] . " - " .
                                        $tutor["carrera"]
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SELECCIONAR CURSO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Curso
                        </label>

                        <select
                            name="curso_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Selecciona un curso
                            </option>

                            <?php foreach ($cursos as $curso): ?>

                                <?php if ($curso["estado"] !== "ACTIVO"): ?>
                                    <?php continue; ?>
                                <?php endif; ?>

                                <option
                                    value="<?= (int)$curso["id"] ?>"
                                    <?= (string)$cursoAsignarId === (string)$curso["id"]
                                        ? "selected"
                                        : "" ?>
                                >
                                    <?= htmlspecialchars(
                                        $curso["codigo"] . " - " .
                                        $curso["nombre"] . " (" .
                                        $curso["carrera"] . ")"
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- BOTÓN ASIGNAR -->

                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        <i class="bi bi-bookmark-check me-1"></i>

                        Asignar curso

                    </button>

                </form>

            </div>

        </div>



        <!-- ====================================================
             25. LISTADO DE ASIGNACIONES
             ==================================================== -->

        <h4 class="seccion-titulo">
            Cursos asignados a tutores
        </h4>

        <div class="card">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead>

                            <tr>
                                <th>Tutor</th>
                                <th>Curso</th>
                                <th>Carrera</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($asignaciones as $asignacion): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars(
                                            $asignacion["nombre"] . " " .
                                            $asignacion["apellido"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $asignacion["codigo"] . " - " .
                                            $asignacion["curso"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $asignacion["carrera"]
                                        ) ?>
                                    </td>

                                    <td>

                                        <form
                                            method="POST"
                                            action="cursos.php"
                                        >

                                            <input
                                                type="hidden"
                                                name="token"
                                                value="<?= htmlspecialchars($tokenSesion) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="quitar_asignacion"
                                            >

                                            <input
                                                type="hidden"
                                                name="asignacion_id"
                                                value="<?= (int)$asignacion["id"] ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('¿Deseas quitar este curso del tutor?')"
                                            >

                                                <i class="bi bi-trash me-1"></i>

                                                Quitar

                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                            <?php if (count($asignaciones) === 0): ?>

                                <tr>

                                    <td colspan="4" class="text-center py-4">
                                        No hay cursos asignados a tutores.
                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>



    <!-- ========================================================
         26. LISTADO DE CURSOS
         ======================================================== -->

    <?php else: ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">

            <h4 class="seccion-titulo mb-0">
                Cursos registrados
            </h4>


            <!-- BOTÓN PARA REGISTRAR NUEVO CURSO -->

            <a
                href="cursos.php?seccion=registrar"
                class="btn btn-success"
            >

                <i class="bi bi-plus-circle me-1"></i>

                Nuevo curso

            </a>

        </div>


        <div class="card">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead>

                            <tr>
                                <th>Código</th>
                                <th>Curso</th>
                                <th>Carrera</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($cursos as $curso): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($curso["codigo"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($curso["nombre"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($curso["carrera"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $curso["descripcion"] ?? ""
                                        ) ?>
                                    </td>


                                    <!-- ESTADO DEL CURSO -->

                                    <td>

                                        <?php

                                        $color = "danger";

                                        if ($curso["estado"] === "ACTIVO") {
                                            $color = "success";
                                        }

                                        ?>

                                        <span class="badge bg-<?= $color ?>">

                                            <?= htmlspecialchars($curso["estado"]) ?>

                                        </span>

                                    </td>


                                    <!-- ACCIONES -->

                                    <td>

                                        <div class="d-flex gap-2 flex-wrap">

                                            <!-- EDITAR CURSO -->

                                            <a
                                                href="cursos.php?seccion=editar&id=<?= (int)$curso["id"] ?>"
                                                class="btn btn-primary btn-sm"
                                            >

                                                <i class="bi bi-pencil-square me-1"></i>

                                                Editar

                                            </a>


                                            <!-- ACTIVAR O DESACTIVAR -->

                                            <form
                                                action="cursos.php"
                                                method="POST"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="token"
                                                    value="<?= htmlspecialchars($tokenSesion) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="curso_id"
                                                    value="<?= (int)$curso["id"] ?>"
                                                >


                                                <?php if ($curso["estado"] === "ACTIVO"): ?>

                                                    <button
                                                        type="submit"
                                                        name="accion"
                                                        value="desactivar"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="return confirm('¿Deseas desactivar este curso?')"
                                                    >

                                                        <i class="bi bi-x-circle me-1"></i>

                                                        Desactivar

                                                    </button>


                                                <?php else: ?>

                                                    <button
                                                        type="submit"
                                                        name="accion"
                                                        value="activar"
                                                        class="btn btn-success btn-sm"
                                                    >

                                                        <i class="bi bi-check-circle me-1"></i>

                                                        Activar

                                                    </button>

                                                <?php endif; ?>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                            <?php if (count($cursos) === 0): ?>

                                <tr>

                                    <td colspan="6" class="text-center py-4">
                                        No hay cursos registrados.
                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>



    <!-- ========================================================
         27. VOLVER AL PANEL
         ======================================================== -->

    <div class="mt-4">

        <a
            href="panel.php"
            class="boton-accion"
        >

            <i class="bi bi-arrow-left me-1"></i>

            Volver al panel

        </a>

    </div>

</main>

</body>
</html>