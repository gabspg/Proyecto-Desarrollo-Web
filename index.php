
<?php
/* ============================================================
   SISTEMA: TUTORLINK
   ARCHIVO: index.php
   DESCRIPCIÓN: Inicio de sesión y acceso al registro
   ============================================================ */

require_once __DIR__ . "/seguridad/sesion.php";

if (empty($_SESSION["csrf_login"])) {
    $_SESSION["csrf_login"] = bin2hex(random_bytes(32));
}

/* ============================================================
   1. VERIFICAR SI EL USUARIO YA INICIÓ SESIÓN
   ============================================================ */

if (isset($_SESSION['usuario_id'])) {

    $rutas = [
        'ADMIN' => APP_BASE_URL . '/administrador/panel.php',
        'TUTOR' => APP_BASE_URL . '/tutor/panel.php',
        'ESTUDIANTE' => APP_BASE_URL . '/estudiante/panel.php'
    ];

    $rol = $_SESSION['rol'] ?? '';

    if (isset($rutas[$rol])) {
        header("Location: " . $rutas[$rol]);
        exit;
    }
}

/* ============================================================
   2. OBTENER MENSAJES DE ERROR
   ============================================================ */

$error = $_GET['error'] ?? '';
$perfilEliminado = ($_GET['perfil'] ?? '') === 'eliminado';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Iniciar sesión - TutorLink</title>
    <link rel="icon" type="image/png" href="img/favicon.png">

    <!-- BOOTSTRAP -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <!-- ICONOS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
        integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD"
        crossorigin="anonymous"
    >

    <!-- CSS DEL LOGIN -->
    <link
        href="<?= APP_BASE_URL ?>/estilos/login.css?v=6"
        rel="stylesheet"
    >

</head>

<body class="login-page">

<!-- CONTENEDOR PRINCIPAL -->

<div class="login-container">

    <!-- IMAGEN IZQUIERDA -->

    <section class="login-visual">

    </section>

    <!-- FORMULARIO DERECHO -->

    <section class="login-form-panel">

        <!-- ENCABEZADO -->

        <div class="login-form-header">

            <div class="login-icono">
                <i class="bi bi-person-circle"></i>
            </div>

            <h2>Iniciar sesión</h2>
            

            <p>
                Bienvenido a TutorLink
            </p>

        </div>

        <!-- MENSAJES DE ERROR -->

        <?php if ($error === '1'): ?>

            <div class="alert alert-danger login-error">
                Usuario o contraseña incorrectos,
                o cuenta no activa.
            </div>

        <?php endif; ?>

        <?php if ($error === '2'): ?>

            <div class="alert alert-warning login-error">
                Debes completar todos los campos.
            </div>

        <?php endif; ?>

        <?php if ($perfilEliminado): ?>
            <div class="alert alert-success login-error" role="status">
                Tu perfil y sus datos relacionados se eliminaron correctamente.
            </div>
        <?php endif; ?>

        <!-- FORMULARIO -->

        <form
            action="<?= APP_BASE_URL ?>/apis/login.php"
            method="POST"
            class="login-form"
        >

            <input
                type="hidden"
                name="token"
                value="<?php echo htmlspecialchars($_SESSION["csrf_login"], ENT_QUOTES, "UTF-8"); ?>"
            >

            <!-- USUARIO -->

            <div class="form-group">

                <label for="usuario">
                    Correo electrónico o nombre de usuario
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="usuario"
                    name="usuario"
                    placeholder="Ingresa tu correo o usuario"
                    autocomplete="username"
                    required
                >

            </div>

            <!-- CONTRASEÑA -->

            <div class="form-group">

                <label for="contrasena">
                    Contraseña
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="contrasena"
                    name="contrasena"
                    placeholder="Ingresa tu contraseña"
                    autocomplete="current-password"
                    required
                >

            </div>

            <!-- BOTÓN INICIAR SESIÓN -->

            <button
                type="submit"
                class="login-boton"
            >

                <i class="bi bi-box-arrow-in-right"></i>

                Iniciar sesión

            </button>

        </form>

        <!-- CREAR CUENTA -->

        <div class="crear-cuenta-contenedor">

            <p>
                ¿Todavía no formas parte de TutorLink?
            </p>

            <a
                href="<?= APP_BASE_URL ?>/autenticacion/registro.php"
                class="crear-cuenta-boton"
            >

                <i class="bi bi-person-plus-fill"></i>

                Crear cuenta

            </a>

        </div>

        <!-- PIE DE PÁGINA -->

        <div class="login-footer">
            © 2026 TutorLink
        </div>

    </section>

</div>

</body>
</html>
