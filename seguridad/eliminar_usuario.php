<?php

function eliminarUsuarioPermanentemente(PDO $conexion, int $usuarioId): string
{
    $conexion->beginTransaction();

    try {
        $consulta = $conexion->prepare(
            "SELECT r.nombre
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE u.id = :id
             FOR UPDATE"
        );
        $consulta->execute(["id" => $usuarioId]);
        $rol = $consulta->fetchColumn();

        if (!in_array($rol, ["ESTUDIANTE", "TUTOR"], true)) {
            throw new DomainException("Solo se pueden eliminar perfiles de estudiantes o tutores.");
        }

        $consulta = $conexion->prepare(
            "DELETE FROM atenciones
             WHERE registrado_por = :atencion_usuario
                OR solicitud_id IN (
                    SELECT s.id
                    FROM solicitudes_tutoria s
                    LEFT JOIN disponibilidades d
                        ON d.id = s.disponibilidad_id
                    WHERE s.estudiante_id = :solicitud_estudiante
                       OR d.tutor_id = :solicitud_tutor
                )"
        );
        $consulta->execute([
            "atencion_usuario" => $usuarioId,
            "solicitud_estudiante" => $usuarioId,
            "solicitud_tutor" => $usuarioId
        ]);

        $consulta = $conexion->prepare(
            "DELETE FROM historial_estados
             WHERE cambiado_por = :historial_usuario
                OR solicitud_id IN (
                    SELECT s.id
                    FROM solicitudes_tutoria s
                    LEFT JOIN disponibilidades d
                        ON d.id = s.disponibilidad_id
                    WHERE s.estudiante_id = :solicitud_estudiante
                       OR d.tutor_id = :solicitud_tutor
                )"
        );
        $consulta->execute([
            "historial_usuario" => $usuarioId,
            "solicitud_estudiante" => $usuarioId,
            "solicitud_tutor" => $usuarioId
        ]);

        $consulta = $conexion->prepare(
            "DELETE FROM notificaciones WHERE usuario_id = :id"
        );
        $consulta->execute(["id" => $usuarioId]);

        $consulta = $conexion->prepare(
            "DELETE FROM solicitudes_tutoria
             WHERE estudiante_id = :estudiante
                OR disponibilidad_id IN (
                    SELECT id
                    FROM disponibilidades
                    WHERE tutor_id = :tutor
                )"
        );
        $consulta->execute([
            "estudiante" => $usuarioId,
            "tutor" => $usuarioId
        ]);

        $consulta = $conexion->prepare(
            "DELETE FROM disponibilidades WHERE tutor_id = :id"
        );
        $consulta->execute(["id" => $usuarioId]);

        $consulta = $conexion->prepare(
            "DELETE FROM tutor_cursos WHERE tutor_id = :id"
        );
        $consulta->execute(["id" => $usuarioId]);

        $consulta = $conexion->prepare(
            "DELETE FROM usuarios WHERE id = :id"
        );
        $consulta->execute(["id" => $usuarioId]);

        if ($consulta->rowCount() !== 1) {
            throw new DomainException("No se encontró el perfil para eliminar.");
        }

        $conexion->commit();

        return $rol;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $error;
    }
}
