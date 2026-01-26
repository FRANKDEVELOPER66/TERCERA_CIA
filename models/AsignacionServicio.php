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

        // 1. TÁCTICO - 1 especialista
        $tactico = self::asignarTactico($fecha, $usuario_id, $id_oficial);
        if ($tactico) $asignaciones[] = $tactico;

        // 2. RECONOCIMIENTO - 3 especialistas + 4 soldados
        $reconocimiento = self::asignarReconocimiento($fecha, $usuario_id, $id_oficial);
        $asignaciones = array_merge($asignaciones, $reconocimiento);

        // 3. SERVICIO NOCTURNO - 4 soldados (del día anterior)
        $nocturno = self::asignarServicioNocturno($fecha, $usuario_id, $id_oficial);
        $asignaciones = array_merge($asignaciones, $nocturno);

        // 4. BANDERÍN - Sargentos
        $banderin = self::asignarBanderin($fecha, $usuario_id, $id_oficial);
        if ($banderin) $asignaciones[] = $banderin;

        // 5. CUARTELERO - Sargentos y Cabos
        $cuartelero = self::asignarCuartelero($fecha, $usuario_id, $id_oficial);
        if ($cuartelero) $asignaciones[] = $cuartelero;

        // 6. SEMANA - Sargento 1ro. (solo lunes) ⬅️ AGREGAR ESTO
        $semana = self::asignarSemana($fecha, $usuario_id, $id_oficial);
        if ($semana) {
            if (is_array($semana)) {
                $asignaciones = array_merge($asignaciones, $semana);
            } else {
                $asignaciones[] = $semana;
            }
        }

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
        // Calcular el lunes de la semana actual
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');

        $sql = "SELECT p.id_personal
        FROM bhr_personal p
        LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
            AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
        LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal 
            AND hr.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'TÁCTICO')
        WHERE p.tipo = 'ESPECIALISTA'
            AND p.activo = 1
            AND cd.id_calendario IS NULL
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio = :fecha2
            )
            AND p.id_personal NOT IN (
                -- Excluir a quien tiene servicio SEMANA
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio = :fecha_lunes
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
        ORDER BY 
            COALESCE(hr.dias_desde_ultimo, 999) DESC,
            COALESCE(hr.prioridad, 0) ASC,
            RAND()
        LIMIT 1";

        $params = [
            ':fecha' => $fecha,
            ':fecha2' => $fecha,
            ':fecha_lunes' => $lunes_str
        ];
        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'TÁCTICO',
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
        $soldados = self::obtenerPersonalDisponible($fecha, 'TROPA', 3, 'SERVICIO NOCTURNO'); // ⬅️ CAMBIAR DE 4 A 3

        foreach ($soldados as $sold) {
            $asignacion = self::crearAsignacion(
                $sold['id_personal'],
                'SERVICIO NOCTURNO',
                $fecha,
                '21:00:00',
                '04:45:00',
                $usuario_id,
                $id_oficial
            );
            if ($asignacion) {
                $asignaciones[] = $asignacion;

                // Crear exclusión
                $fecha_siguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
                self::crearExclusion($sold['id_personal'], 'RECONOCIMIENTO', $fecha_siguiente, 'Servicio nocturno previo');
            }
        }

        return $asignaciones;
    }

    /**
     * Asigna BANDERÍN (solo Sargentos)
     */
    private static function asignarBanderin($fecha, $usuario_id, $id_oficial = null)
    {
        // Calcular el lunes de la semana actual
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');

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
                WHERE fecha_servicio = :fecha2
            )
            AND p.id_personal NOT IN (
                -- Excluir a quien tiene servicio SEMANA
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio = :fecha_lunes
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
            ':fecha_lunes' => $lunes_str
        ];
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
        // Calcular el lunes de la semana actual
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');

        $sql = "SELECT p.id_personal
        FROM bhr_personal p
        INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
        LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
            AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
        LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal 
            AND hr.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
        WHERE p.tipo = 'TROPA'
            AND (g.nombre LIKE 'Sargento%' OR g.nombre LIKE 'Cabo%')
            AND p.activo = 1
            AND cd.id_calendario IS NULL
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio = :fecha2
            )
            AND p.id_personal NOT IN (
                -- Excluir a quien tiene servicio SEMANA
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio = :fecha_lunes
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
            ':fecha_lunes' => $lunes_str
        ];
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
    private static function obtenerPersonalDisponible($fecha, $tipo, $cantidad, $nombre_servicio, $fecha_exclusion = null)
    {
        // Calcular el lunes de la semana actual
        $fecha_obj = new \DateTime($fecha);
        $dia_semana = $fecha_obj->format('N');
        $dias_desde_lunes = $dia_semana - 1;
        $fecha_lunes = clone $fecha_obj;
        $fecha_lunes->modify("-{$dias_desde_lunes} days");
        $lunes_str = $fecha_lunes->format('Y-m-d');

        $exclusion_sql = '';
        if ($fecha_exclusion) {
            $exclusion_sql = "AND p.id_personal NOT IN (
            SELECT a2.id_personal FROM asignaciones_servicio a2
            INNER JOIN tipos_servicio ts2 ON a2.id_tipo_servicio = ts2.id_tipo_servicio
            WHERE a2.fecha_servicio = :fecha_exclusion
            AND ts2.nombre = 'SERVICIO NOCTURNO'
        )";
        }

        $sql = "SELECT p.id_personal
            FROM bhr_personal p
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
                AND p.id_personal NOT IN (
                    SELECT id_personal FROM asignaciones_servicio 
                    WHERE fecha_servicio = :fecha3
                )
                AND p.id_personal NOT IN (
                    -- Excluir a quien tiene servicio SEMANA
                    SELECT id_personal FROM asignaciones_servicio 
                    WHERE fecha_servicio = :fecha_lunes
                    AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
                )
                {$exclusion_sql}
            ORDER BY 
                COALESCE(hr.dias_desde_ultimo, 999) DESC,
                COALESCE(hr.prioridad, 0) ASC,
                RAND()
            LIMIT :cantidad";

        $params = [
            ':fecha' => $fecha,
            ':fecha2' => $fecha,
            ':fecha3' => $fecha,
            ':tipo' => $tipo,
            ':servicio' => $nombre_servicio,
            ':servicio2' => $nombre_servicio,
            ':cantidad' => $cantidad,
            ':fecha_lunes' => $lunes_str
        ];

        if ($fecha_exclusion) {
            $params[':fecha_exclusion'] = $fecha_exclusion;
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
                return [
                    'error' => true,
                    'mensaje' => "Tipo de servicio no encontrado: {$nombre_servicio}"
                ];
            }

            $asignacion = new self([
                'id_personal' => $id_personal,
                'id_tipo_servicio' => $tipo_servicio['id_tipo_servicio'],
                'id_oficial_encargado' => $id_oficial, // <-- USAR EL PARÁMETRO
                'fecha_servicio' => $fecha,
                'hora_inicio' => $hora_inicio,
                'hora_fin' => $hora_fin,
                'estado' => 'PROGRAMADO',
                'created_by' => $usuario_id
            ]);

            $resultado = $asignacion->crear();

            if ($resultado['resultado'] === false || $resultado['resultado'] === 0) {
                return [
                    'error' => true,
                    'mensaje' => 'No se pudo crear en BD'
                ];
            }

            // Actualizar historial de rotaciones
            self::actualizarHistorial($id_personal, $tipo_servicio['id_tipo_servicio'], $fecha);

            return $asignacion;
        } catch (\Exception $e) {
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
