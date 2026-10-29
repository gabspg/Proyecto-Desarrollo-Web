
<?php

/* ============================================================
   SISTEMA DE GESTIÓN DE TUTORÍAS Y ASESORÍAS ACADÉMICAS
   MÓDULO: ADMINISTRADOR
   ARCHIVO: usuarios.php
   ============================================================ */

require_once "../config/conexion.php";
require_once "../seguridad/verificar_sesion.php";


/* ============================================================
   1. VERIFICAR ACCESO
   ============================================================ */

verificarAcceso("ADMIN");

$nombreAdministrador = htmlspecialchars(
    $_SESSION["nombre"] ?? "Administrador",
    ENT_QUOTES,
    "UTF-8"
);


/* ============================================================
   2. TOKEN DE SEGURIDAD
   ============================================================ */

if (empty($_SESSION["token_usuarios"])) {
    $_SESSION["token_usuarios"] = bin2hex(random_bytes(32));
}

$mensaje = "";
$error = "";


/* ============================================================
   3. SELECCIONAR SECCIÓN
   ============================================================ */

$seccion = $_GET["seccion"] ?? "usuarios";

if (!in_array($seccion, ["usuarios", "tutores"], true)) {
    $seccion = "usuarios";
}


/* ============================================================
   4. PROCESAR ACCIONES
   ============================================================ */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";
    $token = $_POST["token"] ?? "";
    $tokenSesion = $_SESSION["token_usuarios"];

    // Validar token de seguridad
    if (
        !is_string($token) ||
        !hash_equals($tokenSesion, $token)
    ) {
        exit("Solicitud no válida.");
    }

    // Validar ID del usuario
    $usuarioId = filter_input(
        INPUT_POST,
        "usuario_id",
        FILTER_VALIDATE_INT
    );

    if (!$usuarioId) {
        exit("Usuario no válido.");
    }


    /* ========================================================
       5. APROBAR O RECHAZAR TUTORES
       ======================================================== */

    if ($accion === "aprobar" || $accion === "rechazar") {

        $nuevoEstado = "ACTIVO";

        if ($accion === "rechazar") {
            $nuevoEstado = "INACTIVO";
        }

        $consulta = $conexion->prepare(
            "UPDATE usuarios
             INNER JOIN roles
                ON usuarios.rol_id = roles.id
             SET usuarios.estado = :estado
             WHERE usuarios.id = :usuario_id
             AND roles.nombre = 'TUTOR'
             AND usuarios.estado = 'PENDIENTE'"
        );

        $consulta->execute([
            "estado" => $nuevoEstado,
            "usuario_id" => $usuarioId
        ]);

        if ($consulta->rowCount() === 0) {
            $error = "No se encontró un tutor pendiente.";
        } else {
            $mensaje = "Estado del tutor actualizado correctamente.";
        }
    }


    /* ========================================================
       6. ACTIVAR O DESACTIVAR USUARIOS
       ======================================================== */

    if ($accion === "activar" || $accion === "desactivar") {

        $nuevoEstado = "ACTIVO";

        if ($accion === "desactivar") {
            $nuevoEstado = "INACTIVO";
        }

        $consulta = $conexion->prepare(
            "UPDATE usuarios
             INNER JOIN roles
                ON usuarios.rol_id = roles.id
             SET usuarios.estado = :estado
             WHERE usuarios.id = :usuario_id
             AND roles.nombre IN ('ESTUDIANTE', 'TUTOR')
             AND usuarios.estado IN ('ACTIVO', 'INACTIVO')"
        );

        $consulta->execute([
            "estado" => $nuevoEstado,
            "usuario_id" => $usuarioId
        ]);

        if ($consulta->rowCount() === 0) {
            $error = "No se pudo cambiar el estado del usuario.";
        } else {
            $mensaje = "Estado del usuario actualizado correctamente.";
        }
    }


    /* ========================================================
       7. REDIRECCIÓN DESPUÉS DE GUARDAR
       ======================================================== */

    if ($mensaje !== "") {

        $_SESSION["mensaje_usuarios"] = $mensaje;

        $destino = "usuarios";

        if ($accion === "aprobar" || $accion === "rechazar") {
            $destino = "tutores";
        }

        header("Location: usuarios.php?seccion=" . $destino);
        exit;
    }
}


/* ============================================================
   8. CONSULTAR USUARIOS REGISTRADOS
   ============================================================ */

$consulta = $conexion->prepare(
    "SELECT
        usuarios.id,
        usuarios.nombre,
        usuarios.apellido,
        usuarios.username,
        usuarios.correo,
        usuarios.estado,
        roles.nombre AS rol,
        carreras.nombre AS carrera
     FROM usuarios
     INNER JOIN roles
        ON usuarios.rol_id = roles.id
     LEFT JOIN carreras
        ON usuarios.carrera_id = carreras.id
     ORDER BY usuarios.id DESC"
);

$consulta->execute();

$usuarios = $consulta->fetchAll(PDO::FETCH_ASSOC);


/* ============================================================
   9. RECUPERAR MENSAJES
   ============================================================ */

if (isset($_SESSION["mensaje_usuarios"])) {

    $mensaje = $_SESSION["mensaje_usuarios"];

    unset($_SESSION["mensaje_usuarios"]);
}


/* ============================================================
   10. DATOS PARA EL ENCABEZADO
   ============================================================ */

$tituloPagina = "Administración de usuarios";
$descripcionPagina = "Gestiona las cuentas y solicitudes de tutores.";

if ($seccion === "tutores") {

    $tituloPagina = "Aprobar tutores";
    $descripcionPagina = "Revisa las solicitudes pendientes de aprobación.";
}

$totalUsuarios = count($usuarios);
$totalPendientes = 0;

foreach ($usuarios as $usuario) {

    if (
        $usuario["rol"] === "TUTOR" &&
        $usuario["estado"] === "PENDIENTE"
    ) {
        $totalPendientes++;
    }
}

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
     11. MENÚ LATERAL DEL ADMINISTRADOR
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


    <!-- ADMINISTRAR USUARIOS -->

    <a
        href="usuarios.php?seccion=usuarios"
        class="<?= $seccion === 'usuarios' ? 'activo' : '' ?>"
    >
        <i class="bi bi-people-fill"></i>
        Usuarios
    </a>


    <!-- APROBAR TUTORES -->

    <a
        href="usuarios.php?seccion=tutores"
        class="<?= $seccion === 'tutores' ? 'activo' : '' ?>"
    >
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

    <a href="../logout.php" class="salir">
        <i class="bi bi-box-arrow-right"></i>
        Cerrar sesión
    </a>

</aside>



<!-- ============================================================
     12. CONTENIDO PRINCIPAL
     ============================================================ -->

<main class="contenido">


    <!-- ========================================================
         13. BARRA SUPERIOR
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
         14. BANNER DE LA SECCIÓN
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

        <i class="bi bi-people bienvenida-icono"></i>

    </section>



    <!-- ========================================================
         15. RESUMEN DE USUARIOS
         ======================================================== -->

    <div class="row g-3 mb-4">

        <!-- TOTAL DE USUARIOS -->

        <div class="col-md-6">

            <div class="estadistica azul">

                <div>

                    <h3>
                        <?= $totalUsuarios ?>
                    </h3>

                    <p>
                        Usuarios registrados
                    </p>

                </div>

                <i class="bi bi-people-fill"></i>

            </div>

        </div>


        <!-- TUTORES PENDIENTES -->

        <div class="col-md-6">

            <div class="estadistica verde">

                <div>

                    <h3>
                        <?= $totalPendientes ?>
                    </h3>

                    <p>
                        Tutores pendientes
                    </p>

                </div>

                <i class="bi bi-person-check-fill"></i>

            </div>

        </div>

    </div>



    <!-- ========================================================
         16. MENSAJES DEL SISTEMA
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
         17. SECCIÓN: APROBAR TUTORES
         ======================================================== -->

    <?php if ($seccion === "tutores"): ?>

        <h4 class="seccion-titulo">
            Solicitudes de tutores
        </h4>

        <div class="card">

            <div class="card-body">

                <p class="text-muted mb-4">
                    Revisa las cuentas que están pendientes de aprobación.
                </p>


                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead>

                            <tr>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <th>Carrera</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php $hayPendientes = false; ?>

                            <?php foreach ($usuarios as $usuario): ?>

                                <?php

                                if (
                                    $usuario["rol"] !== "TUTOR" ||
                                    $usuario["estado"] !== "PENDIENTE"
                                ) {
                                    continue;
                                }

                                $hayPendientes = true;

                                ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars(
                                            $usuario["nombre"] . " " .
                                            $usuario["apellido"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($usuario["username"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($usuario["correo"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $usuario["carrera"] ?? "Sin carrera"
                                        ) ?>
                                    </td>

                                    <td>

                                        <form
                                            action="usuarios.php?seccion=tutores"
                                            method="POST"
                                            class="d-inline"
                                        >

                                            <input
                                                type="hidden"
                                                name="usuario_id"
                                                value="<?= (int)$usuario["id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="token"
                                                value="<?= htmlspecialchars(
                                                    $_SESSION["token_usuarios"]
                                                ) ?>"
                                            >


                                            <!-- APROBAR -->

                                            <button
                                                type="submit"
                                                name="accion"
                                                value="aprobar"
                                                class="btn btn-success btn-sm"
                                            >

                                                <i class="bi bi-check-lg"></i>

                                                Aprobar

                                            </button>


                                            <!-- RECHAZAR -->

                                            <button
                                                type="submit"
                                                name="accion"
                                                value="rechazar"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('¿Deseas rechazar esta solicitud?')"
                                            >

                                                <i class="bi bi-x-lg"></i>

                                                Rechazar

                                            </button>

                                        </form>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                            <?php if (!$hayPendientes): ?>

                                <tr>

                                    <td colspan="5" class="text-center py-4">

                                        <i class="bi bi-check-circle text-success me-2"></i>

                                        No hay tutores pendientes.

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>



    <!-- ========================================================
         18. SECCIÓN: ADMINISTRAR USUARIOS
         ======================================================== -->

    <?php else: ?>

        <h4 class="seccion-titulo">
            Usuarios registrados
        </h4>

        <div class="card">

            <div class="card-body">

                <p class="text-muted mb-4">
                    Consulta y administra las cuentas registradas.
                </p>


                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead>

                            <tr>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th>Carrera</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($usuarios as $usuario): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars(
                                            $usuario["nombre"] . " " .
                                            $usuario["apellido"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($usuario["username"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($usuario["correo"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($usuario["rol"]) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $usuario["carrera"] ?? "Sin carrera"
                                        ) ?>
                                    </td>


                                    <!-- ESTADO -->

                                    <td>

                                        <?php

                                        $color = "secondary";

                                        if ($usuario["estado"] === "ACTIVO") {
                                            $color = "success";
                                        }

                                        if ($usuario["estado"] === "PENDIENTE") {
                                            $color = "warning";
                                        }

                                        if ($usuario["estado"] === "INACTIVO") {
                                            $color = "danger";
                                        }

                                        ?>

                                        <span class="badge bg-<?= $color ?>">

                                            <?= htmlspecialchars($usuario["estado"]) ?>

                                        </span>

                                    </td>


                                    <!-- ACCIONES -->

                                    <td>

                                        <?php if ($usuario["rol"] === "ADMIN"): ?>

                                            <span class="text-muted">
                                                Sin acciones
                                            </span>


                                        <?php elseif ($usuario["estado"] === "PENDIENTE"): ?>

                                            <a
                                                href="usuarios.php?seccion=tutores"
                                                class="btn btn-warning btn-sm"
                                            >

                                                <i class="bi bi-eye"></i>

                                                Revisar

                                            </a>


                                        <?php else: ?>

                                            <form
                                                action="usuarios.php?seccion=usuarios"
                                                method="POST"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="usuario_id"
                                                    value="<?= (int)$usuario["id"] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="token"
                                                    value="<?= htmlspecialchars(
                                                        $_SESSION["token_usuarios"]
                                                    ) ?>"
                                                >


                                                <?php if ($usuario["estado"] === "ACTIVO"): ?>

                                                    <button
                                                        type="submit"
                                                        name="accion"
                                                        value="desactivar"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="return confirm('¿Deseas desactivar este usuario?')"
                                                    >

                                                        <i class="bi bi-person-x"></i>

                                                        Desactivar

                                                    </button>


                                                <?php else: ?>

                                                    <button
                                                        type="submit"
                                                        name="accion"
                                                        value="activar"
                                                        class="btn btn-success btn-sm"
                                                    >

                                                        <i class="bi bi-person-check"></i>

                                                        Activar

                                                    </button>

                                                <?php endif; ?>

                                            </form>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                            <?php if (count($usuarios) === 0): ?>

                                <tr>

                                    <td colspan="7" class="text-center py-4">
                                        No hay usuarios registrados.
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
         19. VOLVER AL PANEL
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
