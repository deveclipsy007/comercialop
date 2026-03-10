<?php

declare(strict_types=1);

namespace App\Core;

class App
{
    private static array $config = [];
    private Router $router;

    public function boot(): void
    {
        // Carregar .env
        $this->loadEnv();

        // Configurações
        self::$config['app']      = require CONFIG_PATH . '/app.php';
        self::$config['services'] = require CONFIG_PATH . '/services.php';
        self::$config['operon']   = require CONFIG_PATH . '/operon.php';

        // Iniciar sessão
        Session::start();

        // Configurar timezone
        date_default_timezone_set(env('TOKEN_TIMEZONE', 'America/Sao_Paulo'));

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
