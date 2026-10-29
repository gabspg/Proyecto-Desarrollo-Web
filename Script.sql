
-- =====================================================
-- SISTEMA DE GESTIÓN DE TUTORÍAS ACADÉMICAS
-- BASE DE DATOS: db_tutorias
-- MOTOR: MySQL 8.0
-- =====================================================

CREATE DATABASE IF NOT EXISTS db_tutorias
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE db_tutorias;


-- =====================================================
-- 1. CARRERAS
-- =====================================================

CREATE TABLE carreras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;


-- =====================================================
-- 2. ROLES
-- =====================================================

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;


-- =====================================================
-- 3. USUARIOS
-- =====================================================

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    carrera_id INT NULL,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    username VARCHAR(20) NOT NULL UNIQUE,
    correo VARCHAR(150) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,

    estado ENUM(
        'PENDIENTE',
        'ACTIVO',
        'INACTIVO'
    ) NOT NULL DEFAULT 'PENDIENTE',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (rol_id)
        REFERENCES roles(id),

    FOREIGN KEY (carrera_id)
        REFERENCES carreras(id)
        ON DELETE SET NULL

) ENGINE=InnoDB;


-- =====================================================
-- 4. CURSOS
-- =====================================================

CREATE TABLE cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrera_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    descripcion TEXT,

    estado ENUM(
        'ACTIVO',
        'INACTIVO'
    ) NOT NULL DEFAULT 'ACTIVO',

    UNIQUE (carrera_id, nombre),

    FOREIGN KEY (carrera_id)
        REFERENCES carreras(id)

) ENGINE=InnoDB;


-- =====================================================
-- 5. TUTOR CURSOS
-- =====================================================

CREATE TABLE tutor_cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    curso_id INT NOT NULL,

    UNIQUE (tutor_id, curso_id),
    UNIQUE (id, tutor_id, curso_id),

    FOREIGN KEY (tutor_id)
        REFERENCES usuarios(id),

    FOREIGN KEY (curso_id)
        REFERENCES cursos(id)

) ENGINE=InnoDB;


-- =====================================================
-- 6. DISPONIBILIDADES
-- =====================================================

CREATE TABLE disponibilidades (
    id INT AUTO_INCREMENT PRIMARY KEY,

    tutor_curso_id INT NOT NULL,
    tutor_id INT NOT NULL,
    curso_id INT NOT NULL,

    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,

    modalidad ENUM(
        'PRESENCIAL',
        'VIRTUAL'
    ) NOT NULL,

    tipo_tutoria ENUM(
        'INDIVIDUAL',
        'GRUPAL'
    ) NOT NULL DEFAULT 'INDIVIDUAL',

    lugar_link VARCHAR(255),

    cupo_maximo INT NOT NULL DEFAULT 1,
    cupo_actual INT NOT NULL DEFAULT 0,

    estado ENUM(
        'DISPONIBLE',
        'AGOTADO',
        'CANCELADO'
    ) NOT NULL DEFAULT 'DISPONIBLE',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (
        tutor_curso_id,
        tutor_id,
        curso_id
    ) REFERENCES tutor_cursos(
        id,
        tutor_id,
        curso_id
    ),

    CONSTRAINT chk_horarios
        CHECK (hora_fin > hora_inicio),

    CONSTRAINT chk_cupos
        CHECK (
            cupo_maximo > 0
            AND cupo_actual >= 0
            AND cupo_actual <= cupo_maximo
        ),

    CONSTRAINT chk_tipo_tutoria
        CHECK (
            tipo_tutoria = 'GRUPAL'
            OR cupo_maximo = 1
        ),

    INDEX idx_tutor_fecha (tutor_id, fecha)

) ENGINE=InnoDB;


-- =====================================================
-- 7. SOLICITUDES DE TUTORÍA
-- =====================================================

CREATE TABLE solicitudes_tutoria (
    id INT AUTO_INCREMENT PRIMARY KEY,

    estudiante_id INT NOT NULL,
    disponibilidad_id INT NOT NULL,

    motivo TEXT NOT NULL,

    estado ENUM(
        'PENDIENTE',
        'ACEPTADA',
        'RECHAZADA',
        'ATENDIDA',
        'CANCELADA'
    ) NOT NULL DEFAULT 'PENDIENTE',

    reserva_activa TINYINT
        GENERATED ALWAYS AS (
            CASE
                WHEN estado IN ('PENDIENTE','ACEPTADA')
                THEN 1
                ELSE NULL
            END
        ) STORED,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (estudiante_id)
        REFERENCES usuarios(id),

    FOREIGN KEY (disponibilidad_id)
        REFERENCES disponibilidades(id),

    UNIQUE (
        estudiante_id,
        disponibilidad_id,
        reserva_activa
    ),

    INDEX idx_solicitud_estado (estado)

) ENGINE=InnoDB;


-- =====================================================
-- 8. HISTORIAL DE ESTADOS
-- =====================================================

CREATE TABLE historial_estados (
    id INT AUTO_INCREMENT PRIMARY KEY,

    solicitud_id INT NOT NULL,

    estado_anterior VARCHAR(20),
    estado_nuevo VARCHAR(20) NOT NULL,

    cambiado_por INT NOT NULL,

    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    comentario TEXT,

    FOREIGN KEY (solicitud_id)
        REFERENCES solicitudes_tutoria(id),

    FOREIGN KEY (cambiado_por)
        REFERENCES usuarios(id)

) ENGINE=InnoDB;


-- =====================================================
-- 9. ATENCIONES
-- =====================================================

CREATE TABLE atenciones (
    id INT AUTO_INCREMENT PRIMARY KEY,

    solicitud_id INT NOT NULL UNIQUE,
    registrado_por INT NOT NULL,

    asistencia BOOLEAN NOT NULL DEFAULT TRUE,

    observaciones TEXT,
    resultado TEXT,
    recomendaciones TEXT,

    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (solicitud_id)
        REFERENCES solicitudes_tutoria(id),

    FOREIGN KEY (registrado_por)
        REFERENCES usuarios(id)

) ENGINE=InnoDB;


-- =====================================================
-- 10. NOTIFICACIONES
-- =====================================================

CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT NOT NULL,
    solicitud_id INT NULL,

    tipo ENUM(
        'SOLICITUD',
        'ACEPTACION',
        'RECHAZO',
        'CANCELACION',
        'RECORDATORIO',
        'GENERAL'
    ) NOT NULL DEFAULT 'GENERAL',

    mensaje TEXT NOT NULL,

    leido BOOLEAN NOT NULL DEFAULT FALSE,

    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id),

    FOREIGN KEY (solicitud_id)
        REFERENCES solicitudes_tutoria(id)
        ON DELETE SET NULL

) ENGINE=InnoDB;


-- =====================================================
-- DATOS INICIALES
-- =====================================================

-- Carreras
INSERT INTO carreras (codigo, nombre)
VALUES
('ING-SIS', 'Ingeniería'),
('DER', 'Derecho');

-- Roles
INSERT INTO roles (nombre)
VALUES
('ESTUDIANTE'),
('TUTOR'),
('ADMIN');

-- Guardar solicitudes de recuperación de contraseña
CREATE TABLE recuperacion_contrasenas (
    id INT AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT NOT NULL,

    token_hash VARCHAR(64) NOT NULL UNIQUE,

    fecha_vencimiento DATETIME NOT NULL,

    utilizado BOOLEAN NOT NULL DEFAULT FALSE,

    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE
);

USE db_tutorias;

SELECT
    id,
    tutor_id,
    curso_id,
    fecha,
    hora_inicio,
    estado,
    cupo_actual,
    cupo_maximo
FROM disponibilidades;

SELECT NOW() AS fecha_actual_mysql;
SELECT NOW() AS hora_actual_mysql;
