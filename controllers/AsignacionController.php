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
            'titulo' => 'Generador de Servicios Semanales'
        ]);
    }

    /**
     * Genera asignaciones para una semana completa
     */
    // En AsignacionController.php
    /**
     * ✨ MODIFICACIÓN: Recibir grupos disponibles desde el frontend
     * Genera asignaciones para una semana completa
     */
    /**
     * Genera asignaciones para una semana completa
     * ✅ VERSIÓN CORREGIDA - Usa AsignacionServicio:: en lugar de self::
     */
    public static function generarSemanaAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        ob_start();

        $debug = [];
        $debug['paso_1'] = 'Iniciando proceso';

        $fecha_inicio = $_POST['fecha_inicio'] ?? '';

        // ✨ NUEVO: Recibir grupos disponibles
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

        // ✨ VALIDACIÓN: Si no hay grupos, usar todos por defecto
        if (empty($grupos_disponibles)) {
            error_log("⚠️ No se especificaron grupos, usando todos por defecto");

            // ✅ CORRECCIÓN: Usar AsignacionServicio:: en lugar de self::
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
            if ($fecha->format('N') != 1) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'La fecha debe ser un LUNES',
                    'debug' => $debug
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $usuario_id = $_SESSION['user_id'] ?? null;

            // ✨ MODIFICADO: Pasar grupos disponibles al generador
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

    /**
     * ✨ NUEVO ENDPOINT: Contar personal disponible por grupos
     * Ruta: /API/asignaciones/contar-personal
     */
    /**
     * ✨ ENDPOINT: Contar personal disponible por grupos
     * Ruta: /API/asignaciones/contar-personal
     */
    public static function contarPersonalAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            $grupos = $data['grupos'] ?? [];

            error_log("📦 Grupos recibidos: " . json_encode($grupos));

            if (!is_array($grupos)) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Formato de grupos inválido'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (empty($grupos)) {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No se proporcionaron grupos'
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Convertir a placeholders para SQL
            $placeholders = [];
            $params = [];

            foreach ($grupos as $index => $id_grupo) {
                $key = ":grupo_{$index}";
                $placeholders[] = $key;
                $params[$key] = (int)$id_grupo;
            }

            $in_clause = implode(',', $placeholders);

            // ✅ CAMBIO: Usar AsignacionServicio::fetchFirst() en lugar de self::fetchFirst()

            // Contar Oficiales
            $sql_oficiales = "SELECT COUNT(*) as total 
                         FROM bhr_personal 
                         WHERE tipo = 'OFICIAL' 
                         AND activo = 1
                         AND id_grupo_descanso IN ($in_clause)";

            $oficiales = AsignacionServicio::fetchFirst($sql_oficiales, $params);

            // Contar Especialistas
            $sql_especialistas = "SELECT COUNT(*) as total 
                             FROM bhr_personal 
                             WHERE tipo = 'ESPECIALISTA' 
                             AND activo = 1
                             AND id_grupo_descanso IN ($in_clause)";

            $especialistas = AsignacionServicio::fetchFirst($sql_especialistas, $params);

            // Contar Tropa
            $sql_tropa = "SELECT COUNT(*) as total 
                     FROM bhr_personal 
                     WHERE tipo = 'TROPA' 
                     AND activo = 1
                     AND id_grupo_descanso IN ($in_clause)";

            $tropa = AsignacionServicio::fetchFirst($sql_tropa, $params);

            $total = ($oficiales['total'] ?? 0) + ($especialistas['total'] ?? 0) + ($tropa['total'] ?? 0);

            error_log("✅ Total personal disponible: " . $total);

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
            error_log("❌ ERROR en contarPersonalAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al contar personal',
                'detalle' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    /**
     * Obtiene las asignaciones de una semana específica
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
     * Elimina las asignaciones de una semana
     */
    /**
     * Elimina las asignaciones de una semana
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
                'mensaje' => 'Asignaciones eliminadas y historial recalculado exitosamente',
                'registros_eliminados' => $resultado['resultado'] ?? 0
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            error_log("❌ ERROR en eliminarSemanaAPI: " . $e->getMessage());

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al eliminar asignaciones',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Exporta a PDF las asignaciones de una semana
     */
    public static function exportarPDFSemanaAPI()
    {
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';

        if (empty($fecha_inicio)) {
            die('Debe proporcionar una fecha de inicio');
        }

        try {
            $asignaciones = AsignacionServicio::obtenerAsignacionesSemana($fecha_inicio);

            // Aquí implementarías la generación del PDF
            // Puedes usar TCPDF, FPDF o Dompdf

            // Por ahora retornamos un mensaje
            header('Content-Type: application/json');
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Función de PDF pendiente de implementar',
                'datos' => $asignaciones
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al generar PDF',
                'detalle' => $e->getMessage(),
            ]);
        }
    }

    public static function exportarPDF(Router $router)
    {

        $fecha_inicio = $_GET['fecha'] ?? null;

        if (!$fecha_inicio) {
            header('Location: /TERCERA_CIA/asignaciones');
            exit;
        }

        try {
            // Verificar que sea lunes
            $fecha = new \DateTime($fecha_inicio);
            if ($fecha->format('N') != 1) {
                header('Location: /TERCERA_CIA/asignaciones');
                exit;
            }

            // Obtener asignaciones de la semana
            $asignaciones = AsignacionServicio::obtenerAsignacionesSemana($fecha_inicio);

            // ⬇️ AGREGAR ESTO PARA DEBUG
            error_log("========== DEBUG ASIGNACIONES ==========");
            error_log("Total asignaciones: " . count($asignaciones));

            $servicios_unicos = [];
            foreach ($asignaciones as $asig) {
                $servicios_unicos[$asig['servicio']] = true;
            }
            error_log("Servicios encontrados: " . json_encode(array_keys($servicios_unicos)));
            error_log("========================================");
            // ⬆️ FIN DEBUG
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

            // Crear PDF
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

            // === GENERAR UNA PÁGINA POR DÍA ===
            $contador_dia = 0;
            foreach ($dias as $fecha_dia => $servicios_dia) {
                if ($contador_dia > 0) {
                    $html .= '<pagebreak />';
                }

                $fecha_obj = new \DateTime($fecha_dia);
                $dia_nombre = self::getNombreDia($fecha_obj->format('N'));
                $fecha_formateada = self::formatearFechaEspanol($fecha_obj);

                // Agrupar servicios por tipo
                $servicios_agrupados = [];
                $oficial_dia = '';

                foreach ($servicios_dia as $servicio) {
                    $tipo = $servicio['servicio'];

                    if (!isset($servicios_agrupados[$tipo])) {
                        $servicios_agrupados[$tipo] = [];
                    }

                    $servicios_agrupados[$tipo][] = $servicio;

                    // Guardar oficial del día
                    if (!empty($servicio['oficial_encargado'])) {
                        $oficial_dia = $servicio['grado_oficial'] . ' ' . $servicio['oficial_encargado'];
                    }
                }

                $html .= self::generarPaginaDia($dia_nombre, $fecha_formateada, $oficial_dia, $servicios_agrupados);
                $contador_dia++;
            }

            // === PÁGINA FINAL: CRONOGRAMA SEMANAL ===
            $html .= '<pagebreak />';
            $html .= self::generarCronogramaSemanal($asignaciones, $fecha_inicio);

            $mpdf->WriteHTML($html);

            $nombreArchivo = 'servicios_semana_' . $fecha_inicio . '.pdf';
            $mpdf->Output($nombreArchivo, 'I');
        } catch (\Exception $e) {
            error_log("Error al generar PDF: " . $e->getMessage());
            header('Location: /TERCERA_CIA/asignaciones');
            exit;
        }
    }

    /**
     * Formatea fecha en español
     */
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
            <strong> OFICIAL ENCARGADO:</strong> ' . htmlspecialchars($oficial_dia) . '
        </div>';
        }

        $html .= '</div>';

        // Orden de servicios
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

            // ⬇️ Mostrar el nombre con acento para que se vea bonito
            $nombre_mostrar = ($tipo_servicio === 'TACTICO') ? 'TÁCTICO' : $tipo_servicio;

            // ⬇️ AGREGAR "Y CUARTO TURNO" AL CUARTELERO
            if ($tipo_servicio === 'CUARTELERO') {
                $nombre_mostrar = 'CUARTELERO Y CUARTO TURNO';
            }

            $html .= '
        <div style="background: ' . $color . '; color: white; border-radius: 12px; padding: 4px; margin-bottom: 5px;">
    <h3 style="margin: 0 0 10px 0; font-size: 15px;">▶  ' . strtoupper($nombre_mostrar) . '</h3>';

            // ⬇️ SI ES SERVICIO NOCTURNO, ORDENAR Y NUMERAR
            if ($tipo_servicio === 'SERVICIO NOCTURNO') {
                // Ordenar por hora_inicio
                usort($personal, function ($a, $b) {
                    return strcmp($a['hora_inicio'], $b['hora_inicio']);
                });

                // Asignar turno según posición
                $turnos = [' PRIMER TURNO', ' SEGUNDO TURNO', ' TERCER TURNO', ' CUARTO TURNO'];

                foreach ($personal as $index => $persona) {
                    $turno_texto = $turnos[$index] ?? '';

                    // ⬇️ MOSTRAR GRADO + ESPECIALISTA (si aplica) + NOMBRE
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
                // Para otros servicios, sin turno
                foreach ($personal as $persona) {
                    // ⬇️ MOSTRAR GRADO + ESPECIALISTA (si aplica) + NOMBRE
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

    // === GENERAR CRONOGRAMA SEMANAL (ÚLTIMA PÁGINA) ===
    private static function generarCronogramaSemanal($asignaciones, $fecha_inicio)
    {
        $fecha_inicio_obj = new \DateTime($fecha_inicio);
        $fecha_fin_obj = new \DateTime($fecha_inicio);
        $fecha_fin_obj->modify('+6 days'); // Sumar 6 días para llegar al domingo

        $fecha_inicio_formateada = self::formatearFechaEspanol($fecha_inicio_obj);
        $fecha_fin_formateada = self::formatearFechaEspanol($fecha_fin_obj);

        $html = '
    <div style="text-align: center; margin-bottom: 20px;">
        <h1 style="color: #2d5016; margin: 0;">CRONOGRAMA SEMANAL</h1>
        <h3 style="color: #ff7b00; margin: 5px 0;">Del ' . $fecha_inicio_formateada . ' al ' . $fecha_fin_formateada . '</h3>
    </div>';

        // Agrupar por personal (permitir múltiples servicios por día)
        $personal_servicios = [];

        foreach ($asignaciones as $asig) {
            $id = $asig['id_personal'];
            $dia_num = date('N', strtotime($asig['fecha_servicio'])); // 1=Lunes, 7=Domingo

            if (!isset($personal_servicios[$id])) {
                $personal_servicios[$id] = [
                    'nombre' => $asig['nombre_completo'],
                    'grado' => $asig['grado'],
                    'servicios' => [],
                    'tiene_semana' => false  // ⬅️ FLAG PARA SEMANA
                ];

                // Inicializar cada día como array vacío
                for ($d = 1; $d <= 7; $d++) {
                    $personal_servicios[$id]['servicios'][$d] = [];
                }
            }

            // ⬇️ SI ES SEMANA, MARCAR EL FLAG Y LLENAR TODOS LOS DÍAS
            if ($asig['servicio'] === 'Semana') {
                $personal_servicios[$id]['tiene_semana'] = true;
                // Llenar todos los días con SEM
                for ($d = 1; $d <= 7; $d++) {
                    $personal_servicios[$id]['servicios'][$d] = ['SEM'];
                }
                continue; // ⬅️ Saltar al siguiente servicio
            }

            // Agregar servicio (puede haber varios por día)
            $abrev = self::getAbreviatura($asig['servicio']);

            // Si es servicio nocturno, agregar número de turno
            if ($asig['servicio'] === 'SERVICIO NOCTURNO') {
                $turno = self::obtenerNumeroTurno($asig, $asignaciones);

                // ⬇️ USAR TEXTO COMPLETO EN LUGAR DE ABREVIATURA
                $turnos_texto = [
                    1 => '1ER TURNO',
                    2 => '2DO TURNO',
                    3 => '3ER TURNO'
                ];

                $abrev = $turnos_texto[$turno] ?? 'TURNO ' . $turno;
            }

            $personal_servicios[$id]['servicios'][$dia_num][] = $abrev;
        }

        // Primero obtener el tipo de cada persona (ESPECIALISTA o TROPA)
        foreach ($personal_servicios as $id => &$persona) {
            // Buscar el tipo en las asignaciones
            foreach ($asignaciones as $asig) {
                if ($asig['id_personal'] == $id) {
                    $persona['tipo'] = $asig['tipo_personal'];
                    break;
                }
            }

            // Contar total de servicios
            $total = 0;
            foreach ($persona['servicios'] as $dia) {
                $total += count($dia);
            }
            $persona['total_servicios'] = $total;
        }
        unset($persona); // Romper referencia

        // Ordenar: 1) Por tipo (ESPECIALISTA primero), 2) Por grado, 3) Por total de servicios
        uasort($personal_servicios, function ($a, $b) {
            // 1. Primero ESPECIALISTAS
            $tipo_a = $a['tipo'] ?? 'TROPA';
            $tipo_b = $b['tipo'] ?? 'TROPA';

            if ($tipo_a === 'ESPECIALISTA' && $tipo_b !== 'ESPECIALISTA') {
                return -1; // a va primero
            }
            if ($tipo_a !== 'ESPECIALISTA' && $tipo_b === 'ESPECIALISTA') {
                return 1; // b va primero
            }

            // 2. Si son del mismo tipo, ordenar por grado (orden en BD)
            $orden_grados = [
                'Soldado de Primera' => 1,
                'Soldado de Segunda' => 2,
                'Cabo' => 3,
                'Sargento 2do.' => 4,
                'Sargento 1ro.' => 5
            ];

            $grado_a = $orden_grados[$a['grado']] ?? 999;
            $grado_b = $orden_grados[$b['grado']] ?? 999;

            if ($grado_a !== $grado_b) {
                return $grado_a - $grado_b; // Ascendente
            }

            // 3. Si tienen el mismo grado, ordenar por total de servicios (ascendente)
            return $a['total_servicios'] - $b['total_servicios'];
        });

        // Tabla
        $html .= '
    <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
        <thead>
            <tr style="background: #2d5016; color: white;">
                <th style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 28%;">NOMBRE</th>
                <th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 10%;">L</th>
                <th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 10%;">M</th>
                <th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 10%;">M</th>
                <th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 10%;">J</th>
                <th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 10%;">V</th>
                <th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 10%;">S</th>
                <th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 10%;">D</th>
                <th style="border: 1px solid #ddd; padding: 6px; text-align: center; width: 7%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>';

        $contador = 0;
        $tipo_anterior = null;

        foreach ($personal_servicios as $persona) {
            $bgColor = ($contador % 2 == 0) ? '#f8f9fa' : '#ffffff';

            // ⬇️ AGREGAR FILA DE SEPARACIÓN ENTRE ESPECIALISTAS Y TROPA
            $tipo_actual = $persona['tipo'] ?? 'TROPA';

            if ($tipo_anterior !== null && $tipo_anterior !== $tipo_actual) {
                $html .= '
            <tr>
                <td colspan="9" style="background: #2d5016; height: 3px; padding: 0;"></td>
            </tr>';
            }

            $tipo_anterior = $tipo_actual;

            // Contar total de servicios
            $total_servicios = 0;
            foreach ($persona['servicios'] as $dia) {
                $total_servicios += count($dia);
            }

            $html .= '
        <tr style="background: ' . $bgColor . ';">
            <td style="border: 1px solid #ddd; padding: 4px; font-weight: bold; font-size: 8px;">
                ' . htmlspecialchars($persona['grado'] . ' ' . $persona['nombre']) . '
            </td>';

            for ($dia = 1; $dia <= 7; $dia++) {
                $servicios_dia = $persona['servicios'][$dia];

                if (empty($servicios_dia)) {
                    $html .= '
                <td style="border: 1px solid #ddd; padding: 4px; text-align: center; color: #ccc;">
                    -
                </td>';
                } else {
                    // Si tiene SEMANA, mostrar solo eso en grande
                    if (in_array('SEM', $servicios_dia)) {
                        $html .= '
                    <td style="border: 1px solid #ddd; padding: 4px; text-align: center; font-weight: bold; color: #ff9966; font-size: 10px;">
                        SEM
                    </td>';
                    } else {
                        // Mostrar todos los servicios separados por línea
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

            // Columna de TOTAL
            $html .= '
            <td style="border: 1px solid #ddd; padding: 4px; text-align: center; font-weight: bold; background: #e8f5e9;">
                ' . $total_servicios . '
            </td>
        </tr>';

            $contador++;
        }

        $html .= '
        </tbody>
    </table>';

        // Leyenda mejorada
        $html .= '
    <div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px;">
        <h4 style="margin: 0 0 8px 0; font-size: 11px;">LEYENDA DE SERVICIOS</h4>
        <table style="width: 100%; font-size: 9px;">
            <tr>
                <td><strong style="color: #ff9966;">SEM</strong> = Semana (toda la semana)</td>
                <td><strong style="color: #c85a28;">TAC</strong> = Táctico (Especialista)</td>
                <td><strong style="color: #d4763b;">TAC-T</strong> = Táctico Tropa</td>
            </tr>
            <tr>
                <td><strong style="color: #2d5016;">RECO</strong> = Reconocimiento</td>
                <td><strong style="color: #1a472a;">1ER/2DO/3ER TURNO</strong> = Servicio Nocturno</td>
                <td><strong style="color: #b8540f;">BAN</strong> = Banderín</td>
            </tr>
            <tr>
                <td><strong style="color: #3d6b1f;">CUARTO TURNO</strong> = Cuartelero</td>
                <td colspan="2"></td>
            </tr>
        </table>
    </div>';

        return $html;
    }



    private static function obtenerNumeroTurno($asignacion_actual, $todas_asignaciones)
    {
        // Filtrar solo servicios nocturnos del mismo día
        $nocturnos_dia = array_filter($todas_asignaciones, function ($asig) use ($asignacion_actual) {
            return $asig['fecha_servicio'] === $asignacion_actual['fecha_servicio']
                && $asig['servicio'] === 'SERVICIO NOCTURNO';
        });

        // Ordenar por id_asignacion (orden de creación)
        usort($nocturnos_dia, function ($a, $b) {
            return $a['id_asignacion'] - $b['id_asignacion'];
        });

        // Encontrar la posición (turno)
        $turno = 1;
        foreach ($nocturnos_dia as $nocturno) {
            if ($nocturno['id_asignacion'] === $asignacion_actual['id_asignacion']) {
                return $turno;
            }
            $turno++;
        }

        return 1; // Por defecto
    }
    // === FUNCIONES AUXILIARES ===
    private static function getNombreDia($num)
    {
        $dias = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
        return $dias[$num];
    }

    private static function getAbreviatura($servicio)
    {
        $abreviaturas = [
            'Semana' => 'SEM',
            'TACTICO' => 'TACTICO',
            'TACTICO TROPA' => 'TAC-T',
            'RECONOCIMIENTO' => 'ERI',           // ⬅️ CAMBIAR de 'REC' a 'RECO'
            'SERVICIO NOCTURNO' => 'NOC',         // ⬅️ Este ya no se usa (se reemplaza con 1ER TURNO)
            'BANDERÍN' => 'BANDERIN',
            'CUARTELERO' => 'CUARTELERO'        // ⬅️ CAMBIAR de 'CUA' a 'CUARTO TURNO'
        ];
        return $abreviaturas[$servicio] ?? '-';
    }

    private static function getColorAbreviatura($abrev)
    {
        // Para manejar TAC-T (TACTICO TROPA)
        if (strpos($abrev, 'TAC-T') === 0) {
            return '#d4763b';
        }

        $servicio_base = substr($abrev, 0, 3);

        $colores = [
            'SEM' => '#ff9966',
            'TAC' => '#c85a28',
            'REC' => '#2d5016',
            'NOC' => '#1a472a',
            'BAN' => '#b8540f',
            'CUA' => '#3d6b1f',
            'QUA' => '#3d6b1f'  // Para CUARTO TURNO
        ];

        return $colores[$servicio_base] ?? '#000000';
    }

    public static function debugAsignaciones(Router $router)
    {
        isAuth();
        hasPermission(['ADMINISTRADOR']);

        header('Content-Type: application/json');

        $fecha_inicio = $_GET['fecha'] ?? null;

        if (!$fecha_inicio) {
            echo json_encode(['error' => 'No se proporcionó fecha']);
            exit;
        }

        try {
            $asignaciones = AsignacionServicio::obtenerAsignacionesSemana($fecha_inicio);

            // Obtener servicios únicos
            $servicios_unicos = [];
            $por_dia = [];

            foreach ($asignaciones as $asig) {
                $servicios_unicos[$asig['servicio']] = true;

                $fecha = $asig['fecha_servicio'];
                if (!isset($por_dia[$fecha])) {
                    $por_dia[$fecha] = [];
                }
                $por_dia[$fecha][] = $asig['servicio'];
            }

            $debug = [
                'total_asignaciones' => count($asignaciones),
                'servicios_unicos' => array_keys($servicios_unicos),
                'por_dia' => $por_dia,
                'primera_asignacion' => $asignaciones[0] ?? null
            ];

            echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}
