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
    public static function generarSemanaAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $debug = []; // Array para debug
        $debug['paso_1'] = 'Iniciando proceso';

        $fecha_inicio = $_POST['fecha_inicio'] ?? '';

        if (empty($fecha_inicio)) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Debe proporcionar una fecha de inicio',
                'debug' => $debug
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $debug['paso_2'] = 'Fecha recibida: ' . $fecha_inicio;

        try {
            // Validar que sea lunes
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

            $debug['paso_3'] = 'Fecha validada como lunes';

            // Generar asignaciones
            $usuario_id = $_SESSION['user_id'] ?? null;
            $debug['paso_4'] = 'Usuario ID: ' . ($usuario_id ?? 'NULL');

            $resultado = AsignacionServicio::generarAsignacionesSemanal($fecha_inicio, $usuario_id);

            $debug['paso_5'] = 'Resultado de generación';
            $debug['resultado_completo'] = $resultado;

            if ($resultado['exito']) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => $resultado['mensaje'],
                    'datos' => $resultado['asignaciones'],
                    'total_generadas' => count($resultado['asignaciones']),
                    'debug' => $debug
                ], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(400);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => $resultado['mensaje'],
                    'errores' => $resultado['errores'],
                    'debug' => $debug
                ], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            $debug['paso_error'] = 'Excepción capturada';
            $debug['error_mensaje'] = $e->getMessage();
            $debug['error_linea'] = $e->getLine();
            $debug['error_archivo'] = $e->getFile();

            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al generar asignaciones',
                'detalle' => $e->getMessage(),
                'debug' => $debug
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
            AsignacionServicio::eliminarAsignacionesSemana($fecha_inicio);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Asignaciones eliminadas exitosamente',
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
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
                $fecha_formateada = $fecha_obj->format('d \d\e F \d\e Y');

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

    // === GENERAR PÁGINA DE UN DÍA ===
    private static function generarPaginaDia($dia_nombre, $fecha_formateada, $oficial_dia, $servicios_agrupados)
    {
        $html = '
        <div style="border-bottom: 3px solid #2d5016; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="color: #2d5016; margin: 0; text-align: center; font-size: 24px;">
                3RA. CIA 2DO BTN BHR
            </h2>
            <h1 style="color: #ff7b00; margin: 10px 0; text-align: center; font-size: 20px;">
                ' . strtoupper($dia_nombre) . ', ' . strtoupper($fecha_formateada) . '
            </h1>';

        if (!empty($oficial_dia)) {
            $html .= '
            <div style="background: #2d5016; color: white; padding: 10px; border-radius: 8px; text-align: center; margin-top: 10px;">
                <strong> OFICIAL DEL ENCARGADO:</strong> ' . htmlspecialchars($oficial_dia) . '
            </div>';
        }

        $html .= '</div>';

        // Orden de servicios
        $orden_servicios = ['Semana', 'TACTICO', 'RECONOCIMIENTO', 'SERVICIO NOCTURNO', 'BANDERÍN', 'CUARTELERO'];
        $colores = [
            'Semana' => '#a03500ff',
            'TACTICO' => '#c85a28',
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

            $html .= '
            <div style="background: ' . $color . '; color: white; border-radius: 12px; padding: 15px; margin-bottom: 15px;">
        <h3 style="margin: 0 0 10px 0; font-size: 18px;">🛡️ ' . strtoupper($nombre_mostrar) . '</h3>';

            foreach ($personal as $persona) {
                $horario = $persona['hora_inicio'] . ' - ' . $persona['hora_fin'];
                $html .= '
                <div style="background: rgba(255,255,255,0.2); padding: 8px 12px; border-radius: 8px; margin-bottom: 5px;">
                    <strong>' . htmlspecialchars($persona['grado']) . '</strong> ' .
                    htmlspecialchars($persona['nombre_completo']) .
                    ' <span style="float: right;">' . $horario . '</span>
                </div>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    // === GENERAR CRONOGRAMA SEMANAL (ÚLTIMA PÁGINA) ===
    private static function generarCronogramaSemanal($asignaciones, $fecha_inicio)
    {
        $fecha = new \DateTime($fecha_inicio);

        $html = '
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="color: #2d5016; margin: 0;">CRONOGRAMA SEMANAL</h1>
            <h3 style="color: #ff7b00; margin: 5px 0;">Semana del ' . $fecha->format('d/m/Y') . '</h3>
        </div>';

        // Agrupar por personal
        $personal_servicios = [];

        foreach ($asignaciones as $asig) {
            $id = $asig['id_personal'];
            $dia_num = date('N', strtotime($asig['fecha_servicio'])); // 1=Lunes, 7=Domingo

            if (!isset($personal_servicios[$id])) {
                $personal_servicios[$id] = [
                    'nombre' => $asig['nombre_completo'],
                    'grado' => $asig['grado'],
                    'servicios' => array_fill(1, 7, '-')
                ];
            }

            // Abreviaturas
            $abrev = self::getAbreviatura($asig['servicio']);
            $personal_servicios[$id]['servicios'][$dia_num] = $abrev;
        }

        // Tabla
        $html .= '
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <thead>
                <tr style="background: #2d5016; color: white;">
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; width: 35%;">NOMBRE</th>
                    <th style="border: 1px solid #ddd; padding: 6px; text-align: center;">L</th>
                    <th style="border: 1px solid #ddd; padding: 6px; text-align: center;">M</th>
                    <th style="border: 1px solid #ddd; padding: 6px; text-align: center;">M</th>
                    <th style="border: 1px solid #ddd; padding: 6px; text-align: center;">J</th>
                    <th style="border: 1px solid #ddd; padding: 6px; text-align: center;">V</th>
                    <th style="border: 1px solid #ddd; padding: 6px; text-align: center;">S</th>
                    <th style="border: 1px solid #ddd; padding: 6px; text-align: center;">D</th>
                </tr>
            </thead>
            <tbody>';

        $contador = 0;
        foreach ($personal_servicios as $persona) {
            $bgColor = ($contador % 2 == 0) ? '#f8f9fa' : '#ffffff';

            $html .= '
            <tr style="background: ' . $bgColor . ';">
                <td style="border: 1px solid #ddd; padding: 6px; font-weight: bold;">
                    ' . htmlspecialchars($persona['grado'] . ' ' . $persona['nombre']) . '
                </td>';

            for ($dia = 1; $dia <= 7; $dia++) {
                $servicio = $persona['servicios'][$dia];
                $color = self::getColorAbreviatura($servicio);

                $html .= '
                <td style="border: 1px solid #ddd; padding: 6px; text-align: center; font-weight: bold; color: ' . $color . ';">
                    ' . $servicio . '
                </td>';
            }

            $html .= '</tr>';
            $contador++;
        }

        $html .= '
            </tbody>
        </table>';

        // Leyenda
        $html .= '
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0;">LEYENDA</h4>
            <table style="width: 100%; font-size: 10px;">
                <tr>
                    <td><strong style="color: #ff9966;">SEM</strong> = Semana</td>
                    <td><strong style="color: #c85a28;">TAC</strong> = Táctico</td>
                    <td><strong style="color: #2d5016;">REC</strong> = Reconocimiento</td>
                    <td><strong style="color: #1a472a;">NOC</strong> = Servicio Nocturno</td>
                </tr>
                <tr>
                    <td><strong style="color: #b8540f;">BAN</strong> = Banderín</td>
                    <td><strong style="color: #3d6b1f;">CUA</strong> = Cuartelero</td>
                    <td colspan="2"></td>
                </tr>
            </table>
        </div>';

        return $html;
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
            'TACTICO' => 'TAC',  // ⬅️ SIN ACENTO
            'RECONOCIMIENTO' => 'REC',
            'SERVICIO NOCTURNO' => 'NOC',
            'BANDERÍN' => 'BAN',
            'CUARTELERO' => 'CUA'
        ];
        return $abreviaturas[$servicio] ?? '-';
    }

    private static function getColorAbreviatura($abrev)
    {
        $colores = [
            'SEM' => '#ff9966',
            'TAC' => '#c85a28',
            'REC' => '#2d5016',
            'NOC' => '#1a472a',
            'BAN' => '#b8540f',
            'CUA' => '#3d6b1f'
        ];
        return $colores[$abrev] ?? '#000000';
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
