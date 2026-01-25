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
     * Genera asignaciones para una semana completa (Lunes a Domingo)
     */
    public static function generarAsignacionesSemanal($fecha_inicio, $usuario_id = null)
    {
        $resultado = [
            'exito' => false,
            'mensaje' => '',
            'asignaciones' => [],
            'errores' => []
        ];

        try {
            // Verificar que sea lunes
            $fecha = new \DateTime($fecha_inicio);
            if ($fecha->format('N') != 1) {
                $resultado['mensaje'] = 'La fecha debe ser un lunes';
                return $resultado;
            }

            $asignaciones_creadas = [];

            // Generar para 7 días (lunes a domingo)
            for ($dia = 0; $dia < 7; $dia++) {
                $fecha_servicio = clone $fecha;
                $fecha_servicio->modify("+{$dia} days");
                $fecha_str = $fecha_servicio->format('Y-m-d');

                // Generar cada tipo de servicio para este día
                $servicios_dia = self::generarServiciosPorDia($fecha_str, $usuario_id);
                $asignaciones_creadas = array_merge($asignaciones_creadas, $servicios_dia);
            }

            $resultado['exito'] = true;
            $resultado['mensaje'] = 'Asignaciones generadas exitosamente';
            $resultado['asignaciones'] = $asignaciones_creadas;

            return $resultado;
        } catch (\Exception $e) {
            $resultado['mensaje'] = 'Error al generar asignaciones: ' . $e->getMessage();
            return $resultado;
        }
    }

    /**
     * Genera todos los servicios para un día específico
     */
    private static function generarServiciosPorDia($fecha, $usuario_id = null)
    {
        $asignaciones = [];

        // 1. TÁCTICO - 1 especialista
        $tactico = self::asignarTactico($fecha, $usuario_id);
        if ($tactico) $asignaciones[] = $tactico;

        // 2. RECONOCIMIENTO - 3 especialistas + 4 soldados
        $reconocimiento = self::asignarReconocimiento($fecha, $usuario_id);
        $asignaciones = array_merge($asignaciones, $reconocimiento);

        // 3. SERVICIO NOCTURNO - 4 soldados (del día anterior)
        $nocturno = self::asignarServicioNocturno($fecha, $usuario_id);
        $asignaciones = array_merge($asignaciones, $nocturno);

        // 4. BANDERÍN - Sargentos
        $banderin = self::asignarBanderin($fecha, $usuario_id);
        if ($banderin) $asignaciones[] = $banderin;

        // 5. CUARTELERO - Sargentos y Cabos
        $cuartelero = self::asignarCuartelero($fecha, $usuario_id);
        if ($cuartelero) $asignaciones[] = $cuartelero;

        return $asignaciones;
    }

    /**
     * Asigna servicio TÁCTICO (1 especialista disponible)
     */
    private static function asignarTactico($fecha, $usuario_id)
    {
        $sql = "SELECT p.id_personal
                FROM bhr_personal p
                LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
    AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin

                LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal 
                    AND hr.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'TÁCTICO')
                WHERE p.tipo = 'ESPECIALISTA'
                    AND p.activo = 1
                    AND cd.id_calendario IS NULL -- No está de descanso
                    AND p.id_personal NOT IN (
                        SELECT id_personal FROM asignaciones_servicio 
                        WHERE fecha_servicio = :fecha2
                    )
                ORDER BY 
                    COALESCE(hr.dias_desde_ultimo, 999) DESC,
                    COALESCE(hr.prioridad, 0) ASC,
                    RAND()
                LIMIT 1";

        $params = [':fecha' => $fecha, ':fecha2' => $fecha];
        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'TÁCTICO',
                $fecha,
                '00:00:00',
                '23:59:59',
                $usuario_id
            );
        }

        return null;
    }

    /**
     * Asigna RECONOCIMIENTO (3 especialistas + 4 soldados)
     */
    private static function asignarReconocimiento($fecha, $usuario_id)
    {
        $asignaciones = [];

        // 3 Especialistas
        $especialistas = self::obtenerPersonalDisponible($fecha, 'ESPECIALISTA', 3, 'RECONOCIMIENTO');
        foreach ($especialistas as $esp) {
            $asignacion = self::crearAsignacion(
                $esp['id_personal'],
                'RECONOCIMIENTO',
                $fecha,
                '06:00:00',
                '18:00:00',
                $usuario_id
            );
            if ($asignacion) $asignaciones[] = $asignacion;
        }

        // 4 Soldados (excluir los del nocturno del día anterior)
        $fecha_anterior = date('Y-m-d', strtotime($fecha . ' -1 day'));
        $soldados = self::obtenerPersonalDisponible($fecha, 'TROPA', 4, 'RECONOCIMIENTO', $fecha_anterior);
        foreach ($soldados as $sold) {
            $asignacion = self::crearAsignacion(
                $sold['id_personal'],
                'RECONOCIMIENTO',
                $fecha,
                '06:00:00',
                '18:00:00',
                $usuario_id
            );
            if ($asignacion) $asignaciones[] = $asignacion;
        }

        return $asignaciones;
    }

    /**
     * Asigna SERVICIO NOCTURNO (4 soldados)
     */
    private static function asignarServicioNocturno($fecha, $usuario_id)
    {
        $asignaciones = [];
        $soldados = self::obtenerPersonalDisponible($fecha, 'TROPA', 4, 'SERVICIO NOCTURNO');

        foreach ($soldados as $sold) {
            $asignacion = self::crearAsignacion(
                $sold['id_personal'],
                'SERVICIO NOCTURNO',
                $fecha,
                '18:00:00',
                '06:00:00',
                $usuario_id
            );
            if ($asignacion) {
                $asignaciones[] = $asignacion;

                // Crear exclusión para que no vaya a reconocimiento al día siguiente
                $fecha_siguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));
                self::crearExclusion($sold['id_personal'], 'RECONOCIMIENTO', $fecha_siguiente, 'Servicio nocturno previo');
            }
        }

        return $asignaciones;
    }

    /**
     * Asigna BANDERÍN (solo Sargentos)
     */
    private static function asignarBanderin($fecha, $usuario_id)
    {
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
                ORDER BY 
                    COALESCE(hr.dias_desde_ultimo, 999) DESC,
                    g.orden ASC,
                    RAND()
                LIMIT 1";

        $params = [':fecha' => $fecha, ':fecha2' => $fecha];
        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'BANDERÍN',
                $fecha,
                '00:00:00',
                '23:59:59',
                $usuario_id
            );
        }

        return null;
    }

    /**
     * Asigna CUARTELERO (Sargentos y Cabos)
     */
    private static function asignarCuartelero($fecha, $usuario_id)
    {
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
                ORDER BY 
                    COALESCE(hr.dias_desde_ultimo, 999) DESC,
                    g.orden ASC,
                    RAND()
                LIMIT 1";

        $params = [':fecha' => $fecha, ':fecha2' => $fecha];
        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'CUARTELERO',
                $fecha,
                '00:00:00',
                '23:59:59',
                $usuario_id
            );
        }

        return null;
    }

    /**
     * Obtiene personal disponible para un servicio
     */
    private static function obtenerPersonalDisponible($fecha, $tipo, $cantidad, $nombre_servicio, $fecha_exclusion = null)
    {
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
            ':cantidad' => $cantidad
        ];

        if ($fecha_exclusion) {
            $params[':fecha_exclusion'] = $fecha_exclusion;
        }

        return self::fetchArray($sql, $params);
    }

    /**
     * Crea una asignación de servicio
     */
    private static function crearAsignacion($id_personal, $nombre_servicio, $fecha, $hora_inicio, $hora_fin, $usuario_id)
    {
        try {
            // Obtener ID del tipo de servicio
            $tipo_servicio = self::fetchFirst(
                "SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = :nombre",
                [':nombre' => $nombre_servicio]
            );

            if (!$tipo_servicio) return null;

            $asignacion = new self([
                'id_personal' => $id_personal,
                'id_tipo_servicio' => $tipo_servicio['id_tipo_servicio'],
                'fecha_servicio' => $fecha,
                'hora_inicio' => $hora_inicio,
                'hora_fin' => $hora_fin,
                'estado' => 'PROGRAMADO',
                'created_by' => $usuario_id
            ]);

            $resultado = $asignacion->crear();

            // Actualizar historial de rotaciones
            self::actualizarHistorial($id_personal, $tipo_servicio['id_tipo_servicio'], $fecha);

            return $asignacion;
        } catch (\Exception $e) {
            error_log("Error creando asignación: " . $e->getMessage());
            return null;
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
    public static function obtenerAsignacionesSemana($fecha_inicio)
    {
        $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . ' +6 days'));

        $sql = "SELECT 
                    a.*,
                    CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
                    g.nombre as grado,
                    ts.nombre as servicio,
                    ts.tipo_personal
                FROM asignaciones_servicio a
                INNER JOIN bhr_personal p ON a.id_personal = p.id_personal
                INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
                INNER JOIN tipos_servicio ts ON a.id_tipo_servicio = ts.id_tipo_servicio
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
}
