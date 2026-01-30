<?php

namespace Controllers;

use Exception;
use Model\AsignacionServicio;
use Model\Personal;
use Model\TiposServicio;
use MVC\Router;
use Mpdf\Mpdf;

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
     * ✅ MEJORADO: Exporta PDF de ciclo de 10 días
     * - 10 páginas individuales (una por día)
     * - 1 cronograma dividido en 2 tablas de 5 días cada una CON FECHAS REALES
     * - Muestra el TIPO de personal correctamente (ESPECIALISTA)
     * - Separa visualmente ESPECIALISTAS de TROPA con líneas divisorias
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

    /**
     * ✅ Genera página individual de un día (sin cambios)
     */
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

    /**
     * ✅ MEJORADO: Genera cronograma dividido en 2 tablas de 5 días cada una
     * - Muestra días de la semana y fechas reales en los encabezados
     * - Muestra el tipo de personal correctamente (ESPECIALISTA)
     * - Separa visualmente ESPECIALISTAS de TROPA
     */
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

    /**
     * ✅ MEJORADO: Genera una tabla para un rango de días específico CON FECHAS REALES
     * - Muestra días de la semana abreviados (MAR, MIÉ, etc.)
     * - Muestra el número de día del mes
     * - Separa ESPECIALISTAS de TROPA con línea divisoria verde
     */
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

    // ========================================
    // FUNCIONES AUXILIARES
    // ========================================

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

    /**
     * ✅ MODIFICADA: Obtiene nombre de día COMPLETO en español (en mayúsculas)
     */
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
}
