<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Database;

class App
{
    private static array $config = [];
    private Router $router;

    public function boot(): void
    {
        // Suprimir deprecation notices que vazam HTML em respostas JSON (PHP 8.5+)
        error_reporting(E_ALL & ~E_DEPRECATED);

        // Carregar .env
        $this->loadEnv();

        // Configurações
        self::$config['app']      = require CONFIG_PATH . '/app.php';
        self::$config['services'] = require CONFIG_PATH . '/services.php';
        self::$config['operon']   = require CONFIG_PATH . '/operon.php';

        // Iniciar sessão
        Session::start();

        // Configurar timezone — usa o fuso do tenant se disponível
        $tz = env('TOKEN_TIMEZONE', 'America/Sao_Paulo');
        try {
            $tenantId = Session::get('tenant_id');
            if ($tenantId) {
                $tenant = Database::selectFirst('SELECT settings FROM tenants WHERE id = ?', [$tenantId]);
                $tenantSettings = json_decode($tenant['settings'] ?? '{}', true);
                if (!empty($tenantSettings['timezone'])) {
                    $tz = $tenantSettings['timezone'];
                }
            }
        } catch (\Exception $e) {
            // DB not ready yet — use default
        }
        date_default_timezone_set($tz);

        // Iniciar router
        $this->router = new Router();

        // Carregar rotas
        $router = $this->router;
        require_once ROOT_PATH . '/routes/web.php';
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        // CORS preflight para Extension API (OPTIONS não tem rota registrada)
        if ($method === 'OPTIONS' && str_starts_with($uri, '/api/ext/')) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if (str_starts_with($origin, 'chrome-extension://') || $origin === '') {
                header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
                header('Access-Control-Max-Age: 86400');
            }
            http_response_code(204);
            return;
        }

        $this->router->dispatch($method, $uri);
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file  = array_shift($parts);
        $value = self::$config[$file] ?? null;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value ?? $default;
    }

    private function loadEnv(): void
    {
        $envFile = ROOT_PATH . '/.env';
        if (!file_exists($envFile)) {
            $envFile = ROOT_PATH . '/.env.example';
        }
        if (!file_exists($envFile)) return;

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            // Remover aspas
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }
            if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1);
            }
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}
