
<?php

/* ============================================================
   SISTEMA: TUTORLINK
   ARCHIVO: recuperar_contrasena.php
   DESCRIPCIÓN: Solicitar recuperación de contraseña
   ============================================================ */


/* ============================================================
   1. OBTENER MENSAJES DEL FORMULARIO
   ============================================================ */

$mensaje = $_GET['mensaje'] ?? '';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recuperar contraseña - TutorLink</title>


    <!-- ========================================================
         2. BOOTSTRAP
         ======================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">


<!-- ============================================================
     3. CONTENEDOR PRINCIPAL
     ============================================================ -->

<div class="container min-vh-100 d-flex justify-content-center align-items-center">

    <div
        class="card shadow-sm p-4"
        style="width: 100%; max-width: 420px;"
    >


        <!-- ====================================================
             4. ENCABEZADO
             ==================================================== -->

        <h3 class="text-center mb-3">
            Recuperar contraseña
        </h3>

        <p class="text-center text-muted">
            Ingresa tu correo electrónico para solicitar
            un enlace de recuperación.
        </p>


        <!-- ====================================================
             5. MENSAJE DE CONFIRMACIÓN
             ==================================================== -->

        <?php if ($mensaje === 'enviado'): ?>

            <div class="alert alert-success">

                Si el correo está registrado, recibirás
                un enlace para restablecer tu contraseña.

            </div>

        <?php endif; ?>


        <!-- ====================================================
             6. MENSAJE DE ERROR
             ==================================================== -->

        <?php if ($mensaje === 'error'): ?>

            <div class="alert alert-danger">

                No se pudo procesar la solicitud.
                Inténtalo nuevamente.

            </div>

        <?php endif; ?>


        <!-- ====================================================
             7. FORMULARIO DE RECUPERACIÓN
             ==================================================== -->

        <!-- enviar_recuperacion.php está en la misma carpeta -->

        <form
            action="enviar_recuperacion.php"
            method="POST"
        >


            <!-- CORREO ELECTRÓNICO -->

            <div class="mb-3">

                <label for="correo" class="form-label">
                    Correo electrónico
                </label>

                <input
                    type="email"
                    class="form-control"
                    id="correo"
                    name="correo"
                    placeholder="Ingresa tu correo registrado"
                    maxlength="150"
                    autocomplete="email"
                    required
                >

            </div>


            <!-- =================================================
                 8. BOTÓN ENVIAR ENLACE
                 ================================================= -->

            <button
                type="submit"
                class="btn btn-primary w-100"
            >

                Enviar enlace de recuperación

            </button>

        </form>


        <!-- ====================================================
             9. REGRESAR AL INICIO DE SESIÓN
             ==================================================== -->

        <div class="text-center mt-3">

            <!-- CORREGIDO: index.php está un nivel arriba -->

            <a href="../index.php">
                Volver al inicio de sesión
            </a>

        </div>

    </div>

</div>

</body>
</html>