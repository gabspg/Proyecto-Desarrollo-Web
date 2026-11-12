<?php
require_once __DIR__ . "/bootstrap.php";
apiRequerirPost("token_cursos");
require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../seguridad/verificar_sesion.php";
$usuarioId = apiRequerirRol($conexion, "ADMIN");
apiToken("token_cursos");
$tokenSesion = $_SESSION["token_cursos"];
$error = "";
$mensaje = "";
$seccion = (string)($_POST["seccion"] ?? "lista");
$cursoId = 0;
$codigo = "";
$nombre = "";
$carreraId = "";
$descripcion = "";
$tutorId = "";
$cursoAsignarId = "";

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

        header("Location: ../administrador/cursos.php");
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

                header("Location: ../administrador/cursos.php");
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

                header("Location: ../administrador/cursos.php");
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

                header("Location: ../administrador/cursos.php?seccion=asignar");
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

                header("Location: ../administrador/cursos.php?seccion=asignar");
                exit;
            }

        } catch (PDOException $errorBD) {

            error_log($errorBD->getMessage());

            $error = "No se puede quitar esta asignación porque puede tener tutorías relacionadas.";
        }
    }
}
if ($error !== "") {
    $_SESSION["error_cursos"] = $error;
    $seccionDestino = in_array($seccion, ["registrar", "editar", "asignar"], true)
        ? $seccion
        : "lista";
    $destino = "../administrador/cursos.php";
    if ($seccionDestino !== "lista") {
        $destino .= "?seccion=" . urlencode($seccionDestino);
    }
    header("Location: " . $destino);
    exit;
}
header("Location: ../administrador/cursos.php");
exit;
