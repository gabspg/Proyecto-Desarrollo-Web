<?php
require_once "../config/conexion.php";
require_once "../seguridad/verificar_sesion.php";
verificarAcceso("TUTOR");

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$tutorId = (int)($_SESSION["usuario_id"] ?? 0);
$nombre = $_SESSION["nombre"] ?? "Tutor";

if ($tutorId < 1) {
    exit("Sesión inválida.");
}

$stmt = $conexion->prepare(
    "SELECT u.id
     FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE u.id = ?
       AND r.nombre = 'TUTOR'
       AND u.estado = 'ACTIVO'"
);
$stmt->execute([$tutorId]);
if (!$stmt->fetchColumn()) {
    exit("El tutor no está activo.");
}

if (empty($_SESSION["csrf_notificaciones_tutor"])) {
    $_SESSION["csrf_notificaciones_tutor"] = bin2hex(random_bytes(32));
}
$token = $_SESSION["csrf_notificaciones_tutor"];
$mensaje = (string)($_SESSION["mensaje_notificaciones"] ?? "");
$error = (string)($_SESSION["error_notificaciones"] ?? "");
unset($_SESSION["mensaje_notificaciones"], $_SESSION["error_notificaciones"]);

$stmt = $conexion->prepare(
    "SELECT id, solicitud_id, tipo, mensaje, fecha_envio
     FROM notificaciones
     WHERE usuario_id = ?
     ORDER BY id DESC"
);
$stmt->execute([$tutorId]);
$notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notificaciones - TutorLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
    <link rel="stylesheet" href="../estilos/tutor.css">
    <style nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, "UTF-8") ?>">
        .notificacion {
            border-bottom: 1px solid #edf0f2;
            padding: 18px 0;
        }
        .notificacion:last-child {
            border-bottom: 0;
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="logo"><i class="bi bi-mortarboard-fill"></i> TutorLink</div>
    <div class="menu-titulo">MENÚ PRINCIPAL</div>
    <a href="panel.php"><i class="bi bi-grid-1x2-fill"></i> Inicio</a>
    <a href="cursos.php?vista=cursos"><i class="bi bi-journal-bookmark-fill"></i> Cursos</a>
    <a href="cursos.php?vista=horarios"><i class="bi bi-calendar-week-fill"></i> Horarios</a>
    <div class="menu-titulo">GESTIÓN DE TUTORÍAS</div>
    <a href="solicitudes.php"><i class="bi bi-envelope-paper-fill"></i> Solicitudes recibidas</a>
    <a href="atenciones.php"><i class="bi bi-clipboard-check-fill"></i> Registro de atenciones</a>
    <a class="activo" href="notificaciones.php"><i class="bi bi-bell-fill"></i> Notificaciones</a>
    <form action="../apis/logout.php" method="POST" class="logout-form">
        <input type="hidden" name="token" value="<?= escapar($_SESSION["csrf_logout"]) ?>">
        <button type="submit" class="salir"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
    </form>
</aside>

<main class="contenido">
    <header class="barra-superior">
        <div>
            <h1 class="titulo-pagina">Notificaciones</h1>
            <p class="subtitulo">TutorLink - Sistema de Gestión de Tutorías y Asesorías Académicas</p>
        </div>
        <div class="perfil">
            <div class="avatar"><?= escapar(strtoupper(substr($nombre, 0, 1))) ?></div>
            <div><strong><?= escapar($nombre) ?></strong><div class="text-muted small">Tutor</div></div>
        </div>
    </header>

    <section class="bienvenida">
        <div>
            <h2>Tus notificaciones</h2>
            <p>Consulta avisos sobre solicitudes y cancelaciones de tutorías.</p>
        </div>
        <i class="bi bi-bell bienvenida-icono"></i>
    </section>

    <?php if ($mensaje !== ""): ?>
        <div class="alert alert-success"><?= escapar($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error !== ""): ?>
        <div class="alert alert-danger"><?= escapar($error) ?></div>
    <?php endif; ?>

    <section class="mt-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="mb-3">Mis notificaciones (<?= count($notificaciones) ?>)</h4>
                <?php if (!$notificaciones): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                        <h5>No tienes notificaciones todavía</h5>
                    </div>
                <?php endif; ?>

                <?php foreach ($notificaciones as $notificacion): ?>
                    <article class="notificacion">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <strong><?= escapar($notificacion["tipo"]) ?></strong>
                                <p class="mb-1 mt-2"><?= nl2br(escapar($notificacion["mensaje"])) ?></p>
                                <small class="text-muted">
                                    <?= escapar(date("d/m/Y H:i", strtotime($notificacion["fecha_envio"]))) ?>
                                </small>
                                <?php if (!empty($notificacion["solicitud_id"])): ?>
                                    <div>
                                        <a class="btn btn-link btn-sm px-0" href="solicitudes.php">
                                            Ver solicitudes <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form
                                method="post"
                                action="../apis/notificaciones.php"
                                data-confirm="¿Deseas eliminar esta notificación?"
                            >
                                <input type="hidden" name="token" value="<?= escapar($token) ?>">
                                <input type="hidden" name="notificacion_id" value="<?= (int)$notificacion["id"] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash"></i> Eliminar
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
<script src="../js/form-confirm.js" defer></script>
</body>
</html>
