-- =====================================================
-- RECREAR SEMANA COMPLETA DEL 26 ENE AL 01 FEB 2026
-- SCRIPT FINAL - ORDEN CORRECTO SEGÚN PDFs ORIGINALES
-- =====================================================

-- Limpiar semana completa

DELETE FROM asignaciones_servicio 
WHERE fecha_servicio BETWEEN '2026-01-26' AND '2026-02-01';


-- =====================================================
-- SERVICIO DE SEMANA (7 días)
-- =====================================================
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(208, 7, '2026-01-26', '00:00:00', '23:59:59', 'PROGRAMADO', 1); -- Jeremy Enrique Medrano Nolberto

-- =====================================================
-- LUNES 26 DE ENERO 2026
-- =====================================================

-- TÁCTICO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(206, 2, '2026-01-26', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Vivian Nineth Orantes Cruz

-- TÁCTICO TROPA
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(193, 8, '2026-01-26', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Félix Gerardo Pascual Contreras

-- RECONOCIMIENTO (6 personas)
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(203, 3, '2026-01-26', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Eduardo Daniel Mateo Cruz
(183, 3, '2026-01-26', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Darly Beatriz Asencio Zuñiga
(212, 3, '2026-01-26', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Marvin Estuardo Coc Chun
(210, 3, '2026-01-26', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Axel David López Ramírez
(191, 3, '2026-01-26', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Pablo Lázaro Tiul Bá
(232, 3, '2026-01-26', '06:00:00', '18:00:00', 'PROGRAMADO', 1); -- Nolvin de Jesús Gallardo Felipe

-- SERVICIO NOCTURNO (3 turnos) - ORDEN SEGÚN PDF ORIGINAL
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(189, 6, '2026-01-26', '21:00:00', '23:30:00', 'PROGRAMADO', 1), -- PRIMER TURNO - Salvador Tipol Quej
(241, 6, '2026-01-26', '23:30:00', '02:00:00', 'PROGRAMADO', 1), -- SEGUNDO TURNO - Miguel Angel Sequen Marroquín
(237, 6, '2026-01-26', '02:00:00', '04:45:00', 'PROGRAMADO', 1); -- TERCER TURNO - Arnoldo Anselmo Ical Guzmán

-- BANDERÍN
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(210, 4, '2026-01-26', '06:00:00', '20:00:00', 'PROGRAMADO', 1); -- Axel David López Ramírez

-- CUARTELERO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(190, 5, '2026-01-26', '08:00:00', '07:45:00', 'PROGRAMADO', 1); -- Dimas Díaz Mauricio

-- =====================================================
-- MARTES 27 DE ENERO 2026
-- =====================================================

-- TÁCTICO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(184, 2, '2026-01-27', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Alexander Ismael García Nufio

-- TÁCTICO TROPA
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(233, 8, '2026-01-27', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Mario Roberto Bol Chub

-- RECONOCIMIENTO (6 personas)
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(202, 3, '2026-01-27', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Denilson Esquivel Trigueros
(223, 3, '2026-01-27', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Brenda América Catalán Hernández
(234, 3, '2026-01-27', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Elmer Alexander Maquin Choc
(213, 3, '2026-01-27', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Henry Ariel López Gregorio
(192, 3, '2026-01-27', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Mynor Geovanni Tun Choc
(231, 3, '2026-01-27', '06:00:00', '18:00:00', 'PROGRAMADO', 1); -- Nelson Anibal Choc Chub

-- SERVICIO NOCTURNO (3 turnos) - ORDEN SEGÚN PDF ORIGINAL
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(211, 6, '2026-01-27', '21:00:00', '23:30:00', 'PROGRAMADO', 1), -- PRIMER TURNO - Luis Fernando Xol Ché
(230, 6, '2026-01-27', '23:30:00', '02:00:00', 'PROGRAMADO', 1), -- SEGUNDO TURNO - Waldemar Sagui Cú
(192, 6, '2026-01-27', '02:00:00', '04:45:00', 'PROGRAMADO', 1); -- TERCER TURNO - Mynor Geovanni Tun Choc

-- BANDERÍN
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(212, 4, '2026-01-27', '06:00:00', '20:00:00', 'PROGRAMADO', 1); -- Marvin Estuardo Coc Chun

-- CUARTELERO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(193, 5, '2026-01-27', '08:00:00', '07:45:00', 'PROGRAMADO', 1); -- Félix Gerardo Pascual Contreras

-- =====================================================
-- MIÉRCOLES 28 DE ENERO 2026
-- =====================================================

-- TÁCTICO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(223, 2, '2026-01-28', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Brenda América Catalán Hernández

-- TÁCTICO TROPA
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(190, 8, '2026-01-28', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Dimas Díaz Mauricio

-- RECONOCIMIENTO (6 personas)
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(207, 3, '2026-01-28', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Henry Alfredo Morente Morente
(187, 3, '2026-01-28', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Byron Rodrigo Juarez Choc
(235, 3, '2026-01-28', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Héctor Wlademar Cac Tox
(219, 3, '2026-01-28', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Wilgen Humberto Caal Alvarez
(195, 3, '2026-01-28', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Cristofer Yahir García Nufio
(240, 3, '2026-01-28', '06:00:00', '18:00:00', 'PROGRAMADO', 1); -- Guillermo Otoniel Santiago Nájera

-- SERVICIO NOCTURNO (3 turnos) - ORDEN SEGÚN PDF ORIGINAL
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(214, 6, '2026-01-28', '21:00:00', '23:30:00', 'PROGRAMADO', 1), -- PRIMER TURNO - Jaime Danilo Chub Ical
(234, 6, '2026-01-28', '23:30:00', '02:00:00', 'PROGRAMADO', 1), -- SEGUNDO TURNO - Elmer Alexander Maquin Choc
(190, 6, '2026-01-28', '02:00:00', '04:45:00', 'PROGRAMADO', 1); -- TERCER TURNO - Dimas Díaz Mauricio

-- BANDERÍN
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(191, 4, '2026-01-28', '06:00:00', '20:00:00', 'PROGRAMADO', 1); -- Pablo Lázaro Tiul Bá

-- CUARTELERO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(232, 5, '2026-01-28', '08:00:00', '07:45:00', 'PROGRAMADO', 1); -- Nolvin de Jesús Gallardo Felipe

-- =====================================================
-- JUEVES 29 DE ENERO 2026
-- =====================================================

-- TÁCTICO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(188, 2, '2026-01-29', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Jonathan Rodolfo Gabriel Pérez

-- TÁCTICO TROPA
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(192, 8, '2026-01-29', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Mynor Geovanni Tun Choc

-- RECONOCIMIENTO (6 personas)
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(204, 3, '2026-01-29', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Angel Josue Perez Aguirre
(224, 3, '2026-01-29', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Cristian Otoniel Paz
(196, 3, '2026-01-29', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Hezrai Jeroham Guzmán Gonzalez
(215, 3, '2026-01-29', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Rodolfo Hiqui Xol
(236, 3, '2026-01-29', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Armando Sub Ico
(194, 3, '2026-01-29', '06:00:00', '18:00:00', 'PROGRAMADO', 1); -- Denis Abrahama Aldana Pineda

-- SERVICIO NOCTURNO (3 turnos) - ORDEN SEGÚN PDF ORIGINAL
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(231, 6, '2026-01-29', '21:00:00', '23:30:00', 'PROGRAMADO', 1), -- PRIMER TURNO - Nelson Anibal Choc Chub
(195, 6, '2026-01-29', '23:30:00', '02:00:00', 'PROGRAMADO', 1), -- SEGUNDO TURNO - Cristofer Yahir García Nufio
(236, 6, '2026-01-29', '02:00:00', '04:45:00', 'PROGRAMADO', 1); -- TERCER TURNO - Armando Sub Ico

-- BANDERÍN
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(193, 4, '2026-01-29', '06:00:00', '20:00:00', 'PROGRAMADO', 1); -- Félix Gerardo Pascual Contreras

-- CUARTELERO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(213, 5, '2026-01-29', '08:00:00', '07:45:00', 'PROGRAMADO', 1); -- Henry Ariel López Gregorio

-- =====================================================
-- VIERNES 30 DE ENERO 2026
-- =====================================================

-- TÁCTICO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(207, 2, '2026-01-30', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Henry Alfredo Morente Morente

-- TÁCTICO TROPA
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(211, 8, '2026-01-30', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Luis Fernando Xol Ché

-- RECONOCIMIENTO (6 personas)
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(227, 3, '2026-01-30', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Oscar Ico Coc
(226, 3, '2026-01-30', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Ivonne Anayté Manzanero Cohuoj
(230, 3, '2026-01-30', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Waldemar Sagui Cú
(189, 3, '2026-01-30', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Salvador Tipol Quej
(233, 3, '2026-01-30', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Mario Roberto Bol Chub
(237, 3, '2026-01-30', '06:00:00', '18:00:00', 'PROGRAMADO', 1); -- Arnoldo Anselmo Ical Guzmán

-- SERVICIO NOCTURNO (3 turnos) - ORDEN SEGÚN PDF ORIGINAL
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(194, 6, '2026-01-30', '21:00:00', '23:30:00', 'PROGRAMADO', 1), -- PRIMER TURNO - Denis Abrahama Aldana Pineda
(240, 6, '2026-01-30', '23:30:00', '02:00:00', 'PROGRAMADO', 1), -- SEGUNDO TURNO - Guillermo Otoniel Santiago Nájera
(232, 6, '2026-01-30', '02:00:00', '04:45:00', 'PROGRAMADO', 1); -- TERCER TURNO - Nolvin de Jesús Gallardo Felipe

-- BANDERÍN
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(232, 4, '2026-01-30', '06:00:00', '20:00:00', 'PROGRAMADO', 1); -- Nolvin de Jesús Gallardo Felipe

-- CUARTELERO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(235, 5, '2026-01-30', '08:00:00', '07:45:00', 'PROGRAMADO', 1); -- Héctor Wlademar Cac Tox

-- =====================================================
-- SÁBADO 31 DE ENERO 2026
-- =====================================================

-- TÁCTICO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(183, 2, '2026-01-31', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Darly Beatriz Asencio Zuñiga

-- TÁCTICO TROPA
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(210, 8, '2026-01-31', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Axel David López Ramírez

-- RECONOCIMIENTO (6 personas)
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(186, 3, '2026-01-31', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Gerson Danilo Ramos Lemus
(185, 3, '2026-01-31', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Ligni Maybeli Ramos Obando
(196, 3, '2026-01-31', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Hezrai Jeroham Guzmán Gonzalez
(215, 3, '2026-01-31', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Rodolfo Hiqui Xol
(241, 3, '2026-01-31', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Miguel Angel Sequen Marroquín
(214, 3, '2026-01-31', '06:00:00', '18:00:00', 'PROGRAMADO', 1); -- Jaime Danilo Chub Ical

-- SERVICIO NOCTURNO (3 turnos) - ORDEN SEGÚN PDF ORIGINAL
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(235, 6, '2026-01-31', '21:00:00', '23:30:00', 'PROGRAMADO', 1), -- PRIMER TURNO - Héctor Wlademar Cac Tox
(219, 6, '2026-01-31', '23:30:00', '02:00:00', 'PROGRAMADO', 1), -- SEGUNDO TURNO - Wilgen Humberto Caal Alvarez
(213, 6, '2026-01-31', '02:00:00', '04:45:00', 'PROGRAMADO', 1); -- TERCER TURNO - Henry Ariel López Gregorio

-- BANDERÍN
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(211, 4, '2026-01-31', '06:00:00', '20:00:00', 'PROGRAMADO', 1); -- Luis Fernando Xol Ché

-- CUARTELERO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(192, 5, '2026-01-31', '08:00:00', '07:45:00', 'PROGRAMADO', 1); -- Mynor Geovanni Tun Choc

-- =====================================================
-- DOMINGO 01 DE FEBRERO 2026
-- =====================================================

-- TÁCTICO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(203, 2, '2026-02-01', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Eduardo Daniel Mateo Cruz

-- TÁCTICO TROPA
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(213, 8, '2026-02-01', '21:00:00', '20:45:00', 'PROGRAMADO', 1); -- Henry Ariel López Gregorio

-- RECONOCIMIENTO (6 personas)
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(206, 3, '2026-02-01', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Vivian Nineth Orantes Cruz
(205, 3, '2026-02-01', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Raymundo Ical Mo
(212, 3, '2026-02-01', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Marvin Estuardo Coc Chun
(191, 3, '2026-02-01', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Pablo Lázaro Tiul Bá
(231, 3, '2026-02-01', '06:00:00', '18:00:00', 'PROGRAMADO', 1), -- Nelson Anibal Choc Chub
(234, 3, '2026-02-01', '06:00:00', '18:00:00', 'PROGRAMADO', 1); -- Elmer Alexander Maquin Choc

-- SERVICIO NOCTURNO (3 turnos) - ORDEN SEGÚN PDF ORIGINAL
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(193, 6, '2026-02-01', '21:00:00', '23:30:00', 'PROGRAMADO', 1), -- PRIMER TURNO - Félix Gerardo Pascual Contreras
(196, 6, '2026-02-01', '23:30:00', '02:00:00', 'PROGRAMADO', 1), -- SEGUNDO TURNO - Hezrai Jeroham Guzmán Gonzalez
(215, 6, '2026-02-01', '02:00:00', '04:45:00', 'PROGRAMADO', 1); -- TERCER TURNO - Rodolfo Hiqui Xol

-- BANDERÍN
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(233, 4, '2026-02-01', '06:00:00', '20:00:00', 'PROGRAMADO', 1); -- Mario Roberto Bol Chub

-- CUARTELERO
INSERT INTO asignaciones_servicio (id_personal, id_tipo_servicio, fecha_servicio, hora_inicio, hora_fin, estado, created_by) VALUES
(230, 5, '2026-02-01', '08:00:00', '07:45:00', 'PROGRAMADO', 1); -- Waldemar Sagui Cú

-- =====================================================
-- VERIFICACIÓN FINAL
-- =====================================================
SELECT COUNT(*) as total_insertado 
FROM asignaciones_servicio 
WHERE fecha_servicio BETWEEN '2026-01-26' AND '2026-02-01';

-- Ver resumen por día
SELECT 
    a.fecha_servicio,
    DATE_FORMAT(a.fecha_servicio, '%W %d') as dia,
    COUNT(*) as total_asignaciones
FROM asignaciones_servicio a
WHERE a.fecha_servicio BETWEEN '2026-01-26' AND '2026-02-01'
GROUP BY a.fecha_servicio
ORDER BY a.fecha_servicio;