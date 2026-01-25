<?php

namespace Controllers;

use Exception;
use Model\AsignacionServicio;
use Model\Personal;
use Model\TiposServicio;
use MVC\Router;

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
}
