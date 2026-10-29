
<?php
require_once "../config/conexion.php";
require_once "../seguridad/verificar_sesion.php";

verificarAcceso("TUTOR");

/* ==========================================================
   TUTORLINK - REGISTRO DE ATENCIONES
   ========================================================== */

function escapar($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$tutorId = (int)($_SESSION["usuario_id"] ?? 0);
$nombre = $_SESSION["nombre"] ?? "Tutor";

if ($tutorId <= 0) {
    exit("No se pudo identificar al tutor.");
}

/* ==========================================================
   SEGURIDAD
   ========================================================== */

if (empty($_SESSION["token_atenciones_tutor"])) {
    $_SESSION["token_atenciones_tutor"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["token_atenciones_tutor"];

$error = "";
$mensaje = "";

$solicitudId = 0;
$asistencia = "1";
$observaciones = "";
$resultado = "";
$recomendaciones = "";

$seccion = $_GET["seccion"] ?? "lista";

if (!in_array($seccion, ["lista", "registrar", "editar"], true)) {
    $seccion = "lista";
}

/* ==========================================================
   VERIFICAR TUTOR ACTIVO
   ========================================================== */

$stmt = $conexion->prepare(
    "SELECT u.id
     FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE u.id = :tutor
       AND r.nombre = 'TUTOR'
       AND u.estado = 'ACTIVO'"
);

$stmt->execute(["tutor" => $tutorId]);

if (!$stmt->fetch()) {
    exit("El tutor no está activo.");
}

/* ==========================================================
   PROCESAR REGISTRO Y EDICIÓN
   ========================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tokenRecibido = $_POST["token"] ?? "";

    if (
        !is_string($tokenRecibido) ||
        !hash_equals($token, $tokenRecibido)
    ) {
        exit("Solicitud no válida.");
    }

    $accion = $_POST["accion"] ?? "";

    if (in_array($accion, ["registrar", "editar"], true)) {

        $seccion = $accion;

        $solicitudId = filter_input(
            INPUT_POST,
            "solicitud_id",
            FILTER_VALIDATE_INT
        ) ?: 0;

        $asistencia = $_POST["asistencia"] ?? "";
        $observaciones = trim($_POST["observaciones"] ?? "");
        $resultado = trim($_POST["resultado"] ?? "");
        $recomendaciones = trim($_POST["recomendaciones"] ?? "");

        if (!$solicitudId) {
            $error = "Selecciona una solicitud válida.";
        }

        if (
            $error === "" &&
            !in_array($asistencia, ["0", "1"], true)
        ) {
            $error = "Selecciona si el estudiante asistió.";
        }

        if ($error === "" && $resultado === "") {
            $error = "Ingresa el resultado de la tutoría.";
        }

        /* VERIFICAR SOLICITUD DEL TUTOR */

        if ($error === "") {

            $stmt = $conexion->prepare(
                "SELECT
                    s.id,
                    s.estado,
                    d.fecha,
                    d.hora_fin
                 FROM solicitudes_tutoria s
                 INNER JOIN disponibilidades d
                    ON d.id = s.disponibilidad_id
                 WHERE s.id = :solicitud
                   AND d.tutor_id = :tutor"
            );

            $stmt->execute([
                "solicitud" => $solicitudId,
                "tutor" => $tutorId
            ]);

            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {

                $error = "La solicitud no pertenece a este tutor.";

            } elseif (
                !in_array(
                    $solicitud["estado"],
                    ["ACEPTADA", "ATENDIDA"],
                    true
                )
            ) {

                $error = "Solo puedes registrar atenciones de solicitudes aceptadas.";

            } elseif (
                strtotime(
                    $solicitud["fecha"] . " " .
                    $solicitud["hora_fin"]
                ) > time()
            ) {

                $error = "La tutoría todavía no ha finalizado.";
            }
        }

        /* GUARDAR ATENCIÓN */

        if ($error === "") {

            try {

                $conexion->beginTransaction();

                $stmt = $conexion->prepare(
                    "SELECT id
                     FROM atenciones
                     WHERE solicitud_id = :solicitud"
                );

                $stmt->execute([
                    "solicitud" => $solicitudId
                ]);

                $atencionExistente = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    $accion === "registrar" &&
                    $atencionExistente
                ) {

                    throw new RuntimeException(
                        "Esta solicitud ya tiene una atención registrada."
                    );
                }

                if (
                    $accion === "editar" &&
                    !$atencionExistente
                ) {

                    throw new RuntimeException(
                        "No existe una atención para editar."
                    );
                }

                if ($accion === "registrar") {

                    $stmt = $conexion->prepare(
                        "INSERT INTO atenciones
                        (
                            solicitud_id,
                            registrado_por,
                            asistencia,
                            observaciones,
                            resultado,
                            recomendaciones
                        )
                        VALUES
                        (
                            :solicitud,
                            :tutor,
                            :asistencia,
                            :observaciones,
                            :resultado,
                            :recomendaciones
                        )"
                    );

                    $stmt->execute([
                        "solicitud" => $solicitudId,
                        "tutor" => $tutorId,
                        "asistencia" => (int)$asistencia,
                        "observaciones" => $observaciones,
                        "resultado" => $resultado,
                        "recomendaciones" => $recomendaciones
                    ]);

                    /* ACTUALIZAR SOLICITUD A ATENDIDA */

                    $stmt = $conexion->prepare(
                        "UPDATE solicitudes_tutoria
                         SET estado = 'ATENDIDA'
                         WHERE id = :solicitud
                           AND estado = 'ACEPTADA'"
                    );

                    $stmt->execute([
                        "solicitud" => $solicitudId
                    ]);

                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException(
                            "No se pudo actualizar el estado de la solicitud."
                        );
                    }

                    /* GUARDAR HISTORIAL */

                    $stmt = $conexion->prepare(
                        "INSERT INTO historial_estados
                        (
                            solicitud_id,
                            estado_anterior,
                            estado_nuevo,
                            cambiado_por,
                            comentario
                        )
                        VALUES
                        (
                            :solicitud,
                            'ACEPTADA',
                            'ATENDIDA',
                            :tutor,
                            'Se registró la atención de la tutoría.'
                        )"
                    );

                    $stmt->execute([
                        "solicitud" => $solicitudId,
                        "tutor" => $tutorId
                    ]);

                    $_SESSION["mensaje_atenciones"] =
                        "Atención registrada correctamente.";

                } else {

                    $stmt = $conexion->prepare(
                        "UPDATE atenciones
                         SET asistencia = :asistencia,
                             observaciones = :observaciones,
                             resultado = :resultado,
                             recomendaciones = :recomendaciones
                         WHERE solicitud_id = :solicitud
                           AND registrado_por = :tutor"
                    );

                    $stmt->execute([
                        "asistencia" => (int)$asistencia,
                        "observaciones" => $observaciones,
                        "resultado" => $resultado,
                        "recomendaciones" => $recomendaciones,
                        "solicitud" => $solicitudId,
                        "tutor" => $tutorId
                    ]);

                    $_SESSION["mensaje_atenciones"] =
                        "Atención actualizada correctamente.";
                }

                $conexion->commit();

                header("Location: atenciones.php");
                exit;

            } catch (RuntimeException $e) {

                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                $error = $e->getMessage();

            } catch (PDOException $e) {

                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                error_log($e->getMessage());

                $error = "No se pudo guardar la atención.";
            }
        }
    }
}

/* ==========================================================
   CONSULTAR SOLICITUDES PARA REGISTRAR
   ========================================================== */

$stmt = $conexion->prepare(
    "SELECT
        s.id,
        e.nombre AS estudiante_nombre,
        e.apellido AS estudiante_apellido,
        c.nombre AS curso_nombre,
        d.fecha,
        d.hora_inicio,
        d.hora_fin
     FROM solicitudes_tutoria s
     INNER JOIN disponibilidades d
        ON d.id = s.disponibilidad_id
     INNER JOIN usuarios e
        ON e.id = s.estudiante_id
     INNER JOIN cursos c
        ON c.id = d.curso_id
     LEFT JOIN atenciones a
        ON a.solicitud_id = s.id
     WHERE d.tutor_id = :tutor
       AND s.estado = 'ACEPTADA'
       AND a.id IS NULL
       AND TIMESTAMP(d.fecha, d.hora_fin) <= NOW()
     ORDER BY d.fecha DESC"
);

$stmt->execute(["tutor" => $tutorId]);

$solicitudesDisponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   CARGAR ATENCIÓN PARA EDITAR
   ========================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "GET" &&
    $seccion === "editar"
) {

    $solicitudId = filter_input(
        INPUT_GET,
        "solicitud_id",
        FILTER_VALIDATE_INT
    );

    if (!$solicitudId) {
        exit("Atención inválida.");
    }

    $stmt = $conexion->prepare(
        "SELECT a.*
         FROM atenciones a
         INNER JOIN solicitudes_tutoria s
            ON s.id = a.solicitud_id
         INNER JOIN disponibilidades d
            ON d.id = s.disponibilidad_id
         WHERE a.solicitud_id = :solicitud
           AND d.tutor_id = :tutor
           AND a.registrado_por = :tutor_registro"
    );

    $stmt->execute([
        "solicitud" => $solicitudId,
        "tutor" => $tutorId,
        "tutor_registro" => $tutorId
    ]);

    $editar = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editar) {
        exit("No se encontró la atención.");
    }

    $asistencia = (string)$editar["asistencia"];
    $observaciones = $editar["observaciones"] ?? "";
    $resultado = $editar["resultado"] ?? "";
    $recomendaciones = $editar["recomendaciones"] ?? "";
}

/* ==========================================================
   CONSULTAR ATENCIONES REGISTRADAS
   ========================================================== */

$stmt = $conexion->prepare(
    "SELECT
        a.id,
        a.solicitud_id,
        a.asistencia,
        a.observaciones,
        a.resultado,
        a.recomendaciones,
        a.fecha_registro,
        e.nombre AS estudiante_nombre,
        e.apellido AS estudiante_apellido,
        c.nombre AS curso_nombre,
        d.fecha,
        d.hora_inicio,
        d.hora_fin
     FROM atenciones a
     INNER JOIN solicitudes_tutoria s
        ON s.id = a.solicitud_id
     INNER JOIN disponibilidades d
        ON d.id = s.disponibilidad_id
     INNER JOIN usuarios e
        ON e.id = s.estudiante_id
     INNER JOIN cursos c
        ON c.id = d.curso_id
     WHERE d.tutor_id = :tutor
     ORDER BY a.fecha_registro DESC"
);

$stmt->execute(["tutor" => $tutorId]);

$atenciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   MENSAJES
   ========================================================== */

if (isset($_SESSION["mensaje_atenciones"])) {

    $mensaje = $_SESSION["mensaje_atenciones"];

    unset($_SESSION["mensaje_atenciones"]);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registro de atenciones - TutorLink</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../estilos/tutor.css">

</head>

<body>

<!-- MENÚ LATERAL -->

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

    <a href="atenciones.php" class="activo">
        <i class="bi bi-clipboard-check-fill"></i>
        Registro de atenciones
    </a>

    <a href="/tutorias/autenticacion/logout.php" class="salir">
        <i class="bi bi-box-arrow-right"></i>
        Cerrar sesión
    </a>

</aside>

<!-- CONTENIDO PRINCIPAL -->

<main class="contenido">

    <header class="barra-superior">

        <div>

            <h1 class="titulo-pagina">
                Registro de atenciones
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

    <!-- BIENVENIDA -->

    <section class="bienvenida">

        <div>

            <h2>Gestión de atenciones</h2>

            <p>
                Registra la asistencia, observaciones,
                resultados y recomendaciones de las
                tutorías realizadas.
            </p>

        </div>

        <i class="bi bi-clipboard-check bienvenida-icono"></i>

    </section>

    <!-- MENSAJES -->

    <?php if ($mensaje !== ""): ?>

        <div class="alert alert-success">
            <?= escapar($mensaje) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="alert alert-danger">
            <?= escapar($error) ?>
        </div>

    <?php endif; ?>

    <!-- BOTONES -->

    <div class="d-flex gap-2 flex-wrap mb-4">

        <a
            href="atenciones.php"
            class="btn btn-primary"
        >
            <i class="bi bi-list-ul"></i>
            Consultar atenciones
        </a>

        <a
            href="atenciones.php?seccion=registrar"
            class="btn btn-success"
        >
            <i class="bi bi-plus-circle"></i>
            Registrar atención
        </a>

    </div>

    <!-- FORMULARIO -->

    <?php if (
        $seccion === "registrar" ||
        $seccion === "editar"
    ): ?>

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <h4 class="mb-4">
                    <?= $seccion === "editar"
                        ? "Editar atención"
                        : "Registrar nueva atención" ?>
                </h4>

                <form method="POST" action="atenciones.php">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= escapar($token) ?>"
                    >

                    <input
                        type="hidden"
                        name="accion"
                        value="<?= escapar($seccion) ?>"
                    >

                    <!-- SOLICITUD -->

                    <div class="mb-3">

                        <label class="form-label">
                            Solicitud de tutoría
                        </label>

                        <?php if ($seccion === "editar"): ?>

                            <input
                                type="hidden"
                                name="solicitud_id"
                                value="<?= (int)$solicitudId ?>"
                            >

                            <input
                                type="text"
                                class="form-control"
                                value="Solicitud #<?= (int)$solicitudId ?>"
                                readonly
                            >

                        <?php else: ?>

                            <select
                                name="solicitud_id"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Selecciona una solicitud
                                </option>

                                <?php foreach (
                                    $solicitudesDisponibles as $solicitud
                                ): ?>

                                    <option
                                        value="<?= (int)$solicitud["id"] ?>"
                                        <?= $solicitudId === (int)$solicitud["id"]
                                            ? "selected"
                                            : "" ?>
                                    >

                                        <?= escapar(
                                            $solicitud["estudiante_nombre"] . " " .
                                            $solicitud["estudiante_apellido"] . " - " .
                                            $solicitud["curso_nombre"] . " - " .
                                            $solicitud["fecha"]
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        <?php endif; ?>

                    </div>

                    <!-- ASISTENCIA -->

                    <div class="mb-3">

                        <label class="form-label">
                            ¿El estudiante asistió?
                        </label>

                        <select
                            name="asistencia"
                            class="form-select"
                            required
                        >

                            <option
                                value="1"
                                <?= $asistencia === "1" ? "selected" : "" ?>
                            >
                                Sí, asistió
                            </option>

                            <option
                                value="0"
                                <?= $asistencia === "0" ? "selected" : "" ?>
                            >
                                No asistió
                            </option>

                        </select>

                    </div>

                    <!-- OBSERVACIONES -->

                    <div class="mb-3">

                        <label class="form-label">
                            Observaciones
                        </label>

                        <textarea
                            name="observaciones"
                            class="form-control"
                            rows="3"
                            placeholder="Describe lo ocurrido durante la tutoría."
                        ><?= escapar($observaciones) ?></textarea>

                    </div>

                    <!-- RESULTADO -->

                    <div class="mb-3">

                        <label class="form-label">
                            Resultado de la tutoría
                        </label>

                        <textarea
                            name="resultado"
                            class="form-control"
                            rows="3"
                            placeholder="Describe los resultados obtenidos."
                            required
                        ><?= escapar($resultado) ?></textarea>

                    </div>

                    <!-- RECOMENDACIONES -->

                    <div class="mb-3">

                        <label class="form-label">
                            Recomendaciones
                        </label>

                        <textarea
                            name="recomendaciones"
                            class="form-control"
                            rows="3"
                            placeholder="Indica recomendaciones para el estudiante."
                        ><?= escapar($recomendaciones) ?></textarea>

                    </div>

                    <!-- BOTONES -->

                    <div class="d-flex gap-2 flex-wrap">

                        <button
                            type="submit"
                            class="btn btn-success"
                        >

                            <i class="bi bi-check-circle"></i>

                            <?= $seccion === "editar"
                                ? "Guardar cambios"
                                : "Registrar atención" ?>

                        </button>

                        <a
                            href="atenciones.php"
                            class="btn btn-secondary"
                        >
                            Cancelar
                        </a>

                    </div>

                </form>

            </div>

        </div>

    <!-- LISTADO -->

    <?php else: ?>

        <h4 class="seccion-titulo">
            Atenciones registradas
        </h4>

        <div class="card shadow-sm">

            <div class="card-body p-4">

                <div class="table-responsive">

                    <table class="table table-bordered table-striped align-middle">

                        <thead>

                            <tr>
                                <th>Estudiante</th>
                                <th>Curso</th>
                                <th>Fecha</th>
                                <th>Horario</th>
                                <th>Asistencia</th>
                                <th>Observaciones</th>
                                <th>Resultado</th>
                                <th>Recomendaciones</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($atenciones as $atencion): ?>

                                <tr>

                                    <td>
                                        <?= escapar(
                                            $atencion["estudiante_nombre"] . " " .
                                            $atencion["estudiante_apellido"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= escapar(
                                            $atencion["curso_nombre"]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= escapar($atencion["fecha"]) ?>
                                    </td>

                                    <td>
                                        <?= escapar(
                                            substr($atencion["hora_inicio"], 0, 5)
                                        ) ?>
                                        -
                                        <?= escapar(
                                            substr($atencion["hora_fin"], 0, 5)
                                        ) ?>
                                    </td>

                                    <td>

                                        <?php if ((int)$atencion["asistencia"] === 1): ?>

                                            <span class="badge bg-success">
                                                Asistió
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-danger">
                                                No asistió
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= nl2br(
                                            escapar($atencion["observaciones"] ?? "")
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= nl2br(
                                            escapar($atencion["resultado"] ?? "")
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= nl2br(
                                            escapar($atencion["recomendaciones"] ?? "")
                                        ) ?>
                                    </td>

                                    <td>

                                        <a
                                            href="atenciones.php?seccion=editar&amp;solicitud_id=<?= (int)$atencion["solicitud_id"] ?>"
                                            class="btn btn-primary btn-sm"
                                        >

                                            <i class="bi bi-pencil-square"></i>
                                            Editar

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            <?php if (empty($atenciones)): ?>

                                <tr>

                                    <td
                                        colspan="9"
                                        class="text-center py-4"
                                    >

                                        Todavía no tienes atenciones registradas.

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>

    <a
        href="panel.php"
        class="btn btn-secondary mt-4"
    >

        <i class="bi bi-arrow-left"></i>
        Volver al panel

    </a>

</main>

</body>
</html>