<?php
require_once __DIR__ . '/../includes/app.php';

use MVC\Router;
use Controllers\AppController;
use Controllers\AsignacionController;
use Controllers\PersonalController;

$router = new Router();
$router->setBaseURL('/' . $_ENV['APP_NAME']);

$router->get('/', [AppController::class, 'index']);

// PERSONAL
$router->get('/personal', [PersonalController::class, 'index']);
$router->get('/API/personal/buscar', [PersonalController::class, 'buscarAPI']);
$router->post('/API/personal/guardar', [PersonalController::class, 'guardarAPI']);
$router->post('/API/personal/modificar', [PersonalController::class, 'modificarAPI']);
$router->post('/API/personal/eliminar', [PersonalController::class, 'eliminarAPI']);

// ============================================
// RUTAS DE ASIGNACIONES
// ============================================
$router->get('/asignaciones', [AsignacionController::class, 'index']);

// APIs de Asignaciones
$router->post('/API/asignaciones/generar', [AsignacionController::class, 'generarSemanaAPI']);
$router->get('/API/asignaciones/obtener', [AsignacionController::class, 'obtenerSemanaAPI']);
$router->post('/API/asignaciones/eliminar', [AsignacionController::class, 'eliminarSemanaAPI']);
$router->get('/API/asignaciones/pdf', [AsignacionController::class, 'exportarPDFSemanaAPI']);
$router->get('/asignaciones/exportar-pdf', [AsignacionController::class, 'exportarPDF']);
$router->get('/asignaciones/debug', [AsignacionController::class, 'debugAsignaciones']);
$router->get('/asignaciones/debug', [AsignacionController::class, 'debugAsignaciones']);
// ✅ Debe estar EXACTAMENTE así:
$router->post('/API/asignaciones/contar-personal', [AsignacionController::class, 'contarPersonalAPI']);



$router->get('/API/asignaciones/verificar-fecha', [AsignacionController::class, 'verificarFechaAPI']);

// Ruta para obtener la próxima fecha disponible
$router->get('/API/asignaciones/proxima-fecha', [AsignacionController::class, 'proximaFechaAPI']);

$router->get('/API/asignaciones/obtener-todos-ciclos', [AsignacionController::class, 'obtenerTodosCiclosAPI']);

// Las


// Comprueba y valida las rutas
$router->comprobarRutas();
