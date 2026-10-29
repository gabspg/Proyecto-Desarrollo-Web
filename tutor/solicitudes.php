
<?php
require_once "../config/conexion.php";
require_once "../seguridad/verificar_sesion.php";
verificarAcceso("TUTOR");

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$tutorId = (int)($_SESSION["usuario_id"] ?? 0);
$nombre = $_SESSION["nombre"] ?? "Tutor";

if ($tutorId <= 0) {
    exit("No se pudo identificar al tutor.");
}

if (empty($_SESSION["token_solicitudes_tutor"])) {
    $_SESSION["token_solicitudes_tutor"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["token_solicitudes_tutor"];
$error = "";
$mensaje = "";

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
   ACEPTAR O RECHAZAR SOLICITUDES
   ========================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tokenRecibido = $_POST["token"] ?? "";

    if (!is_string($tokenRecibido) ||
        !hash_equals($token, $tokenRecibido)) {
        exit("Solicitud no válida.");
    }

    $solicitudId = filter_input(
        INPUT_POST,
        "solicitud_id",
        FILTER_VALIDATE_INT
    );

    $accion = $_POST["accion"] ?? "";

    if (!$solicitudId) {
        $error = "La solicitud seleccionada no es válida.";
    } elseif (!in_array($accion, ["aceptar", "rechazar"], true)) {
        $error = "Acción no permitida.";
    } else {

        try {
            $conexion->beginTransaction();

            $stmt = $conexion->prepare(
                "SELECT
                    s.id,
                    s.estudiante_id,
                    s.estado,
                    s.disponibilidad_id,
                    d.estado AS estado_disponibilidad,
                    d.cupo_actual,
                    d.cupo_maximo
                 FROM solicitudes_tutoria s
                 INNER JOIN disponibilidades d
                    ON d.id = s.disponibilidad_id
                 WHERE s.id = :solicitud
                   AND d.tutor_id = :tutor
                 FOR UPDATE"
            );
            $stmt->execute([
                "solicitud" => $solicitudId,
                "tutor" => $tutorId
            ]);

            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                throw new RuntimeException("No se encontró la solicitud.");
            }

            if ($solicitud["estado"] !== "PENDIENTE") {
                throw new RuntimeException("Esta solicitud ya fue procesada.");
            }

            $estadoNuevo = $accion === "aceptar"
                ? "ACEPTADA"
                : "RECHAZADA";

            if ($accion === "aceptar") {

                if ($solicitud["estado_disponibilidad"] !== "DISPONIBLE") {
                    throw new RuntimeException("La tutoría ya no está disponible.");
                }

                $stmt = $conexion->prepare(
                    "UPDATE disponibilidades
                     SET cupo_actual = cupo_actual + 1,
                         estado = CASE
                             WHEN cupo_actual + 1 >= cupo_maximo
                             THEN 'AGOTADO'
                             ELSE 'DISPONIBLE'
                         END
                     WHERE id = :disponibilidad
                       AND estado = 'DISPONIBLE'
                       AND cupo_actual < cupo_maximo"
                );
                $stmt->execute([
                    "disponibilidad" => $solicitud["disponibilidad_id"]
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException("No hay cupos disponibles.");
                }
            }

            $stmt = $conexion->prepare(
                "UPDATE solicitudes_tutoria
                 SET estado = :estado
                 WHERE id = :solicitud
                   AND estado = 'PENDIENTE'"
            );
            $stmt->execute([
                "estado" => $estadoNuevo,
                "solicitud" => $solicitudId
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException("No se pudo actualizar la solicitud.");
            }

            $comentario = $accion === "aceptar"
                ? "El tutor aceptó la solicitud."
                : "El tutor rechazó la solicitud.";

            $stmt = $conexion->prepare(
                "INSERT INTO historial_estados
                (solicitud_id, estado_anterior, estado_nuevo,
                 cambiado_por, comentario)
                 VALUES
                (:solicitud, 'PENDIENTE', :nuevo,
                 :tutor, :comentario)"
            );
            $stmt->execute([
                "solicitud" => $solicitudId,
                "nuevo" => $estadoNuevo,
                "tutor" => $tutorId,
                "comentario" => $comentario
            ]);

            $tipoNotificacion = $accion === "aceptar"
                ? "ACEPTACION"
                : "RECHAZO";

            $textoNotificacion = $accion === "aceptar"
                ? "Tu solicitud de tutoría fue aceptada."
                : "Tu solicitud de tutoría fue rechazada.";

            $stmt = $conexion->prepare(
                "INSERT INTO notificaciones
                (usuario_id, solicitud_id, tipo, mensaje)
                 VALUES
                (:estudiante, :solicitud, :tipo, :mensaje)"
            );
            $stmt->execute([
                "estudiante" => $solicitud["estudiante_id"],
                "solicitud" => $solicitudId,
                "tipo" => $tipoNotificacion,
                "mensaje" => $textoNotificacion
            ]);

            $conexion->commit();

            $_SESSION["mensaje_solicitudes_tutor"] =
                $accion === "aceptar"
                ? "Solicitud aceptada correctamente."
                : "Solicitud rechazada correctamente.";

            header("Location: solicitudes.php");
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
            $error = "No se pudo procesar la solicitud.";
        }
    }
}

if (isset($_SESSION["mensaje_solicitudes_tutor"])) {
    $mensaje = $_SESSION["mensaje_solicitudes_tutor"];
    unset($_SESSION["mensaje_solicitudes_tutor"]);
}

/* ==========================================================
   CONSULTAR SOLICITUDES
   ========================================================== */

$stmt = $conexion->prepare(
    "SELECT
        s.id, s.motivo, s.estado, s.created_at,
        e.nombre AS estudiante_nombre,
        e.apellido AS estudiante_apellido,
        c.codigo AS curso_codigo,
        c.nombre AS curso_nombre,
        d.fecha, d.hora_inicio, d.hora_fin,
        d.modalidad, d.tipo_tutoria, d.lugar_link,
        d.estado AS estado_horario
     FROM solicitudes_tutoria s
     INNER JOIN usuarios e ON e.id = s.estudiante_id
     INNER JOIN disponibilidades d ON d.id = s.disponibilidad_id
     INNER JOIN cursos c ON c.id = d.curso_id
     WHERE d.tutor_id = :tutor
     ORDER BY
         CASE WHEN s.estado = 'PENDIENTE' THEN 0 ELSE 1 END,
         s.created_at DESC"
);
$stmt->execute(["tutor" => $tutorId]);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($solicitudes);
$pendientes = 0;
$aceptadas = 0;
$rechazadas = 0;

foreach ($solicitudes as $solicitud) {
    switch ($solicitud["estado"]) {
        case "PENDIENTE":
            $pendientes++;
            break;
        case "ACEPTADA":
            $aceptadas++;
            break;
        case "RECHAZADA":
            $rechazadas++;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes recibidas - TutorLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../estilos/tutor.css">
</head>
<body>

<aside class="sidebar">
    <div class="logo">
        <i class="bi bi-mortarboard-fill"></i> TutorLink
    </div>

    <div class="menu-titulo">MENÚ PRINCIPAL</div>

    <a href="panel.php">
        <i class="bi bi-grid-1x2-fill"></i> Inicio
    </a>

    <a href="cursos.php?vista=cursos">
        <i class="bi bi-journal-bookmark-fill"></i> Cursos
    </a>

    <a href="cursos.php?vista=horarios">
        <i class="bi bi-calendar-week-fill"></i> Horarios
    </a>

    <div class="menu-titulo">GESTIÓN DE TUTORÍAS</div>

    <a href="solicitudes.php" class="activo">
        <i class="bi bi-envelope-paper-fill"></i> Solicitudes recibidas
    </a>

    <a href="atenciones.php">
        <i class="bi bi-clipboard-check-fill"></i> Registro de atenciones
    </a>

    <a href="/tutorias/autenticacion/logout.php" class="salir">
        <i class="bi bi-box-arrow-right"></i> Cerrar sesión
    </a>
</aside>

<main class="contenido">

    <header class="barra-superior">
        <div>
            <h1 class="titulo-pagina">Solicitudes recibidas</h1>
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

    <section class="bienvenida">
        <div>
            <h2>Gestión de solicitudes</h2>
            <p>
                Consulta las solicitudes enviadas por los estudiantes
                y administra su aceptación o rechazo.
            </p>
        </div>
        <i class="bi bi-envelope-paper bienvenida-icono"></i>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= escapar($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Total de solicitudes</p>
                    <h2><?= $total ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Pendientes</p>
                    <h2 class="text-warning"><?= $pendientes ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Aceptadas</p>
                    <h2 class="text-success"><?= $aceptadas ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Rechazadas</p>
                    <h2 class="text-danger"><?= $rechazadas ?></h2>
                </div>
            </div>
        </div>
    </div>

    <h4 class="seccion-titulo">Solicitudes de tutoría</h4>

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
                            <th>Modalidad</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $solicitud): ?>
                            <tr>
                                <td>
                                    <?= escapar(
                                        $solicitud["estudiante_nombre"] . " " .
                                        $solicitud["estudiante_apellido"]
                                    ) ?>
                                </td>

                                <td>
                                    <strong><?= escapar($solicitud["curso_nombre"]) ?></strong>
                                    <div class="text-muted small">
                                        <?= escapar($solicitud["curso_codigo"]) ?>
                                    </div>
                                </td>

                                <td><?= escapar($solicitud["fecha"]) ?></td>

                                <td>
                                    <?= escapar(substr($solicitud["hora_inicio"], 0, 5)) ?>
                                    -
                                    <?= escapar(substr($solicitud["hora_fin"], 0, 5)) ?>
                                </td>

                                <td>
                                    <?php if ($solicitud["modalidad"] === "VIRTUAL"): ?>
                                        <span class="badge bg-primary">
                                            <i class="bi bi-camera-video"></i> Virtual
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-geo-alt"></i> Presencial
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="min-width:180px;">
                                    <?= nl2br(escapar($solicitud["motivo"])) ?>
                                </td>

                                <td>
                                    <?php
                                    $claseEstado = "bg-secondary";
                                    if ($solicitud["estado"] === "PENDIENTE") {
                                        $claseEstado = "bg-warning text-dark";
                                    } elseif ($solicitud["estado"] === "ACEPTADA") {
                                        $claseEstado = "bg-success";
                                    } elseif ($solicitud["estado"] === "RECHAZADA") {
                                        $claseEstado = "bg-danger";
                                    }
                                    ?>
                                    <span class="badge <?= $claseEstado ?>">
                                        <?= escapar($solicitud["estado"]) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($solicitud["estado"] === "PENDIENTE"): ?>

                                        <div class="d-flex gap-2 flex-wrap">

                                            <form method="POST" action="solicitudes.php"
                                                  onsubmit="return confirm('¿Deseas aceptar esta solicitud?')">
                                                <input type="hidden" name="token" value="<?= escapar($token) ?>">
                                                <input type="hidden" name="solicitud_id" value="<?= (int)$solicitud["id"] ?>">
                                                <input type="hidden" name="accion" value="aceptar">

                                                <button type="submit" class="btn btn-success btn-sm"
                                                    <?= $solicitud["estado_horario"] !== "DISPONIBLE" ? "disabled" : "" ?>>
                                                    <i class="bi bi-check-circle"></i> Aceptar
                                                </button>
                                            </form>

                                            <form method="POST" action="solicitudes.php"
                                                  onsubmit="return confirm('¿Deseas rechazar esta solicitud?')">
                                                <input type="hidden" name="token" value="<?= escapar($token) ?>">
                                                <input type="hidden" name="solicitud_id" value="<?= (int)$solicitud["id"] ?>">
                                                <input type="hidden" name="accion" value="rechazar">

                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="bi bi-x-circle"></i> Rechazar
                                                </button>
                                            </form>

                                        </div>

                                    <?php else: ?>
                                        <span class="text-muted">Procesada</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($solicitudes)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-inbox fs-2 text-muted"></i>
                                    <p class="mt-2 mb-0">
                                        Todavía no tienes solicitudes recibidas.
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <a href="panel.php" class="btn btn-secondary mt-4">
        <i class="bi bi-arrow-left"></i> Volver al panel
    </a>

</main>
</body>
</html>