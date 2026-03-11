<?php

/**
 * ProcessCallJob
 * Script CLI executado em background via CallController.
 */

if (php_sapi_name() !== 'cli') {
    die("Acesso negado. Apenas CLI.");
}

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/Helpers/helpers.php';

// Simple PSR-4 autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

// Inicializar Ambiente
require_once APP_PATH . '/Core/Database.php';

$callId = isset($argv[1]) ? (int)$argv[1] : 0;
$tenantId = isset($argv[2]) ? $argv[2] : '';

if (!$callId || !$tenantId) {
    error_log("[ProcessCallJob] Argumentos inválidos: runId={$callId}, tenantId={$tenantId}");
    exit(1);
}

try {
    $service = new \App\Services\Transcription\CallAnalyticsService();
    $service->processCall($callId, $tenantId);
} catch (\Exception $e) {
    error_log("[ProcessCallJob] Erro Crítico: " . $e->getMessage());
    \App\Models\Call::updateStatus($callId, \App\Models\Call::STATUS_FAILED, [
        'error_message' => 'Falha Crítica no Worker: ' . $e->getMessage()
    ]);
    exit(1);
}

exit(0);
