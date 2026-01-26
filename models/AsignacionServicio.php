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
        $oficial = self::obtenerOficialDisponible($fecha);
        $id_oficial = $oficial ? $oficial['id_personal'] : null;

        error_log("🔷 === GENERANDO SERVICIOS PARA: {$fecha} ===");

        try {
            // 1. SEMANA (solo lunes)
            $semana = self::asignarSemana($fecha, $usuario_id, $id_oficial);
            if ($semana && !is_array($semana)) {
                $asignaciones[] = $semana;
                error_log("✅ SEMANA asignado");
            }

            // 2. TÁCTICO
            $tactico = self::asignarTactico($fecha, $usuario_id, $id_oficial);
            if ($tactico && !is_array($tactico)) {
                $asignaciones[] = $tactico;
                error_log("✅ TÁCTICO asignado");
            } else {
                throw new \Exception("No hay especialistas disponibles para TÁCTICO en {$fecha}");
            }

            // 3. RECONOCIMIENTO - AQUÍ PUEDE FALLAR
            error_log("⏳ Iniciando asignación de RECONOCIMIENTO...");
            $reconocimiento = self::asignarReconocimiento($fecha, $usuario_id, $id_oficial);

            foreach ($reconocimiento as $rec) {
                $asignaciones[] = $rec;
            }
            error_log("✅ RECONOCIMIENTO asignado (" . count($reconocimiento) . " personas)");

            // 4. BANDERÍN
            $banderin = self::asignarBanderin($fecha, $usuario_id, $id_oficial);
            if ($banderin && !is_array($banderin)) {
                $asignaciones[] = $banderin;
                error_log("✅ BANDERÍN asignado");
            } else {
                throw new \Exception("No hay sargentos disponibles para BANDERÍN en {$fecha}");
            }

            // 5. CUARTELERO
            $cuartelero = self::asignarCuartelero($fecha, $usuario_id, $id_oficial);
            if ($cuartelero && !is_array($cuartelero)) {
                $asignaciones[] = $cuartelero;
                error_log("✅ CUARTELERO asignado");
            } else {
                throw new \Exception("No hay sargentos/cabos disponibles para CUARTELERO en {$fecha}");
            }

            // 6. SERVICIO NOCTURNO
            $nocturno = self::asignarServicioNocturno($fecha, $usuario_id, $id_oficial);
            foreach ($nocturno as $noc) {
                $asignaciones[] = $noc;
            }
            error_log("✅ SERVICIO NOCTURNO asignado (" . count($nocturno) . " personas)");

            // VALIDACIÓN FINAL
            $fecha_obj = new \DateTime($fecha);
            $es_lunes = ($fecha_obj->format('N') == 1);

            $total_esperado = $es_lunes ? 12 : 11;
            $total_generado = count($asignaciones);

            error_log("📊 RESUMEN {$fecha}: {$total_generado}/{$total_esperado} asignaciones");

            if ($total_generado < $total_esperado) {
                throw new \Exception("Asignaciones incompletas para {$fecha}: {$total_generado}/{$total_esperado}");
            }

            error_log("✅ ÉXITO: Día completo con {$total_generado} asignaciones");

            return $asignaciones;
        } catch (\Exception $e) {
            error_log("❌ ERROR en generarServiciosPorDia({$fecha}): " . $e->getMessage());
            throw $e; // Re-lanzar para que lo capture generarAsignacionesSemanal
        }
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
        $logs = [];
        $ids_ya_asignados = []; // ⬇️ NUEVO: Rastrear IDs ya usados

        $logs[] = "🔍 === ASIGNANDO RECONOCIMIENTO para {$fecha} ===";

        // ============================================
        // ESPECIALISTAS
        // ============================================
        $especialistas = self::obtenerPersonalDisponible($fecha, 'ESPECIALISTA', 2, 'RECONOCIMIENTO', null, null, false, $ids_ya_asignados);

        $logs[] = "👷 INTENTO 1 - Especialistas encontrados: " . count($especialistas) . "/2 (modo normal)";

        if (count($especialistas) < 2) {
            $logs[] = "⚠️ No hay suficientes especialistas, activando modo EMERGENCIA";

            $especialistas = self::obtenerPersonalDisponible($fecha, 'ESPECIALISTA', 2, 'RECONOCIMIENTO', null, null, true, $ids_ya_asignados);

            $logs[] = "👷 INTENTO 2 - Especialistas encontrados: " . count($especialistas) . "/2 (modo emergencia)";
        }

        $especialistas_encontrados = count($especialistas);

        if ($especialistas_encontrados < 2) {
            $logs[] = "⚠️ Solo hay {$especialistas_encontrados} especialistas, completando con SARGENTOS";

            $sargentos_necesarios = 2 - $especialistas_encontrados;

            // ⬇️ EXCLUIR IDs ya usados
            $filtro_excluir = '';
            $params_excluir = [];
            if (!empty($ids_ya_asignados)) {
                $placeholders = [];
                foreach ($ids_ya_asignados as $idx => $id) {
                    $key = ":excluir_{$idx}";
                    $placeholders[] = $key;
                    $params_excluir[$key] = $id;
                }
                $filtro_excluir = "AND p.id_personal NOT IN (" . implode(',', $placeholders) . ")";
            }

            $sargentos = self::fetchArray(
                "SELECT p.id_personal, p.nombres, p.apellidos
             FROM bhr_personal p
             INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
             LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
                AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
             WHERE p.tipo = 'TROPA'
                AND g.nombre LIKE 'Sargento%'
                AND p.activo = 1
                AND cd.id_calendario IS NULL
                {$filtro_excluir}
                AND p.id_personal NOT IN (
                    SELECT id_personal FROM asignaciones_servicio 
                    WHERE fecha_servicio = :fecha2
                )
             ORDER BY g.orden ASC, RAND()
             LIMIT :cantidad",
                array_merge([
                    ':fecha' => $fecha,
                    ':fecha2' => $fecha,
                    ':cantidad' => $sargentos_necesarios
                ], $params_excluir)
            );

            $logs[] = "👷 INTENTO 3 - Sargentos de reemplazo: " . count($sargentos) . "/{$sargentos_necesarios}";

            foreach ($sargentos as $sarg) {
                $especialistas[] = $sarg;
                $logs[] = "  → Sargento reemplazo: {$sarg['nombres']} {$sarg['apellidos']} (ID: {$sarg['id_personal']})";
            }
        }

        // ============================================
        // ASIGNAR ESPECIALISTAS
        // ============================================
        $logs[] = "📝 Asignando " . count($especialistas) . " especialistas...";

        foreach ($especialistas as $esp) {
            $nombre = ($esp['nombres'] ?? '') . ' ' . ($esp['apellidos'] ?? '');

            $asignacion = self::crearAsignacion(
                $esp['id_personal'],
                'RECONOCIMIENTO',
                $fecha,
                '06:00:00',
                '12:00:00',
                $usuario_id,
                $id_oficial
            );

            if (is_array($asignacion) && isset($asignacion['error'])) {
                $logs[] = "❌ ERROR al asignar especialista {$nombre} (ID: {$esp['id_personal']}): " . ($asignacion['mensaje'] ?? 'Error desconocido');
            } elseif ($asignacion && !is_array($asignacion)) {
                $asignaciones[] = $asignacion;
                $ids_ya_asignados[] = $esp['id_personal']; // ⬇️ AGREGAR A LA LISTA
                $logs[] = "✅ Especialista asignado: {$nombre} (ID: {$esp['id_personal']})";
            } else {
                $logs[] = "⚠️ Asignación NULL para especialista {$nombre} (ID: {$esp['id_personal']})";
            }
        }

        // ============================================
        // SOLDADOS
        // ============================================
        $fecha_anterior = date('Y-m-d', strtotime($fecha . ' -1 day'));

        // ⬇️ PASAR IDs ya asignados
        $soldados = self::obtenerPersonalDisponible($fecha, 'TROPA', 4, 'RECONOCIMIENTO', $fecha_anterior, null, false, $ids_ya_asignados);

        $logs[] = "🎖️ INTENTO 1 - Soldados encontrados: " . count($soldados) . "/4 (modo normal)";

        if (count($soldados) < 4) {
            $logs[] = "⚠️ Faltan soldados, INCLUYENDO quienes hicieron nocturno ayer";

            $soldados = self::obtenerPersonalDisponible($fecha, 'TROPA', 4, 'RECONOCIMIENTO', null, null, true, $ids_ya_asignados);

            $logs[] = "🎖️ INTENTO 2 - Soldados encontrados: " . count($soldados) . "/4 (modo emergencia)";
        }

        $logs[] = "📝 Asignando " . count($soldados) . " soldados...";

        foreach ($soldados as $sold) {
            $nombre = ($sold['nombres'] ?? '') . ' ' . ($sold['apellidos'] ?? '');

            $asignacion = self::crearAsignacion(
                $sold['id_personal'],
                'RECONOCIMIENTO',
                $fecha,
                '06:00:00',
                '18:00:00',
                $usuario_id,
                $id_oficial
            );

            if (is_array($asignacion) && isset($asignacion['error'])) {
                $logs[] = "❌ ERROR al asignar soldado {$nombre} (ID: {$sold['id_personal']}): " . ($asignacion['mensaje'] ?? 'Error desconocido');
            } elseif ($asignacion && !is_array($asignacion)) {
                $asignaciones[] = $asignacion;
                $ids_ya_asignados[] = $sold['id_personal']; // ⬇️ AGREGAR A LA LISTA
                $logs[] = "✅ Soldado asignado: {$nombre} (ID: {$sold['id_personal']})";
            } else {
                $logs[] = "⚠️ Asignación NULL para soldado {$nombre} (ID: {$sold['id_personal']})";
            }
        }

        $total = count($asignaciones);
        $logs[] = "📊 RECONOCIMIENTO RESULTADO FINAL: {$total}/6 personas creadas en BD";

        foreach ($logs as $log) {
            error_log($log);
        }

        if ($total < 6) {
            $mensaje_error = "No hay suficiente personal para RECONOCIMIENTO en {$fecha}. Se necesitan 6, se encontraron {$total}";

            throw new \Exception($mensaje_error . "\n" . implode("\n", $logs));
        }

        return $asignaciones;
    }

    /**
     * Asigna SERVICIO NOCTURNO (3 soldados) ⬅️ CAMBIAR COMENTARIO
     */
    private static function asignarServicioNocturno($fecha, $usuario_id, $id_oficial = null)
    {
        $asignaciones = [];

        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');
        $fecha_domingo = date('Y-m-d', strtotime($lunes_str . ' +6 days'));

        error_log("🌙 === ASIGNANDO SERVICIO NOCTURNO ===");

        // ⬇️ SQL MODIFICADO: Permite quien ya tiene servicio ese día (excepto SEMANA y CUARTELERO)
        $sql = "SELECT 
        p.id_personal,
        p.nombres,
        p.apellidos,
        g.nombre as grado,
        
        (SELECT COUNT(*) 
         FROM asignaciones_servicio a_noc
         INNER JOIN tipos_servicio ts_noc ON a_noc.id_tipo_servicio = ts_noc.id_tipo_servicio
         WHERE a_noc.id_personal = p.id_personal 
         AND a_noc.fecha_servicio BETWEEN :fecha_lunes_count AND :fecha_domingo_count
         AND ts_noc.nombre = 'SERVICIO NOCTURNO'
        ) as nocturnos_semana,
        
        (SELECT DATEDIFF(:fecha_actual, MAX(a_last.fecha_servicio))
         FROM asignaciones_servicio a_last
         INNER JOIN tipos_servicio ts_last ON a_last.id_tipo_servicio = ts_last.id_tipo_servicio
         WHERE a_last.id_personal = p.id_personal
         AND ts_last.nombre = 'SERVICIO NOCTURNO'
        ) as dias_ultimo_nocturno,
        
        (SELECT COUNT(*) 
         FROM asignaciones_servicio a_total
         WHERE a_total.id_personal = p.id_personal 
         AND a_total.fecha_servicio BETWEEN :fecha_lunes_count2 AND :fecha_domingo_count2
        ) as servicios_semana_total,
        
        -- ⬇️ NUEVO: Verificar si ya tiene servicio HOY
        (SELECT GROUP_CONCAT(ts.nombre SEPARATOR ', ')
         FROM asignaciones_servicio a_hoy
         INNER JOIN tipos_servicio ts ON a_hoy.id_tipo_servicio = ts.id_tipo_servicio
         WHERE a_hoy.id_personal = p.id_personal
         AND a_hoy.fecha_servicio = :fecha_hoy
        ) as servicios_hoy
        
    FROM bhr_personal p
    INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
    LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
        AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
    WHERE p.tipo = 'TROPA'
        AND (g.nombre = 'Sargento 2do.' OR g.nombre LIKE 'Cabo%')
        AND p.activo = 1
        AND cd.id_calendario IS NULL
        AND p.id_personal NOT IN (
            SELECT id_personal FROM asignaciones_servicio 
            WHERE fecha_servicio BETWEEN :fecha_lunes AND :fecha_domingo
            AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
        )
        AND p.id_personal NOT IN (
            SELECT id_personal FROM asignaciones_servicio 
            WHERE fecha_servicio = :fecha_cuartelero
            AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
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
            ':fecha_hoy' => $fecha,
            ':fecha_lunes' => $lunes_str,
            ':fecha_domingo' => $fecha_domingo,
            ':fecha_lunes_count' => $lunes_str,
            ':fecha_domingo_count' => $fecha_domingo,
            ':fecha_lunes_count2' => $lunes_str,
            ':fecha_domingo_count2' => $fecha_domingo,
            ':fecha_cuartelero' => $fecha
        ];

        $soldados = self::fetchArray($sql, $params);

        error_log("🌙 Soldados encontrados: " . count($soldados));

        if (count($soldados) === 0) {
            error_log("⚠️ NO SE ENCONTRARON SOLDADOS PARA NOCTURNO");
            return [];
        }

        // Asignar turnos
        $horarios = [
            1 => ['21:00:00', '23:30:00'],
            2 => ['23:30:00', '02:00:00'],
            3 => ['02:00:00', '04:45:00']
        ];

        $turno = 1;
        foreach ($soldados as $sold) {
            // ⬇️ LOG: Mostrar si ya tiene otro servicio
            if (!empty($sold['servicios_hoy'])) {
                error_log("⚡ DOBLE ASIGNACIÓN: {$sold['nombres']} {$sold['apellidos']} ya tiene: {$sold['servicios_hoy']}");
            }

            $asignacion = self::crearAsignacion(
                $sold['id_personal'],
                'SERVICIO NOCTURNO',
                $fecha,
                $horarios[$turno][0],
                $horarios[$turno][1],
                $usuario_id,
                $id_oficial
            );

            if ($asignacion && !is_array($asignacion)) {
                $asignaciones[] = $asignacion;
                error_log("✅ NOCTURNO Turno {$turno}: {$sold['nombres']} {$sold['apellidos']}");
            }

            $turno++;
        }

        return $asignaciones;
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

        $sql = "SELECT p.id_personal,
        -- Contar cuántas veces ha sido CUARTELERO esta semana
        (SELECT COUNT(*) 
         FROM asignaciones_servicio a_cua
         INNER JOIN tipos_servicio ts_cua ON a_cua.id_tipo_servicio = ts_cua.id_tipo_servicio
         WHERE a_cua.id_personal = p.id_personal 
         AND a_cua.fecha_servicio BETWEEN :fecha_lunes_count AND :fecha_domingo_count
         AND ts_cua.nombre = 'CUARTELERO'
        ) as veces_cuartelero_semana,
        
        COALESCE(hr.dias_desde_ultimo, 999) as dias_ultimo
        
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
            -- Excluir quien tiene SEMANA
            SELECT id_personal FROM asignaciones_servicio 
            WHERE fecha_servicio BETWEEN :fecha_lunes AND :fecha_domingo
            AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
        )
        AND p.id_personal NOT IN (
            -- ⬅️ EXCLUSIÓN: No asignar si fue CUARTELERO en los últimos 2 días
            SELECT id_personal FROM asignaciones_servicio 
            WHERE fecha_servicio BETWEEN DATE_SUB(:fecha2, INTERVAL 2 DAY) AND DATE_SUB(:fecha3, INTERVAL 1 DAY)
            AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
        )
    ORDER BY 
        veces_cuartelero_semana ASC,  -- ⬅️ Prioridad: quien menos veces ha sido cuartelero
        dias_ultimo DESC,              -- ⬅️ Luego: quien tiene más días sin hacerlo
        g.orden ASC,
        RAND()
    LIMIT 1";

        $params = [
            ':fecha' => $fecha,
            ':fecha2' => $fecha,
            ':fecha3' => $fecha,
            ':fecha_lunes' => $lunes_str,
            ':fecha_domingo' => $fecha_domingo,
            ':fecha_lunes_count' => $lunes_str,
            ':fecha_domingo_count' => $fecha_domingo
        ];

        error_log("=== DEBUG asignarCuartelero ===");
        error_log("Params: " . print_r($params, true));

        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            error_log("✅ CUARTELERO seleccionado: ID {$resultado['id_personal']} (Veces esta semana: {$resultado['veces_cuartelero_semana']}, Días desde último: {$resultado['dias_ultimo']})");

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

        error_log("⚠️ NO se encontró CUARTELERO disponible para {$fecha}");
        return null;
    }
    /**
     * Obtiene personal disponible para un servicio
     * @param bool $modo_emergencia Si es true, relaja las restricciones
     */
    /**
     * Obtiene personal disponible para un servicio
     * @param array $excluir_ids IDs de personal ya seleccionado en este proceso (en memoria)
     */
    private static function obtenerPersonalDisponible($fecha, $tipo, $cantidad, $nombre_servicio, $fecha_exclusion = null, $incluir_grados = null, $modo_emergencia = false, $excluir_ids = [])
    {
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');
        $fecha_domingo = date('Y-m-d', strtotime($lunes_str . ' +6 days'));

        // Construir filtro de grados
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

        // ⬇️ NUEVO: Filtro para excluir IDs en memoria
        $filtro_excluir_ids = '';
        $params_excluir = [];
        if (!empty($excluir_ids)) {
            $placeholders_excluir = [];
            foreach ($excluir_ids as $index => $id) {
                $key = ":excluir_{$index}";
                $placeholders_excluir[] = $key;
                $params_excluir[$key] = $id;
            }
            $filtro_excluir_ids = "AND p.id_personal NOT IN (" . implode(',', $placeholders_excluir) . ")";
        }

        $exclusion_sql = '';
        if ($fecha_exclusion && !$modo_emergencia) {
            $exclusion_sql = "AND p.id_personal NOT IN (
            SELECT a2.id_personal FROM asignaciones_servicio a2
            INNER JOIN tipos_servicio ts2 ON a2.id_tipo_servicio = ts2.id_tipo_servicio
            WHERE a2.fecha_servicio = :fecha_exclusion
            AND ts2.nombre = 'SERVICIO NOCTURNO'
        )";
        }

        $sql = "SELECT p.id_personal,
            p.nombres,
            p.apellidos,
            g.nombre as grado,
            
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
            {$filtro_excluir_ids}
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

        // Agregar parámetros de grados y exclusiones
        $params = array_merge($params, $params_grados, $params_excluir);

        if ($fecha_exclusion) {
            $params[':fecha_exclusion'] = $fecha_exclusion;
        }

        if ($modo_emergencia) {
            error_log("🚨 MODO EMERGENCIA ACTIVADO para {$nombre_servicio}");
        }

        if (!empty($excluir_ids)) {
            error_log("🚫 Excluyendo IDs en memoria: " . implode(', ', $excluir_ids));
        }

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
            'debug' => [],
            'detalle_por_dia' => []
        ];

        try {
            $fecha = new \DateTime($fecha_inicio);
            if ($fecha->format('N') != 1) {
                $resultado['mensaje'] = 'La fecha debe ser un lunes';
                return $resultado;
            }

            // ⬇️ ACTUALIZAR DÍAS DESDE ÚLTIMO SERVICIO
            self::actualizarDiasDesdeUltimo();
            error_log("✅ Historial de rotaciones actualizado");

            $fecha_domingo = date('Y-m-d', strtotime($fecha_inicio . ' +6 days'));

            // ⬇️ SOLO BORRAR ASIGNACIONES, NO HISTORIAL
            error_log("🗑️ Limpiando asignaciones de {$fecha_inicio} a {$fecha_domingo} (manteniendo historial)");

            $eliminadas = self::ejecutarQuery(
                "DELETE FROM asignaciones_servicio 
             WHERE fecha_servicio BETWEEN :inicio AND :fin",
                [':inicio' => $fecha_inicio, ':fin' => $fecha_domingo]
            );

            error_log("✅ Asignaciones eliminadas: " . ($eliminadas['resultado'] ?? 0));
            error_log("ℹ️ El historial de rotaciones se mantiene intacto");

            $asignaciones_creadas = [];

            // Generar para 7 días
            for ($dia = 0; $dia < 7; $dia++) {
                $fecha_servicio = clone $fecha;
                $fecha_servicio->modify("+{$dia} days");
                $fecha_str = $fecha_servicio->format('Y-m-d');

                try {
                    $servicios_dia = self::generarServiciosPorDia($fecha_str, $usuario_id);

                    $asignaciones_creadas = array_merge($asignaciones_creadas, $servicios_dia);

                    $resultado['detalle_por_dia'][$fecha_str] = [
                        'fecha' => $fecha_str,
                        'total' => count($servicios_dia),
                        'exito' => true
                    ];
                } catch (\Exception $e) {
                    // ⬇️ Si falla, hacer ROLLBACK de las asignaciones pero NO del historial
                    error_log("❌ Error en {$fecha_str}, haciendo rollback de asignaciones");

                    self::ejecutarQuery(
                        "DELETE FROM asignaciones_servicio 
                     WHERE fecha_servicio BETWEEN :inicio AND :fecha_actual",
                        [':inicio' => $fecha_inicio, ':fecha_actual' => $fecha_str]
                    );

                    $resultado['errores'][] = [
                        'fecha' => $fecha_str,
                        'error' => $e->getMessage()
                    ];

                    $resultado['detalle_por_dia'][$fecha_str] = [
                        'fecha' => $fecha_str,
                        'total' => 0,
                        'exito' => false,
                        'error' => $e->getMessage()
                    ];

                    throw new \Exception("Error en {$fecha_str}: " . $e->getMessage());
                }
            }

            $resultado['exito'] = true;
            $resultado['mensaje'] = 'Asignaciones generadas exitosamente para toda la semana';
            $resultado['asignaciones'] = $asignaciones_creadas;

            return $resultado;
        } catch (\Exception $e) {
            $resultado['mensaje'] = $e->getMessage();
            $resultado['debug']['excepcion'] = [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine()
            ];
            return $resultado;
        }
    }

    // Agregar al final de la clase AsignacionServicio
    public static function diagnosticarPersonalDisponible($fecha)
    {
        error_log("=== DIAGNÓSTICO DE PERSONAL DISPONIBLE ===");

        // 1. Total de personal activo
        $total = self::fetchFirst("SELECT COUNT(*) as total FROM bhr_personal WHERE activo = 1", []);
        error_log("Total personal activo: " . $total['total']);

        // 2. Por tipo
        $por_tipo = self::fetchArray("SELECT tipo, COUNT(*) as total FROM bhr_personal WHERE activo = 1 GROUP BY tipo", []);
        foreach ($por_tipo as $tipo) {
            error_log("  - {$tipo['tipo']}: {$tipo['total']}");
        }

        // 3. Especialistas disponibles (sin restricciones)
        $especialistas = self::fetchArray(
            "SELECT p.id_personal, p.nombres, p.apellidos, g.nombre as grado
         FROM bhr_personal p
         INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
         WHERE p.tipo = 'ESPECIALISTA' AND p.activo = 1",
            []
        );
        error_log("Especialistas totales: " . count($especialistas));
        foreach ($especialistas as $esp) {
            error_log("  - {$esp['grado']} {$esp['nombres']} {$esp['apellidos']} (ID: {$esp['id_personal']})");
        }

        // 4. Especialistas con descanso
        $fecha_obj = new \DateTime($fecha);
        $lunes_str = self::obtenerLunesDeLaSemana($fecha);
        $fecha_domingo = date('Y-m-d', strtotime($lunes_str . ' +6 days'));

        $con_descanso = self::fetchArray(
            "SELECT p.id_personal, p.nombres, p.apellidos, cd.fecha_inicio, cd.fecha_fin
         FROM bhr_personal p
         INNER JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
         WHERE p.tipo = 'ESPECIALISTA' 
         AND p.activo = 1
         AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin",
            [':fecha' => $fecha]
        );
        error_log("Especialistas de descanso el {$fecha}: " . count($con_descanso));
        foreach ($con_descanso as $desc) {
            error_log("  - {$desc['nombres']} {$desc['apellidos']} (Descanso: {$desc['fecha_inicio']} a {$desc['fecha_fin']})");
        }

        // 5. Especialistas con SEMANA
        $con_semana = self::fetchArray(
            "SELECT p.id_personal, p.nombres, p.apellidos
         FROM bhr_personal p
         INNER JOIN asignaciones_servicio a ON p.id_personal = a.id_personal
         WHERE p.tipo = 'ESPECIALISTA'
         AND a.fecha_servicio BETWEEN :lunes AND :domingo
         AND a.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')",
            [':lunes' => $lunes_str, ':domingo' => $fecha_domingo]
        );
        error_log("Especialistas con SEMANA esta semana: " . count($con_semana));
        foreach ($con_semana as $sem) {
            error_log("  - {$sem['nombres']} {$sem['apellidos']}");
        }

        // 6. Especialistas ya asignados ese día
        $ya_asignados = self::fetchArray(
            "SELECT p.id_personal, p.nombres, p.apellidos, ts.nombre as servicio
         FROM bhr_personal p
         INNER JOIN asignaciones_servicio a ON p.id_personal = a.id_personal
         INNER JOIN tipos_servicio ts ON a.id_tipo_servicio = ts.id_tipo_servicio
         WHERE p.tipo = 'ESPECIALISTA'
         AND a.fecha_servicio = :fecha",
            [':fecha' => $fecha]
        );
        error_log("Especialistas ya asignados el {$fecha}: " . count($ya_asignados));
        foreach ($ya_asignados as $asig) {
            error_log("  - {$asig['nombres']} {$asig['apellidos']} -> {$asig['servicio']}");
        }

        // 7. TROPA disponible
        $tropa = self::fetchArray(
            "SELECT p.id_personal, p.nombres, p.apellidos, g.nombre as grado
         FROM bhr_personal p
         INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
         WHERE p.tipo = 'TROPA' AND p.activo = 1",
            []
        );
        error_log("Tropa total: " . count($tropa));

        $tropa_descanso = self::fetchArray(
            "SELECT COUNT(*) as total
         FROM bhr_personal p
         INNER JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
         WHERE p.tipo = 'TROPA' 
         AND p.activo = 1
         AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin",
            [':fecha' => $fecha]
        );
        error_log("Tropa de descanso: " . $tropa_descanso[0]['total']);
    }

    /**
     * Crea una asignación de servicio
     */
    private static function crearAsignacion($id_personal, $nombre_servicio, $fecha, $hora_inicio, $hora_fin, $usuario_id, $id_oficial = null)
    {
        try {
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

            // ⬇️ Verificar duplicados (NO debería pasar si limpiaste antes)
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
                error_log("⚠️ DUPLICADO DETECTADO (no debería pasar): Personal {$id_personal} - {$nombre_servicio} - {$fecha}");
                return [
                    'error' => true,
                    'mensaje' => "Personal ya tiene este servicio asignado",
                    'duplicado' => true
                ];
            }

            // Crear nueva asignación
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
                error_log("❌ ERROR al crear en BD: Personal {$id_personal}");
                return [
                    'error' => true,
                    'mensaje' => 'No se pudo crear en BD',
                    'detalles' => $resultado
                ];
            }

            // ⬇️ Actualizar historial SOLO si la fecha es de esta semana o futura
            $hoy = date('Y-m-d');
            if ($fecha >= $hoy) {
                self::actualizarHistorial($id_personal, $tipo_servicio['id_tipo_servicio'], $fecha);
            }

            error_log("✅ ASIGNACIÓN CREADA: Personal {$id_personal} -> {$nombre_servicio} el {$fecha}");

            return $asignacion;
        } catch (\Exception $e) {
            error_log("❌ EXCEPCIÓN en crearAsignacion: " . $e->getMessage());
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
    /**
     * Actualiza el historial de rotaciones
     * Solo se debe llamar cuando la asignación es DEFINITIVA
     */
    private static function actualizarHistorial($id_personal, $id_tipo_servicio, $fecha)
    {
        // Verificar si ya existe un registro de historial
        $existe = self::fetchFirst(
            "SELECT id_historial, fecha_ultimo_servicio 
         FROM historial_rotaciones 
         WHERE id_personal = :id_personal 
         AND id_tipo_servicio = :id_tipo_servicio",
            [
                ':id_personal' => $id_personal,
                ':id_tipo_servicio' => $id_tipo_servicio
            ]
        );

        if ($existe) {
            // ⬇️ SOLO actualizar si la nueva fecha es MÁS RECIENTE
            if ($fecha > $existe['fecha_ultimo_servicio']) {
                $sql = "UPDATE historial_rotaciones 
                    SET fecha_ultimo_servicio = :fecha,
                        dias_desde_ultimo = 0
                    WHERE id_personal = :id_personal 
                    AND id_tipo_servicio = :id_tipo_servicio";

                self::ejecutarQuery($sql, [
                    ':fecha' => $fecha,
                    ':id_personal' => $id_personal,
                    ':id_tipo_servicio' => $id_tipo_servicio
                ]);

                error_log("📝 Historial actualizado: Personal {$id_personal} - Servicio {$id_tipo_servicio} - Fecha: {$fecha}");
            } else {
                error_log("ℹ️ Historial NO actualizado (fecha {$fecha} no es más reciente que {$existe['fecha_ultimo_servicio']})");
            }
        } else {
            // Crear nuevo registro
            $sql = "INSERT INTO historial_rotaciones (id_personal, id_tipo_servicio, fecha_ultimo_servicio, dias_desde_ultimo, prioridad)
                VALUES (:id_personal, :id_tipo_servicio, :fecha, 0, 0)";

            self::ejecutarQuery($sql, [
                ':id_personal' => $id_personal,
                ':id_tipo_servicio' => $id_tipo_servicio,
                ':fecha' => $fecha
            ]);

            error_log("📝 Historial creado: Personal {$id_personal} - Servicio {$id_tipo_servicio} - Fecha: {$fecha}");
        }
    }

    /**
     * Actualiza los días desde el último servicio para todos los registros
     * Se debe ejecutar ANTES de generar una nueva semana
     */
    public static function actualizarDiasDesdeUltimo()
    {
        $hoy = date('Y-m-d');

        $sql = "UPDATE historial_rotaciones 
            SET dias_desde_ultimo = DATEDIFF(:hoy, fecha_ultimo_servicio)
            WHERE fecha_ultimo_servicio IS NOT NULL";

        $resultado = self::ejecutarQuery($sql, [':hoy' => $hoy]);

        error_log("📊 Días desde último servicio actualizados para todos los registros");

        return $resultado;
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
            p.tipo AS tipo_personal,
            g.nombre as grado,
            ts.nombre as servicio,
            ts.tipo_personal as tipo_servicio_requerido,
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
