-- =====================================================
-- GENERADOR AUTOMÁTICO DE CALENDARIO DE DESCANSOS
-- =====================================================
-- PASO 1: Limpiar calendario antiguo (opcional)
-- DELETE FROM calendario_descansos;
-- PASO 2: Configurar fecha inicial
-- Cambiar esta fecha según cuando quieras que inicie el sistema
SET
    @fecha_inicial = '2026-02-01';

-- Cambiar a la fecha que necesites
-- PASO 3: Configurar cuántos meses adelante quieres generar
-- Por ejemplo, 6 meses = 180 días aproximadamente
SET
    @meses_adelante = 6;

SET
    @dias_totales = @meses_adelante * 30;

-- =====================================================
-- GENERACIÓN PARA OFICIALES
-- =====================================================
-- Grupo A - Oficiales (id_grupo = 1)
-- Patrón: 20 días adentro, 10 días descanso, repite cada 30 días
-- Inicia su PRIMER descanso en el día 21
-- Calcular cuántos ciclos necesitamos (cada ciclo = 30 días)
SET
    @ciclos = CEIL(@dias_totales / 30);

-- Insertar ciclos para Grupo A Oficiales
INSERT INTO
    calendario_descansos (
        id_grupo_descanso,
        fecha_inicio,
        fecha_fin,
        estado
    )
SELECT
    1 as id_grupo_descanso,
    DATE_ADD(@fecha_inicial, INTERVAL (20 + (ciclo * 30)) DAY) as fecha_inicio,
    DATE_ADD(@fecha_inicial, INTERVAL (29 + (ciclo * 30)) DAY) as fecha_fin,
    'PROGRAMADO' as estado
FROM
    (
        SELECT
            0 as ciclo
        UNION
        SELECT
            1
        UNION
        SELECT
            2
        UNION
        SELECT
            3
        UNION
        SELECT
            4
        UNION
        SELECT
            5
        UNION
        SELECT
            6
        UNION
        SELECT
            7
        UNION
        SELECT
            8
        UNION
        SELECT
            9
        UNION
        SELECT
            10
        UNION
        SELECT
            11
    ) as ciclos
WHERE
    (20 + (ciclo * 30)) <= @dias_totales;

-- Grupo B - Oficiales (id_grupo = 2)
-- Patrón: Inicia su PRIMER descanso en el día 31 (desfase de 10 días con A)
INSERT INTO
    calendario_descansos (
        id_grupo_descanso,
        fecha_inicio,
        fecha_fin,
        estado
    )
SELECT
    2 as id_grupo_descanso,
    DATE_ADD(@fecha_inicial, INTERVAL (30 + (ciclo * 30)) DAY) as fecha_inicio,
    DATE_ADD(@fecha_inicial, INTERVAL (39 + (ciclo * 30)) DAY) as fecha_fin,
    'PROGRAMADO' as estado
FROM
    (
        SELECT
            0 as ciclo
        UNION
        SELECT
            1
        UNION
        SELECT
            2
        UNION
        SELECT
            3
        UNION
        SELECT
            4
        UNION
        SELECT
            5
        UNION
        SELECT
            6
        UNION
        SELECT
            7
        UNION
        SELECT
            8
        UNION
        SELECT
            9
        UNION
        SELECT
            10
        UNION
        SELECT
            11
    ) as ciclos
WHERE
    (30 + (ciclo * 30)) <= @dias_totales;

-- Grupo C - Oficiales (id_grupo = 3)
-- Patrón: Inicia su PRIMER descanso en el día 41 (desfase de 10 días con B)
INSERT INTO
    calendario_descansos (
        id_grupo_descanso,
        fecha_inicio,
        fecha_fin,
        estado
    )
SELECT
    3 as id_grupo_descanso,
    DATE_ADD(@fecha_inicial, INTERVAL (40 + (ciclo * 30)) DAY) as fecha_inicio,
    DATE_ADD(@fecha_inicial, INTERVAL (49 + (ciclo * 30)) DAY) as fecha_fin,
    'PROGRAMADO' as estado
FROM
    (
        SELECT
            0 as ciclo
        UNION
        SELECT
            1
        UNION
        SELECT
            2
        UNION
        SELECT
            3
        UNION
        SELECT
            4
        UNION
        SELECT
            5
        UNION
        SELECT
            6
        UNION
        SELECT
            7
        UNION
        SELECT
            8
        UNION
        SELECT
            9
        UNION
        SELECT
            10
        UNION
        SELECT
            11
    ) as ciclos
WHERE
    (40 + (ciclo * 30)) <= @dias_totales;

-- =====================================================
-- GENERACIÓN PARA ESPECIALISTAS (mismo patrón)
-- =====================================================
-- Grupo A - Especialistas (id_grupo = 4)
INSERT INTO
    calendario_descansos (
        id_grupo_descanso,
        fecha_inicio,
        fecha_fin,
        estado
    )
SELECT
    4 as id_grupo_descanso,
    DATE_ADD(@fecha_inicial, INTERVAL (20 + (ciclo * 30)) DAY) as fecha_inicio,
    DATE_ADD(@fecha_inicial, INTERVAL (29 + (ciclo * 30)) DAY) as fecha_fin,
    'PROGRAMADO' as estado
FROM
    (
        SELECT
            0 as ciclo
        UNION
        SELECT
            1
        UNION
        SELECT
            2
        UNION
        SELECT
            3
        UNION
        SELECT
            4
        UNION
        SELECT
            5
        UNION
        SELECT
            6
        UNION
        SELECT
            7
        UNION
        SELECT
            8
        UNION
        SELECT
            9
        UNION
        SELECT
            10
        UNION
        SELECT
            11
    ) as ciclos
WHERE
    (20 + (ciclo * 30)) <= @dias_totales;

-- Grupo B - Especialistas (id_grupo = 5)
INSERT INTO
    calendario_descansos (
        id_grupo_descanso,
        fecha_inicio,
        fecha_fin,
        estado
    )
SELECT
    5 as id_grupo_descanso,
    DATE_ADD(@fecha_inicial, INTERVAL (30 + (ciclo * 30)) DAY) as fecha_inicio,
    DATE_ADD(@fecha_inicial, INTERVAL (39 + (ciclo * 30)) DAY) as fecha_fin,
    'PROGRAMADO' as estado
FROM
    (
        SELECT
            0 as ciclo
        UNION
        SELECT
            1
        UNION
        SELECT
            2
        UNION
        SELECT
            3
        UNION
        SELECT
            4
        UNION
        SELECT
            5
        UNION
        SELECT
            6
        UNION
        SELECT
            7
        UNION
        SELECT
            8
        UNION
        SELECT
            9
        UNION
        SELECT
            10
        UNION
        SELECT
            11
    ) as ciclos
WHERE
    (30 + (ciclo * 30)) <= @dias_totales;

-- Grupo C - Especialistas (id_grupo = 6)
INSERT INTO
    calendario_descansos (
        id_grupo_descanso,
        fecha_inicio,
        fecha_fin,
        estado
    )
SELECT
    6 as id_grupo_descanso,
    DATE_ADD(@fecha_inicial, INTERVAL (40 + (ciclo * 30)) DAY) as fecha_inicio,
    DATE_ADD(@fecha_inicial, INTERVAL (49 + (ciclo * 30)) DAY) as fecha_fin,
    'PROGRAMADO' as estado
FROM
    (
        SELECT
            0 as ciclo
        UNION
        SELECT
            1
        UNION
        SELECT
            2
        UNION
        SELECT
            3
        UNION
        SELECT
            4
        UNION
        SELECT
            5
        UNION
        SELECT
            6
        UNION
        SELECT
            7
        UNION
        SELECT
            8
        UNION
        SELECT
            9
        UNION
        SELECT
            10
        UNION
        SELECT
            11
    ) as ciclos
WHERE
    (40 + (ciclo * 30)) <= @dias_totales;

-- =====================================================
-- GENERACIÓN PARA TROPA (mismo patrón)
-- =====================================================
-- Grupo A - Tropa (id_grupo = 7)
INSERT INTO
    calendario_descansos (
        id_grupo_descanso,
        fecha_inicio,
        fecha_fin,
        estado
    )
SELECT
    7 as id_grupo_descanso,
    DATE_ADD(@fecha_inicial, INTERVAL (20 + (ciclo * 30)) DAY) as fecha_inicio,
    DATE_ADD(@fecha_inicial, INTERVAL (29 + (ciclo * 30)) DAY) as fecha_fin,
    'PROGRAMADO' as estado
FROM
    (
        SELECT
            0 as ciclo
        UNION
        SELECT
            1
        UNION
        SELECT
            2
        UNION
        SELECT
            3
        UNION
        SELECT
            4
        UNION
        SELECT
            5
        UNION
        SELECT
            6
        UNION
        SELECT
            7
        UNION
        SELECT
            8
        UNION
        SELECT
            9
        UNION
        SELECT
            10
        UNION
        SELECT
            11
    ) as ciclos
WHERE
    (20 + (ciclo * 30)) <= @dias_totales;

-- Grupo B - Tropa (id_grupo = 8)
INSERT INTO
    calendario_descansos (
        id_grupo_descanso,
        fecha_inicio,
        fecha_fin,
        estado
    )
SELECT
    8 as id_grupo_descanso,
    DATE_ADD(@fecha_inicial, INTERVAL (30 + (ciclo * 30)) DAY) as fecha_inicio,
    DATE_ADD(@fecha_inicial, INTERVAL (39 + (ciclo * 30)) DAY) as fecha_fin,
    'PROGRAMADO' as estado
FROM
    (
        SELECT
            0 as ciclo
        UNION
        SELECT
            1
        UNION
        SELECT
            2
        UNION
        SELECT
            3
        UNION
        SELECT
            4
        UNION
        SELECT
            5
        UNION
        SELECT
            6
        UNION
        SELECT
            7
        UNION
        SELECT
            8
        UNION
        SELECT
            9
        UNION
        SELECT
            10
        UNION
        SELECT
            11
    ) as ciclos
WHERE
    (30 + (ciclo * 30)) <= @dias_totales;

-- Grupo C - Tropa (id_grupo = 9)
INSERT INTO
    calendario_descansos (
        id_grupo_descanso,
        fecha_inicio,
        fecha_fin,
        estado
    )
SELECT
    9 as id_grupo_descanso,
    DATE_ADD(@fecha_inicial, INTERVAL (40 + (ciclo * 30)) DAY) as fecha_inicio,
    DATE_ADD(@fecha_inicial, INTERVAL (49 + (ciclo * 30)) DAY) as fecha_fin,
    'PROGRAMADO' as estado
FROM
    (
        SELECT
            0 as ciclo
        UNION
        SELECT
            1
        UNION
        SELECT
            2
        UNION
        SELECT
            3
        UNION
        SELECT
            4
        UNION
        SELECT
            5
        UNION
        SELECT
            6
        UNION
        SELECT
            7
        UNION
        SELECT
            8
        UNION
        SELECT
            9
        UNION
        SELECT
            10
        UNION
        SELECT
            11
    ) as ciclos
WHERE
    (40 + (ciclo * 30)) <= @dias_totales;

-- =====================================================
-- VERIFICACIÓN
-- =====================================================
-- Ver calendario generado
SELECT
    gd.nombre as grupo,
    cd.fecha_inicio,
    cd.fecha_fin,
    DATEDIFF(cd.fecha_fin, cd.fecha_inicio) + 1 as dias_descanso,
    cd.estado
FROM
    calendario_descansos cd
    JOIN grupos_descanso gd ON cd.id_grupo_descanso = gd.id_grupo
ORDER BY
    cd.fecha_inicio,
    gd.tipo,
    gd.nombre;

-- Contar registros por grupo
SELECT
    gd.nombre as grupo,
    COUNT(*) as ciclos_generados
FROM
    calendario_descansos cd
    JOIN grupos_descanso gd ON cd.id_grupo_descanso = gd.id_grupo
GROUP BY
    gd.nombre
ORDER BY
    gd.tipo,
    gd.nombre;

-- Ver qué grupos están de descanso HOY
SELECT
    gd.nombre as grupo,
    gd.tipo,
    cd.fecha_inicio,
    cd.fecha_fin,
    DATEDIFF(cd.fecha_fin, CURDATE()) as dias_restantes
FROM
    calendario_descansos cd
    JOIN grupos_descanso gd ON cd.id_grupo_descanso = gd.id_grupo
WHERE
    CURDATE() BETWEEN cd.fecha_inicio
    AND cd.fecha_fin
ORDER BY
    gd.tipo,
    gd.nombre;