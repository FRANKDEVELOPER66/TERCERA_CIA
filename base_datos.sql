-----BASE DE DATOS-----------
-- ========================================
-- BASE DE DATOS: SISTEMA DE ROTACIÓN MILITAR
-- ========================================
-- Tabla: GRADOS MILITARES
CREATE TABLE grados (
    id_grado INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    tipo ENUM('OFICIAL', 'ESPECIALISTA', 'TROPA') NOT NULL,
    orden INT NOT NULL COMMENT 'Para ordenar jerárquicamente'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Datos de ejemplo
INSERT INTO
    grados (nombre, tipo, orden)
VALUES
    ('Capitán 1ro.', 'OFICIAL', 1),
    ('Capitán 2do.', 'OFICIAL', 2),
    ('Teniente', 'OFICIAL', 3),
    ('Subteniente', 'OFICIAL', 4),
    ('Sargento Técnico', 'ESPECIALISTA', 5),
    ('Sargento Primero', 'ESPECIALISTA', 6),
    ('Sargento Segundo', 'ESPECIALISTA', 7),
    ('Cabo Esp.', 'ESPECIALISTA', 8),
    ('Soldado de Primera', 'ESPECIALISTA', 9),
    ('Soldado de Segunda', 'ESPECIALISTA', 10),
    ('Sargento 1ro.', 'TROPA', 11),
    ('Sargento 2do.', 'TROPA', 12),
    ('Cabo', 'TROPA', 13),
    ('Soldado de 1ra.', 'TROPA', 14),
    ('Soldado de 2da.', 'TROPA', 15),
    ('Soldado', 'TROPA', 16);

-- ========================================
-- Tabla: GRUPOS DE DESCANSO
CREATE TABLE grupos_descanso (
    id_grupo INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(20) NOT NULL UNIQUE COMMENT 'Ej: GRUPO_A, GRUPO_B, GRUPO_C',
    tipo ENUM('OFICIAL', 'ESPECIALISTA', 'TROPA') NOT NULL,
    color VARCHAR(7) COMMENT 'Color hexadecimal para UI'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT INTO
    grupos_descanso (nombre, tipo, color)
VALUES
    ('OFC_GRUPO_A', 'OFICIAL', '#9B59B6'),
    ('OFC_GRUPO_B', 'OFICIAL', '#E74C3C'),
    ('OFC_GRUPO_C', 'OFICIAL', '#E74C3C'),
    ('ESP_GRUPO_A', 'ESPECIALISTA', '#FF6B6B'),
    ('ESP_GRUPO_B', 'ESPECIALISTA', '#4ECDC4'),
    ('ESP_GRUPO_C', 'ESPECIALISTA', '#45B7D1'),
    ('TRP_GRUPO_A', 'TROPA', '#FFA07A'),
    ('TRP_GRUPO_B', 'TROPA', '#98D8C8'),
    ('TRP_GRUPO_C', 'TROPA', '#6C5CE7');

-- ========================================
-- Tabla: PERSONAL
CREATE TABLE personal (
    id_personal INT PRIMARY KEY AUTO_INCREMENT,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    id_grado INT NOT NULL,
    id_grupo_descanso INT,
    tipo ENUM('OFICIAL', 'ESPECIALISTA', 'TROPA') NOT NULL,
    es_encargado BOOLEAN DEFAULT FALSE COMMENT 'Si es oficial encargado de un servicio',
    activo BOOLEAN DEFAULT TRUE,
    fecha_ingreso DATE NOT NULL,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_grado) REFERENCES grados(id_grado),
    FOREIGN KEY (id_grupo_descanso) REFERENCES grupos_descanso(id_grupo),
    INDEX idx_tipo (tipo),
    INDEX idx_grupo (id_grupo_descanso),
    INDEX idx_activo (activo),
    INDEX idx_encargado (es_encargado)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT INTO
    personal (
        nombres,
        apellidos,
        id_grado,
        id_grupo_descanso,
        tipo,
        es_encargado,
        activo,
        fecha_ingreso,
        observaciones
    )
VALUES
    (
        'Francisco Daniel',
        'Rivas Garcia',
        52,
        -- 👈 id_grado REAL
        2,
        -- OFC_GRUPO_B
        'OFICIAL',
        1,
        1,
        '2023-06-10',
        'Oficial encargado del servicio de guardia'
    );

-- ========================================
-- Tabla: CALENDARIO DE DESCANSOS
CREATE TABLE calendario_descansos (
    id_calendario INT PRIMARY KEY AUTO_INCREMENT,
    id_grupo_descanso INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('PROGRAMADO', 'EN_CURSO', 'FINALIZADO') DEFAULT 'PROGRAMADO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_grupo_descanso) REFERENCES grupos_descanso(id_grupo),
    INDEX idx_fechas (fecha_inicio, fecha_fin),
    INDEX idx_estado (estado)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ========================================
-- Tabla: TIPOS DE SERVICIO
CREATE TABLE tipos_servicio (
    id_tipo_servicio INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    tipo_personal ENUM('ESPECIALISTA', 'TROPA', 'AMBOS') NOT NULL,
    cantidad_especialistas INT DEFAULT 0,
    cantidad_soldados INT DEFAULT 0,
    requiere_oficial BOOLEAN DEFAULT FALSE COMMENT 'Si requiere un oficial encargado',
    duracion_horas INT COMMENT 'Duración del servicio en horas',
    prioridad_asignacion INT DEFAULT 1 COMMENT 'Orden de asignación (1=primero)'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT INTO
    tipos_servicio (
        nombre,
        tipo_personal,
        cantidad_especialistas,
        cantidad_soldados,
        requiere_oficial,
        duracion_horas,
        prioridad_asignacion
    )
VALUES
    ('SERVICIO_NOCHE', 'TROPA', 0, 4, TRUE, 12, 1),
    ('TACTICO', 'ESPECIALISTA', 1, 0, TRUE, 24, 2),
    ('RECONOCIMIENTO', 'AMBOS', 3, 4, TRUE, 12, 3);

-- ========================================
-- Tabla: ASIGNACIONES DE SERVICIO
CREATE TABLE asignaciones_servicio (
    id_asignacion INT PRIMARY KEY AUTO_INCREMENT,
    id_personal INT NOT NULL,
    id_tipo_servicio INT NOT NULL,
    id_oficial_encargado INT COMMENT 'Oficial responsable del servicio',
    fecha_servicio DATE NOT NULL,
    hora_inicio TIME,
    hora_fin TIME,
    estado ENUM(
        'PROGRAMADO',
        'EN_CURSO',
        'COMPLETADO',
        'CANCELADO'
    ) DEFAULT 'PROGRAMADO',
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT COMMENT 'ID del usuario que creó la asignación',
    FOREIGN KEY (id_personal) REFERENCES personal(id_personal),
    FOREIGN KEY (id_tipo_servicio) REFERENCES tipos_servicio(id_tipo_servicio),
    FOREIGN KEY (id_oficial_encargado) REFERENCES personal(id_personal),
    INDEX idx_fecha (fecha_servicio),
    INDEX idx_personal_fecha (id_personal, fecha_servicio),
    INDEX idx_tipo_fecha (id_tipo_servicio, fecha_servicio),
    INDEX idx_oficial (id_oficial_encargado),
    -- Evitar que una persona tenga múltiples servicios el mismo día (excepto casos especiales)
    UNIQUE KEY unique_persona_fecha_servicio (id_personal, fecha_servicio, id_tipo_servicio)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ========================================
-- Tabla: HISTORIAL DE ROTACIONES
CREATE TABLE historial_rotaciones (
    id_historial INT PRIMARY KEY AUTO_INCREMENT,
    id_personal INT NOT NULL,
    id_tipo_servicio INT NOT NULL,
    fecha_ultimo_servicio DATE NOT NULL,
    dias_desde_ultimo INT COMMENT 'Días transcurridos desde último servicio',
    prioridad INT DEFAULT 0 COMMENT 'Para algoritmo de selección equitativa',
    FOREIGN KEY (id_personal) REFERENCES personal(id_personal),
    FOREIGN KEY (id_tipo_servicio) REFERENCES tipos_servicio(id_tipo_servicio),
    INDEX idx_personal_servicio (id_personal, id_tipo_servicio)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- ========================================
-- VISTAS ÚTILES
-- ========================================
-- Vista: Personal Disponible Hoy
CREATE VIEW v_personal_disponible_hoy AS
SELECT
    p.id_personal,
    p.nombres,
    p.apellidos,
    p.tipo,
    g.nombre as grado,
    gd.nombre as grupo_descanso,
    CASE
        WHEN cd.id_calendario IS NOT NULL THEN 'DESCANSO'
        ELSE 'DISPONIBLE'
    END as estado
FROM
    personal p
    INNER JOIN grados g ON p.id_grado = g.id_grado
    INNER JOIN grupos_descanso gd ON p.id_grupo_descanso = gd.id_grupo
    LEFT JOIN calendario_descansos cd ON cd.id_grupo_descanso = p.id_grupo_descanso
    AND CURDATE() BETWEEN cd.fecha_inicio
    AND cd.fecha_fin
WHERE
    p.activo = TRUE;

-- Vista: Servicios del Día
CREATE VIEW v_servicios_hoy AS
SELECT
    ts.nombre as servicio,
    p.nombres,
    p.apellidos,
    p.tipo,
    g.nombre as grado,
    asig.hora_inicio,
    asig.hora_fin,
    asig.estado
FROM
    asignaciones_servicio asig
    INNER JOIN personal p ON asig.id_personal = p.id_personal
    INNER JOIN grados g ON p.id_grado = g.id_grado
    INNER JOIN tipos_servicio ts ON asig.id_tipo_servicio = ts.id_tipo_servicio
WHERE
    asig.fecha_servicio = CURDATE()
ORDER BY
    ts.nombre,
    asig.hora_inicio;

-- Vista: Carga de Trabajo por Persona (últimos 30 días)
CREATE VIEW v_carga_trabajo AS
SELECT
    p.id_personal,
    p.nombres,
    p.apellidos,
    p.tipo,
    COUNT(asig.id_asignacion) as servicios_realizados,
    MAX(asig.fecha_servicio) as ultimo_servicio,
    DATEDIFF(CURDATE(), MAX(asig.fecha_servicio)) as dias_sin_servicio
FROM
    personal p
    LEFT JOIN asignaciones_servicio asig ON p.id_personal = asig.id_personal
    AND asig.fecha_servicio >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND asig.estado != 'CANCELADO'
WHERE
    p.activo = TRUE
GROUP BY
    p.id_personal,
    p.nombres,
    p.apellidos,
    p.tipo;

-- ========================================
-- PROCEDIMIENTOS ALMACENADOS
-- ========================================
DELIMITER / / -- ========================================
-- PROCEDIMIENTO PRINCIPAL: ASIGNACIÓN AUTOMÁTICA DIARIA
-- ========================================
CREATE PROCEDURE sp_asignar_servicios_automatico(IN p_fecha DATE) BEGIN DECLARE v_servicio_noche_id INT;

DECLARE v_tactico_id INT;

DECLARE v_reconocimiento_id INT;

DECLARE v_mensaje VARCHAR(500);

DECLARE v_error INT DEFAULT 0;

-- Manejo de errores
DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN
SET
    v_error = 1;

ROLLBACK;

END;

-- Obtener IDs de tipos de servicio
SELECT
    id_tipo_servicio INTO v_servicio_noche_id
FROM
    tipos_servicio
WHERE
    nombre = 'SERVICIO_NOCHE';

SELECT
    id_tipo_servicio INTO v_tactico_id
FROM
    tipos_servicio
WHERE
    nombre = 'TACTICO';

SELECT
    id_tipo_servicio INTO v_reconocimiento_id
FROM
    tipos_servicio
WHERE
    nombre = 'RECONOCIMIENTO';

START TRANSACTION;

-- 1. ASIGNAR SERVICIO DE NOCHE (4 soldados)
CALL sp_asignar_servicio_individual(p_fecha, v_servicio_noche_id);

-- 2. ASIGNAR TÁCTICO (1 especialista)
CALL sp_asignar_servicio_individual(p_fecha, v_tactico_id);

-- 3. ASIGNAR RECONOCIMIENTO (3 especialistas + 4 soldados)
CALL sp_asignar_servicio_individual(p_fecha, v_reconocimiento_id);

IF v_error = 0 THEN COMMIT;

SET
    v_mensaje = CONCAT(
        '✅ Servicios asignados exitosamente para ',
        DATE_FORMAT(p_fecha, '%d/%m/%Y')
    );

ELSE
SET
    v_mensaje = '❌ Error al asignar servicios';

END IF;

SELECT
    v_mensaje as mensaje;

END / / -- ========================================
-- PROCEDIMIENTO: ASIGNAR UN SERVICIO ESPECÍFICO
-- ========================================
CREATE PROCEDURE sp_asignar_servicio_individual(
    IN p_fecha DATE,
    IN p_tipo_servicio INT
) BEGIN DECLARE v_cantidad_esp INT;

DECLARE v_cantidad_sold INT;

DECLARE v_requiere_oficial BOOLEAN;

DECLARE v_contador INT DEFAULT 0;

DECLARE v_id_oficial INT;

DECLARE done INT DEFAULT FALSE;

-- Variables para cursor
DECLARE v_id_personal INT;

-- Cursor para personal disponible
DECLARE cur_disponibles CURSOR FOR
SELECT
    id_personal
FROM
    tmp_disponibles
ORDER BY
    prioridad DESC,
    dias_sin_servicio DESC;

DECLARE CONTINUE HANDLER FOR NOT FOUND
SET
    done = TRUE;

-- Obtener configuración del servicio
SELECT
    cantidad_especialistas,
    cantidad_soldados,
    requiere_oficial INTO v_cantidad_esp,
    v_cantidad_sold,
    v_requiere_oficial
FROM
    tipos_servicio
WHERE
    id_tipo_servicio = p_tipo_servicio;

-- Crear tabla temporal con personal disponible
DROP TEMPORARY TABLE IF EXISTS tmp_disponibles;

CREATE TEMPORARY TABLE tmp_disponibles (
    id_personal INT,
    tipo VARCHAR(20),
    dias_sin_servicio INT,
    prioridad INT
);

-- PASO 1: Asignar oficial encargado si se requiere
IF v_requiere_oficial THEN
SELECT
    id_personal INTO v_id_oficial
FROM
    personal p
WHERE
    p.tipo = 'OFICIAL'
    AND p.activo = TRUE
    AND p.es_encargado = TRUE -- No está de descanso (oficiales pueden o no tener grupo)
    AND (
        p.id_grupo_descanso IS NULL
        OR NOT EXISTS (
            SELECT
                1
            FROM
                calendario_descansos cd
            WHERE
                cd.id_grupo_descanso = p.id_grupo_descanso
                AND p_fecha BETWEEN cd.fecha_inicio
                AND cd.fecha_fin
        )
    ) -- No tiene otro servicio asignado
    AND NOT EXISTS (
        SELECT
            1
        FROM
            asignaciones_servicio asig
        WHERE
            asig.id_personal = p.id_personal
            AND asig.fecha_servicio = p_fecha
            AND asig.estado != 'CANCELADO'
    )
ORDER BY
    RAND()
LIMIT
    1;

END IF;

-- PASO 2: Llenar tabla temporal con especialistas disponibles
IF v_cantidad_esp > 0 THEN
INSERT INTO
    tmp_disponibles
SELECT
    p.id_personal,
    p.tipo,
    COALESCE(DATEDIFF(p_fecha, hr.fecha_ultimo_servicio), 999) as dias_sin_servicio,
    COALESCE(hr.prioridad, 0) as prioridad
FROM
    personal p
    LEFT JOIN historial_rotaciones hr ON hr.id_personal = p.id_personal
    AND hr.id_tipo_servicio = p_tipo_servicio
WHERE
    p.tipo = 'ESPECIALISTA'
    AND p.activo = TRUE -- No está de descanso
    AND NOT EXISTS (
        SELECT
            1
        FROM
            calendario_descansos cd
        WHERE
            cd.id_grupo_descanso = p.id_grupo_descanso
            AND p_fecha BETWEEN cd.fecha_inicio
            AND cd.fecha_fin
    ) -- No tiene servicio ese día
    AND NOT EXISTS (
        SELECT
            1
        FROM
            asignaciones_servicio asig
        WHERE
            asig.id_personal = p.id_personal
            AND asig.fecha_servicio = p_fecha
            AND asig.estado != 'CANCELADO'
    )
ORDER BY
    dias_sin_servicio DESC,
    prioridad DESC
LIMIT
    v_cantidad_esp;

END IF;

-- PASO 3: Llenar tabla temporal con soldados disponibles
IF v_cantidad_sold > 0 THEN
INSERT INTO
    tmp_disponibles
SELECT
    p.id_personal,
    p.tipo,
    COALESCE(DATEDIFF(p_fecha, hr.fecha_ultimo_servicio), 999) as dias_sin_servicio,
    COALESCE(hr.prioridad, 0) as prioridad
FROM
    personal p
    LEFT JOIN historial_rotaciones hr ON hr.id_personal = p.id_personal
    AND hr.id_tipo_servicio = p_tipo_servicio
WHERE
    p.tipo = 'TROPA'
    AND p.activo = TRUE -- No está de descanso
    AND NOT EXISTS (
        SELECT
            1
        FROM
            calendario_descansos cd
        WHERE
            cd.id_grupo_descanso = p.id_grupo_descanso
            AND p_fecha BETWEEN cd.fecha_inicio
            AND cd.fecha_fin
    ) -- No tiene servicio ese día
    AND NOT EXISTS (
        SELECT
            1
        FROM
            asignaciones_servicio asig
        WHERE
            asig.id_personal = p.id_personal
            AND asig.fecha_servicio = p_fecha
            AND asig.estado != 'CANCELADO'
    ) -- Si es RECONOCIMIENTO, no puede estar en servicio de noche
    AND (
        p_tipo_servicio != (
            SELECT
                id_tipo_servicio
            FROM
                tipos_servicio
            WHERE
                nombre = 'RECONOCIMIENTO'
        )
        OR NOT EXISTS (
            SELECT
                1
            FROM
                asignaciones_servicio asig
                INNER JOIN tipos_servicio ts ON asig.id_tipo_servicio = ts.id_tipo_servicio
            WHERE
                asig.id_personal = p.id_personal
                AND asig.fecha_servicio = p_fecha
                AND ts.nombre = 'SERVICIO_NOCHE'
                AND asig.estado != 'CANCELADO'
        )
    )
ORDER BY
    dias_sin_servicio DESC,
    prioridad DESC
LIMIT
    v_cantidad_sold;

END IF;

-- PASO 4: Insertar asignaciones
OPEN cur_disponibles;

read_loop: LOOP FETCH cur_disponibles INTO v_id_personal;

IF done THEN LEAVE read_loop;

END IF;

INSERT INTO
    asignaciones_servicio (
        id_personal,
        id_tipo_servicio,
        id_oficial_encargado,
        fecha_servicio,
        estado
    )
VALUES
    (
        v_id_personal,
        p_tipo_servicio,
        v_id_oficial,
        p_fecha,
        'PROGRAMADO'
    );

SET
    v_contador = v_contador + 1;

END LOOP;

CLOSE cur_disponibles;

DROP TEMPORARY TABLE IF EXISTS tmp_disponibles;

END / / -- Procedimiento: Obtener personal disponible para un tipo de servicio en una fecha
CREATE PROCEDURE sp_personal_disponible_servicio(
    IN p_fecha DATE,
    IN p_tipo_servicio INT
) BEGIN
SELECT
    p.id_personal,
    CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
    p.tipo,
    g.nombre as grado,
    COALESCE(hr.dias_desde_ultimo, 999) as dias_sin_servicio,
    COALESCE(hr.prioridad, 0) as prioridad
FROM
    personal p
    INNER JOIN grados g ON p.id_grado = g.id_grado
    LEFT JOIN historial_rotaciones hr ON hr.id_personal = p.id_personal
    AND hr.id_tipo_servicio = p_tipo_servicio
WHERE
    p.activo = TRUE -- No está de descanso
    AND NOT EXISTS (
        SELECT
            1
        FROM
            calendario_descansos cd
            INNER JOIN grupos_descanso gd ON cd.id_grupo_descanso = gd.id_grupo
        WHERE
            cd.id_grupo_descanso = p.id_grupo_descanso
            AND p_fecha BETWEEN cd.fecha_inicio
            AND cd.fecha_fin
    ) -- No tiene servicio ese día
    AND NOT EXISTS (
        SELECT
            1
        FROM
            asignaciones_servicio asig
        WHERE
            asig.id_personal = p.id_personal
            AND asig.fecha_servicio = p_fecha
            AND asig.estado != 'CANCELADO'
    ) -- Si es reconocimiento, no puede estar en servicio de noche
    AND (
        p_tipo_servicio != (
            SELECT
                id_tipo_servicio
            FROM
                tipos_servicio
            WHERE
                nombre = 'RECONOCIMIENTO'
        )
        OR NOT EXISTS (
            SELECT
                1
            FROM
                asignaciones_servicio asig
                INNER JOIN tipos_servicio ts ON asig.id_tipo_servicio = ts.id_tipo_servicio
            WHERE
                asig.id_personal = p.id_personal
                AND asig.fecha_servicio = p_fecha
                AND ts.nombre = 'SERVICIO_NOCHE'
                AND asig.estado != 'CANCELADO'
        )
    )
ORDER BY
    dias_sin_servicio DESC,
    prioridad DESC;

END / / DELIMITER;

-- ========================================
-- TRIGGERS
-- ========================================
DELIMITER / / -- Trigger: Actualizar historial después de asignar servicio
CREATE TRIGGER tr_actualizar_historial_after_insert
AFTER
INSERT
    ON asignaciones_servicio FOR EACH ROW BEGIN
INSERT INTO
    historial_rotaciones (
        id_personal,
        id_tipo_servicio,
        fecha_ultimo_servicio,
        dias_desde_ultimo,
        prioridad
    )
VALUES
    (
        NEW.id_personal,
        NEW.id_tipo_servicio,
        NEW.fecha_servicio,
        0,
        0
    ) ON DUPLICATE KEY
UPDATE
    fecha_ultimo_servicio = NEW.fecha_servicio,
    dias_desde_ultimo = 0;

END / / DELIMITER;

-- ========================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ========================================
CREATE INDEX idx_personal_activo_tipo ON personal(activo, tipo);

CREATE INDEX idx_asignacion_fecha_estado ON asignaciones_servicio(fecha_servicio, estado);

CREATE INDEX idx_calendario_grupo_fechas ON calendario_descansos(id_grupo_descanso, fecha_inicio, fecha_fin);

CREATE TABLE `asignaciones_servicio` (
  `id_asignacion` int NOT NULL AUTO_INCREMENT,
  `id_personal` int NOT NULL,
  `id_tipo_servicio` int NOT NULL,
  `id_oficial_encargado` int DEFAULT NULL COMMENT 'Oficial responsable del servicio',
  `fecha_servicio` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `estado` enum('PROGRAMADO','EN_COMISION','REEMPLAZADO','CUMPLIDO','EN_CURSO','COMPLETADO','CANCELADO') DEFAULT 'PROGRAMADO',
  `id_comision` int DEFAULT NULL,
  `observaciones_comision` text,
  `estado_comision` enum('PROGRAMADO','EN_COMISION','REEMPLAZADO','CUMPLIDO') DEFAULT 'PROGRAMADO',
  `observaciones` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id_asignacion`),
  UNIQUE KEY `unique_persona_fecha_servicio` (`id_personal`,`fecha_servicio`,`id_tipo_servicio`),
  KEY `idx_fecha` (`fecha_servicio`),
  KEY `idx_personal_fecha` (`id_personal`,`fecha_servicio`),
  KEY `idx_tipo_fecha` (`id_tipo_servicio`,`fecha_servicio`),
  KEY `idx_oficial` (`id_oficial_encargado`),
  KEY `idx_estado_comision` (`estado_comision`),
  KEY `idx_comision` (`id_comision`),
  CONSTRAINT `asignaciones_servicio_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `bhr_personal` (`id_personal`),
  CONSTRAINT `asignaciones_servicio_ibfk_2` FOREIGN KEY (`id_tipo_servicio`) REFERENCES `tipos_servicio` (`id_tipo_servicio`),
  CONSTRAINT `asignaciones_servicio_ibfk_3` FOREIGN KEY (`id_oficial_encargado`) REFERENCES `bhr_personal` (`id_personal`),
  CONSTRAINT `fk_asignacion_comision` FOREIGN KEY (`id_comision`) REFERENCES `comisiones_oficiales` (`id_comision`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6948 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `bhr_grados` (
  `id_grado` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `tipo` enum('OFICIAL','ESPECIALISTA','TROPA') NOT NULL,
  `orden` int NOT NULL COMMENT 'Para ordenar jerárquicamente',
  PRIMARY KEY (`id_grado`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `bhr_personal` (
  `id_personal` int NOT NULL AUTO_INCREMENT,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `id_grado` int NOT NULL,
  `id_grupo_descanso` int DEFAULT NULL,
  `tipo` enum('OFICIAL','ESPECIALISTA','TROPA') NOT NULL,
  `es_encargado` tinyint(1) DEFAULT '0' COMMENT 'Si es oficial encargado de un servicio',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_ingreso` date NOT NULL,
  `observaciones` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_personal`),
  KEY `id_grado` (`id_grado`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_grupo` (`id_grupo_descanso`),
  KEY `idx_activo` (`activo`),
  KEY `idx_encargado` (`es_encargado`),
  CONSTRAINT `bhr_personal_ibfk_1` FOREIGN KEY (`id_grado`) REFERENCES `bhr_grados` (`id_grado`),
  CONSTRAINT `bhr_personal_ibfk_2` FOREIGN KEY (`id_grupo_descanso`) REFERENCES `grupos_descanso` (`id_grupo`)
) ENGINE=InnoDB AUTO_INCREMENT=242 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `calendario_descansos` (
  `id_calendario` int NOT NULL AUTO_INCREMENT,
  `id_grupo_descanso` int NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `estado` enum('PROGRAMADO','EN_CURSO','FINALIZADO') DEFAULT 'PROGRAMADO',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_calendario`),
  KEY `id_grupo_descanso` (`id_grupo_descanso`),
  KEY `idx_fechas` (`fecha_inicio`,`fecha_fin`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `calendario_descansos_ibfk_1` FOREIGN KEY (`id_grupo_descanso`) REFERENCES `grupos_descanso` (`id_grupo`)
) ENGINE=InnoDB AUTO_INCREMENT=214 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `comisiones_oficiales` (
  `id_comision` int NOT NULL AUTO_INCREMENT,
  `id_personal` int NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `destino` varchar(100) DEFAULT 'Ciudad Capital',
  `numero_oficio` varchar(50) NOT NULL,
  `motivo` text,
  `dias_totales` int GENERATED ALWAYS AS (((to_days(`fecha_fin`) - to_days(`fecha_inicio`)) + 1)) STORED,
  `estado` enum('ACTIVA','FINALIZADA','CANCELADA') DEFAULT 'ACTIVA',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_comision`),
  UNIQUE KEY `numero_oficio` (`numero_oficio`),
  KEY `idx_comision_personal` (`id_personal`),
  KEY `idx_comision_fechas` (`fecha_inicio`,`fecha_fin`),
  KEY `idx_comision_estado` (`estado`),
  CONSTRAINT `comisiones_oficiales_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `bhr_personal` (`id_personal`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `compensaciones_historial` (
  `id_compensacion` int NOT NULL AUTO_INCREMENT,
  `id_personal` int NOT NULL,
  `motivo_compensacion` enum('REEMPLAZO','COMISION_LARGA','CARGA_EXCESIVA') NOT NULL,
  `id_referencia` int DEFAULT NULL COMMENT 'ID de reemplazo o comisión',
  `fecha_otorgada` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_aplicada` timestamp NULL DEFAULT NULL,
  `estado` enum('PENDIENTE','APLICADA','EXPIRADA') DEFAULT 'PENDIENTE',
  `id_asignacion_compensada` int DEFAULT NULL COMMENT 'Asignación donde se aplicó la compensación',
  `notas` text,
  PRIMARY KEY (`id_compensacion`),
  KEY `id_asignacion_compensada` (`id_asignacion_compensada`),
  KEY `idx_compensacion_personal` (`id_personal`),
  KEY `idx_compensacion_estado` (`estado`),
  CONSTRAINT `compensaciones_historial_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `bhr_personal` (`id_personal`) ON DELETE CASCADE,
  CONSTRAINT `compensaciones_historial_ibfk_2` FOREIGN KEY (`id_asignacion_compensada`) REFERENCES `asignaciones_servicio` (`id_asignacion`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `exclusiones_servicio` (
  `id_exclusion` int NOT NULL AUTO_INCREMENT,
  `id_personal` int NOT NULL,
  `id_tipo_servicio` int NOT NULL,
  `fecha_exclusion` date NOT NULL,
  `motivo` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_exclusion`),
  KEY `idx_personal_fecha` (`id_personal`,`fecha_exclusion`),
  KEY `idx_tipo_fecha` (`id_tipo_servicio`,`fecha_exclusion`),
  CONSTRAINT `exclusiones_servicio_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `bhr_personal` (`id_personal`),
  CONSTRAINT `exclusiones_servicio_ibfk_2` FOREIGN KEY (`id_tipo_servicio`) REFERENCES `tipos_servicio` (`id_tipo_servicio`)
) ENGINE=InnoDB AUTO_INCREMENT=2108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `grupos_descanso` (
  `id_grupo` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(20) NOT NULL COMMENT 'Ej: GRUPO_A, GRUPO_B, GRUPO_C',
  `tipo` enum('OFICIAL','ESPECIALISTA','TROPA') NOT NULL,
  `color` varchar(7) DEFAULT NULL COMMENT 'Color hexadecimal para UI',
  PRIMARY KEY (`id_grupo`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `historial_rotaciones` (
  `id_historial` int NOT NULL AUTO_INCREMENT,
  `id_personal` int NOT NULL,
  `id_tipo_servicio` int NOT NULL,
  `fecha_ultimo_servicio` date NOT NULL,
  `dias_desde_ultimo` int DEFAULT NULL COMMENT 'Días transcurridos desde último servicio',
  `prioridad` int DEFAULT '0' COMMENT 'Para algoritmo de selección equitativa',
  `servicios_como_reemplazo` int DEFAULT '0' COMMENT 'Veces que ha sido reemplazo',
  `compensacion_pendiente` tinyint(1) DEFAULT '0' COMMENT 'Tiene prioridad por haber reemplazado',
  `dias_en_comision` int DEFAULT '0' COMMENT 'Días acumulados en comisión',
  PRIMARY KEY (`id_historial`),
  KEY `id_tipo_servicio` (`id_tipo_servicio`),
  KEY `idx_personal_servicio` (`id_personal`,`id_tipo_servicio`),
  CONSTRAINT `historial_rotaciones_ibfk_1` FOREIGN KEY (`id_personal`) REFERENCES `bhr_personal` (`id_personal`),
  CONSTRAINT `historial_rotaciones_ibfk_2` FOREIGN KEY (`id_tipo_servicio`) REFERENCES `tipos_servicio` (`id_tipo_servicio`)
) ENGINE=InnoDB AUTO_INCREMENT=2014 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `reemplazos_servicio` (
  `id_reemplazo` int NOT NULL AUTO_INCREMENT,
  `id_asignacion_original` int NOT NULL,
  `id_personal_original` int NOT NULL,
  `id_personal_reemplazo` int NOT NULL,
  `id_comision` int NOT NULL,
  `fecha_servicio` date NOT NULL,
  `id_tipo_servicio` int NOT NULL,
  `nombre_servicio` varchar(100) NOT NULL,
  `servicios_acumulados_reemplazo` int DEFAULT '0' COMMENT 'Servicios que tenía antes del reemplazo',
  `veces_reemplazo_ciclo` int DEFAULT '0' COMMENT 'Veces que ha sido reemplazo en este ciclo',
  `compensacion_otorgada` tinyint(1) DEFAULT '0' COMMENT 'Si se le dio prioridad después',
  `fecha_reemplazo` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `realizado_por` int NOT NULL,
  PRIMARY KEY (`id_reemplazo`),
  KEY `id_asignacion_original` (`id_asignacion_original`),
  KEY `id_tipo_servicio` (`id_tipo_servicio`),
  KEY `idx_reemplazo_fecha` (`fecha_servicio`),
  KEY `idx_reemplazo_original` (`id_personal_original`),
  KEY `idx_reemplazo_reemplazo` (`id_personal_reemplazo`),
  KEY `idx_reemplazo_comision` (`id_comision`),
  CONSTRAINT `reemplazos_servicio_ibfk_1` FOREIGN KEY (`id_asignacion_original`) REFERENCES `asignaciones_servicio` (`id_asignacion`) ON DELETE CASCADE,
  CONSTRAINT `reemplazos_servicio_ibfk_2` FOREIGN KEY (`id_personal_original`) REFERENCES `bhr_personal` (`id_personal`) ON DELETE RESTRICT,
  CONSTRAINT `reemplazos_servicio_ibfk_3` FOREIGN KEY (`id_personal_reemplazo`) REFERENCES `bhr_personal` (`id_personal`) ON DELETE RESTRICT,
  CONSTRAINT `reemplazos_servicio_ibfk_4` FOREIGN KEY (`id_comision`) REFERENCES `comisiones_oficiales` (`id_comision`) ON DELETE CASCADE,
  CONSTRAINT `reemplazos_servicio_ibfk_5` FOREIGN KEY (`id_tipo_servicio`) REFERENCES `tipos_servicio` (`id_tipo_servicio`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

tipos_servicioCREATE TABLE `tipos_servicio` (
  `id_tipo_servicio` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text,
  `tipo_personal` enum('ESPECIALISTA','TROPA','AMBOS') NOT NULL,
  `cantidad_especialistas` int DEFAULT '0',
  `cantidad_soldados` int DEFAULT '0',
  `requiere_oficial` tinyint(1) DEFAULT '0' COMMENT 'Si requiere un oficial encargado',
  `duracion_horas` int DEFAULT NULL COMMENT 'Duración del servicio en horas',
  `prioridad_asignacion` int DEFAULT '1' COMMENT 'Orden de asignación (1=primero)',
  PRIMARY KEY (`id_tipo_servicio`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ========================================
-- 6. VISTA PARA REPORTES DE COMISIONES
-- ========================================
CREATE OR REPLACE VIEW v_comisiones_activas AS
SELECT 
    co.id_comision,
    co.numero_oficio,
    co.fecha_inicio,
    co.fecha_fin,
    co.dias_totales,
    co.destino,
    co.estado,
    CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
    p.tipo as tipo_personal,
    g.nombre as grado,
    COUNT(DISTINCT a.id_asignacion) as servicios_afectados,
    COUNT(DISTINCT r.id_reemplazo) as reemplazos_realizados,
    CONCAT(u.nombre, ' ', u.apellido) as registrado_por,
    co.created_at as fecha_registro
FROM comisiones_oficiales co
INNER JOIN bhr_personal p ON co.id_personal = p.id_personal
INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
LEFT JOIN asignaciones_servicio a ON co.id_comision = a.id_comision
LEFT JOIN reemplazos_servicio r ON co.id_comision = r.id_comision
LEFT JOIN usuarios u ON co.created_by = u.id_usuario
WHERE co.estado = 'ACTIVA'
GROUP BY co.id_comision;

-- ========================================
-- 7. PROCEDIMIENTO: Actualizar días en comisión
-- ========================================
DELIMITER $$

CREATE PROCEDURE actualizar_dias_comision()
BEGIN
    -- Actualizar días en comisión en el historial
    UPDATE historial_rotaciones hr
    INNER JOIN (
        SELECT 
            id_personal,
            SUM(DATEDIFF(
                LEAST(fecha_fin, CURDATE()),
                fecha_inicio
            ) + 1) as total_dias
        FROM comisiones_oficiales
        WHERE estado = 'ACTIVA'
        AND fecha_inicio <= CURDATE()
        GROUP BY id_personal
    ) dias_com ON hr.id_personal = dias_com.id_personal
    SET hr.dias_en_comision = dias_com.total_dias;
END$$

DELIMITER ;

-- ========================================
-- 8. TRIGGER: Finalizar comisión automáticamente
-- ========================================
DELIMITER $$

CREATE TRIGGER trg_finalizar_comision_auto
BEFORE UPDATE ON comisiones_oficiales
FOR EACH ROW
BEGIN
    IF NEW.estado = 'ACTIVA' AND NEW.fecha_fin < CURDATE() THEN
        SET NEW.estado = 'FINALIZADA';
    END IF;
END$$

DELIMITER ;

-- ========================================
-- 9. DATOS INICIALES / CONFIGURACIÓN
-- ========================================

-- Configurar valores por defecto en asignaciones existentes
UPDATE asignaciones_servicio 
SET estado = 'CUMPLIDO' 
WHERE fecha_servicio < CURDATE() 
AND estado IS NULL;

UPDATE asignaciones_servicio 
SET estado = 'PROGRAMADO' 
WHERE fecha_servicio >= CURDATE() 
AND estado IS NULL;

-- ========================================
-- 10. VERIFICACIÓN DE LA MIGRACIÓN
-- ========================================
SELECT 
    'comisiones_oficiales' as tabla,
    COUNT(*) as registros
FROM comisiones_oficiales
UNION ALL
SELECT 
    'reemplazos_servicio' as tabla,
    COUNT(*) as registros
FROM reemplazos_servicio
UNION ALL
SELECT
    'compensaciones_historial' as tabla,
    COUNT(*) as registros
FROM compensaciones_historial;

-- ========================================
-- FIN DE MIGRACIÓN
-- ========================================