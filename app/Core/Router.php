<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $routes = $this->routes[$method] ?? [];

        // Correspondência exata
        if (isset($routes[$uri])) {
            $this->callHandler($routes[$uri], []);
            return;
        }

        // Correspondência com parâmetros (:id, :slug, etc.)
        foreach ($routes as $route => $handler) {
            $pattern = preg_replace('/\/:([^\/]+)/', '/([^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                // Extrair nomes dos parâmetros
                preg_match_all('/:([^\/]+)/', $route, $names);
                $params = array_combine($names[1], $matches);
                $this->callHandler($handler, $params);
                return;
            }
        }

        // 404
        http_response_code(404);
        View::render('errors/404', ['uri' => $uri], 'layout.app');
    }

    private function callHandler(callable|array $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            call_user_func_array([$controller, $method], $params);
            return;
        }
    }
}
