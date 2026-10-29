
<?php
/* ============================================================
   SISTEMA: TUTORLINK
   ARCHIVO: registro.php
   DESCRIPCIÓN: Registro de estudiantes y tutores
   ============================================================ */

session_start();


/* ============================================================
   1. CONEXIÓN A LA BASE DE DATOS
   ============================================================ */

// CORREGIDO: La carpeta config está un nivel arriba
require_once "../config/conexion.php";


/* ============================================================
   2. VERIFICAR SI EL USUARIO YA INICIÓ SESIÓN
   ============================================================ */

if (isset($_SESSION['usuario_id'])) {

    // CORREGIDO: index.php está en la carpeta principal
    header("Location: ../index.php");
    exit;
}


/* ============================================================
   3. OBTENER CARRERAS DE LA BASE DE DATOS
   ============================================================ */

$consulta = $conexion->query(
    "SELECT id, nombre FROM carreras ORDER BY nombre"
);

$carreras = $consulta->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Crear cuenta - TutorLink</title>


    <!-- ========================================================
         4. BOOTSTRAP
         ======================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- ========================================================
         5. ICONOS BOOTSTRAP
         ======================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- ========================================================
         6. CSS DE REGISTRO
         CORREGIDO: estilos está un nivel arriba
         ======================================================== -->

    <link
        href="../estilos/registro.css?v=5"
        rel="stylesheet"
    >

</head>


<body class="registro-page">


<!-- ============================================================
     7. CONTENEDOR PRINCIPAL
     ============================================================ -->

<div class="registro-container">


    <!-- ========================================================
         8. SECCIÓN IZQUIERDA: IMAGEN DE TUTORLINK
         La imagen se carga desde registro.css
         Ruta de imagen: ../img/registro.png
         ======================================================== -->

    <section class="registro-visual">

        <div class="registro-logo">

            <i class="bi bi-mortarboard-fill"></i>

            TutorLink

        </div>


        <div class="registro-texto">

            <h1>
                Tu aprendizaje comienza aquí
            </h1>

            <p>
                Conecta con tutores, comparte conocimientos
                y alcanza tus metas académicas.
            </p>

        </div>

    </section>



    <!-- ========================================================
         9. SECCIÓN DERECHA: FORMULARIO DE REGISTRO
         ======================================================== -->

    <section class="registro-form-panel">


        <!-- ENCABEZADO -->

        <div class="registro-form-header">

            <div class="registro-icono">

                <i class="bi bi-person-plus-fill"></i>

            </div>

            <h2>
                Crear cuenta
            </h2>

            <p>
                Da el primer paso para formar parte de TutorLink
            </p>

        </div>



        <!-- ====================================================
             10. FORMULARIO DE REGISTRO
             ==================================================== -->

        <form
            action="guardar_usuario.php"
            method="POST"
            class="registro-form"
        >


            <!-- =================================================
                 NOMBRE Y APELLIDO
                 ================================================= -->

            <div class="registro-fila">

                <div class="form-group">

                    <label for="nombre">
                        Nombre
                    </label>

                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        class="form-control"
                        placeholder="Ingresa tu nombre"
                        maxlength="50"
                        autocomplete="given-name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="apellido">
                        Apellido
                    </label>

                    <input
                        type="text"
                        id="apellido"
                        name="apellido"
                        class="form-control"
                        placeholder="Ingresa tu apellido"
                        maxlength="50"
                        autocomplete="family-name"
                        required
                    >

                </div>

            </div>



            <!-- =================================================
                 NOMBRE DE USUARIO
                 ================================================= -->

            <div class="form-group">

                <label for="username">
                    Nombre de usuario
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    placeholder="Elige un nombre de usuario"
                    maxlength="20"
                    autocomplete="username"
                    required
                >

            </div>



            <!-- =================================================
                 CORREO ELECTRÓNICO
                 ================================================= -->

            <div class="form-group">

                <label for="correo">
                    Correo electrónico
                </label>

                <input
                    type="email"
                    id="correo"
                    name="correo"
                    class="form-control"
                    placeholder="ejemplo@correo.com"
                    maxlength="150"
                    autocomplete="email"
                    required
                >

            </div>



            <!-- =================================================
                 CONTRASEÑA
                 ================================================= -->

            <div class="form-group">

                <label for="contrasena">
                    Contraseña
                </label>

                <input
                    type="password"
                    id="contrasena"
                    name="contrasena"
                    class="form-control"
                    placeholder="Crea una contraseña"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>



            <!-- =================================================
                 CONFIRMAR CONTRASEÑA
                 ================================================= -->

            <div class="form-group">

                <label for="confirmar_contrasena">
                    Confirmar contraseña
                </label>

                <input
                    type="password"
                    id="confirmar_contrasena"
                    name="confirmar_contrasena"
                    class="form-control"
                    placeholder="Repite tu contraseña"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>



            <!-- =================================================
                 TIPO DE USUARIO
                 ================================================= -->

            <div class="form-group">

                <label for="rol">
                    Tipo de usuario
                </label>

                <select
                    id="rol"
                    name="rol"
                    class="form-select"
                    required
                >

                    <option value="">
                        Selecciona tu rol
                    </option>

                    <option value="ESTUDIANTE">
                        Estudiante
                    </option>

                    <option value="TUTOR">
                        Catedrático / Tutor
                    </option>

                </select>

            </div>



            <!-- =================================================
                 CARRERA
                 ================================================= -->

            <div class="form-group">

                <label for="carrera_id">
                    Carrera
                </label>

                <select
                    id="carrera_id"
                    name="carrera_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Selecciona tu carrera
                    </option>

                    <?php foreach ($carreras as $carrera): ?>

                        <option
                            value="<?php echo (int) $carrera['id']; ?>"
                        >

                            <?php
                                echo htmlspecialchars(
                                    $carrera['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>



            <!-- =================================================
                 11. BOTÓN CREAR CUENTA
                 ================================================= -->

            <button
                type="submit"
                class="registro-boton"
            >

                <i class="bi bi-person-check-fill"></i>

                Crear cuenta

            </button>

        </form>



        <!-- ====================================================
             12. REGRESAR AL INICIO DE SESIÓN
             ==================================================== -->

        <div class="registro-login">

            <p>
                ¿Ya tienes una cuenta?
            </p>

            <!-- CORREGIDO: index.php está un nivel arriba -->

            <a href="../index.php">
                Iniciar sesión
            </a>

        </div>



        <!-- ====================================================
             13. PIE DE PÁGINA
             ==================================================== -->

        <div class="registro-footer">

            © 2026 TutorLink

        </div>

    </section>

</div>

</body>
</html>