<?php
require_once __DIR__ . '/../includes/app.php';

use MVC\Router;
use Controllers\AppController;
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

// Comprueba y valida las rutas
$router->comprobarRutas();
