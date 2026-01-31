<?php

namespace Model;

use Model\ComisionOficial;

class AsignacionServicio extends ActiveRecord
{
    private static $grupos_disponibles_actuales = [];

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
     * ✅ Función auxiliar para construir filtro de grupos
     */
    private static function construirFiltroGrupos(&$params)
    {
        $filtro = '';

        if (!empty(self::$grupos_disponibles_actuales)) {
            $placeholders = [];
            foreach (self::$grupos_disponibles_actuales as $index => $id_grupo) {
                $key = ":grupo_disp_{$index}";
                $placeholders[] = $key;
                $params[$key] = (int)$id_grupo;
            }
            $filtro = "AND p.id_grupo_descanso IN (" . implode(',', $placeholders) . ")";
            error_log("🔍 Filtro de grupos aplicado: " . $filtro);
        }

        return $filtro;
    }

    // ========================================
    // 🆕 SISTEMA DE COMISIONES
    // ========================================

    /**
     * 🆕 Registra una comisión oficial y busca reemplazos automáticamente
     */
    public static function registrarComision($datos)
    {
        try {
            self::beginTransaction();

            error_log("🔷 === INICIANDO REGISTRO DE COMISIÓN ===");

            // 1. Validar datos básicos
            if (empty($datos['numero_oficio'])) {
                throw new \Exception('El número de oficio es obligatorio');
            }

            if (empty($datos['id_personal'])) {
                throw new \Exception('El ID de personal es obligatorio');
            }

            error_log("📋 Datos recibidos: " . json_encode($datos));

            // 2. Verificar que no exista oficio duplicado
            $existe_oficio = self::fetchFirst(
                "SELECT id_comision FROM comisiones_oficiales WHERE numero_oficio = :oficio",
                [':oficio' => $datos['numero_oficio']]
            );

            if ($existe_oficio) {
                throw new \Exception('Ya existe una comisión con este número de oficio: ' . $datos['numero_oficio']);
            }

            // 3. Crear registro de comisión usando el modelo
            error_log("💾 Creando registro de comisión...");

            $comision = new \Model\ComisionOficial([
                'id_personal' => $datos['id_personal'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'destino' => $datos['destino'] ?? 'Ciudad Capital',
                'numero_oficio' => $datos['numero_oficio'],
                'motivo' => $datos['motivo'] ?? '',
                'created_by' => $datos['created_by']
            ]);

            $resultado = $comision->crear();

            error_log("📋 Resultado de crear(): " . json_encode($resultado));

            if (!$resultado || !isset($resultado['id']) || !$resultado['id']) {
                throw new \Exception('No se pudo crear el registro de comisión - Sin ID retornado');
            }

            $id_comision = $resultado['id'];
            error_log("✅ Comisión registrada con ID: {$id_comision}");

            // 4. Buscar servicios afectados
            error_log("🔍 Buscando servicios afectados...");

            $servicios_afectados = self::fetchArray(
                "SELECT a.*, ts.nombre as nombre_servicio, ts.tipo_personal
                 FROM asignaciones_servicio a
                 INNER JOIN tipos_servicio ts ON a.id_tipo_servicio = ts.id_tipo_servicio
                 WHERE a.id_personal = :id_personal
                 AND a.fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                 AND a.estado = 'PROGRAMADO'
                 ORDER BY a.fecha_servicio ASC",
                [
                    ':id_personal' => $datos['id_personal'],
                    ':fecha_inicio' => $datos['fecha_inicio'],
                    ':fecha_fin' => $datos['fecha_fin']
                ]
            );

            error_log("📊 Servicios afectados encontrados: " . count($servicios_afectados));

            $reemplazos_realizados = [];
            $servicios_sin_reemplazo = [];

            // 5. Procesar cada servicio afectado
            // 5. Procesar cada servicio afectado
            foreach ($servicios_afectados as $servicio) {
                error_log("⚙️ === PROCESANDO SERVICIO ===");
                error_log("📋 Servicio: {$servicio['nombre_servicio']}");
                error_log("📅 Fecha: {$servicio['fecha_servicio']}");
                error_log("🆔 ID Asignación: {$servicio['id_asignacion']}");
                error_log("🎯 Tipo personal: " . ($servicio['tipo_personal'] ?? 'NULL'));

                // ✅ PASO 1: Marcar servicio original como REEMPLAZADO
                // Usar una verificación más robusta que no dependa de ejecutarQuery
                $actualizado = self::ejecutarQuery(
                    "UPDATE asignaciones_servicio 
     SET estado = 'REEMPLAZADO', 
         id_comision = :id_comision,
         observaciones_comision = :obs
     WHERE id_asignacion = :id_asignacion
     AND estado = 'PROGRAMADO'",
                    [
                        ':id_comision' => $id_comision,
                        ':obs' => "Comisión a {$datos['destino']} - Oficio {$datos['numero_oficio']}",
                        ':id_asignacion' => $servicio['id_asignacion']
                    ]
                );

                if ($actualizado && $actualizado['resultado']) {
                    error_log("✅ Servicio marcado como REEMPLAZADO (filas: {$actualizado['filas_afectadas']})");
                } else {
                    error_log("⚠️ UPDATE no confirmado, continuando con búsqueda de reemplazo");
                }

                // ✅ PASO 2: Buscar reemplazo (INDEPENDIENTE del resultado del UPDATE)
                error_log("🔍 Buscando reemplazo para: {$servicio['nombre_servicio']}");
                $reemplazo = self::buscarReemplazoInteligente($servicio, $datos['id_personal']);

                if ($reemplazo) {
                    error_log("✅ Reemplazo encontrado: {$reemplazo['nombre_completo']} (ID: {$reemplazo['id_personal']})");

                    // Verificar duplicado
                    $existe_reemplazo = self::fetchFirst(
                        "SELECT id_asignacion 
             FROM asignaciones_servicio 
             WHERE id_personal = :id_personal
             AND fecha_servicio = :fecha
             AND id_tipo_servicio = :tipo
             AND estado = 'PROGRAMADO'",
                        [
                            ':id_personal' => $reemplazo['id_personal'],
                            ':fecha' => $servicio['fecha_servicio'],
                            ':tipo' => $servicio['id_tipo_servicio']
                        ]
                    );

                    if ($existe_reemplazo) {
                        error_log("⚠️ Ya existe un reemplazo para este servicio, saltando...");
                        $servicios_sin_reemplazo[] = [
                            'fecha' => $servicio['fecha_servicio'],
                            'servicio' => $servicio['nombre_servicio']
                        ];
                        continue;
                    }

                    // Crear nueva asignación de reemplazo
                    $nueva_asignacion = new self([
                        'id_personal' => $reemplazo['id_personal'],
                        'id_tipo_servicio' => $servicio['id_tipo_servicio'],
                        'id_oficial_encargado' => $servicio['id_oficial_encargado'],
                        'fecha_servicio' => $servicio['fecha_servicio'],
                        'hora_inicio' => $servicio['hora_inicio'],
                        'hora_fin' => $servicio['hora_fin'],
                        'estado' => 'PROGRAMADO',
                        'observaciones' => "REEMPLAZO - Oficio {$datos['numero_oficio']}",
                        'created_by' => $datos['created_by']
                    ]);

                    $resultado_asignacion = $nueva_asignacion->crear();

                    if ($resultado_asignacion && $resultado_asignacion['resultado']) {
                        // Registrar en tabla de reemplazos
                        self::ejecutarQuery(
                            "INSERT INTO reemplazos_servicio 
                 (id_asignacion_original, id_personal_original, id_personal_reemplazo, 
                  id_comision, fecha_servicio, id_tipo_servicio, nombre_servicio,
                  servicios_acumulados_reemplazo, veces_reemplazo_ciclo, realizado_por)
                 VALUES (:id_orig, :pers_orig, :pers_reempl, :comision, :fecha, :tipo, :nombre,
                         :acum, :veces, :user)",
                            [
                                ':id_orig' => $servicio['id_asignacion'],
                                ':pers_orig' => $datos['id_personal'],
                                ':pers_reempl' => $reemplazo['id_personal'],
                                ':comision' => $id_comision,
                                ':fecha' => $servicio['fecha_servicio'],
                                ':tipo' => $servicio['id_tipo_servicio'],
                                ':nombre' => $servicio['nombre_servicio'],
                                ':acum' => $reemplazo['servicios_en_ciclo'] ?? 0,
                                ':veces' => $reemplazo['veces_reemplazo'] ?? 0,
                                ':user' => $datos['created_by']
                            ]
                        );

                        // Actualizar historial
                        self::actualizarHistorial(
                            $reemplazo['id_personal'],
                            $servicio['id_tipo_servicio'],
                            $servicio['fecha_servicio']
                        );

                        $reemplazos_realizados[] = [
                            'fecha' => $servicio['fecha_servicio'],
                            'servicio' => $servicio['nombre_servicio'],
                            'reemplazo' => $reemplazo['nombre_completo'],
                            'grado' => $reemplazo['grado']
                        ];
                    } else {
                        error_log("❌ Error al crear asignación de reemplazo");
                        $servicios_sin_reemplazo[] = [
                            'fecha' => $servicio['fecha_servicio'],
                            'servicio' => $servicio['nombre_servicio']
                        ];
                    }
                } else {
                    error_log("⚠️ No se encontró reemplazo disponible");
                    $servicios_sin_reemplazo[] = [
                        'fecha' => $servicio['fecha_servicio'],
                        'servicio' => $servicio['nombre_servicio']
                    ];
                }
            }

            // 6. Calcular días de comisión
            $fecha_inicio_obj = new \DateTime($datos['fecha_inicio']);
            $fecha_fin_obj = new \DateTime($datos['fecha_fin']);
            $dias_comision = $fecha_inicio_obj->diff($fecha_fin_obj)->days + 1;

            error_log("📊 Resumen: {$dias_comision} días, " . count($reemplazos_realizados) . " reemplazos, " . count($servicios_sin_reemplazo) . " sin reemplazo");

            self::commit();
            error_log("✅ Transacción completada exitosamente");

            return [
                'exito' => true,
                'mensaje' => 'Comisión procesada exitosamente',
                'data' => [
                    'id_comision' => $id_comision,
                    'numero_oficio' => $datos['numero_oficio'],
                    'dias_comision' => $dias_comision,
                    'servicios_afectados' => count($servicios_afectados),
                    'reemplazos_realizados' => count($reemplazos_realizados),
                    'servicios_sin_reemplazo' => count($servicios_sin_reemplazo),
                    'detalles_reemplazos' => $reemplazos_realizados,
                    'detalles_sin_reemplazo' => $servicios_sin_reemplazo
                ]
            ];
        } catch (\Exception $e) {
            self::rollback();
            error_log("❌ ERROR en registrarComision: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            return [
                'exito' => false,
                'mensaje' => 'Error al procesar comisión: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 🧠 ALGORITMO DE REEMPLAZO INTELIGENTE - ✅ CORREGIDO
     * Busca el mejor candidato siguiendo reglas estrictas de equidad
     */
    private static function buscarReemplazoInteligente($servicio_afectado, $id_comisionado)
    {
        $fecha_servicio = $servicio_afectado['fecha_servicio'];
        $id_tipo_servicio = $servicio_afectado['id_tipo_servicio'];
        $nombre_servicio = $servicio_afectado['nombre_servicio'];
        $tipo_personal_db = $servicio_afectado['tipo_personal'] ?? null;

        error_log("🔍 === BÚSQUEDA DE REEMPLAZO ===");
        error_log("📋 Servicio: {$nombre_servicio}");
        error_log("📅 Fecha: {$fecha_servicio}");
        error_log("🎯 Tipo personal DB: {$tipo_personal_db}");
        error_log("👤 ID Comisionado: {$id_comisionado}");

        // Determinar tipo de búsqueda
        $tipo_busqueda = null;
        switch ($nombre_servicio) {
            case 'TACTICO':
                $tipo_busqueda = 'ESPECIALISTA';
                break;
            case 'TACTICO TROPA':
                $tipo_busqueda = 'TROPA';
                break;
            case 'RECONOCIMIENTO':
                $tipo_busqueda = 'ESPECIALISTA';
                break;
            case 'SERVICIO NOCTURNO':
            case 'CUARTELERO':
            case 'BANDERÍN':
            case 'Semana':
                $tipo_busqueda = 'TROPA';
                break;
            default:
                if ($tipo_personal_db === 'AMBOS' || $tipo_personal_db === null) {
                    $tipo_busqueda = 'TROPA';
                } else {
                    $tipo_busqueda = $tipo_personal_db;
                }
                break;
        }

        error_log("🎯 Tipo de búsqueda determinado: {$tipo_busqueda}");

        $grados_permitidos = self::obtenerGradosPermitidos($nombre_servicio, $tipo_busqueda);
        error_log("📋 Grados permitidos IDs: " . json_encode($grados_permitidos));

        $fecha_ayer = date('Y-m-d', strtotime($fecha_servicio . ' -1 day'));
        $fecha_manana = date('Y-m-d', strtotime($fecha_servicio . ' +1 day'));

        // Calcular rango del ciclo
        $sql_inicio_ciclo = "SELECT MIN(fecha_servicio) as inicio 
                     FROM asignaciones_servicio 
                     WHERE fecha_servicio <= :fecha_ciclo_calc 
                     AND DATEDIFF(:fecha_ciclo_calc2, fecha_servicio) < 10";

        $ciclo = self::fetchFirst($sql_inicio_ciclo, [
            ':fecha_ciclo_calc' => $fecha_servicio,
            ':fecha_ciclo_calc2' => $fecha_servicio
        ]);
        $fecha_inicio_ciclo = ($ciclo && $ciclo['inicio']) ? $ciclo['inicio'] : $fecha_servicio;
        $fecha_fin_ciclo = date('Y-m-d', strtotime($fecha_inicio_ciclo . ' +9 days'));

        error_log("📅 Rango ciclo: {$fecha_inicio_ciclo} a {$fecha_fin_ciclo}");

        // Construir params con nombres únicos
        $params = [
            ':fecha_base'          => $fecha_servicio,
            ':fecha_ayer'          => $fecha_ayer,
            ':fecha_manana'        => $fecha_manana,
            ':tipo'                => $tipo_busqueda,
            ':id_comisionado'      => $id_comisionado,
            ':fecha_inicio_ciclo'  => $fecha_inicio_ciclo,
            ':fecha_fin_ciclo'     => $fecha_fin_ciclo,
            // Duplicados con nombres únicos para cada uso en subqueries
            ':fecha_sub1'          => $fecha_servicio,
            ':fecha_sub2_ayer'     => $fecha_ayer,
            ':fecha_sub3'          => $fecha_servicio,
            ':fecha_sub4'          => $fecha_servicio,
            ':fecha_sub5_inicio'   => $fecha_inicio_ciclo,
            ':fecha_sub5_fin'      => $fecha_fin_ciclo,
            ':fecha_sub6_inicio'   => $fecha_inicio_ciclo,
            ':fecha_sub6_fin'      => $fecha_fin_ciclo,
            ':fecha_descanso'      => $fecha_servicio,
        ];

        // Filtro grados
        $filtro_grados = '';
        if (!empty($grados_permitidos)) {
            $placeholders_grados = [];
            foreach ($grados_permitidos as $idx => $id_grado) {
                $key = ":grado_{$idx}";
                $placeholders_grados[] = $key;
                $params[$key] = $id_grado;
            }
            $filtro_grados = "AND p.id_grado IN (" . implode(',', $placeholders_grados) . ")";
            error_log("🎓 Filtro grados: {$filtro_grados}");
        }

        // Filtro grupos
        $filtro_grupos = self::construirFiltroGrupos($params);

        // CONSULTA PRINCIPAL - todos los placeholders únicos
        $sql = "SELECT 
        p.id_personal,
        CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
        g.nombre as grado,
        p.tipo as tipo_personal,
        
        (SELECT COUNT(*) FROM asignaciones_servicio a_ciclo
         WHERE a_ciclo.id_personal = p.id_personal
         AND a_ciclo.fecha_servicio BETWEEN :fecha_sub6_inicio AND :fecha_sub6_fin
         AND a_ciclo.estado = 'PROGRAMADO'
        ) as servicios_en_ciclo,
        
        COALESCE(hr.dias_desde_ultimo, 999) as dias_desde_ultimo,
        
        (SELECT COUNT(*) FROM reemplazos_servicio rs
         WHERE rs.id_personal_reemplazo = p.id_personal
         AND rs.fecha_servicio BETWEEN :fecha_sub5_inicio AND :fecha_sub5_fin
        ) as veces_reemplazo,
        
        COALESCE(hr.compensacion_pendiente, FALSE) as tiene_compensacion,
        COALESCE(hr.servicios_como_reemplazo, 0) as total_reemplazos
        
    FROM bhr_personal p
    INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
    LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
        AND :fecha_descanso BETWEEN cd.fecha_inicio AND cd.fecha_fin
    LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal
    WHERE p.tipo = :tipo
        AND p.activo = 1
        AND p.id_personal != :id_comisionado
        AND cd.id_calendario IS NULL
        {$filtro_grados}
        {$filtro_grupos}
        AND (SELECT COUNT(*) FROM asignaciones_servicio a1
             WHERE a1.id_personal = p.id_personal 
             AND a1.fecha_servicio = :fecha_sub1
             AND a1.estado = 'PROGRAMADO'
        ) = 0
        AND (SELECT COUNT(*) FROM asignaciones_servicio a2
             INNER JOIN tipos_servicio ts2 ON a2.id_tipo_servicio = ts2.id_tipo_servicio
             WHERE a2.id_personal = p.id_personal 
             AND a2.fecha_servicio = :fecha_sub2_ayer
             AND ts2.nombre = 'SERVICIO NOCTURNO'
             AND a2.estado = 'PROGRAMADO'
        ) = 0
        AND (SELECT COUNT(*) FROM asignaciones_servicio a3
             WHERE a3.id_personal = p.id_personal 
             AND a3.fecha_servicio = :fecha_sub3
             AND a3.estado = 'PROGRAMADO'
        ) = 0
        AND (SELECT COUNT(*) FROM comisiones_oficiales co
             WHERE co.id_personal = p.id_personal
             AND :fecha_sub4 BETWEEN co.fecha_inicio AND co.fecha_fin
             AND co.estado = 'ACTIVA'
        ) = 0
        AND (SELECT COUNT(*) FROM asignaciones_servicio a_ciclo2
             WHERE a_ciclo2.id_personal = p.id_personal
             AND a_ciclo2.fecha_servicio BETWEEN :fecha_inicio_ciclo AND :fecha_fin_ciclo
             AND a_ciclo2.estado = 'PROGRAMADO'
        ) < 3
    ORDER BY
        tiene_compensacion DESC,
        servicios_en_ciclo ASC,
        veces_reemplazo ASC,
        total_reemplazos ASC,
        dias_desde_ultimo DESC,
        RAND()
    LIMIT 1";

        error_log("🔍 SQL COMPLETO:");
        error_log($sql);
        error_log("📊 Parámetros: " . json_encode($params));

        $reemplazo = self::fetchFirst($sql, $params);

        if ($reemplazo) {
            error_log("✅ REEMPLAZO ENCONTRADO:");
            error_log("   👤 {$reemplazo['nombre_completo']}");
            error_log("   🎖️ Grado: {$reemplazo['grado']}");
            error_log("   📊 Tipo: {$reemplazo['tipo_personal']}");
            error_log("   📈 Servicios en ciclo: {$reemplazo['servicios_en_ciclo']}");
        } else {
            error_log("❌ NO SE ENCONTRÓ REEMPLAZO");
        }

        return $reemplazo;
    }

    /**
     * ✅ Obtiene los grados permitidos según el tipo de servicio
     */
    private static function obtenerGradosPermitidos($nombre_servicio, $tipo_personal)
    {
        $grados = [];

        error_log("🎓 obtenerGradosPermitidos - Servicio: {$nombre_servicio}, Tipo: {$tipo_personal}");

        switch ($nombre_servicio) {
            case 'TACTICO':
                if ($tipo_personal === 'ESPECIALISTA') {
                    $grados = self::fetchArray(
                        "SELECT id_grado FROM bhr_grados WHERE nombre IN ('Soldado de Segunda', 'Soldado de Primera')",
                        []
                    );
                }
                break;

            case 'TACTICO TROPA':
                $grados = self::fetchArray(
                    "SELECT id_grado FROM bhr_grados WHERE nombre LIKE 'Sargento%'",
                    []
                );
                break;

            case 'BANDERÍN':
            case 'CUARTELERO':
                $grados = self::fetchArray(
                    "SELECT id_grado FROM bhr_grados WHERE nombre LIKE 'Sargento%' OR nombre LIKE 'Cabo%'",
                    []
                );
                break;

            case 'RECONOCIMIENTO':
                if ($tipo_personal === 'ESPECIALISTA') {
                    $grados = self::fetchArray(
                        "SELECT id_grado FROM bhr_grados WHERE nombre IN ('Soldado de Segunda', 'Soldado de Primera')",
                        []
                    );
                } else {
                    $grados = self::fetchArray(
                        "SELECT id_grado FROM bhr_grados WHERE nombre IN ('Soldado de Segunda', 'Soldado de Primera', 'Cabo')",
                        []
                    );
                }
                break;

            case 'SERVICIO NOCTURNO':
                $grados = self::fetchArray(
                    "SELECT id_grado FROM bhr_grados WHERE nombre = 'Sargento 2do.' OR nombre LIKE 'Cabo%'",
                    []
                );
                break;

            case 'Semana':
                $grados = self::fetchArray(
                    "SELECT id_grado FROM bhr_grados WHERE nombre = 'Sargento 1ro.'",
                    []
                );
                break;
        }

        $grados_ids = array_column($grados, 'id_grado');
        error_log("✅ Grados IDs retornados: " . json_encode($grados_ids));

        return $grados_ids;
    }

    /**
     * ✅ COMPLETO: Genera 10 días consecutivos
     */
    public static function generarAsignacionesSemanal($fecha_inicio, $usuario_id = null, $grupos_disponibles = [])
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

            // ✨ NUEVO: Validar que no haya traslape con otros ciclos
            $fecha_fin_ciclo = date('Y-m-d', strtotime($fecha_inicio . ' +9 days'));

            $traslape = self::verificarTraslapeCiclo($fecha_inicio, $fecha_fin_ciclo);
            if ($traslape) {
                $resultado['mensaje'] = 'Ya existe un ciclo en este rango de fechas. Debe eliminar el ciclo existente primero.';
                $resultado['debug']['traslape'] = $traslape;
                return $resultado;
            }

            // Guardar grupos disponibles
            self::$grupos_disponibles_actuales = $grupos_disponibles;
            error_log("🎯 Grupos disponibles para este ciclo de 10 días: " . json_encode($grupos_disponibles));

            // 1️⃣ Obtener personal afectado
            error_log("📋 Obteniendo personal afectado por regeneración...");
            $personal_a_recalcular = self::fetchArray(
                "SELECT DISTINCT id_personal 
                 FROM asignaciones_servicio 
                 WHERE fecha_servicio BETWEEN :inicio AND :fin",
                [':inicio' => $fecha_inicio, ':fin' => $fecha_fin_ciclo]
            );

            error_log("👥 Personal afectado: " . count($personal_a_recalcular) . " personas");

            // 2️⃣ Eliminar asignaciones antiguas del ciclo
            error_log("🗑️ Limpiando asignaciones de {$fecha_inicio} a {$fecha_fin_ciclo}");
            $eliminadas = self::ejecutarQuery(
                "DELETE FROM asignaciones_servicio 
                 WHERE fecha_servicio BETWEEN :inicio AND :fin",
                [':inicio' => $fecha_inicio, ':fin' => $fecha_fin_ciclo]
            );

            error_log("✅ Asignaciones eliminadas: " . ($eliminadas['resultado'] ?? 0));

            // 3️⃣ Recalcular historial
            foreach ($personal_a_recalcular as $persona) {
                self::recalcularHistorialPersona($persona['id_personal']);
            }
            error_log("✅ Historial recalculado");

            // 4️⃣ Actualizar días desde último servicio
            self::actualizarDiasDesdeUltimo();

            $asignaciones_creadas = [];

            // 5️⃣ Generar asignaciones para los 10 días
            for ($dia = 0; $dia < 10; $dia++) {
                $fecha_servicio = clone $fecha;
                $fecha_servicio->modify("+{$dia} days");
                $fecha_str = $fecha_servicio->format('Y-m-d');

                try {
                    error_log("📅 Generando servicios para: {$fecha_str} (Día " . ($dia + 1) . "/10)");
                    $servicios_dia = self::generarServiciosPorDia($fecha_str, $usuario_id, $dia, $fecha_inicio);

                    $asignaciones_creadas = array_merge($asignaciones_creadas, $servicios_dia);

                    $resultado['detalle_por_dia'][$fecha_str] = [
                        'fecha' => $fecha_str,
                        'total' => count($servicios_dia),
                        'exito' => true
                    ];

                    error_log("✅ Día completado: {$fecha_str} - " . count($servicios_dia) . " asignaciones");
                } catch (\Exception $e) {
                    error_log("❌ Error en {$fecha_str}: " . $e->getMessage());
                    error_log("🔄 Haciendo rollback del ciclo completo...");

                    self::ejecutarQuery(
                        "DELETE FROM asignaciones_servicio 
                         WHERE fecha_servicio BETWEEN :inicio AND :fecha_actual",
                        [':inicio' => $fecha_inicio, ':fecha_actual' => $fecha_str]
                    );

                    foreach ($personal_a_recalcular as $persona) {
                        self::recalcularHistorialPersona($persona['id_personal']);
                    }

                    $resultado['errores'][] = [
                        'fecha' => $fecha_str,
                        'error' => $e->getMessage()
                    ];

                    throw new \Exception("Error en {$fecha_str}: " . $e->getMessage());
                }
            }

            // 6️⃣ Éxito
            $resultado['exito'] = true;
            $resultado['mensaje'] = 'Asignaciones generadas exitosamente para el ciclo de 10 días';
            $resultado['asignaciones'] = $asignaciones_creadas;

            error_log("🎉 GENERACIÓN COMPLETA: " . count($asignaciones_creadas) . " asignaciones totales");

            return $resultado;
        } catch (\Exception $e) {
            error_log("💥 ERROR FATAL: " . $e->getMessage());

            $resultado['mensaje'] = $e->getMessage();
            $resultado['debug']['excepcion'] = [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine()
            ];
            return $resultado;
        }
    }

    /**
     * ✨ NUEVA FUNCIÓN: Verificar si hay traslape de ciclos
     */
    private static function verificarTraslapeCiclo($fecha_inicio, $fecha_fin)
    {
        $sql = "SELECT COUNT(*) as total
                FROM asignaciones_servicio
                WHERE fecha_servicio BETWEEN :inicio AND :fin";

        $resultado = self::fetchFirst($sql, [
            ':inicio' => $fecha_inicio,
            ':fin' => $fecha_fin
        ]);

        if ($resultado && $resultado['total'] > 0) {
            $detalle = self::fetchFirst(
                "SELECT MIN(fecha_servicio) as fecha_inicio, MAX(fecha_servicio) as fecha_fin
                 FROM asignaciones_servicio
                 WHERE fecha_servicio BETWEEN :inicio AND :fin",
                [':inicio' => $fecha_inicio, ':fin' => $fecha_fin]
            );

            return [
                'inicio' => $detalle['fecha_inicio'],
                'fin' => $detalle['fecha_fin']
            ];
        }

        return null;
    }

    /**
     * ✅ MODIFICADO: Ahora recibe el día del ciclo y fecha de inicio
     */
    private static function generarServiciosPorDia($fecha, $usuario_id, $dia_ciclo, $fecha_inicio_ciclo)
    {
        $asignaciones = [];
        $oficial = self::obtenerOficialDisponible($fecha);
        $id_oficial = $oficial ? $oficial['id_personal'] : null;

        error_log("🔷 === GENERANDO SERVICIOS PARA: {$fecha} (Día " . ($dia_ciclo + 1) . "/10) ===");

        try {
            // 1. SEMANA - Solo el primer día del ciclo
            if ($dia_ciclo === 0) {
                $semana = self::asignarSemana($fecha, $usuario_id, $id_oficial, $fecha_inicio_ciclo);
                if ($semana && !is_array($semana)) {
                    $asignaciones[] = $semana;
                    error_log("✅ SEMANA asignado (10 días completos)");
                }
            }

            // 2. TÁCTICO
            $tactico = self::asignarTactico($fecha, $usuario_id, $id_oficial);
            if ($tactico && !is_array($tactico)) {
                $asignaciones[] = $tactico;
                error_log("✅ TÁCTICO asignado");
            } else {
                throw new \Exception("No hay especialistas disponibles para TÁCTICO en {$fecha}");
            }

            // 2.5 TÁCTICO TROPA
            $tactico_tropa = self::asignarTacticoTropa($fecha, $usuario_id, $id_oficial);
            if ($tactico_tropa && !is_array($tactico_tropa)) {
                $asignaciones[] = $tactico_tropa;
                error_log("✅ TÁCTICO TROPA asignado");
            } else {
                throw new \Exception("No hay sargentos disponibles para TÁCTICO TROPA en {$fecha}");
            }

            // 3. RECONOCIMIENTO
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
            $es_primer_dia = ($dia_ciclo === 0);
            $total_esperado = $es_primer_dia ? 13 : 12;
            $total_generado = count($asignaciones);

            error_log("📊 RESUMEN {$fecha}: {$total_generado}/{$total_esperado} asignaciones");

            if ($total_generado < $total_esperado) {
                throw new \Exception("Asignaciones incompletas para {$fecha}: {$total_generado}/{$total_esperado}");
            }

            return $asignaciones;
        } catch (\Exception $e) {
            error_log("❌ ERROR en generarServiciosPorDia({$fecha}): " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================
    // ✅ FUNCIONES DE ASIGNACIÓN COMPLETAS
    // ========================================

    private static function obtenerOficialDisponible($fecha)
    {
        $params = [':fecha' => $fecha];
        $filtro_grupos = self::construirFiltroGrupos($params);

        $sql = "SELECT p.id_personal, g.nombre as grado, g.orden
            FROM bhr_personal p
            INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
            LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
                AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
            WHERE p.tipo = 'OFICIAL'
                AND p.activo = 1
                AND cd.id_calendario IS NULL
                {$filtro_grupos}
            ORDER BY g.orden ASC
            LIMIT 1";

        return self::fetchFirst($sql, $params);
    }

    /**
     * ✅ COMPLETO: SEMANA ahora cubre 10 días
     */
    private static function asignarSemana($fecha, $usuario_id, $id_oficial, $fecha_inicio_ciclo)
    {
        $fecha_fin = date('Y-m-d', strtotime($fecha_inicio_ciclo . ' +9 days'));
        $params = [
            ':fecha' => $fecha,
            ':fecha_inicio' => $fecha_inicio_ciclo,
            ':fecha_fin' => $fecha_fin
        ];

        $filtro_grupos = self::construirFiltroGrupos($params);

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
            {$filtro_grupos}
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
        ORDER BY 
            COALESCE(hr.dias_desde_ultimo, 999) DESC,
            RAND()
        LIMIT 1";

        $resultado = self::fetchFirst($sql, $params);

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

    /**
     * ✅ COMPLETO: TÁCTICO con rangos de 10 días
     */
    private static function asignarTactico($fecha, $usuario_id, $id_oficial = null)
    {
        // Calcular inicio del ciclo actual
        $sql_inicio_ciclo = "SELECT MIN(fecha_servicio) as inicio
                            FROM asignaciones_servicio
                            WHERE fecha_servicio <= :fecha
                            AND DATEDIFF(:fecha, fecha_servicio) < 10";

        $ciclo = self::fetchFirst($sql_inicio_ciclo, [':fecha' => $fecha]);

        if ($ciclo && $ciclo['inicio']) {
            $fecha_inicio_ciclo = $ciclo['inicio'];
        } else {
            $fecha_inicio_ciclo = $fecha;
        }

        $fecha_fin_ciclo = date('Y-m-d', strtotime($fecha_inicio_ciclo . ' +9 days'));

        $params = [
            ':fecha' => $fecha,
            ':fecha_inicio' => $fecha_inicio_ciclo,
            ':fecha_fin' => $fecha_fin_ciclo,
            ':fecha_inicio_count' => $fecha_inicio_ciclo,
            ':fecha_fin_count' => $fecha_fin_ciclo,
            ':fecha_cuartelero' => $fecha
        ];

        $filtro_grupos = self::construirFiltroGrupos($params);

        $sql = "SELECT p.id_personal,
            (SELECT COUNT(*) 
             FROM asignaciones_servicio a_count 
             INNER JOIN tipos_servicio ts_count ON a_count.id_tipo_servicio = ts_count.id_tipo_servicio
             WHERE a_count.id_personal = p.id_personal 
             AND a_count.fecha_servicio BETWEEN :fecha_inicio_count AND :fecha_fin_count
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
            {$filtro_grupos}
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio = :fecha_cuartelero
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
            )
        ORDER BY 
            veces_tactico ASC,
            COALESCE(hr.dias_desde_ultimo, 999) DESC,
            RAND()
        LIMIT 1";

        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'TACTICO',
                $fecha,
                '21:00:00',
                '20:45:00',
                $usuario_id,
                $id_oficial
            );
        }

        return null;
    }

    /**
     * ✅ COMPLETO: TÁCTICO TROPA con rangos de 10 días
     */
    private static function asignarTacticoTropa($fecha, $usuario_id, $id_oficial = null)
    {
        // Calcular rango del ciclo
        $sql_inicio_ciclo = "SELECT MIN(fecha_servicio) as inicio 
                            FROM asignaciones_servicio 
                            WHERE fecha_servicio <= :fecha 
                            AND DATEDIFF(:fecha, fecha_servicio) < 10";

        $ciclo = self::fetchFirst($sql_inicio_ciclo, [':fecha' => $fecha]);
        $fecha_inicio_ciclo = ($ciclo && $ciclo['inicio']) ? $ciclo['inicio'] : $fecha;
        $fecha_fin_ciclo = date('Y-m-d', strtotime($fecha_inicio_ciclo . ' +9 days'));

        $params = [
            ':fecha' => $fecha,
            ':fecha_inicio' => $fecha_inicio_ciclo,
            ':fecha_fin' => $fecha_fin_ciclo,
            ':fecha_inicio_count' => $fecha_inicio_ciclo,
            ':fecha_fin_count' => $fecha_fin_ciclo,
            ':fecha_cuartelero' => $fecha
        ];

        $filtro_grupos = self::construirFiltroGrupos($params);

        $sql = "SELECT p.id_personal,
        (SELECT COUNT(*) 
         FROM asignaciones_servicio a_count 
         INNER JOIN tipos_servicio ts_count ON a_count.id_tipo_servicio = ts_count.id_tipo_servicio
         WHERE a_count.id_personal = p.id_personal 
         AND a_count.fecha_servicio BETWEEN :fecha_inicio_count AND :fecha_fin_count
         AND ts_count.nombre = 'TACTICO TROPA'
        ) as veces_tactico_tropa
        FROM bhr_personal p
        INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
        LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
            AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
        LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal 
            AND hr.id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'TACTICO TROPA')
        WHERE p.tipo = 'TROPA'
            AND g.nombre LIKE 'Sargento%'
            AND p.activo = 1
            AND cd.id_calendario IS NULL
            {$filtro_grupos}
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio = :fecha_cuartelero
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
            )
        ORDER BY 
            veces_tactico_tropa ASC,
            COALESCE(hr.dias_desde_ultimo, 999) DESC,
            RAND()
        LIMIT 1";

        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'TACTICO TROPA',
                $fecha,
                '21:00:00',
                '20:45:00',
                $usuario_id,
                $id_oficial
            );
        }

        return null;
    }

    /**
     * ✅ COMPLETO: RECONOCIMIENTO con rangos de 10 días
     */
    private static function asignarReconocimiento($fecha, $usuario_id, $id_oficial = null)
    {
        $asignaciones = [];
        $logs = [];
        $ids_ya_asignados = [];

        $logs[] = "🔍 === ASIGNANDO RECONOCIMIENTO para {$fecha} ===";

        // ESPECIALISTAS
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

            // Calcular rango del ciclo para sargentos
            $sql_inicio_ciclo = "SELECT MIN(fecha_servicio) as inicio 
                                FROM asignaciones_servicio 
                                WHERE fecha_servicio <= :fecha 
                                AND DATEDIFF(:fecha, fecha_servicio) < 10";

            $ciclo = self::fetchFirst($sql_inicio_ciclo, [':fecha' => $fecha]);
            $fecha_inicio_ciclo = ($ciclo && $ciclo['inicio']) ? $ciclo['inicio'] : $fecha;
            $fecha_fin_ciclo = date('Y-m-d', strtotime($fecha_inicio_ciclo . ' +9 days'));

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

            $params_sargentos = array_merge([
                ':fecha' => $fecha,
                ':fecha2' => $fecha,
                ':cantidad' => $sargentos_necesarios,
                ':fecha_cuartelero' => $fecha,
                ':fecha_inicio' => $fecha_inicio_ciclo,
                ':fecha_fin' => $fecha_fin_ciclo
            ], $params_excluir);

            $filtro_grupos = self::construirFiltroGrupos($params_sargentos);

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
                    {$filtro_grupos}
                    {$filtro_excluir}
                    AND p.id_personal NOT IN (
                        SELECT id_personal FROM asignaciones_servicio 
                        WHERE fecha_servicio = :fecha2
                    )
                    AND p.id_personal NOT IN (
                        SELECT id_personal FROM asignaciones_servicio 
                        WHERE fecha_servicio = :fecha_cuartelero
                        AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
                    )
                    AND p.id_personal NOT IN (
                        SELECT id_personal FROM asignaciones_servicio 
                        WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                        AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
                    )
                 ORDER BY g.orden ASC, RAND()
                 LIMIT :cantidad",
                $params_sargentos
            );

            $logs[] = "👷 INTENTO 3 - Sargentos de reemplazo: " . count($sargentos) . "/{$sargentos_necesarios}";

            foreach ($sargentos as $sarg) {
                $especialistas[] = $sarg;
                $logs[] = "  → Sargento reemplazo: {$sarg['nombres']} {$sarg['apellidos']} (ID: {$sarg['id_personal']})";
            }
        }

        // ASIGNAR ESPECIALISTAS
        $logs[] = "📝 Asignando " . count($especialistas) . " especialistas...";

        foreach ($especialistas as $esp) {
            $nombre = ($esp['nombres'] ?? '') . ' ' . ($esp['apellidos'] ?? '');

            $asignacion = self::crearAsignacion(
                $esp['id_personal'],
                'RECONOCIMIENTO',
                $fecha,
                '06:00:00',
                '18:00:00',
                $usuario_id,
                $id_oficial
            );

            if (is_array($asignacion) && isset($asignacion['error'])) {
                $logs[] = "❌ ERROR al asignar especialista {$nombre} (ID: {$esp['id_personal']}): " . ($asignacion['mensaje'] ?? 'Error desconocido');
            } elseif ($asignacion && !is_array($asignacion)) {
                $asignaciones[] = $asignacion;
                $ids_ya_asignados[] = $esp['id_personal'];
                $logs[] = "✅ Especialista asignado: {$nombre} (ID: {$esp['id_personal']})";
            } else {
                $logs[] = "⚠️ Asignación NULL para especialista {$nombre} (ID: {$esp['id_personal']})";
            }
        }

        // SOLDADOS
        $fecha_anterior = date('Y-m-d', strtotime($fecha . ' -1 day'));

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
                $ids_ya_asignados[] = $sold['id_personal'];
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
     * ✅ COMPLETO: BANDERÍN con rangos de 10 días
     */
    private static function asignarBanderin($fecha, $usuario_id, $id_oficial = null)
    {
        // Calcular rango del ciclo
        $sql_inicio_ciclo = "SELECT MIN(fecha_servicio) as inicio 
                            FROM asignaciones_servicio 
                            WHERE fecha_servicio <= :fecha 
                            AND DATEDIFF(:fecha, fecha_servicio) < 10";

        $ciclo = self::fetchFirst($sql_inicio_ciclo, [':fecha' => $fecha]);
        $fecha_inicio_ciclo = ($ciclo && $ciclo['inicio']) ? $ciclo['inicio'] : $fecha;
        $fecha_fin_ciclo = date('Y-m-d', strtotime($fecha_inicio_ciclo . ' +9 days'));

        $params = [
            ':fecha' => $fecha,
            ':fecha2' => $fecha,
            ':fecha3' => $fecha,
            ':fecha_inicio' => $fecha_inicio_ciclo,
            ':fecha_fin' => $fecha_fin_ciclo,
            ':fecha_cuartelero' => $fecha
        ];

        $filtro_grupos = self::construirFiltroGrupos($params);

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
            {$filtro_grupos}
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN DATE_SUB(:fecha2, INTERVAL 2 DAY) AND DATE_SUB(:fecha3, INTERVAL 1 DAY)
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'BANDERÍN')
            )
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio = :fecha_cuartelero
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
            )   
        ORDER BY 
            COALESCE(hr.dias_desde_ultimo, 999) DESC,
            g.orden ASC,
            RAND()
        LIMIT 1";

        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            return self::crearAsignacion(
                $resultado['id_personal'],
                'BANDERÍN',
                $fecha,
                '06:00:00',
                '20:00:00',
                $usuario_id,
                $id_oficial
            );
        }

        return null;
    }

    /**
     * ✅ COMPLETO: CUARTELERO con rangos de 10 días
     */
    private static function asignarCuartelero($fecha, $usuario_id, $id_oficial = null)
    {
        // Calcular rango del ciclo
        $sql_inicio_ciclo = "SELECT MIN(fecha_servicio) as inicio 
                            FROM asignaciones_servicio 
                            WHERE fecha_servicio <= :fecha 
                            AND DATEDIFF(:fecha, fecha_servicio) < 10";

        $ciclo = self::fetchFirst($sql_inicio_ciclo, [':fecha' => $fecha]);
        $fecha_inicio_ciclo = ($ciclo && $ciclo['inicio']) ? $ciclo['inicio'] : $fecha;
        $fecha_fin_ciclo = date('Y-m-d', strtotime($fecha_inicio_ciclo . ' +9 days'));

        $params = [
            ':fecha' => $fecha,
            ':fecha2' => $fecha,
            ':fecha3' => $fecha,
            ':fecha_check' => $fecha,
            ':fecha_inicio' => $fecha_inicio_ciclo,
            ':fecha_fin' => $fecha_fin_ciclo,
            ':fecha_inicio_count' => $fecha_inicio_ciclo,
            ':fecha_fin_count' => $fecha_fin_ciclo
        ];

        $filtro_grupos = self::construirFiltroGrupos($params);

        $sql = "SELECT p.id_personal,
        (SELECT COUNT(*) 
         FROM asignaciones_servicio a_cua
         INNER JOIN tipos_servicio ts_cua ON a_cua.id_tipo_servicio = ts_cua.id_tipo_servicio
         WHERE a_cua.id_personal = p.id_personal 
         AND a_cua.fecha_servicio BETWEEN :fecha_inicio_count AND :fecha_fin_count
         AND ts_cua.nombre = 'CUARTELERO'
        ) as veces_cuartelero_ciclo,
        
        COALESCE(hr.dias_desde_ultimo, 999) as dias_ultimo,
        
        (SELECT COUNT(*) 
         FROM asignaciones_servicio a_dia
         WHERE a_dia.id_personal = p.id_personal 
         AND a_dia.fecha_servicio = :fecha_check
        ) as servicios_ese_dia
        
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
            {$filtro_grupos}
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN DATE_SUB(:fecha2, INTERVAL 2 DAY) AND DATE_SUB(:fecha3, INTERVAL 1 DAY)
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
            )
        ORDER BY 
            servicios_ese_dia ASC,
            veces_cuartelero_ciclo ASC,
            dias_ultimo DESC,
            g.orden ASC,
            RAND()
        LIMIT 1";

        $resultado = self::fetchFirst($sql, $params);

        if ($resultado) {
            if ($resultado['servicios_ese_dia'] == 0) {
                error_log("✅ CUARTELERO seleccionado SIN otros servicios: ID {$resultado['id_personal']}");
            } else {
                error_log("⚠️ CUARTELERO seleccionado CON {$resultado['servicios_ese_dia']} servicios ese día: ID {$resultado['id_personal']}");
            }

            return self::crearAsignacion(
                $resultado['id_personal'],
                'CUARTELERO',
                $fecha,
                '08:00:00',
                '07:45:00',
                $usuario_id,
                $id_oficial
            );
        }

        error_log("⚠️ NO se encontró CUARTELERO disponible para {$fecha}");
        return null;
    }

    /**
     * ✅ COMPLETO: SERVICIO NOCTURNO con rangos de 10 días
     */
    private static function asignarServicioNocturno($fecha, $usuario_id, $id_oficial = null)
    {
        $asignaciones = [];

        // Calcular rango del ciclo
        $sql_inicio_ciclo = "SELECT MIN(fecha_servicio) as inicio 
                            FROM asignaciones_servicio 
                            WHERE fecha_servicio <= :fecha 
                            AND DATEDIFF(:fecha, fecha_servicio) < 10";

        $ciclo = self::fetchFirst($sql_inicio_ciclo, [':fecha' => $fecha]);
        $fecha_inicio_ciclo = ($ciclo && $ciclo['inicio']) ? $ciclo['inicio'] : $fecha;
        $fecha_fin_ciclo = date('Y-m-d', strtotime($fecha_inicio_ciclo . ' +9 days'));

        error_log("🌙 === ASIGNANDO SERVICIO NOCTURNO ===");

        $params = [
            ':fecha' => $fecha,
            ':fecha_actual' => $fecha,
            ':fecha_hoy' => $fecha,
            ':fecha_inicio' => $fecha_inicio_ciclo,
            ':fecha_fin' => $fecha_fin_ciclo,
            ':fecha_inicio_count' => $fecha_inicio_ciclo,
            ':fecha_fin_count' => $fecha_fin_ciclo,
            ':fecha_inicio_count2' => $fecha_inicio_ciclo,
            ':fecha_fin_count2' => $fecha_fin_ciclo,
            ':fecha_cuartelero' => $fecha
        ];

        $filtro_grupos = self::construirFiltroGrupos($params);

        $sql = "SELECT 
        p.id_personal,
        p.nombres,
        p.apellidos,
        g.nombre as grado,
        
        (SELECT COUNT(*) 
         FROM asignaciones_servicio a_noc
         INNER JOIN tipos_servicio ts_noc ON a_noc.id_tipo_servicio = ts_noc.id_tipo_servicio
         WHERE a_noc.id_personal = p.id_personal 
         AND a_noc.fecha_servicio BETWEEN :fecha_inicio_count AND :fecha_fin_count
         AND ts_noc.nombre = 'SERVICIO NOCTURNO'
        ) as nocturnos_ciclo,
        
        (SELECT DATEDIFF(:fecha_actual, MAX(a_last.fecha_servicio))
         FROM asignaciones_servicio a_last
         INNER JOIN tipos_servicio ts_last ON a_last.id_tipo_servicio = ts_last.id_tipo_servicio
         WHERE a_last.id_personal = p.id_personal
         AND ts_last.nombre = 'SERVICIO NOCTURNO'
        ) as dias_ultimo_nocturno,
        
        (SELECT COUNT(*) 
         FROM asignaciones_servicio a_total
         WHERE a_total.id_personal = p.id_personal 
         AND a_total.fecha_servicio BETWEEN :fecha_inicio_count2 AND :fecha_fin_count2
        ) as servicios_ciclo_total,
        
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
            {$filtro_grupos}
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio = :fecha_cuartelero
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'CUARTELERO')
            )
        ORDER BY 
            nocturnos_ciclo ASC,
            COALESCE(dias_ultimo_nocturno, 999) DESC,
            servicios_ciclo_total ASC,
            g.orden ASC,
            RAND()
        LIMIT 3";

        $soldados = self::fetchArray($sql, $params);

        error_log("🌙 Soldados encontrados: " . count($soldados));

        if (count($soldados) === 0) {
            error_log("⚠️ NO SE ENCONTRARON SOLDADOS PARA NOCTURNO");
            return [];
        }

        $horarios = [
            1 => ['21:00:00', '23:30:00'],
            2 => ['23:30:00', '02:00:00'],
            3 => ['02:00:00', '04:45:00']
        ];

        $turno = 1;
        foreach ($soldados as $sold) {
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
     * ✅ COMPLETO: obtenerPersonalDisponible con rangos de 10 días
     */
    private static function obtenerPersonalDisponible($fecha, $tipo, $cantidad, $nombre_servicio, $fecha_exclusion = null, $incluir_grados = null, $modo_emergencia = false, $excluir_ids = [])
    {
        error_log("📋 Buscando personal: tipo={$tipo}, servicio={$nombre_servicio}, cantidad={$cantidad}");

        // Calcular rango del ciclo
        $sql_inicio_ciclo = "SELECT MIN(fecha_servicio) as inicio 
                            FROM asignaciones_servicio 
                            WHERE fecha_servicio <= :fecha_temp 
                            AND DATEDIFF(:fecha_temp, fecha_servicio) < 10";

        $ciclo = self::fetchFirst($sql_inicio_ciclo, [':fecha_temp' => $fecha]);
        $fecha_inicio_ciclo = ($ciclo && $ciclo['inicio']) ? $ciclo['inicio'] : $fecha;
        $fecha_fin_ciclo = date('Y-m-d', strtotime($fecha_inicio_ciclo . ' +9 days'));

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

        // Filtro para excluir IDs en memoria
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

        $params = [
            ':fecha' => $fecha,
            ':fecha2' => $fecha,
            ':tipo' => $tipo,
            ':servicio' => $nombre_servicio,
            ':servicio2' => $nombre_servicio,
            ':servicio3' => $nombre_servicio,
            ':cantidad' => (int)$cantidad,
            ':fecha_inicio' => $fecha_inicio_ciclo,
            ':fecha_fin' => $fecha_fin_ciclo,
            ':fecha_inicio_count' => $fecha_inicio_ciclo,
            ':fecha_fin_count' => $fecha_fin_ciclo,
            ':fecha_inicio_count2' => $fecha_inicio_ciclo,
            ':fecha_fin_count2' => $fecha_fin_ciclo
        ];

        // Construir filtro de grupos
        $filtro_grupos = self::construirFiltroGrupos($params);

        // Merge de parámetros
        $params = array_merge($params, $params_grados, $params_excluir);

        if ($fecha_exclusion) {
            $params[':fecha_exclusion'] = $fecha_exclusion;
        }

        $sql = "SELECT p.id_personal,
            p.nombres,
            p.apellidos,
            g.nombre as grado,
            
            (SELECT COUNT(*) 
             FROM asignaciones_servicio a_count 
             WHERE a_count.id_personal = p.id_personal 
             AND a_count.fecha_servicio BETWEEN :fecha_inicio_count AND :fecha_fin_count
            ) as servicios_ciclo_total,
            
            (SELECT COUNT(*) 
             FROM asignaciones_servicio a_count2
             INNER JOIN tipos_servicio ts_count ON a_count2.id_tipo_servicio = ts_count.id_tipo_servicio
             WHERE a_count2.id_personal = p.id_personal 
             AND a_count2.fecha_servicio BETWEEN :fecha_inicio_count2 AND :fecha_fin_count2
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
            {$filtro_grupos}
            {$filtro_grados}
            {$filtro_excluir_ids}
            AND p.id_personal NOT IN (
                SELECT id_personal FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                AND id_tipo_servicio = (SELECT id_tipo_servicio FROM tipos_servicio WHERE nombre = 'Semana')
            )
            {$exclusion_sql}
        ORDER BY 
            servicios_ciclo_total ASC,
            veces_este_servicio ASC,
            dias_ultimo DESC,
            prioridad_hist ASC,
            g.orden ASC,
            RAND()
        LIMIT :cantidad";

        if ($modo_emergencia) {
            error_log("🚨 MODO EMERGENCIA ACTIVADO para {$nombre_servicio}");
        }

        if (!empty($excluir_ids)) {
            error_log("🚫 Excluyendo IDs en memoria: " . implode(', ', $excluir_ids));
        }

        $personal = self::fetchArray($sql, $params);

        if (empty($personal)) {
            error_log("❌ No se encontró personal disponible para {$nombre_servicio}");

            if (!$modo_emergencia) {
                error_log("🚨 Intentando modo emergencia...");
                return self::obtenerPersonalDisponible(
                    $fecha,
                    $tipo,
                    $cantidad,
                    $nombre_servicio,
                    $fecha_exclusion,
                    $incluir_grados,
                    true,
                    $excluir_ids
                );
            }

            throw new \Exception("No hay suficiente personal disponible para {$nombre_servicio}");
        }

        error_log("✅ Personal encontrado: " . count($personal) . " de {$cantidad} solicitados");

        return $personal;
    }

    // ========================================
    // FUNCIONES AUXILIARES
    // ========================================

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
                error_log("⚠️ DUPLICADO DETECTADO: Personal {$id_personal} - {$nombre_servicio} - {$fecha}");
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
                error_log("❌ ERROR al crear en BD: Personal {$id_personal}");
                return [
                    'error' => true,
                    'mensaje' => 'No se pudo crear en BD',
                    'detalles' => $resultado
                ];
            }

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

    private static function actualizarHistorial($id_personal, $id_tipo_servicio, $fecha)
    {
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
            }
        } else {
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
     * ✅ MODIFICADO: obtenerAsignacionesSemana ahora maneja ciclos de 10 días
     */
    public static function obtenerAsignacionesSemana($fecha_inicio)
    {
        $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . ' +9 days'));

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
     * ✅ MODIFICADO: eliminarAsignacionesSemana ahora maneja ciclos de 10 días
     */
    public static function eliminarAsignacionesSemana($fecha_inicio)
    {
        try {
            $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . ' +9 days'));

            error_log("🗑️ === ELIMINANDO CICLO: {$fecha_inicio} a {$fecha_fin} ===");

            $personal_afectado = self::fetchArray(
                "SELECT DISTINCT id_personal 
                 FROM asignaciones_servicio 
                 WHERE fecha_servicio BETWEEN :inicio AND :fin",
                [':inicio' => $fecha_inicio, ':fin' => $fecha_fin]
            );

            $cantidad_personas = is_array($personal_afectado) ? count($personal_afectado) : 0;
            error_log("👥 Personal afectado: {$cantidad_personas} personas");

            $sql = "DELETE FROM asignaciones_servicio 
                WHERE fecha_servicio BETWEEN :inicio AND :fin";

            $resultado = self::ejecutarQuery($sql, [
                ':inicio' => $fecha_inicio,
                ':fin' => $fecha_fin
            ]);

            $registros_eliminados = 0;
            if (is_array($resultado) && isset($resultado['resultado'])) {
                $registros_eliminados = $resultado['resultado'];
            }

            error_log("✅ Asignaciones eliminadas: {$registros_eliminados}");

            if ($cantidad_personas > 0) {
                error_log("🔄 Recalculando historial...");

                $recalculados = 0;
                foreach ($personal_afectado as $persona) {
                    if (self::recalcularHistorialPersona($persona['id_personal'])) {
                        $recalculados++;
                    }
                }

                error_log("✅ Historial recalculado para {$recalculados}/{$cantidad_personas} personas");
            }

            error_log("🎉 ELIMINACIÓN COMPLETADA");

            return [
                'resultado' => $registros_eliminados,
                'personal_afectado' => $cantidad_personas
            ];
        } catch (\Exception $e) {
            error_log("❌ ERROR en eliminarAsignacionesSemana: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✨ NUEVA FUNCIÓN: Verificar si una fecha está disponible o dentro de un ciclo existente
     */
    public static function verificarDisponibilidadFecha($fecha)
    {
        $resultado = [
            'disponible' => true,
            'en_ciclo_existente' => false,
            'ciclo_inicio' => null,
            'ciclo_fin' => null,
            'proxima_fecha_disponible' => null,
            'mensaje' => ''
        ];

        // Buscar si existe algún ciclo que contenga esta fecha
        $sql = "SELECT MIN(fecha_servicio) as inicio, MAX(fecha_servicio) as fin
                FROM asignaciones_servicio
                WHERE fecha_servicio >= DATE_SUB(:fecha, INTERVAL 9 DAY)
                AND fecha_servicio <= DATE_ADD(:fecha, INTERVAL 9 DAY)
                HAVING COUNT(DISTINCT fecha_servicio) > 0";

        $ciclo = self::fetchFirst($sql, [':fecha' => $fecha]);

        if ($ciclo && $ciclo['inicio']) {
            $fecha_inicio = new \DateTime($ciclo['inicio']);
            $fecha_fin = new \DateTime($ciclo['fin']);
            $fecha_consulta = new \DateTime($fecha);

            // Verificar si la fecha está dentro del rango del ciclo
            if ($fecha_consulta >= $fecha_inicio && $fecha_consulta <= $fecha_fin) {
                // Calcular días de diferencia
                $diff = (new \DateTime($ciclo['inicio']))->diff(new \DateTime($fecha));
                $dias_diff = $diff->days;

                // Solo marcar como ocupada si está dentro de los 10 días
                if ($dias_diff < 10) {
                    $resultado['disponible'] = false;
                    $resultado['en_ciclo_existente'] = true;
                    $resultado['ciclo_inicio'] = $ciclo['inicio'];
                    $resultado['ciclo_fin'] = $ciclo['fin'];

                    // Próxima fecha disponible es el día siguiente al fin del ciclo
                    $siguiente = new \DateTime($ciclo['fin']);
                    $siguiente->modify('+1 day');
                    $resultado['proxima_fecha_disponible'] = $siguiente->format('Y-m-d');

                    $resultado['mensaje'] = "Esta fecha pertenece al ciclo del " .
                        self::formatearFechaCorta($ciclo['inicio']) .
                        " al " .
                        self::formatearFechaCorta($ciclo['fin']);
                }
            }
        }

        // Si está disponible, verificar cuál es la próxima fecha recomendada
        if ($resultado['disponible']) {
            $info_proxima = self::obtenerProximaFechaDisponible();
            $resultado['proxima_fecha_disponible'] = $info_proxima['proxima_fecha'];
            $resultado['mensaje'] = 'Fecha disponible para generar ciclo';
        }

        return $resultado;
    }

    /**
     * ✨ NUEVA FUNCIÓN: Obtener la próxima fecha disponible para generar un ciclo
     */
    public static function obtenerProximaFechaDisponible()
    {
        // Buscar el último ciclo generado
        $sql = "SELECT MAX(fecha_servicio) as ultima_fecha
                FROM asignaciones_servicio";

        $resultado_query = self::fetchFirst($sql, []);

        if (!$resultado_query || !$resultado_query['ultima_fecha']) {
            // No hay ciclos, la próxima fecha es hoy
            $hoy = new \DateTime();
            return [
                'tiene_ciclos' => false,
                'ultimo_ciclo_fin' => null,
                'proxima_fecha' => $hoy->format('Y-m-d'),
                'mensaje' => 'No hay ciclos generados. Puede comenzar desde hoy.'
            ];
        }

        $ultima_fecha = new \DateTime($resultado_query['ultima_fecha']);

        // Buscar el inicio de ese ciclo
        $sql_inicio = "SELECT MIN(fecha_servicio) as inicio
                      FROM asignaciones_servicio
                      WHERE fecha_servicio >= DATE_SUB(:fecha, INTERVAL 9 DAY)
                      AND fecha_servicio <= :fecha2";

        $ciclo = self::fetchFirst($sql_inicio, [
            ':fecha' => $resultado_query['ultima_fecha'],
            ':fecha2' => $resultado_query['ultima_fecha']
        ]);

        if ($ciclo && $ciclo['inicio']) {
            $inicio_ultimo_ciclo = new \DateTime($ciclo['inicio']);
            $fin_ultimo_ciclo = clone $inicio_ultimo_ciclo;
            $fin_ultimo_ciclo->modify('+9 days');

            // La próxima fecha disponible es el día siguiente al fin del último ciclo
            $proxima = clone $fin_ultimo_ciclo;
            $proxima->modify('+1 day');

            return [
                'tiene_ciclos' => true,
                'ultimo_ciclo_inicio' => $inicio_ultimo_ciclo->format('Y-m-d'),
                'ultimo_ciclo_fin' => $fin_ultimo_ciclo->format('Y-m-d'),
                'proxima_fecha' => $proxima->format('Y-m-d'),
                'mensaje' => 'La próxima fecha disponible es después del último ciclo'
            ];
        }

        // Fallback: día siguiente a la última fecha encontrada
        $proxima = clone $ultima_fecha;
        $proxima->modify('+1 day');

        return [
            'tiene_ciclos' => true,
            'ultimo_ciclo_fin' => $ultima_fecha->format('Y-m-d'),
            'proxima_fecha' => $proxima->format('Y-m-d'),
            'mensaje' => 'Próxima fecha calculada'
        ];
    }

    /**
     * ✨ NUEVA FUNCIÓN: Formatear fecha corta en español
     */
    private static function formatearFechaCorta($fecha)
    {
        $fecha_obj = new \DateTime($fecha);
        $meses = [
            1 => 'ene',
            2 => 'feb',
            3 => 'mar',
            4 => 'abr',
            5 => 'may',
            6 => 'jun',
            7 => 'jul',
            8 => 'ago',
            9 => 'sep',
            10 => 'oct',
            11 => 'nov',
            12 => 'dic'
        ];

        $dia = $fecha_obj->format('d');
        $mes = $meses[(int)$fecha_obj->format('m')];
        $anio = $fecha_obj->format('Y');

        return "{$dia}/{$mes}/{$anio}";
    }

    /**
     * ✨ FUNCIÓN CORREGIDA: Obtener historial de todos los ciclos generados
     * Busca todas las fechas de inicio de ciclos (identificadas porque tienen servicio "Semana")
     */
    public static function obtenerHistorialCiclos()
    {
        // Estrategia: Buscar todas las asignaciones de SEMANA (que siempre marcan el inicio de un ciclo)
        // Luego validar que cada ciclo tenga 10 días consecutivos

        $sql = "SELECT DISTINCT 
                    a.fecha_servicio as fecha_inicio,
                    DATE_ADD(a.fecha_servicio, INTERVAL 9 DAY) as fecha_fin_esperada,
                    (SELECT COUNT(DISTINCT a2.fecha_servicio) 
                     FROM asignaciones_servicio a2 
                     WHERE a2.fecha_servicio BETWEEN a.fecha_servicio 
                     AND DATE_ADD(a.fecha_servicio, INTERVAL 9 DAY)) as dias_generados,
                    (SELECT COUNT(*) 
                     FROM asignaciones_servicio a3 
                     WHERE a3.fecha_servicio BETWEEN a.fecha_servicio 
                     AND DATE_ADD(a.fecha_servicio, INTERVAL 9 DAY)) as total_asignaciones,
                    (SELECT COUNT(DISTINCT a4.id_personal) 
                     FROM asignaciones_servicio a4 
                     WHERE a4.fecha_servicio BETWEEN a.fecha_servicio 
                     AND DATE_ADD(a.fecha_servicio, INTERVAL 9 DAY)) as personal_involucrado
                FROM asignaciones_servicio a
                INNER JOIN tipos_servicio ts ON a.id_tipo_servicio = ts.id_tipo_servicio
                WHERE ts.nombre = 'Semana'
                ORDER BY a.fecha_servicio DESC";

        $resultados = self::fetchArray($sql, []);

        $ciclos = [];
        $hoy = date('Y-m-d');

        foreach ($resultados as $resultado) {
            // Solo incluir ciclos completos o casi completos (mínimo 8 días para tolerancia)
            if ($resultado['dias_generados'] >= 8) {
                // Calcular la fecha fin real
                $sql_fecha_fin = "SELECT MAX(fecha_servicio) as fecha_fin_real 
                                 FROM asignaciones_servicio 
                                 WHERE fecha_servicio BETWEEN :inicio AND :fin";

                $fecha_fin_data = self::fetchFirst($sql_fecha_fin, [
                    ':inicio' => $resultado['fecha_inicio'],
                    ':fin' => $resultado['fecha_fin_esperada']
                ]);

                $fecha_fin = $fecha_fin_data['fecha_fin_real'] ?? $resultado['fecha_fin_esperada'];

                $ciclo = [
                    'fecha_inicio' => $resultado['fecha_inicio'],
                    'fecha_fin' => $fecha_fin,
                    'total_asignaciones' => (int)$resultado['total_asignaciones'],
                    'personal_involucrado' => (int)$resultado['personal_involucrado'],
                    'activo' => ($fecha_fin >= $hoy),
                    'estado' => ($fecha_fin >= $hoy) ? 'ACTIVO' : 'FINALIZADO'
                ];

                $ciclos[] = $ciclo;
            }
        }

        return $ciclos;
    }

    private static function recalcularHistorialPersona($id_personal)
    {
        error_log("🔄 Recalculando historial para persona ID: {$id_personal}");

        try {
            $tipos_servicio = self::fetchArray(
                "SELECT id_tipo_servicio FROM tipos_servicio",
                []
            );

            foreach ($tipos_servicio as $tipo) {
                $id_tipo = $tipo['id_tipo_servicio'];

                $ultima = self::fetchFirst(
                    "SELECT MAX(fecha_servicio) as ultima_fecha
                     FROM asignaciones_servicio
                     WHERE id_personal = :id_personal
                     AND id_tipo_servicio = :id_tipo",
                    [
                        ':id_personal' => $id_personal,
                        ':id_tipo' => $id_tipo
                    ]
                );

                $ultima_fecha = $ultima['ultima_fecha'] ?? null;

                $existe = self::fetchFirst(
                    "SELECT id_historial 
                     FROM historial_rotaciones 
                     WHERE id_personal = :id_personal 
                     AND id_tipo_servicio = :id_tipo",
                    [
                        ':id_personal' => $id_personal,
                        ':id_tipo' => $id_tipo
                    ]
                );

                if ($existe) {
                    if ($ultima_fecha) {
                        $dias = (new \DateTime())->diff(new \DateTime($ultima_fecha))->days;

                        self::ejecutarQuery(
                            "UPDATE historial_rotaciones 
                             SET fecha_ultimo_servicio = :fecha,
                                 dias_desde_ultimo = :dias
                             WHERE id_personal = :id_personal 
                             AND id_tipo_servicio = :id_tipo",
                            [
                                ':fecha' => $ultima_fecha,
                                ':dias' => $dias,
                                ':id_personal' => $id_personal,
                                ':id_tipo' => $id_tipo
                            ]
                        );
                    } else {
                        self::ejecutarQuery(
                            "DELETE FROM historial_rotaciones 
                             WHERE id_personal = :id_personal 
                             AND id_tipo_servicio = :id_tipo",
                            [
                                ':id_personal' => $id_personal,
                                ':id_tipo' => $id_tipo
                            ]
                        );
                    }
                } else {
                    if ($ultima_fecha) {
                        $dias = (new \DateTime())->diff(new \DateTime($ultima_fecha))->days;

                        self::ejecutarQuery(
                            "INSERT INTO historial_rotaciones 
                             (id_personal, id_tipo_servicio, fecha_ultimo_servicio, dias_desde_ultimo, prioridad)
                             VALUES (:id_personal, :id_tipo, :fecha, :dias, 0)",
                            [
                                ':id_personal' => $id_personal,
                                ':id_tipo' => $id_tipo,
                                ':fecha' => $ultima_fecha,
                                ':dias' => $dias
                            ]
                        );
                    }
                }
            }

            error_log("✅ Historial recalculado para persona {$id_personal}");
            return true;
        } catch (\Exception $e) {
            error_log("❌ ERROR al recalcular historial persona {$id_personal}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🆕 Obtener comisiones activas
     */
    /**
     * 🆕 Obtener comisiones activas con detalles
     */
    public static function obtenerComisionesActivas()
    {
        $sql = "SELECT 
                co.id_comision,
                co.numero_oficio,
                co.fecha_inicio,
                co.fecha_fin,
                co.dias_totales,
                co.destino,
                co.motivo,
                co.estado,
                p.id_personal,
                CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
                g.nombre as grado,
                p.tipo as tipo_personal,
                (SELECT COUNT(*) 
                 FROM asignaciones_servicio a 
                 WHERE a.id_comision = co.id_comision 
                 AND a.estado = 'EN_COMISION') as servicios_afectados,
                (SELECT COUNT(*) 
                 FROM reemplazos_servicio r 
                 WHERE r.id_comision = co.id_comision) as reemplazos_realizados,
                co.created_at as fecha_registro
             FROM comisiones_oficiales co
             INNER JOIN bhr_personal p ON co.id_personal = p.id_personal
             INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
             WHERE co.estado = 'ACTIVA'
             ORDER BY co.fecha_inicio DESC";

        return self::fetchArray($sql, []);
    }

    /**
     * 🆕 Obtener personal con compensaciones pendientes
     */
    public static function obtenerPersonalConCompensacion()
    {
        $sql = "SELECT 
                p.id_personal,
                CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
                g.nombre as grado,
                COALESCE(hr.servicios_como_reemplazo, 0) as servicios_como_reemplazo,
                COUNT(ch.id_compensacion) as compensaciones_pendientes
             FROM bhr_personal p
             INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
             LEFT JOIN historial_rotaciones hr ON p.id_personal = hr.id_personal
             LEFT JOIN compensaciones_historial ch ON p.id_personal = ch.id_personal 
                AND ch.estado = 'PENDIENTE'
             WHERE p.activo = 1
             GROUP BY p.id_personal, p.nombres, p.apellidos, g.nombre, hr.servicios_como_reemplazo
             HAVING compensaciones_pendientes > 0 OR servicios_como_reemplazo > 0
             ORDER BY compensaciones_pendientes DESC, servicios_como_reemplazo DESC";

        return self::fetchArray($sql, []);
    }
}
