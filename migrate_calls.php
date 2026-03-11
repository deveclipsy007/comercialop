<?php

define('ROOT_PATH', __DIR__);
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

// Mock env for CLI
putenv('APP_ENV=development');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=database/operon.db');

use App\Core\Database;

$sqlPath = __DIR__ . '/database/migrations/007_calls_transcriptions.sql';
$sql = file_get_contents($sqlPath);

$clean = preg_replace('/--[^\n]*/', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
    if (!empty($stmt)) {
        Database::execute($stmt, []);
    }
}

echo "Migration 007_calls_transcriptions applied successfully.\n";
