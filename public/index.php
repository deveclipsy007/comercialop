<?php

declare(strict_types=1);

// Desabilitar opcache para dev (evitar servir código cacheado após edições)
if (function_exists('opcache_reset')) {
    ini_set('opcache.revalidate_freq', '0');
    ini_set('opcache.validate_timestamps', '1');
}

// Servir arquivos estáticos no PHP built-in server
if (php_sapi_name() === 'cli-server') {
    $uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $file = __DIR__ . $uri;
    if ($uri !== '/' && is_file($file)) {
        return false; // PHP built-in server serve o arquivo diretamente
    }
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('VIEWS_PATH', ROOT_PATH . '/resources/views');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Simple PSR-4 autoloader (antes do composer install)
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

// Carregar helpers
require_once APP_PATH . '/Helpers/helpers.php';

// Iniciar aplicação
require_once APP_PATH . '/Core/App.php';

$app = new App\Core\App();
$app->boot();
$app->run();
