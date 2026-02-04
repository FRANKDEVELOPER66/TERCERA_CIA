<?php

namespace Controllers;

use Exception;
use Model\AsignacionServicio;
use Model\Personal;
use Model\TiposServicio;
use MVC\Router;
use Mpdf\Mpdf;
use Model\ComisionOficial;

class AsignacionController
{
    public static function index(Router $router)
    {
        $router->render('asignaciones/index', [
            'titulo' => 'Generador de Servicios (Ciclos de 10 Días)'
        ]);
    }

    /**
     * ✅ MODIFICADO: Genera ciclo de 10 días con validación de traslapes
     */
    public static function generarSemanaAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');
        ob_start();

        $debug = [];
        $debug['paso_1'] = 'Iniciando proceso de ciclo de 10 días';

        $fecha_inicio = $_POST['fecha_inicio'] ?? '';
        $grupos_json = $_POST['grupos_disponibles'] ?? '[]';
        $grupos_disponibles = json_decode($grupos_json, true);

        if (!is_array($grupos_disponibles)) {
            $grupos_disponibles = [];
        }

        $debug['grupos_recibidos'] = $grupos_disponibles;
        error_log("🎯 Grupos disponibles recibidos: " . json_encode($grupos_disponibles));

        if (empty($fecha_inicio)) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Debe proporcionar una fecha de inicio',
                'debug' => $debug
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // ✨ Validación: Si no hay grupos, usar todos por defecto
        if (empty($grupos_disponibles)) {
            error_log("⚠️ No se especificaron grupos, usando todos por defecto");
            $todos_grupos = AsignacionServicio::fetchArray("SELECT id_grupo FROM grupos_descanso", []);
            $grupos_disponibles = array_map(function ($g) {
                return $g['id_grupo'];
            }, $todos_grupos);
            $debug['grupos_usados'] = 'Todos (sin filtro)';
        } else {
            $debug['grupos_usados'] = $grupos_disponibles;
        }

        try {
            $fecha = new \DateTime($fecha_inicio);
            $usuario_id = $_SESSION['user_id'] ?? null;

            // ✨ Generar ciclo de 10 días
            $resultado = AsignacionServicio::generarAsignacionesSemanal(
                $fecha_inicio,
                $usuario_id,
                $grupos_disponibles
            );

            $logs_output = ob_get_clean();
            $debug['logs_php'] = $logs_output;

            if ($resultado['exito']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => $resultado['mensaje'],
                    'datos' => $resultado['asignaciones'],
                    'total_generadas' => count($resultado['asignaciones']),
                    'detalle_por_dia' => $resultado['detalle_por_dia'] ?? [],
                    'grupos_usados' => $grupos_disponibles,
                    'debug' => array_merge($resultado['debug'], ['logs' => $logs_output])
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => $resultado['mensaje'],
                    'errores' => $resultado['errores'],
                    'debug' => array_merge($resultado['debug'], ['logs' => $logs_output])
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            $logs_output = ob_get_clean();
            $debug['excepcion'] = $e->getMessage();
            $debug['logs'] = $logs_output;

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al generar asignaciones: ' . $e->getMessage(),
                'debug' => $debug
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function contarPersonalAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            $grupos = $data['grupos'] ?? [];

            if (!is_array($grupos) || empty($grupos)) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No se proporcionaron grupos válidos'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $fecha_actual = date('Y-m-d');
            $placeholders = [];
            $params = [];

            foreach ($grupos as $index => $id_grupo) {
                $key = ":grupo_{$index}";
                $placeholders[] = $key;
                $params[$key] = (int)$id_grupo;
            }

            $in_clause = implode(',', $placeholders);
            $params[':fecha'] = $fecha_actual;

            $sql_oficiales = "SELECT COUNT(*) as total 
                         FROM bhr_personal p
                         LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
                            AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
                         WHERE p.tipo = 'OFICIAL' 
                         AND p.activo = 1
                         AND p.id_grupo_descanso IN ($in_clause)
                         AND cd.id_calendario IS NULL";

            $oficiales = AsignacionServicio::fetchFirst($sql_oficiales, $params);

            $sql_especialistas = "SELECT COUNT(*) as total 
                             FROM bhr_personal p
                             LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
                                AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
                             WHERE p.tipo = 'ESPECIALISTA' 
                             AND p.activo = 1
                             AND p.id_grupo_descanso IN ($in_clause)
                             AND cd.id_calendario IS NULL";

            $especialistas = AsignacionServicio::fetchFirst($sql_especialistas, $params);

            $sql_tropa = "SELECT COUNT(*) as total 
                     FROM bhr_personal p
                     LEFT JOIN calendario_descansos cd ON p.id_grupo_descanso = cd.id_grupo_descanso
                        AND :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
                     WHERE p.tipo = 'TROPA' 
                     AND p.activo = 1
                     AND p.id_grupo_descanso IN ($in_clause)
                     AND cd.id_calendario IS NULL";

            $tropa = AsignacionServicio::fetchFirst($sql_tropa, $params);
            $total = ($oficiales['total'] ?? 0) + ($especialistas['total'] ?? 0) + ($tropa['total'] ?? 0);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Conteo realizado',
                'oficiales' => (int)($oficiales['total'] ?? 0),
                'especialistas' => (int)($especialistas['total'] ?? 0),
                'tropa' => (int)($tropa['total'] ?? 0),
                'total' => $total
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al contar personal',
                'detalle' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * ✨ NUEVO: Obtiene información sobre ciclos y próxima fecha disponible
     */
    public static function verificarFechaAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $fecha = $_GET['fecha'] ?? '';

        if (empty($fecha)) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Debe proporcionar una fecha',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $info = AsignacionServicio::verificarDisponibilidadFecha($fecha);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Información obtenida',
                'data' => $info
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al verificar fecha',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * ✨ NUEVO: Obtiene la próxima fecha disponible para generar ciclo
     */
    public static function proximaFechaAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $info = AsignacionServicio::obtenerProximaFechaDisponible();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Próxima fecha obtenida',
                'data' => $info
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener próxima fecha',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * ✅ MODIFICADO: Obtiene asignaciones de un ciclo de 10 días
     */
    public static function obtenerSemanaAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $fecha_inicio = $_GET['fecha_inicio'] ?? '';

        if (empty($fecha_inicio)) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Debe proporcionar una fecha de inicio',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $asignaciones = AsignacionServicio::obtenerAsignacionesSemana($fecha_inicio);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Datos encontrados',
                'datos' => $asignaciones
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener asignaciones',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * ✅ MODIFICADO: Elimina un ciclo de 10 días
     */
    public static function eliminarSemanaAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $fecha_inicio = $_POST['fecha_inicio'] ?? '';

        if (empty($fecha_inicio)) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Debe proporcionar una fecha de inicio',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $resultado = AsignacionServicio::eliminarAsignacionesSemana($fecha_inicio);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Ciclo eliminado y historial recalculado exitosamente',
                'registros_eliminados' => $resultado['resultado'] ?? 0
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("❌ ERROR en eliminarSemanaAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al eliminar ciclo',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    /**
     * 🆕 API: Obtener compensaciones de un personal
     */
    public static function compensacionesPersonalAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id_personal = $_GET['id_personal'] ?? null;

        if (!$id_personal) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Debe proporcionar el ID del personal'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Obtener compensaciones pendientes
            $compensaciones_pendientes = AsignacionServicio::fetchArray(
                "SELECT 
                ch.id_compensacion,
                ch.fecha_servicio,
                ch.puntos_compensacion,
                ch.estado,
                ch.motivo,
                ch.created_at,
                ts.nombre as servicio,
                co.numero_oficio,
                co.destino,
                co.fecha_inicio as comision_inicio,
                co.fecha_fin as comision_fin
             FROM compensaciones_historial ch
             INNER JOIN tipos_servicio ts ON ch.id_tipo_servicio = ts.id_tipo_servicio
             LEFT JOIN comisiones_oficiales co ON ch.id_comision = co.id_comision
             WHERE ch.id_personal = :id_personal
             AND ch.estado = 'PENDIENTE'
             ORDER BY ch.created_at ASC",
                [':id_personal' => $id_personal]
            );

            // Obtener compensaciones aplicadas
            $compensaciones_aplicadas = AsignacionServicio::fetchArray(
                "SELECT 
                ch.id_compensacion,
                ch.fecha_servicio,
                ch.puntos_compensacion,
                ch.estado,
                ch.motivo,
                ch.fecha_aplicacion,
                ch.created_at,
                ts.nombre as servicio,
                co.numero_oficio,
                CONCAT(u.nombre, ' ', u.apellido) as aplicada_por_nombre
             FROM compensaciones_historial ch
             INNER JOIN tipos_servicio ts ON ch.id_tipo_servicio = ts.id_tipo_servicio
             LEFT JOIN comisiones_oficiales co ON ch.id_comision = co.id_comision
             LEFT JOIN usuarios u ON ch.aplicada_por = u.id
             WHERE ch.id_personal = :id_personal
             AND ch.estado = 'APLICADA'
             ORDER BY ch.fecha_aplicacion DESC
             LIMIT 10",
                [':id_personal' => $id_personal]
            );

            // Calcular total de puntos
            $total_puntos = array_sum(array_column($compensaciones_pendientes, 'puntos_compensacion'));

            // Obtener info del historial
            $historial = AsignacionServicio::fetchFirst(
                "SELECT 
                servicios_como_reemplazo,
                compensacion_pendiente,
                puntos_compensacion_acumulados
             FROM historial_rotaciones
             WHERE id_personal = :id_personal
             LIMIT 1",
                [':id_personal' => $id_personal]
            );

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Compensaciones obtenidas',
                'data' => [
                    'compensaciones_pendientes' => $compensaciones_pendientes,
                    'compensaciones_aplicadas' => $compensaciones_aplicadas,
                    'total_puntos_pendientes' => $total_puntos,
                    'total_reemplazos' => $historial['servicios_como_reemplazo'] ?? 0,
                    'tiene_compensacion_pendiente' => ($historial['compensacion_pendiente'] ?? false) ? true : false
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log("❌ ERROR en compensacionesPersonalAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener compensaciones',
                'detalle' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }


    /**
     * 🆕 API: Verificar disponibilidad para aplicar compensación
     */
    public static function verificarCompensacionAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id_personal = $_GET['id_personal'] ?? null;
        $fecha = $_GET['fecha'] ?? null;

        if (!$id_personal || !$fecha) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Debe proporcionar el ID del personal y la fecha'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // 1. Verificar puntos disponibles
            $compensaciones = AsignacionServicio::fetchFirst(
                "SELECT SUM(puntos_compensacion) as total_puntos
             FROM compensaciones_historial
             WHERE id_personal = :id_personal
             AND estado = 'PENDIENTE'",
                [':id_personal' => $id_personal]
            );

            $puntos_disponibles = $compensaciones['total_puntos'] ?? 0;

            // 2. Obtener servicios en esa fecha
            $servicios_fecha = AsignacionServicio::fetchArray(
                "SELECT 
                a.id_asignacion,
                ts.nombre as servicio,
                ts.id_tipo_servicio,
                a.hora_inicio,
                a.hora_fin,
                a.estado
             FROM asignaciones_servicio a
             INNER JOIN tipos_servicio ts ON a.id_tipo_servicio = ts.id_tipo_servicio
             WHERE a.id_personal = :id_personal
             AND a.fecha_servicio = :fecha
             AND a.estado = 'PROGRAMADO'",
                [
                    ':id_personal' => $id_personal,
                    ':fecha' => $fecha
                ]
            );

            if (empty($servicios_fecha)) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No tiene servicios asignados en esa fecha',
                    'puede_aplicar' => false,
                    'puntos_disponibles' => $puntos_disponibles,
                    'puntos_necesarios' => 0,
                    'servicios' => []
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // 3. Calcular puntos necesarios
            $puntos_necesarios = 0;
            foreach ($servicios_fecha as &$serv) {
                $puntos_servicio = AsignacionServicio::calcularPuntosCompensacion($serv['servicio']);
                $serv['puntos'] = $puntos_servicio;
                $puntos_necesarios += $puntos_servicio;
            }

            $puede_aplicar = ($puntos_disponibles >= $puntos_necesarios);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => $puede_aplicar ? 'Puede aplicar compensación' : 'No tiene suficientes puntos',
                'puede_aplicar' => $puede_aplicar,
                'puntos_disponibles' => $puntos_disponibles,
                'puntos_necesarios' => $puntos_necesarios,
                'puntos_restantes' => max(0, $puntos_disponibles - $puntos_necesarios),
                'servicios' => $servicios_fecha
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log("❌ ERROR en verificarCompensacionAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al verificar compensación',
                'detalle' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 🆕 API: Aplicar compensación
     */
    public static function aplicarCompensacionAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id_personal = $_POST['id_personal'] ?? null;
            $fecha = $_POST['fecha'] ?? null;
            $usuario_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

            if (!$id_personal || !$fecha) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Debe proporcionar el ID del personal y la fecha'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Validar formato de fecha
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Formato de fecha inválido'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Verificar que la fecha no sea pasada
            if (strtotime($fecha) < strtotime(date('Y-m-d'))) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No se puede aplicar compensación en fechas pasadas'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            error_log("💰 Aplicando compensación - Personal: {$id_personal}, Fecha: {$fecha}");

            $resultado = AsignacionServicio::aplicarCompensacion($id_personal, $fecha, $usuario_id);

            if ($resultado['exito']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => $resultado['mensaje'],
                    'data' => [
                        'puntos_gastados' => $resultado['puntos_gastados'],
                        'puntos_restantes' => $resultado['puntos_restantes'],
                        'servicios_cancelados' => $resultado['servicios_cancelados']
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => $resultado['mensaje']
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (\Exception $e) {
            error_log("❌ ERROR en aplicarCompensacionAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al aplicar compensación',
                'detalle' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }


    /**
     * 🆕 API: Listar personal con compensaciones (ya existe pero la mejoramos)
     */
    public static function personalConCompensacionAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $personal = AsignacionServicio::fetchArray(
                "SELECT 
                p.id_personal,
                CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
                g.nombre as grado,
                p.tipo as tipo_personal,

                -- Total de veces que ha sido reemplazo
                (SELECT COUNT(*) 
                 FROM reemplazos_servicio rs 
                 WHERE rs.id_personal_reemplazo = p.id_personal
                ) as total_reemplazos,

                -- ✅ Compensaciones pendientes (ADAPTADO)
                (SELECT COUNT(*) 
                 FROM compensaciones_historial ch 
                 WHERE ch.id_personal = p.id_personal 
                 AND ch.estado = 'PENDIENTE'
                ) as compensaciones_pendientes,

                -- ✅ Suma de puntos pendientes (de JSON en notas)
                (SELECT COALESCE(SUM(
                    CAST(JSON_EXTRACT(notas, '$.puntos') AS UNSIGNED)
                ), 0)
                 FROM compensaciones_historial ch2 
                 WHERE ch2.id_personal = p.id_personal 
                 AND ch2.estado = 'PENDIENTE'
                ) as total_puntos_pendientes,

                -- Compensaciones aplicadas
                (SELECT COUNT(*) 
                 FROM compensaciones_historial ch3 
                 WHERE ch3.id_personal = p.id_personal 
                 AND ch3.estado = 'APLICADA'
                ) as compensaciones_aplicadas

             FROM bhr_personal p
             INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
             WHERE p.activo = 1
             -- Solo mostrar quien tiene compensaciones pendientes o ha sido reemplazo
             AND (
                 EXISTS (
                     SELECT 1 FROM compensaciones_historial ch 
                     WHERE ch.id_personal = p.id_personal 
                     AND ch.estado = 'PENDIENTE'
                 )
                 OR EXISTS (
                     SELECT 1 FROM reemplazos_servicio rs 
                     WHERE rs.id_personal_reemplazo = p.id_personal
                 )
             )
             ORDER BY 
                compensaciones_pendientes DESC,
                total_reemplazos DESC",
                []
            );

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Personal con compensaciones obtenido',
                'total' => count($personal),
                'personal' => $personal
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log("❌ ERROR en personalConCompensacionAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener personal',
                'detalle' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 🆕 API: Historial completo de compensaciones
     */
    public static function historialCompensacionesAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $limite = $_GET['limite'] ?? 50;
        $estado = $_GET['estado'] ?? null; // 'PENDIENTE', 'APLICADA', o null para todas

        try {
            $filtro_estado = '';
            $params = [':limite' => (int)$limite];

            if ($estado && in_array($estado, ['PENDIENTE', 'APLICADA'])) {
                $filtro_estado = "AND ch.estado = :estado";
                $params[':estado'] = $estado;
            }

            $compensaciones = AsignacionServicio::fetchArray(
                "SELECT 
                ch.id_compensacion,
                ch.fecha_servicio,
                ch.puntos_compensacion,
                ch.estado,
                ch.motivo,
                ch.created_at,
                ch.fecha_aplicacion,
                CONCAT(p.nombres, ' ', p.apellidos) as nombre_completo,
                g.nombre as grado,
                p.tipo as tipo_personal,
                ts.nombre as servicio,
                co.numero_oficio,
                co.destino,
                CONCAT(u.nombre, ' ', u.apellido) as aplicada_por
             FROM compensaciones_historial ch
             INNER JOIN bhr_personal p ON ch.id_personal = p.id_personal
             INNER JOIN bhr_grados g ON p.id_grado = g.id_grado
             INNER JOIN tipos_servicio ts ON ch.id_tipo_servicio = ts.id_tipo_servicio
             LEFT JOIN comisiones_oficiales co ON ch.id_comision = co.id_comision
             LEFT JOIN usuarios u ON ch.aplicada_por = u.id
             WHERE 1=1
             {$filtro_estado}
             ORDER BY ch.created_at DESC
             LIMIT :limite",
                $params
            );

            // Estadísticas generales
            $estadisticas = AsignacionServicio::fetchFirst(
                "SELECT 
                COUNT(*) as total_compensaciones,
                SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'APLICADA' THEN 1 ELSE 0 END) as aplicadas,
                SUM(CASE WHEN estado = 'PENDIENTE' THEN puntos_compensacion ELSE 0 END) as puntos_pendientes_total,
                SUM(CASE WHEN estado = 'APLICADA' THEN puntos_compensacion ELSE 0 END) as puntos_aplicados_total
             FROM compensaciones_historial",
                []
            );

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Historial obtenido',
                'compensaciones' => $compensaciones,
                'estadisticas' => $estadisticas
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log("❌ ERROR en historialCompensacionesAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener historial',
                'detalle' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 🆕 API: Cancelar compensación aplicada (revertir)
     */
    public static function cancelarCompensacionAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $id_compensacion = $_POST['id_compensacion'] ?? null;
            $usuario_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

            if (!$id_compensacion) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Debe proporcionar el ID de la compensación'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            AsignacionServicio::beginTransaction();

            // Obtener info de la compensación
            $compensacion = AsignacionServicio::fetchFirst(
                "SELECT * FROM compensaciones_historial WHERE id_compensacion = :id",
                [':id' => $id_compensacion]
            );

            if (!$compensacion) {
                throw new \Exception('Compensación no encontrada');
            }

            if ($compensacion['estado'] !== 'APLICADA') {
                throw new \Exception('Solo se pueden cancelar compensaciones aplicadas');
            }

            // 1. Revertir estado de compensación
            AsignacionServicio::ejecutarQuery(
                "UPDATE compensaciones_historial
             SET estado = 'PENDIENTE',
                 fecha_aplicacion = NULL,
                 aplicada_por = NULL
             WHERE id_compensacion = :id",
                [':id' => $id_compensacion]
            );

            // 2. Restaurar servicios cancelados
            AsignacionServicio::ejecutarQuery(
                "UPDATE asignaciones_servicio
             SET estado = 'PROGRAMADO',
                 observaciones = REPLACE(observaciones, ' - COMPENSADO POR REEMPLAZOS', '')
             WHERE id_personal = :id_personal
             AND fecha_servicio = :fecha
             AND estado = 'COMPENSADO'",
                [
                    ':id_personal' => $compensacion['id_personal'],
                    ':fecha' => $compensacion['fecha_aplicacion']
                ]
            );

            // 3. Actualizar historial
            AsignacionServicio::ejecutarQuery(
                "UPDATE historial_rotaciones
             SET compensacion_pendiente = TRUE,
                 puntos_compensacion_acumulados = puntos_compensacion_acumulados + :puntos
             WHERE id_personal = :id_personal",
                [
                    ':puntos' => $compensacion['puntos_compensacion'],
                    ':id_personal' => $compensacion['id_personal']
                ]
            );

            AsignacionServicio::commit();

            error_log("✅ Compensación {$id_compensacion} cancelada exitosamente");

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Compensación cancelada y servicios restaurados exitosamente'
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            AsignacionServicio::rollback();
            error_log("❌ ERROR en cancelarCompensacionAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al cancelar compensación',
                'detalle' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ========================================
    // 🆕 ENDPOINTS DE COMISIONES
    // ========================================

    /**
     * 🆕 API: Registrar comisión oficial
     */
    /**
     * 🆕 API: Registrar comisión oficial (versión con asignación manual)
     */
    public static function registrarComisionAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');
        ob_start();

        try {
            $id_personal = $_POST['id_personal'] ?? null;
            $fecha_inicio = $_POST['fecha_inicio'] ?? null;
            $fecha_fin = $_POST['fecha_fin'] ?? null;
            $numero_oficio = $_POST['numero_oficio'] ?? null;
            $destino = $_POST['destino'] ?? 'Ciudad Capital';
            $motivo = $_POST['motivo'] ?? '';

            $usuario_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

            ob_clean();

            // Validaciones
            if (!$id_personal || !$fecha_inicio || !$fecha_fin || !$numero_oficio) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Datos incompletos. Se requiere: personal, fechas y número de oficio'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Validar formato de fechas
            if (
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio) ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)
            ) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Formato de fecha inválido'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Validar que fecha_fin >= fecha_inicio
            if (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $resultado = AsignacionServicio::registrarComision([
                'id_personal' => $id_personal,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'destino' => $destino,
                'numero_oficio' => $numero_oficio,
                'motivo' => $motivo,
                'created_by' => $usuario_id
            ]);

            ob_end_clean();

            if ($resultado['exito']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => $resultado['mensaje'],
                    'data' => $resultado['data'],
                    'requiere_asignacion_manual' => !empty($resultado['data']['servicios_pendientes_reemplazo'])
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => $resultado['mensaje']
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (\Exception $e) {
            ob_end_clean();

            error_log("❌ ERROR en registrarComisionAPI: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al registrar comisión: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }


    /**
     * 🆕 API: Confirmar reemplazos seleccionados manualmente
     */
    public static function confirmarReemplazosAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            $reemplazos = $data['reemplazos'] ?? [];
            $id_comision = $data['id_comision'] ?? null;
            $usuario_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 1;

            if (empty($reemplazos) || !$id_comision) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Datos incompletos'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $resultado = AsignacionServicio::confirmarReemplazos($reemplazos, $id_comision, $usuario_id);

            if ($resultado['exito']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => $resultado['mensaje'],
                    'data' => [
                        'confirmados' => $resultado['confirmados'],
                        'errores' => $resultado['errores'],
                        'detalles' => $resultado['detalles_confirmados']
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => $resultado['mensaje']
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (\Exception $e) {
            error_log("❌ ERROR en confirmarReemplazosAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }




    /**
     * 🆕 API: Recalcular TODO el historial de rotaciones
     */
    public static function recalcularHistorialAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            error_log("🔄 === RECALCULANDO TODO EL HISTORIAL ===");

            // Obtener todo el personal activo
            $todo_personal = AsignacionServicio::fetchArray(
                "SELECT id_personal, CONCAT(nombres, ' ', apellidos) as nombre 
             FROM bhr_personal 
             WHERE activo = 1",
                []
            );

            $recalculados = 0;
            $errores = 0;

            foreach ($todo_personal as $persona) {
                error_log("   Procesando: {$persona['nombre']} (ID: {$persona['id_personal']})");

                if (AsignacionServicio::recalcularHistorialPersona($persona['id_personal'])) {
                    $recalculados++;
                } else {
                    $errores++;
                }
            }

            // Actualizar días desde último servicio
            AsignacionServicio::actualizarDiasDesdeUltimo();

            error_log("🎉 RECÁLCULO COMPLETADO");
            error_log("   ✅ Exitosos: {$recalculados}");
            error_log("   ❌ Errores: {$errores}");

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => "Historial recalculado exitosamente",
                'personas_actualizadas' => $recalculados,
                'errores' => $errores,
                'total_personal' => count($todo_personal)
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log("❌ ERROR FATAL: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al recalcular historial: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    /**
     * 🆕 API: Obtener servicios afectados por rango de fechas
     */
    public static function serviciosAfectadosAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $id_personal = $_GET['id_personal'] ?? null;
        $fecha_inicio = $_GET['fecha_inicio'] ?? null;
        $fecha_fin = $_GET['fecha_fin'] ?? null;

        if (!$id_personal || !$fecha_inicio || !$fecha_fin) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Parámetros incompletos'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $servicios = AsignacionServicio::fetchArray(
                "SELECT 
                    a.id_asignacion,
                    a.fecha_servicio,
                    ts.nombre as servicio,
                    a.hora_inicio,
                    a.hora_fin,
                    a.estado
                 FROM asignaciones_servicio a
                 INNER JOIN tipos_servicio ts ON a.id_tipo_servicio = ts.id_tipo_servicio
                 WHERE a.id_personal = :id_personal
                 AND a.fecha_servicio BETWEEN :fecha_inicio AND :fecha_fin
                 AND a.estado = 'PROGRAMADO'
                 ORDER BY a.fecha_servicio ASC",
                [
                    ':id_personal' => $id_personal,
                    ':fecha_inicio' => $fecha_inicio,
                    ':fecha_fin' => $fecha_fin
                ]
            );

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Servicios encontrados',
                'servicios' => $servicios,
                'total' => count($servicios)
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 🆕 API: Obtener comisiones activas
     */
    public static function comisionesActivasAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $comisiones = AsignacionServicio::obtenerComisionesActivas();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Comisiones obtenidas',
                'comisiones' => $comisiones
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            error_log("❌ ERROR en comisionesActivasAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ========================================
    // EXPORTAR PDF (sin cambios)
    // ========================================

    /**
     * ✅ MEJORADO: Exporta PDF de ciclo de 10 días
     */
    public static function exportarPDF(Router $router)
    {
        $fecha_inicio = $_GET['fecha'] ?? null;

        if (!$fecha_inicio) {
            header('Location: /TERCERA_CIA/asignaciones');
            exit;
        }

        try {
            $asignaciones = AsignacionServicio::obtenerAsignacionesSemana($fecha_inicio);

            if (empty($asignaciones)) {
                header('Location: /TERCERA_CIA/asignaciones');
                exit;
            }

            // Agrupar por día
            $dias = [];
            foreach ($asignaciones as $asig) {
                $fecha_servicio = $asig['fecha_servicio'];
                if (!isset($dias[$fecha_servicio])) {
                    $dias[$fecha_servicio] = [];
                }
                $dias[$fecha_servicio][] = $asig;
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'Letter',
                'orientation' => 'P',
                'margin_left' => 5,
                'margin_right' => 5,
                'margin_top' => 5,
                'margin_bottom' => 5,
            ]);

            $html = '';

            // ✅ GENERAR 10 PÁGINAS INDIVIDUALES
            $contador_dia = 0;
            foreach ($dias as $fecha_dia => $servicios_dia) {
                if ($contador_dia > 0) {
                    $html .= '<pagebreak />';
                }

                $fecha_obj = new \DateTime($fecha_dia);
                $dia_nombre = self::getNombreDia($fecha_obj->format('N'));
                $fecha_formateada = self::formatearFechaEspanol($fecha_obj);

                $servicios_agrupados = [];
                $oficial_dia = '';

                foreach ($servicios_dia as $servicio) {
                    $tipo = $servicio['servicio'];
                    if (!isset($servicios_agrupados[$tipo])) {
                        $servicios_agrupados[$tipo] = [];
                    }
                    $servicios_agrupados[$tipo][] = $servicio;

                    if (!empty($servicio['oficial_encargado'])) {
                        $oficial_dia = $servicio['grado_oficial'] . ' ' . $servicio['oficial_encargado'];
                    }
                }

                $html .= self::generarPaginaDia($dia_nombre, $fecha_formateada, $oficial_dia, $servicios_agrupados);
                $contador_dia++;
            }

            // ✅ GENERAR CRONOGRAMA EN 2 TABLAS DE 5 DÍAS CON FECHAS REALES
            $html .= '<pagebreak />';
            $html .= self::generarCronogramaCiclo($asignaciones, $fecha_inicio);

            $mpdf->WriteHTML($html);

            $nombreArchivo = 'servicios_ciclo_' . $fecha_inicio . '.pdf';
            $mpdf->Output($nombreArchivo, 'I');
        } catch (\Exception $e) {
            error_log("Error al generar PDF: " . $e->getMessage());
            header('Location: /TERCERA_CIA/asignaciones');
            exit;
        }
    }

    // ========================================
    // FUNCIONES AUXILIARES PDF (sin cambios)
    // ========================================

    private static function generarPaginaDia($dia_nombre, $fecha_formateada, $oficial_dia, $servicios_agrupados)
    {
        $html = '
    <div style="border-bottom: 3px solid #2d5016; padding-bottom: 10px; margin-bottom: 20px;">
        <h2 style="color: #2d5016; margin: 0; text-align: center; font-size: 24px;">
            SERVICIOS 3RA. CIA. 2DO. BTN. BHR.
        </h2>
        <h1 style="color: #ff7b00; margin: 10px 0; text-align: center; font-size: 20px;">
            PARA EL DÍA ' . strtoupper($dia_nombre) . ' ' . strtoupper($fecha_formateada) . '
        </h1>';

        if (!empty($oficial_dia)) {
            $html .= '
        <div style="background: #2d5016; color: white; padding: 10px; border-radius: 8px; text-align: center; margin-top: 10px;">
            <strong>OFICIAL ENCARGADO:</strong> ' . htmlspecialchars($oficial_dia) . '
        </div>';
        }

        $html .= '</div>';

        $orden_servicios = ['Semana', 'TACTICO', 'TACTICO TROPA', 'RECONOCIMIENTO', 'SERVICIO NOCTURNO', 'BANDERÍN', 'CUARTELERO'];
        $colores = [
            'Semana' => '#a03500ff',
            'TACTICO' => '#c85a28',
            'TACTICO TROPA' => '#d4763b',
            'RECONOCIMIENTO' => '#2d5016',
            'SERVICIO NOCTURNO' => '#1a472a',
            'BANDERÍN' => '#b8540f',
            'CUARTELERO' => '#3d6b1f'
        ];

        foreach ($orden_servicios as $tipo_servicio) {
            if (!isset($servicios_agrupados[$tipo_servicio])) continue;

            $color = $colores[$tipo_servicio] ?? '#2d5016';
            $personal = $servicios_agrupados[$tipo_servicio];
            $nombre_mostrar = ($tipo_servicio === 'TACTICO') ? 'TÁCTICO' : $tipo_servicio;

            if ($tipo_servicio === 'CUARTELERO') {
                $nombre_mostrar = 'CUARTELERO Y CUARTO TURNO';
            }

            $html .= '
        <div style="background: ' . $color . '; color: white; border-radius: 12px; padding: 4px; margin-bottom: 5px;">
    <h3 style="margin: 0 0 10px 0; font-size: 15px;">▶  ' . strtoupper($nombre_mostrar) . '</h3>';

            if ($tipo_servicio === 'SERVICIO NOCTURNO') {
                usort($personal, function ($a, $b) {
                    return strcmp($a['hora_inicio'], $b['hora_inicio']);
                });

                $turnos = [' PRIMER TURNO', ' SEGUNDO TURNO', ' TERCER TURNO', ' CUARTO TURNO'];

                foreach ($personal as $index => $persona) {
                    $turno_texto = $turnos[$index] ?? '';
                    $grado_completo = htmlspecialchars($persona['grado']);
                    if (!empty($persona['tipo_personal']) && $persona['tipo_personal'] === 'ESPECIALISTA') {
                        $grado_completo .= ' ESPECIALISTA';
                    }

                    $html .= '
                <div style="background: rgba(255,255,255,0.2); padding: 6px 10px; border-radius: 8px; margin-bottom: 5px;">
                    <strong>' . $grado_completo . '</strong> ' .
                        htmlspecialchars($persona['nombre_completo']) .
                        '<span style="float: right; font-weight: bold; font-size: 15px;">' .
                        $turno_texto . '</span>
                </div>';
                }
            } else {
                foreach ($personal as $persona) {
                    $grado_completo = htmlspecialchars($persona['grado']);
                    if (!empty($persona['tipo_personal']) && $persona['tipo_personal'] === 'ESPECIALISTA') {
                        $grado_completo .= ' ESPECIALISTA';
                    }

                    $html .= '
                <div style="background: rgba(255,255,255,0.2); padding: 8px 12px; border-radius: 8px; margin-bottom: 5px;">
                    <strong>' . $grado_completo . '</strong> ' .
                        htmlspecialchars($persona['nombre_completo']) . '
                </div>';
                }
            }

            $html .= '</div>';
        }

        return $html;
    }

    private static function generarCronogramaCiclo($asignaciones, $fecha_inicio)
    {
        $fecha_inicio_obj = new \DateTime($fecha_inicio);
        $fecha_fin_obj = new \DateTime($fecha_inicio);
        $fecha_fin_obj->modify('+9 days');

        $fecha_inicio_formateada = self::formatearFechaEspanol($fecha_inicio_obj);
        $fecha_fin_formateada = self::formatearFechaEspanol($fecha_fin_obj);

        $html = '
    <div style="text-align: center; margin-bottom: 20px;">
        <h1 style="color: #2d5016; margin: 0;">CRONOGRAMA CICLO 10 DÍAS</h1>
        <h3 style="color: #ff7b00; margin: 5px 0;">Del ' . $fecha_inicio_formateada . ' al ' . $fecha_fin_formateada . '</h3>
    </div>';

        // Procesar personal y servicios
        $serviciosSemana = array_filter($asignaciones, fn($a) => $a['servicio'] === 'Semana');
        $serviciosDiarios = array_filter($asignaciones, fn($a) => $a['servicio'] !== 'Semana');

        $personal_servicios = [];

        foreach ($serviciosDiarios as $asig) {
            $id = $asig['id_personal'];
            $fecha_servicio = $asig['fecha_servicio'];
            $dia_num = (new \DateTime($fecha_servicio))->diff(new \DateTime($fecha_inicio))->days + 1;

            if (!isset($personal_servicios[$id])) {
                // ✨ MEJORADO: Guardar el tipo de personal
                $grado_mostrar = $asig['grado'];
                if (!empty($asig['tipo_personal']) && $asig['tipo_personal'] === 'ESPECIALISTA') {
                    $grado_mostrar .= ' ESPECIALISTA';
                }

                $personal_servicios[$id] = [
                    'nombre' => $asig['nombre_completo'],
                    'grado' => $grado_mostrar,  // ✅ Ahora incluye "ESPECIALISTA" si aplica
                    'tipo' => $asig['tipo_personal'],
                    'servicios' => [],
                    'tiene_semana' => false
                ];

                for ($d = 1; $d <= 10; $d++) {
                    $personal_servicios[$id]['servicios'][$d] = [];
                }
            }

            $abrev = self::getAbreviatura($asig['servicio']);
            if ($asig['servicio'] === 'SERVICIO NOCTURNO') {
                $turno = self::obtenerNumeroTurno($asig, $asignaciones);
                $turnos_texto = [1 => '1ER TURNO', 2 => '2DO TURNO', 3 => '3ER TURNO'];
                $abrev = $turnos_texto[$turno] ?? 'TURNO ' . $turno;
            }

            $personal_servicios[$id]['servicios'][$dia_num][] = $abrev;
        }

        // Procesar servicio SEMANA
        foreach ($serviciosSemana as $s) {
            $id = $s['id_personal'];
            if (!isset($personal_servicios[$id])) {
                // ✨ MEJORADO: Incluir tipo de personal
                $grado_mostrar = $s['grado'];
                if (!empty($s['tipo_personal']) && $s['tipo_personal'] === 'ESPECIALISTA') {
                    $grado_mostrar .= ' ESPECIALISTA';
                }

                $personal_servicios[$id] = [
                    'nombre' => $s['nombre_completo'],
                    'grado' => $grado_mostrar,
                    'tipo' => $s['tipo_personal'],
                    'servicios' => [],
                    'tiene_semana' => true
                ];

                for ($d = 1; $d <= 10; $d++) {
                    $personal_servicios[$id]['servicios'][$d] = ['SEM'];
                }
            }
        }

        // Calcular totales
        foreach ($personal_servicios as $id => &$persona) {
            $total = 0;
            foreach ($persona['servicios'] as $dia) {
                $total += count($dia);
            }
            $persona['total_servicios'] = $total;
        }
        unset($persona);

        // ✅ MEJORADO: Ordenar separando ESPECIALISTAS y TROPA
        uasort($personal_servicios, function ($a, $b) {
            $tipo_a = $a['tipo'] ?? 'TROPA';
            $tipo_b = $b['tipo'] ?? 'TROPA';

            // Prioridad: ESPECIALISTAS primero
            if ($tipo_a === 'ESPECIALISTA' && $tipo_b !== 'ESPECIALISTA') return -1;
            if ($tipo_a !== 'ESPECIALISTA' && $tipo_b === 'ESPECIALISTA') return 1;

            // Dentro del mismo tipo, ordenar por grado
            $orden_grados = [
                'Soldado de Segunda ESPECIALISTA' => 1,
                'Soldado de Primera ESPECIALISTA' => 2,
                'Soldado de Segunda' => 3,
                'Soldado de Primera' => 4,
                'Cabo' => 5,
                'Sargento 2do.' => 6,
                'Sargento 1ro.' => 7
            ];

            $grado_a = $orden_grados[$a['grado']] ?? 999;
            $grado_b = $orden_grados[$b['grado']] ?? 999;

            if ($grado_a !== $grado_b) return $grado_a - $grado_b;

            return $a['total_servicios'] - $b['total_servicios'];
        });

        // ✅ GENERAR 2 TABLAS DE 5 DÍAS CADA UNA CON FECHAS
        $html .= self::generarTablaRango($personal_servicios, 1, 5, "DÍAS 1-5", $fecha_inicio);
        $html .= '<div style="height: 30px;"></div>'; // Separador
        $html .= self::generarTablaRango($personal_servicios, 6, 10, "DÍAS 6-10", $fecha_inicio);

        // Leyenda
        $html .= '
    <div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px; page-break-inside: avoid;">
        <h4 style="margin: 0 0 8px 0; font-size: 11px;">LEYENDA DE SERVICIOS</h4>
        <table style="width: 100%; font-size: 9px;">
            <tr>
                <td><strong style="color: #ff9966;">SEM</strong> = Semana (10 días completos)</td>
                <td><strong style="color: #c85a28;">TACTICO</strong> = Táctico (Especialista)</td>
                <td><strong style="color: #d4763b;">TAC-T</strong> = Táctico Tropa</td>
            </tr>
            <tr>
                <td><strong style="color: #2d5016;">ERI</strong> = Reconocimiento</td>
                <td><strong style="color: #1a472a;">1ER/2DO/3ER TURNO</strong> = Servicio Nocturno</td>
                <td><strong style="color: #b8540f;">BANDERIN</strong> = Banderín</td>
            </tr>
            <tr>
                <td><strong style="color: #3d6b1f;">CUARTELERO</strong> = Cuartelero (Cuarto Turno)</td>
                <td colspan="2"></td>
            </tr>
        </table>
    </div>';

        return $html;
    }

    private static function generarTablaRango($personal_servicios, $dia_inicio, $dia_fin, $titulo, $fecha_inicio_ciclo)
    {
        $html = '
    <div style="page-break-inside: avoid;">
        <h2 style="color: #2d5016; text-align: center; margin: 10px 0;">' . $titulo . '</h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
            <thead>
                <tr style="background: #2d5016; color: white;">
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 23%; color: white;">NOMBRE</th>';

        // ✅ Headers de días CON FECHAS REALES
        for ($dia = $dia_inicio; $dia <= $dia_fin; $dia++) {
            $fecha_actual = new \DateTime($fecha_inicio_ciclo);
            $fecha_actual->modify('+' . ($dia - 1) . ' days');

            $dia_semana = self::getNombreDiaCorto($fecha_actual->format('N'));
            $dia_numero = $fecha_actual->format('d');

            $html .= '<th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 12%; font-size: 8px; color: white;">
                        <div style="font-weight: bold; color: white;">' . strtoupper($dia_semana) . '</div>
                        <div style="font-size: 11px; font-weight: bold; margin-top: 2px; color: white;">' . $dia_numero . '</div>
                      </th>';
        }

        $html .= '
                    <th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 7%; color: white;">TOTAL</th>
                </tr>
            </thead>
            <tbody>';

        $contador = 0;
        $tipo_anterior = null;
        $primera_tropa = true; // Para controlar que la línea aparezca solo una vez

        foreach ($personal_servicios as $persona) {
            $bgColor = ($contador % 2 == 0) ? '#f8f9fa' : '#ffffff';
            $tipo_actual = $persona['tipo'] ?? 'TROPA';

            // ✅ SEPARADOR VISUAL entre ESPECIALISTAS y TROPA CON ENCABEZADOS REPETIDOS
            if ($tipo_anterior === 'ESPECIALISTA' && $tipo_actual === 'TROPA' && $primera_tropa) {
                $html .= '
            <tr style="background: #2d5016; color: white;">
                <td style="border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold; color: white;">NOMBRE</td>';

                // Repetir los encabezados de fechas
                for ($dia = $dia_inicio; $dia <= $dia_fin; $dia++) {
                    $fecha_actual = new \DateTime($fecha_inicio_ciclo);
                    $fecha_actual->modify('+' . ($dia - 1) . ' days');

                    $dia_semana = self::getNombreDiaCorto($fecha_actual->format('N'));
                    $dia_numero = $fecha_actual->format('d');

                    $html .= '<td style="border: 1px solid #ddd; padding: 6px; text-align: center; font-size: 8px; color: white;">
                                <div style="font-weight: bold; color: white;">' . strtoupper($dia_semana) . '</div>
                                <div style="font-size: 11px; font-weight: bold; margin-top: 2px; color: white;">' . $dia_numero . '</div>
                              </td>';
                }

                $html .= '
                <td style="border: 1px solid #ddd; padding: 6px; text-align: center; font-weight: bold; color: white;">TOTAL</td>
            </tr>';
                $primera_tropa = false;
            }

            $tipo_anterior = $tipo_actual;

            $html .= '
        <tr style="background: ' . $bgColor . ';">
            <td style="border: 1px solid #ddd; padding: 4px; font-weight: bold; font-size: 8px;">
                ' . htmlspecialchars($persona['grado'] . ' ' . $persona['nombre']) . '
            </td>';

            // Generar columnas de días
            for ($dia = $dia_inicio; $dia <= $dia_fin; $dia++) {
                $servicios_dia = $persona['servicios'][$dia];

                if (empty($servicios_dia)) {
                    $html .= '
                <td style="border: 1px solid #ddd; padding: 4px; text-align: center; color: #ccc;">
                    -
                </td>';
                } else {
                    if (in_array('SEM', $servicios_dia)) {
                        $html .= '
                    <td style="border: 1px solid #ddd; padding: 4px; text-align: center; font-weight: bold; color: #ff9966; font-size: 10px;">
                        SEM
                    </td>';
                    } else {
                        $servicios_html = [];
                        foreach ($servicios_dia as $serv) {
                            $color = self::getColorAbreviatura($serv);
                            $servicios_html[] = '<span style="color: ' . $color . '; font-weight: bold;">' . $serv . '</span>';
                        }

                        $html .= '
                    <td style="border: 1px solid #ddd; padding: 4px; text-align: center; font-size: 8px; line-height: 1.3;">
                        ' . implode('<br>', $servicios_html) . '
                    </td>';
                    }
                }
            }

            // Columna TOTAL
            $html .= '
            <td style="border: 1px solid #ddd; padding: 4px; text-align: center; font-weight: bold; background: #e8f5e9;">
                ' . $persona['total_servicios'] . '
            </td>
        </tr>';

            $contador++;
        }

        $html .= '
        </tbody>
    </table>
    </div>';

        return $html;
    }

    private static function obtenerNumeroTurno($asignacion_actual, $todas_asignaciones)
    {
        $nocturnos_dia = array_filter($todas_asignaciones, function ($asig) use ($asignacion_actual) {
            return $asig['fecha_servicio'] === $asignacion_actual['fecha_servicio']
                && $asig['servicio'] === 'SERVICIO NOCTURNO';
        });

        usort($nocturnos_dia, function ($a, $b) {
            return $a['id_asignacion'] - $b['id_asignacion'];
        });

        $turno = 1;
        foreach ($nocturnos_dia as $nocturno) {
            if ($nocturno['id_asignacion'] === $asignacion_actual['id_asignacion']) {
                return $turno;
            }
            $turno++;
        }

        return 1;
    }

    private static function getNombreDia($num)
    {
        $dias = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
        return $dias[$num];
    }

    private static function getNombreDiaCorto($num)
    {
        $dias = ['', 'LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO', 'DOMINGO'];
        return $dias[$num];
    }

    private static function formatearFechaEspanol($fecha_obj)
    {
        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre'
        ];

        $dia = $fecha_obj->format('d');
        $mes = $meses[(int)$fecha_obj->format('m')];
        $anio = $fecha_obj->format('Y');

        return "{$dia} de {$mes} de {$anio}";
    }

    private static function getAbreviatura($servicio)
    {
        $abreviaturas = [
            'Semana' => 'SEM',
            'TACTICO' => 'TACTICO',
            'TACTICO TROPA' => 'TAC-T',
            'RECONOCIMIENTO' => 'ERI',
            'SERVICIO NOCTURNO' => 'NOC',
            'BANDERÍN' => 'BANDERIN',
            'CUARTELERO' => 'CUARTELERO'
        ];
        return $abreviaturas[$servicio] ?? '-';
    }

    private static function getColorAbreviatura($abrev)
    {
        if (strpos($abrev, 'TAC-T') === 0) return '#d4763b';

        $servicio_base = substr($abrev, 0, 3);

        $colores = [
            'SEM' => '#ff9966',
            'TAC' => '#c85a28',
            'REC' => '#2d5016',
            'NOC' => '#1a472a',
            'BAN' => '#b8540f',
            'CUA' => '#3d6b1f',
            'QUA' => '#3d6b1f',
            'ERI' => '#2d5016',
            '1ER' => '#1a472a',
            '2DO' => '#1a472a',
            '3ER' => '#1a472a'
        ];

        return $colores[$servicio_base] ?? '#000000';
    }

    /**
     * ✨ NUEVO: Obtiene el historial de todos los ciclos generados
     */
    public static function historialCiclosAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $ciclos = AsignacionServicio::obtenerHistorialCiclos();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Historial obtenido',
                'ciclos' => $ciclos
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener historial',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * ✨ NUEVO: Obtiene el historial de todos los ciclos generados
     */
    public static function obtenerTodosCiclosAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $ciclos = AsignacionServicio::obtenerHistorialCiclos();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Historial obtenido',
                'ciclos' => $ciclos
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener historial',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
