<?php
require_once __DIR__ . '/includes/app.php';

use Controllers\AsignacionController;

// Simular POST
$_POST['fecha_inicio'] = '2026-01-27'; // Próximo lunes

// Habilitar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== TEST GENERACIÓN DE SERVICIOS ===\n\n";

try {
    AsignacionController::generarSemanaAPI();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}