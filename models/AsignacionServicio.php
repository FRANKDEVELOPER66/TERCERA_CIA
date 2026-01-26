<?php

namespace Model;

class AsignacionServicio extends ActiveRecord
{
    protected static $tabla = 'asignaciones_servicio';
    protected static $idTabla = 'id_asignacion';
    protected static $columnasDB = [
        'id_personal',
        'id_tipo_servicio',
        'id_oficial_encargado',
        'fecha_servicio',
        'hora_inicio',
        'hora_fin',
        'estado',
        'observaciones',
        'created_by'
    ];

    public $id_asignacion;
    public $id_personal;
    public $id_tipo_servicio;
    public $id_oficial_encargado;
    public $fecha_servicio;
    public $hora_inicio;
    public $hora_fin;
    public $estado;
    public $observaciones;
    public $created_by;

    public function __construct($args = [])
    {
        $this->id_asignacion = $args['id_asignacion'] ?? null;
        $this->id_personal = $args['id_personal'] ?? null;
        $this->id_tipo_servicio = $args['id_tipo_servicio'] ?? null;
        $this->id_oficial_encargado = $args['id_oficial_encargado'] ?? null;
        $this->fecha_servicio = $args['fecha_servicio'] ?? '';
        $this->hora_inicio = $args['hora_inicio'] ?? null;
        $this->hora_fin = $args['hora_fin'] ?? null;
        $this->estado = $args['estado'] ?? 'PROGRAMADO';
        $this->observaciones = $args['observaciones'] ?? '';
        $this->created_by = $args['created_by'] ?? null;
    }
    /**
     * Genera todos los servicios para un día específico
     */
    private static function generarServiciosPorDia($fecha, $usuario_id = null)
    {
        $asignaciones = [];

        // Obtener oficial encargado del día
        $oficial = self::obtenerOficialDisponible($fecha);
        $id_oficial = $oficial ? $oficial['id_personal'] : null;

        error_log("🔷 === GENERANDO SERVICIOS PARA: {$fecha} ===");
        error_log("Oficial encargado: " . ($id_oficial ?? 'NINGUNO'));

        // 1. TÁCTICO - 1 especialista
        $tactico = self::asignarTactico($fecha, $usuario_id, $id_oficial);
        if ($tactico && !is_array($tactico)) {
            $asignaciones[] = $tactico;
            error_log("✅ TÁCTICO asignado");
        } elseif (is_array($tactico) && isset($tactico['error'])) {
            error_log("❌ Error en TÁCTICO: " . $tactico['mensaje']);
        }

        // 2. RECONOCIMIENTO - 2 especialistas + 4 soldados
        $reconocimiento = self::asignarReconocimiento($fecha, $usuario_id, $id_oficial);
        foreach ($reconocimiento as $rec) {
            if (!is_array($rec) || !isset($rec['error'])) {
                $asignaciones[] = $rec;
            }
        }
        error_log("✅ RECONOCIMIENTO asignado (" . count($reconocimiento) . " personas)");

        // 3. SERVICIO NOCTURNO - 3 soldados
        error_log("🌙 Intentando asignar SERVICIO NOCTURNO para fecha: {$fecha}");
        $nocturno = self::asignarServicioNocturno($fecha, $usuario_id, $id_oficial);
        error_log("🌙 Resultado nocturno: " . print_r($nocturno, true));
        error_log("🌙 Count nocturno: " . count($nocturno));

        foreach ($nocturno as $noc) {
            if (!is_array($noc) || !isset($noc['error'])) {
                $asignaciones[] = $noc;
                error_log("✅ Nocturno agregado a asignaciones");
            } else {
                error_log("❌ Nocturno con error: " . print_r($noc, true));
            }
        }
        error_log("✅ SERVICIO NOCTURNO procesado (" . count($nocturno) . " personas)");

        // 4. BANDERÍN - Sargentos
        $banderin = self::asignarBanderin($fecha, $usuario_id, $id_oficial);
        if ($banderin && !is_array($banderin)) {
            $asignaciones[] = $banderin;
            error_log("✅ BANDERÍN asignado");
        } elseif (is_array($banderin) && isset($banderin['error'])) {
            error_log("❌ Error en BANDERÍN: " . $banderin['mensaje']);
        }

        // 5. CUARTELERO - Sargentos y Cabos
        $cuartelero = self::asignarCuartelero($fecha, $usuario_id, $id_oficial);
        if ($cuartelero && !is_array($cuartelero)) {
            $asignaciones[] = $cuartelero;
            error_log("✅ CUARTELERO asignado");
        } elseif (is_array($cuartelero) && isset($cuartelero['error'])) {
            error_log("❌ Error en CUARTELERO: " . $cuartelero['mensaje']);
        }

        // 6. SEMANA - Sargento 1ro. (solo lunes)
        $semana = self::asignarSemana($fecha, $usuario_id, $id_oficial);
        if ($semana && !is_array($semana)) {
            // Es un objeto AsignacionServicio exitoso
            $asignaciones[] = $semana;
            error_log("✅ SEMANA asignado");
        } elseif (is_array($semana) && isset($semana['error'])) {
            // Es un array de error
            error_log("❌ Error en SEMANA: " . $semana['mensaje']);
        } elseif ($semana === null) {
            // No se asignó (probablemente no es lunes)
            error_log("ℹ️ SEMANA no asignado (no es lunes o no hay disponible)");
        }

        error_log("🔷 Total asignaciones creadas para {$fecha}: " . count($asignaciones));

        return $asignaciones;
    }

    /**
     * Obtiene el lunes de la semana para una fecha dada
     */
    private static function obtenerLunesDeLaSemana($fecha)
    {
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N'); // 1=Lunes, 7=Domingo
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        return $fecha_lunes->format('Y-m-d');
    }

    /**
     * Asigna servicio TÁCTICO (1 especialista disponible)
     */
    private static function asignarTactico($fecha, $usuario_id, $id_oficial = null)
    {
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');
        $fecha_domingo = date('Y-m-d', strtotime($lunes_str . ' +6 days'));

        $sql = "SELECT p.id_personal,
            (SELECT COUNT(*) 
             FROM asignaciones_servicio a_count 
             INNER JOIN tipos_servicio ts_count ON a_count.id_tipo_servicio = ts_count.id_tipo_servicio
             WHERE a_count.id_personal = p.id_personal 
             AND a_count.fecha_servicio BETWEEN :fecha_lunes_count AND :fecha_domingo_count
             AND ts_count.nombre = 'TACTICO'
            ) as veces_tactico
    FROM bhr_personal p
    LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
        AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
    LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal 
        AND hr.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'TACTICO')
    WHERE p.tipo = 'ESPECIALISTA'
        AND p.activo = 1
        AND cd.id_calendario IS NULL
        AND p.id_personal NOT IN (
            SELECT id_personal FROM asignaciones_servicio 
            WHERE fecha_servicio BETWEEN :fecha_lunes AND :fecha_domingo
            AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
        )
    ORDER BY 
        veces_tactico ASC,
        COALESCE(hr.dias_desde_ultimo, 999) DESC,
        RAND()
    LIMIT 1";

        $params = [
            ':fecha' => $fecha,
            ':fecha_lunes' => $lunes_str,
            ':fecha_domingo' => $fecha_domingo,
            ':fecha_lunes_count' => $lunes_str,
            ':fecha_domingo_count' => $fecha_domingo
        ];

        error_log("=== DEBUG asignarTactico ===");
        error_log("Params: " . print_r($params, true));

        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'TACTICO',
                $fecha,
                '00:00:00',
                '23:59:59',
                $usuario_id,
                $id_oficial
            );
        }

        return null;
    }

    /**
     * Asigna RECONOCIMIENTO (3 especialistas + 4 soldados)
     */
    /**
     * Asigna RECONOCIMIENTO (2 especialistas + 4 soldados)
     */
    private static function asignarReconocimiento($fecha, $usuario_id, $id_oficial = null)
    {
        $asignaciones = [];

        // 2 Especialistas ⬅️ CAMBIAR DE 3 A 2
        $especialistas = self::obtenerPersonalDisponible($fecha, 'ESPECIALISTA', 2, 'RECONOCIMIENTO');
        foreach ($especialistas as $esp) {
            $asignacion = self::crearAsignacion(
                $esp['id_personal'],
                'RECONOCIMIENTO',
                $fecha,
                '06:00:00',
                '12:00:00',
                $usuario_id,
                $id_oficial
            );
            if ($asignacion) $asignaciones[] = $asignacion;
        }

        // 4 Soldados ⬅️ ESTO SE QUEDA IGUAL
        $fecha_anterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
        $soldados = self::obtenerPersonalDisponible($fecha, 'TROPA', 4, 'RECONOCIMIENTO', $fecha_anterior);
        foreach ($soldados as $sold) {
            $asignacion = self::crearAsignacion(
                $sold['id_personal'],
                'RECONOCIMIENTO',
                $fecha,
                '06:00:00',
                '18:00:00',
                $usuario_id,
                $id_oficial
            );
            if ($asignacion) $asignaciones[] = $asignacion;
        }

        return $asignaciones;
    }

    /**
     * Asigna SERVICIO NOCTURNO (3 soldados) ⬅️ CAMBIAR COMENTARIO
     */
    private static function asignarServicioNocturno($fecha, $usuario_id, $id_oficial = null)
    {
        $asignaciones = [];

        // Calcular fechas de la semana
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');
        $fecha_domingo = date('Y-m-d', strtotime($lunes_str . ' +6 days'));

        error_log("🌙 === ASIGNANDO SERVICIO NOCTURNO ===");
        error_log("Fecha: {$fecha}, Semana: {$lunes_str} a {$fecha_domingo}");

        // Solo Soldados para Servicio Nocturno
        $sql = "SELECT 
            p.id_personal,
            p.nombres,
            p.apellidos,
            g.nombre as grado,
            
            -- 1. Contar servicios NOCTURNOS esta semana
            (SELECT COUNT(*) 
             FROM asignaciones_servicio a_noc
             INNER JOIN tipos_servicio ts_noc ON a_noc.id_tipo_servicio = ts_noc.id_tipo_servicio
             WHERE a_noc.id_personal = p.id_personal 
             AND a_noc.fecha_servicio BETWEEN :fecha_lunes_count AND :fecha_domingo_count
             AND ts_noc.nombre = 'SERVICIO NOCTURNO'
            ) as nocturnos_semana,
            
            -- 2. Días desde último nocturno
            (SELECT DATEDIFF(:fecha_actual, MAX(a_last.fecha_servicio))
             FROM asignaciones_servicio a_last
             INNER JOIN tipos_servicio ts_last ON a_last.id_tipo_servicio = ts_last.id_tipo_servicio
             WHERE a_last.id_personal = p.id_personal
             AND ts_last.nombre = 'SERVICIO NOCTURNO'
            ) as dias_ultimo_nocturno,
            
            -- 3. Total de servicios esta semana (todos los tipos)
            (SELECT COUNT(*) 
             FROM asignaciones_servicio a_total
             WHERE a_total.id_personal = p.id_personal 
             AND a_total.fecha_servicio BETWEEN :fecha_lunes_count2 AND :fecha_domingo_count2
            ) as servicios_semana_total
            
        FROM bhr_personal p
        INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
        LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
            AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
        WHERE p.tipo = 'TROPA'
            AND (g.nombre = 'Sargento 2do.' OR g.nombre LIKE 'Cabo%')
            AND p.activo = 1
            AND cd.id_calendario IS NULL
            AND p.id_personal NOT IN (
                -- Excluir quien tiene SEMANA
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_lunes AND :fecha_domingo
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
        ORDER BY 
            nocturnos_semana ASC,
            COALESCE(dias_ultimo_nocturno, 999) DESC,
            servicios_semana_total ASC,
            g.orden ASC,
            RAND()
        LIMIT 3";

        $params = [
            ':fecha' => $fecha,
            ':fecha_actual' => $fecha,
            ':fecha_lunes' => $lunes_str,
            ':fecha_domingo' => $fecha_domingo,
            ':fecha_lunes_count' => $lunes_str,
            ':fecha_domingo_count' => $fecha_domingo,
            ':fecha_lunes_count2' => $lunes_str,
            ':fecha_domingo_count2' => $fecha_domingo
        ];

        error_log("🌙 SQL Params: " . print_r($params, true));

        $soldados = self::fetchArray($sql, $params);

        error_log("🌙 Soldados encontrados: " . count($soldados));

        if (count($soldados) === 0) {
            error_log("⚠️ NO SE ENCONTRARON SOLDADOS DISPONIBLES PARA NOCTURNO");
            return []; // Retornar array vacío, no null
        }

        // Asignar los 3 turnos
        $turno = 1;
        foreach ($soldados as $sold) {
            error_log("🌙 Asignando turno {$turno}: {$sold['nombres']} {$sold['apellidos']}");

            $asignacion = self::crearAsignacion(
                $sold['id_personal'],
                'SERVICIO NOCTURNO',
                $fecha,
                '21:00:00',
                '04:45:00',
                $usuario_id,
                $id_oficial
            );

            if ($asignacion && !is_array($asignacion)) {
                $asignaciones[] = $asignacion;
                error_log("✅ NOCTURNO Turno {$turno}: {$sold['nombres']} {$sold['apellidos']} (Nocturnos esta semana: {$sold['nocturnos_semana']})");
            } elseif (is_array($asignacion) && isset($asignacion['error'])) {
                error_log("❌ Error al crear nocturno turno {$turno}: " . $asignacion['mensaje']);
            }

            $turno++;
        }

        error_log("🌙 Total nocturnos asignados: " . count($asignaciones));

        return $asignaciones; // Siempre retornar array
    }

    /**
     * Asigna BANDERÍN (solo Sargentos)
     */
    private static function asignarBanderin($fecha, $usuario_id, $id_oficial = null)
    {
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');
        $fecha_domingo = date('Y-m-d', strtotime($lunes_str . ' +6 days'));

        $sql = "SELECT p.id_personal
    FROM bhr_personal p
    INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
    LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
        AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
    LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal 
        AND hr.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'BANDERÍN')
    WHERE p.tipo = 'TROPA'
        AND g.nombre LIKE 'Sargento%'
        AND p.activo = 1
        AND cd.id_calendario IS NULL
        AND p.id_personal NOT IN (
            SELECT id_personal FROM asignaciones_servicio 
            WHERE fecha_servicio BETWEEN DATE_SUB(:fecha2, INTERVAL 2 DAY) AND DATE_SUB(:fecha3, INTERVAL 1 DAY)
            AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'BANDERÍN')
        )
        AND p.id_personal NOT IN (
            SELECT id_personal FROM asignaciones_servicio 
            WHERE fecha_servicio BETWEEN :fecha_lunes AND :fecha_domingo
            AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
        )   
    ORDER BY 
        COALESCE(hr.dias_desde_ultimo, 999) DESC,
        g.orden ASC,
        RAND()
    LIMIT 1";

        $params = [
            ':fecha' => $fecha,
            ':fecha2' => $fecha,
            ':fecha3' => $fecha,
            ':fecha_lunes' => $lunes_str,
            ':fecha_domingo' => $fecha_domingo
        ];

        error_log("=== DEBUG asignarBanderin ===");
        error_log("Params: " . print_r($params, true));

        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'BANDERÍN',
                $fecha,
                '00:00:00',
                '23:59:59',
                $usuario_id,
                $id_oficial
            );
        }

        return null;
    }
    /**
     * Asigna CUARTELERO (Sargentos y Cabos)
     */
    private static function asignarCuartelero($fecha, $usuario_id, $id_oficial = null)
    {
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');
        $fecha_domingo = date('Y-m-d', strtotime($lunes_str . ' +6 days'));

        $sql = "SELECT p.id_personal
    FROM bhr_personal p
    INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
    LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
        AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
    LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal 
        AND hr.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
    WHERE p.tipo = 'TROPA'
        AND (g.nombre = 'Sargento 2do.' OR g.nombre LIKE 'Cabo%')
        AND p.activo = 1
        AND cd.id_calendario IS NULL
        AND p.id_personal NOT IN (
            SELECT id_personal FROM asignaciones_servicio 
            WHERE fecha_servicio BETWEEN :fecha_lunes AND :fecha_domingo
            AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
        )
    ORDER BY 
        COALESCE(hr.dias_desde_ultimo, 999) DESC,
        g.orden ASC,
        RAND()
    LIMIT 1";

        $params = [
            ':fecha' => $fecha,
            ':fecha_lunes' => $lunes_str,
            ':fecha_domingo' => $fecha_domingo
        ];

        error_log("=== DEBUG asignarCuartelero ===");
        error_log("Params: " . print_r($params, true));

        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'CUARTELERO',
                $fecha,
                '00:00:00',
                '23:59:59',
                $usuario_id,
                $id_oficial
            );
        }

        return null;
    }
    /**
     * Obtiene personal disponible para un servicio
     */
    private static function obtenerPersonalDisponible($fecha, $tipo, $cantidad, $nombre_servicio, $fecha_exclusion = null, $incluir_grados = null)
    {
        // Calcular el lunes y domingo de la semana actual
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');
        $fecha_domingo = date('Y-m-d', strtotime($lunes_str . ' +6 days'));

        // Construir filtro de grados con parámetros nombrados
        $filtro_grados = '';
        $params_grados = [];
        if ($incluir_grados !== null && is_array($incluir_grados) && count($incluir_grados) > 0) {
            $placeholders = [];
            foreach ($incluir_grados as $index => $id_grado) {
                $key = ":grado_{$index}";
                $placeholders[] = $key;
                $params_grados[$key] = $id_grado;
            }
            $filtro_grados = "AND p.id_grado IN (" . implode(',', $placeholders) . ")";
        }

        $exclusion_sql = '';
        if ($fecha_exclusion) {
            $exclusion_sql = "AND p.id_personal NOT IN (
            SELECT a2.id_personal FROM asignaciones_servicio a2
            INNER JOIN tipos_servicio ts2 ON a2.id_tipo_servicio = ts2.id_tipo_servicio
            WHERE a2.fecha_servicio = :fecha_exclusion
            AND ts2.nombre = 'SERVICIO NOCTURNO'
        )";
        }

        $sql = "SELECT p.id_personal,
            (SELECT COUNT(*) 
             FROM asignaciones_servicio a_count 
             WHERE a_count.id_personal = p.id_personal 
             AND a_count.fecha_servicio BETWEEN :fecha_lunes_count AND :fecha_domingo_count
            ) as servicios_semana_total,
            
            (SELECT COUNT(*) 
             FROM asignaciones_servicio a_count2
             INNER JOIN tipos_servicio ts_count ON a_count2.id_tipo_servicio = ts_count.id_tipo_servicio
             WHERE a_count2.id_personal = p.id_personal 
             AND a_count2.fecha_servicio BETWEEN :fecha_lunes_count2 AND :fecha_domingo_count2
             AND ts_count.nombre = :servicio3
            ) as veces_este_servicio,
            
            COALESCE(hr.dias_desde_ultimo, 999) as dias_ultimo,
            COALESCE(hr.prioridad, 0) as prioridad_hist
            
        FROM bhr_personal p
        INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
        LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
            AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
        LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal 
            AND hr.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = :servicio)
        LEFT JOIN exclusiones_servicio ex ON p.id_personal = ex.id_personal
            AND ex.fecha_exclusion = :fecha2
            AND ex.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = :servicio2)
        WHERE p.tipo = :tipo
            AND p.activo = 1
            AND cd.id_calendario IS NULL
            AND ex.id_exclusion IS NULL
            {$filtro_grados}
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_lunes AND :fecha_domingo
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
            {$exclusion_sql}
        ORDER BY 
            servicios_semana_total ASC,
            veces_este_servicio ASC,
            dias_ultimo DESC,
            prioridad_hist ASC,
            g.orden ASC,
            RAND()
        LIMIT :cantidad";

        $params = [
            ':fecha' => $fecha,
            ':fecha2' => $fecha,
            ':tipo' => $tipo,
            ':servicio' => $nombre_servicio,
            ':servicio2' => $nombre_servicio,
            ':servicio3' => $nombre_servicio,
            ':cantidad' => (int)$cantidad,
            ':fecha_lunes' => $lunes_str,
            ':fecha_domingo' => $fecha_domingo,
            ':fecha_lunes_count' => $lunes_str,
            ':fecha_domingo_count' => $fecha_domingo,
            ':fecha_lunes_count2' => $lunes_str,
            ':fecha_domingo_count2' => $fecha_domingo
        ];

        // Agregar parámetros de grados
        $params = array_merge($params, $params_grados);

        if ($fecha_exclusion) {
            $params[':fecha_exclusion'] = $fecha_exclusion;
        }

        // DEBUG: Contar parámetros
        error_log("=== DEBUG obtenerPersonalDisponible ===");
        error_log("Servicio: {$nombre_servicio}");
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));

        // Contar placeholders en SQL
        preg_match_all('/:(\w+)/', $sql, $matches);
        error_log("Placeholders encontrados: " . print_r(array_unique($matches[1]), true));
        error_log("Total placeholders: " . count(array_unique($matches[1])));
        error_log("Total params: " . count($params));

        return self::fetchArray($sql, $params);
    }

    /**
     * Crea una asignación de servicio
     */
    /**
     * Genera asignaciones para una semana completa (Lunes a Domingo)
     */
    public static function generarAsignacionesSemanal($fecha_inicio, $usuario_id = null)
    {
        $resultado = [
            'exito' => false,
            'mensaje' => '',
            'asignaciones' => [],
            'errores' => [],
            'debug' => [] // Agregar array de debug
        ];

        try {
            $resultado['debug']['paso_1'] = 'Validando fecha';

            // Verificar que sea lunes
            $fecha = new \DateTime($fecha_inicio);
            if ($fecha->format('N') != 1) {
                $resultado['mensaje'] = 'La fecha debe ser un lunes';
                $resultado['debug']['error'] = 'No es lunes';
                return $resultado;
            }

            $resultado['debug']['paso_2'] = 'Fecha validada, iniciando generación';
            $asignaciones_creadas = [];

            // Generar para 7 días (lunes a domingo)
            for ($dia = 0; $dia < 7; $dia++) {
                $fecha_servicio = clone $fecha;
                $fecha_servicio->modify("+{$dia} days");
                $fecha_str = $fecha_servicio->format('Y-m-d');

                $resultado['debug']["dia_{$dia}"] = "Generando para: {$fecha_str}";

                // Generar cada tipo de servicio para este día
                $servicios_dia = self::generarServiciosPorDia($fecha_str, $usuario_id);

                $resultado['debug']["dia_{$dia}_generados"] = count($servicios_dia);

                $asignaciones_creadas = array_merge($asignaciones_creadas, $servicios_dia);
            }

            $resultado['debug']['paso_3'] = 'Total asignaciones creadas: ' . count($asignaciones_creadas);

            $resultado['exito'] = true;
            $resultado['mensaje'] = 'Asignaciones generadas exitosamente';
            $resultado['asignaciones'] = $asignaciones_creadas;

            return $resultado;
        } catch (\Exception $e) {
            $resultado['mensaje'] = 'Error al generar asignaciones: ' . $e->getMessage();
            $resultado['debug']['excepcion'] = [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile()
            ];
            return $resultado;
        }
    }

    /**
     * Crea una asignación de servicio
     */
    private static function crearAsignacion($id_personal, $nombre_servicio, $fecha, $hora_inicio, $hora_fin, $usuario_id, $id_oficial = null)
    {
        try {
            // Obtener ID del tipo de servicio
            $tipo_servicio = self::fetchFirst(
                "SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = :nombre",
                [':nombre' => $nombre_servicio]
            );

            if (!$tipo_servicio) {
                error_log("❌ ERROR: Tipo de servicio no encontrado: {$nombre_servicio}");
                return [
                    'error' => true,
                    'mensaje' => "Tipo de servicio no encontrado: {$nombre_servicio}"
                ];
            }

            // Verificar si ya existe esta asignación
            $existe = self::fetchFirst(
                "SELECT id_asignacion FROM asignaciones_servicio 
             WHERE id_personal = :id_personal 
             AND fecha_servicio = :fecha 
             AND id_tipo_servicio = :id_tipo",
                [
                    ':id_personal' => $id_personal,
                    ':fecha' => $fecha,
                    ':id_tipo' => $tipo_servicio['id_tipo_servicio']
                ]
            );

            if ($existe) {
                error_log("⚠️ DUPLICADO DETECTADO: Personal {$id_personal} ya tiene {$nombre_servicio} el {$fecha}");
                return [
                    'error' => true,
                    'mensaje' => "Personal ya tiene este servicio asignado",
                    'duplicado' => true
                ];
            }

            $asignacion = new self([
                'id_personal' => $id_personal,
                'id_tipo_servicio' => $tipo_servicio['id_tipo_servicio'],
                'id_oficial_encargado' => $id_oficial,
                'fecha_servicio' => $fecha,
                'hora_inicio' => $hora_inicio,
                'hora_fin' => $hora_fin,
                'estado' => 'PROGRAMADO',
                'created_by' => $usuario_id
            ]);

            $resultado = $asignacion->crear();

            if ($resultado['resultado'] === false || $resultado['resultado'] === 0) {
                error_log("❌ ERROR al crear asignación en BD");
                error_log("Datos: " . print_r($asignacion, true));
                error_log("Resultado: " . print_r($resultado, true));
                return [
                    'error' => true,
                    'mensaje' => 'No se pudo crear en BD',
                    'detalles' => $resultado
                ];
            }

            // Actualizar historial de rotaciones
            self::actualizarHistorial($id_personal, $tipo_servicio['id_tipo_servicio'], $fecha);

            error_log("✅ ASIGNACIÓN CREADA: Personal {$id_personal} -> {$nombre_servicio} el {$fecha}");

            return $asignacion;
        } catch (\Exception $e) {
            error_log("❌ EXCEPCIÓN en crearAsignacion: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'error' => true,
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine()
            ];
        }
    }

    /**
     * Crea una exclusión de servicio
     */
    private static function crearExclusion($id_personal, $nombre_servicio, $fecha, $motivo)
    {
        $tipo_servicio = self::fetchFirst(
            "SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = :nombre",
            [':nombre' => $nombre_servicio]
        );

        if (!$tipo_servicio) return;

        $sql = "INSERT INTO exclusiones_servicio (id_personal, id_tipo_servicio, fecha_exclusion, motivo)
                VALUES (:id_personal, :id_tipo_servicio, :fecha, :motivo)";

        self::ejecutarQuery($sql, [
            ':id_personal' => $id_personal,
            ':id_tipo_servicio' => $tipo_servicio['id_tipo_servicio'],
            ':fecha' => $fecha,
            ':motivo' => $motivo
        ]);
    }

    /**
     * Actualiza el historial de rotaciones
     */
    private static function actualizarHistorial($id_personal, $id_tipo_servicio, $fecha)
    {
        $sql = "INSERT INTO historial_rotaciones (id_personal, id_tipo_servicio, fecha_ultimo_servicio, dias_desde_ultimo, prioridad)
                VALUES (:id_personal, :id_tipo_servicio, :fecha, 0, 0)
                ON DUPLICATE KEY UPDATE
                    fecha_ultimo_servicio = :fecha2,
                    dias_desde_ultimo = 0";

        self::ejecutarQuery($sql, [
            ':id_personal' => $id_personal,
            ':id_tipo_servicio' => $id_tipo_servicio,
            ':fecha' => $fecha,
            ':fecha2' => $fecha
        ]);
    }

    /**
     * Obtiene asignaciones de una semana para mostrar
     */
    /**
     * Obtiene asignaciones de una semana para mostrar
     */
    public static function obtenerAsignacionesSemana($fecha_inicio)
    {
        $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . ' +6 days'));

        $sql = "SELECT 
                a.*,
                CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
                g.nombre as grado,
                ts.nombre as servicio,
                ts.tipo_personal,
                CONCAT(oficial.nombres, ' ', oficial.apellidos) as oficial_encargado,
                g_oficial.nombre as grado_oficial
            FROM asignaciones_servicio a
            INNER JOIN bhr_personal p ON a.id_personal = p.id_personal
            INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
            INNER JOIN tipos_servicio ts ON a.id_tipo_servicio = ts.id_tipo_servicio
            LEFT JOIN bhr_personal oficial ON a.id_oficial_encargado = oficial.id_personal
            LEFT JOIN bhr_grados g_oficial ON oficial.id_grado = g_oficial.id_grado
            WHERE a.fecha_servicio BETWEEN :inicio AND :fin
            ORDER BY a.fecha_servicio, ts.prioridad_asignacion, g.orden";

        return self::fetchArray($sql, [
            ':inicio' => $fecha_inicio,
            ':fin' => $fecha_fin
        ]);
    }

    /**
     * Elimina asignaciones de una semana
     */
    public static function eliminarAsignacionesSemana($fecha_inicio)
    {
        $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . ' +6 days'));

        $sql = "DELETE FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :inicio AND :fin";

        return self::ejecutarQuery($sql, [
            ':inicio' => $fecha_inicio,
            ':fin' => $fecha_fin
        ]);
    }

    /**
     * Obtiene el oficial de mayor grado disponible para un día
     */
    private static function obtenerOficialDisponible($fecha)
    {
        $sql = "SELECT p.id_personal, g.nombre as grado, g.orden
            FROM bhr_personal p
            INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
            LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
                AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
            WHERE p.tipo = 'OFICIAL'
                AND p.activo = 1
                AND cd.id_calendario IS NULL -- No está de descanso
            ORDER BY g.orden ASC -- Orden más bajo = grado más alto
            LIMIT 1";

        return self::fetchFirst($sql, [':fecha' => $fecha]);
    }
    /**
     * Asigna SEMANA (1 Sargento 1ro. por semana completa)
     */
    private static function asignarSemana($fecha, $usuario_id, $id_oficial = null)
    {
        // El servicio SEMANA se asigna cada lunes y dura toda la semana
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N'); // 1=Lunes

        // Solo asignar en LUNES
        if ($dia_semana != 1) {
            return null;
        }

        $sql = "SELECT p.id_personal
        FROM bhr_personal p
        INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
        LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
            AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
        LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal 
            AND hr.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
        WHERE p.tipo = 'TROPA'
            AND g.nombre = 'Sargento 1ro.'
            AND p.activo = 1
            AND cd.id_calendario IS NULL
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
        ORDER BY 
            COALESCE(hr.dias_desde_ultimo, 999) DESC,
            RAND()
        LIMIT 1"; // ⬅️ CAMBIAR DE 6 A 1

        $fecha_fin = date('Y-m-d', strtotime($fecha . ' +6 days'));
        $params = [
            ':fecha' => $fecha,
            ':fecha_inicio' => $fecha,
            ':fecha_fin' => $fecha_fin
        ];

        $resultado = self::fetchFirst($sql, $params); // ⬅️ CAMBIAR fetchArray a fetchFirst

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'Semana',
                $fecha,
                '00:00:00',
                '23:59:59',
                $usuario_id,
                $id_oficial
            );
        }

        return null;
    }
}
