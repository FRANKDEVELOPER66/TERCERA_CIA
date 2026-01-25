<?php

namespace Controllers;

use Exception;
use Model\Personal;
use Model\Grados;
use Model\GruposDescanso;
use MVC\Router;

class PersonalController
{
    public static function index(Router $router)
    {
        $grados = Grados::obtenerGrados();
        $grupos_descanso = GruposDescanso::obtenerGrupos();

        $router->render('personal/index', [
            'grados' => $grados,
            'grupos_descanso' => $grupos_descanso
        ]);
    }

    public static function guardarAPI()
    {
        // ⭐ AGREGAR HEADER JSON
        header('Content-Type: application/json; charset=UTF-8');

        $_POST['nombres'] = htmlspecialchars($_POST['nombres']);
        $_POST['apellidos'] = htmlspecialchars($_POST['apellidos']);
        $_POST['observaciones'] = htmlspecialchars($_POST['observaciones'] ?? '');

        try {
            $personal = new Personal($_POST);
            $resultado = $personal->crear();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Personal registrado exitosamente',
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al registrar Personal',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function buscarAPI()
    {
        // ⭐ DEBUG - Ver si llega aquí
        error_log("=== ENTRANDO A buscarAPI de Personal ===");
        error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);

        // ⭐ AGREGAR HEADER JSON
        header('Content-Type: application/json; charset=UTF-8');

        try {
            error_log("Llamando a Personal::traerPersonal()");
            $personal = Personal::traerPersonal();
            error_log("Personal obtenido: " . count($personal) . " registros");

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Datos encontrados',
                'detalle' => '',
                'datos' => $personal
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al buscar Personal',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function modificarAPI()
    {
        // ⭐ AGREGAR HEADER JSON
        header('Content-Type: application/json; charset=UTF-8');

        $_POST['nombres'] = htmlspecialchars($_POST['nombres']);
        $_POST['apellidos'] = htmlspecialchars($_POST['apellidos']);
        $_POST['observaciones'] = htmlspecialchars($_POST['observaciones'] ?? '');

        $id = filter_var($_POST['id_personal'], FILTER_SANITIZE_NUMBER_INT);

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'ID de personal no válido',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $personal = Personal::find($id);

            if (!$personal) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Personal no encontrado',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $personal->sincronizar($_POST);
            $resultado = $personal->actualizar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Datos del Personal Modificados Exitosamente',
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al Modificar Datos',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function eliminarAPI()
    {
        // ⭐ AGREGAR HEADER JSON
        header('Content-Type: application/json; charset=UTF-8');

        $id = filter_var($_POST['id_personal'], FILTER_SANITIZE_NUMBER_INT);

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'ID de personal no válido',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $personal = Personal::find($id);

            if (!$personal) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Personal no encontrado',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $personal->eliminar();

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Personal Eliminado Exitosamente',
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al Eliminar Personal',
                'detalle' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
