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
$router->post('/API/asignaciones/contar-personal', [AsignacionController::class, 'contarPersonalAPI']);
$router->get('/API/asignaciones/verificar-fecha', [AsignacionController::class, 'verificarFechaAPI']);
$router->get('/API/asignaciones/proxima-fecha', [AsignacionController::class, 'proximaFechaAPI']);
$router->get('/API/asignaciones/obtener-todos-ciclos', [AsignacionController::class, 'obtenerTodosCiclosAPI']);
$router->post('/API/asignaciones/registrar-comision', [AsignacionController::class, 'registrarComisionAPI']);
$router->get('/API/asignaciones/servicios-afectados', [AsignacionController::class, 'serviciosAfectadosAPI']);
$router->get('/API/asignaciones/comisiones-activas', [AsignacionController::class, 'comisionesActivasAPI']);
$router->get('/API/asignaciones/personal-con-compensacion', [AsignacionController::class, 'personalConCompensacionAPI']);


$router->post('/API/asignaciones/confirmar-reemplazos', [AsignacionController::class, 'confirmarReemplazosAPI']);
// En la sección de asignaciones
$router->post('/api/asignaciones/recalcular-historial', [AsignacionController::class, 'recalcularHistorialAPI']);



// ========================================
// 🆕 RUTAS DE COMPENSACIONES
// ========================================

// Obtener compensaciones de un personal
$router->get('/asignaciones/compensaciones/personal', [AsignacionController::class, 'compensacionesPersonalAPI']);

// Verificar si puede aplicar compensación
$router->get('/asignaciones/compensaciones/verificar', [AsignacionController::class, 'verificarCompensacionAPI']);

// Aplicar compensación
$router->post('/asignaciones/compensaciones/aplicar', [AsignacionController::class, 'aplicarCompensacionAPI']);

// Listar personal con compensaciones
$router->get('/asignaciones/compensaciones/personal-lista', [AsignacionController::class, 'personalConCompensacionAPI']);

// Historial de compensaciones
$router->get('/asignaciones/compensaciones/historial', [AsignacionController::class, 'historialCompensacionesAPI']);

// Cancelar compensación (revertir)
$router->post('/asignaciones/compensaciones/cancelar', [AsignacionController::class, 'cancelarCompensacionAPI']);


// Agregar la ruta (en la sección donde defines las rutas)
$router->get('/API/personal/activos', [PersonalController::class, 'obtenerActivosAPI']);


// Comprueba y valida las rutas
$router->comprobarRutas();
