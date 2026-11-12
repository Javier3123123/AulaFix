
-- ==========================================
-- ESQUEMA.SQL
-- Sistema de gestión de incidencias
-- ==========================================

CREATE DATABASE IF NOT EXISTS gestion_incidencias
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE gestion_incidencias;

-- ==========================================
-- TABLA: usuarios
-- ==========================================

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    clave_hash VARCHAR(255) NOT NULL,
    rol ENUM('alumno', 'profesor') NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- TABLA: equipos
-- ==========================================

CREATE TABLE equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    descripcion TEXT
);

-- ==========================================
-- TABLA: incidencias
-- ==========================================

CREATE TABLE incidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    usuario_id INT NOT NULL,

    categoria ENUM(
        'hardware',
        'software',
        'red',
        'otro'
    ) NOT NULL,

    prioridad ENUM(
        'baja',
        'media',
        'alta'
    ) NOT NULL,

    descripcion TEXT NOT NULL,

    estado ENUM(
        'abierta',
        'en_curso',
        'resuelta'
    ) NOT NULL DEFAULT 'abierta',

    creada_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    actualizada_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_incidencias_equipos
        FOREIGN KEY (equipo_id)
        REFERENCES equipos(id),

    CONSTRAINT fk_incidencias_usuarios
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
);

-- ==========================================
-- TABLA: historial
-- ==========================================

CREATE TABLE historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidencia_id INT NOT NULL,
    usuario_id INT NOT NULL,

    estado_anterior ENUM(
        'abierta',
        'en_curso',
        'resuelta'
    ),

    estado_nuevo ENUM(
        'abierta',
        'en_curso',
        'resuelta'
    ) NOT NULL,

    comentario TEXT,

    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_historial_incidencias
        FOREIGN KEY (incidencia_id)
        REFERENCES incidencias(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_historial_usuarios
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
);

-- ==========================================
-- FIN DEL ESQUEMA
-- ==========================================
