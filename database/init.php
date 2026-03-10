<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

// Helpers (for env())
require_once APP_PATH . '/Helpers/helpers.php';

use App\Core\Database;

try {
    echo "--- Iniciando Inicialização do Banco de Dados Operon ---\n";
    
    $dbPath = ROOT_PATH . '/database/operon.db';
    
    // Schema
    echo "Aplicando schema.sql...\n";
    $schemaSql = file_get_contents(ROOT_PATH . '/database/schema.sql');
    if ($schemaSql === false) throw new Exception("Não foi possível ler schema.sql");
    
    // Executar múltiplas queries no SQLite via PDO
    Database::connection()->exec($schemaSql);
    echo "✅ Schema aplicado com sucesso!\n";
    
    // Seeds
    echo "Aplicando seeds.sql...\n";
    $seedsSql = file_get_contents(ROOT_PATH . '/database/seeds.sql');
    if ($seedsSql === false) throw new Exception("Não foi possível ler seeds.sql");
    
    Database::connection()->exec($seedsSql);
    echo "✅ Seeds aplicados com sucesso!\n";
    
    echo "--- Banco de Dados Inicializado com Sucesso! ---\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
