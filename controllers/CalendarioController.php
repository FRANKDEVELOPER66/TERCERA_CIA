<?php

namespace Controllers;

use Exception;
use Model\CalendarioDescansos;
use Model\GruposDescanso;
use MVC\Router;

class CalendarioController
{
    public static function index(Router $router)
    {
        $router->render('calendario/index', [
            'titulo' => 'Gestión de Calendario de Descansos'
        ]);
    }

    /**
     * Genera calendario automático para X meses adelante
     */
    public static function generarCalendarioAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $fecha_inicial = $_POST['fecha_inicial'] ?? '';
        $meses = intval($_POST['meses'] ?? 6);

        if (empty($fecha_inicial)) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Debe proporcionar una fecha inicial',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            // Limpiar calendario futuro
            $sql = "DELETE FROM calendario_descansos WHERE fecha_inicio >= :fecha";
            CalendarioDescansos::ejecutarQuery($sql, [':fecha' => $fecha_inicial]);

            // Generar calendario
            $resultado = self::generarCiclosAutomaticos($fecha_inicial, $meses);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => "Calendario generado: {$resultado['total']} ciclos para {$meses} meses",
                'datos' => $resultado
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al generar calendario',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Genera ciclos de descanso automáticamente
     */
    private static function generarCiclosAutomaticos($fecha_inicial, $meses)
    {
        $dias_totales = $meses * 30;
        $ciclos = ceil($dias_totales / 30);
        $total_registros = 0;

        // Patrones de inicio para cada grupo (A, B, C)
        // Grupo A inicia descanso en día 21
        // Grupo B inicia descanso en día 31 (desfase de 10)
        // Grupo C inicia descanso en día 41 (desfase de 20)
        $patrones = [
            'A' => 20,  // Día en que inicia el primer descanso
            'B' => 30,
            'C' => 40
        ];

        $grupos = GruposDescanso::all();

        foreach ($grupos as $grupo) {
            // Determinar patrón según el nombre del grupo
            $letra_grupo = substr($grupo->nombre, -1); // Extrae A, B o C
            $inicio_base = $patrones[$letra_grupo] ?? 20;

            // Generar ciclos para este grupo
            for ($ciclo = 0; $ciclo < $ciclos; $ciclo++) {
                $dias_inicio = $inicio_base + ($ciclo * 30);

                if ($dias_inicio > $dias_totales) break;

                $fecha_inicio = date('Y-m-d', strtotime($fecha_inicial . " +{$dias_inicio} days"));
                $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . " +9 days"));

                $calendario = new CalendarioDescansos([
                    'id_grupo_descanso' => $grupo->id_grupo,
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $fecha_fin,
                    'estado' => 'PROGRAMADO'
                ]);

                $calendario->crear();
                $total_registros++;
            }
        }

        return [
            'total' => $total_registros,
            'grupos' => count($grupos),
            'ciclos_por_grupo' => $ciclos
        ];
    }

    /**
     * Obtiene el calendario actual
     */
    public static function obtenerCalendarioAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d');
        $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d', strtotime('+90 days'));

        try {
            $sql = "SELECT 
                        cd.*,
                        gd.nombre as grupo_nombre,
                        gd.tipo as grupo_tipo,
                        gd.color as grupo_color
                    FROM calendario_descansos cd
                    JOIN grupos_descanso gd ON cd.id_grupo_descanso = gd.id_grupo
                    WHERE cd.fecha_inicio BETWEEN :desde AND :hasta
                    ORDER BY cd.fecha_inicio, gd.tipo, gd.nombre";

            $calendario = CalendarioDescansos::fetchArray($sql, [
                ':desde' => $fecha_desde,
                ':hasta' => $fecha_hasta
            ]);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Calendario obtenido',
                'datos' => $calendario
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener calendario',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Obtiene grupos que están de descanso en una fecha específica
     */
    public static function obtenerDescansosHoyAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $fecha = $_GET['fecha'] ?? date('Y-m-d');

        try {
            $sql = "SELECT 
                        gd.nombre as grupo,
                        gd.tipo,
                        gd.color,
                        cd.fecha_inicio,
                        cd.fecha_fin,
                        DATEDIFF(cd.fecha_fin, :fecha2) as dias_restantes
                    FROM calendario_descansos cd
                    JOIN grupos_descanso gd ON cd.id_grupo_descanso = gd.id_grupo
                    WHERE :fecha BETWEEN cd.fecha_inicio AND cd.fecha_fin
                    ORDER BY gd.tipo, gd.nombre";

            $descansos = CalendarioDescansos::fetchArray($sql, [
                ':fecha' => $fecha,
                ':fecha2' => $fecha
            ]);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Descansos obtenidos',
                'datos' => $descansos
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener descansos',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Limpia calendario antiguo (anterior a una fecha)
     */
    public static function limpiarCalendarioAPI()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $fecha_limite = $_POST['fecha_limite'] ?? date('Y-m-d');

        try {
            $sql = "DELETE FROM calendario_descansos WHERE fecha_fin < :fecha";
            CalendarioDescansos::ejecutarQuery($sql, [':fecha' => $fecha_limite]);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Calendario antiguo eliminado',
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al limpiar calendario',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
