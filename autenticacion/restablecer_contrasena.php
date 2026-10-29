
<?php

/* ============================================================
   SISTEMA: TUTORLINK
   ARCHIVO: restablecer_contrasena.php
   DESCRIPCIÓN: Formulario para restablecer la contraseña
   ============================================================ */


/* ============================================================
   1. CONEXIÓN A LA BASE DE DATOS
   ============================================================ */

// CORREGIDO: La carpeta config está un nivel arriba
require_once "../config/conexion.php";


/* ============================================================
   2. RECIBIR EL TOKEN DEL ENLACE
   ============================================================ */

$token = $_GET['token'] ?? '';


// Verificar que tenga el formato correcto
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    exit("El enlace de recuperación no es válido.");
}


/* ============================================================
   3. CONVERTIR EL TOKEN EN HASH
   ============================================================ */

$tokenHash = hash('sha256', $token);


/* ============================================================
   4. BUSCAR EL ENLACE DE RECUPERACIÓN
   ============================================================ */

$consulta = $conexion->prepare(
    "SELECT id
     FROM recuperacion_contrasenas
     WHERE token_hash = :token_hash
       AND utilizado = 0
       AND fecha_vencimiento > NOW()
     LIMIT 1"
);

$consulta->execute([
    'token_hash' => $tokenHash
]);

$recuperacion = $consulta->fetch(PDO::FETCH_ASSOC);


/* ============================================================
   5. VERIFICAR QUE EL ENLACE SEA VÁLIDO
   ============================================================ */

if (!$recuperacion) {
    exit("El enlace ha vencido o ya fue utilizado.");
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Restablecer contraseña - TutorLink</title>

    <!-- BOOTSTRAP -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body class="bg-light">


<!-- ============================================================
     6. CONTENEDOR PRINCIPAL
     ============================================================ -->

<div class="container min-vh-100 d-flex justify-content-center align-items-center">

    <div
        class="card shadow-sm p-4"
        style="width: 100%; max-width: 420px;"
    >


        <!-- ====================================================
             7. ENCABEZADO
             ==================================================== -->

        <h3 class="text-center mb-3">
            Nueva contraseña
        </h3>

        <p class="text-center text-muted">
            Ingresa tu nueva contraseña para recuperar tu cuenta.
        </p>


        <!-- ====================================================
             8. FORMULARIO DE RECUPERACIÓN
             ==================================================== -->

        <!-- Se mantiene porque guardar_contrasena.php
             está en la misma carpeta autenticacion -->

        <form action="guardar_contrasena.php" method="POST">


            <!-- ENVIAR EL TOKEN DE FORMA OCULTA -->

            <input
                type="hidden"
                name="token"
                value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
            >


            <!-- =================================================
                 9. NUEVA CONTRASEÑA
                 ================================================= -->

            <div class="mb-3">

                <label for="contrasena" class="form-label">
                    Nueva contraseña
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="contrasena"
                    name="contrasena"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <!-- =================================================
                 10. CONFIRMAR CONTRASEÑA
                 ================================================= -->

            <div class="mb-3">

                <label for="confirmar_contrasena" class="form-label">
                    Confirmar nueva contraseña
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="confirmar_contrasena"
                    name="confirmar_contrasena"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <!-- =================================================
                 11. GUARDAR NUEVA CONTRASEÑA
                 ================================================= -->

            <button
                type="submit"
                class="btn btn-primary w-100"
            >
                Guardar nueva contraseña
            </button>

        </form>


        <!-- ====================================================
             12. VOLVER AL INICIO DE SESIÓN
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