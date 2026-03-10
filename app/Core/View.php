<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    /**
     * Renderiza um template com um layout opcional.
     *
     * @param string $template  Caminho relativo ao views dir (ex: 'dashboard/nexus')
     * @param array  $data      Variáveis passadas ao template
     * @param string $layout    Layout a envolver (ex: 'layout.app'). '' para sem layout.
     */
    public static function render(string $template, array $data = [], string $layout = 'layout.app'): void
    {
        $templateFile = VIEWS_PATH . '/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($templateFile)) {
            http_response_code(500);
            echo "<h1>View not found: {$template}</h1>";
            return;
        }

        // Extrair variáveis para o escopo do template
        extract($data, EXTR_SKIP);

        // Capturar output do template
        ob_start();
        include $templateFile;
        $content = ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        // Incluir layout (que usa $content)
        $layoutFile = VIEWS_PATH . '/' . str_replace('.', '/', $layout) . '.php';
        if (!file_exists($layoutFile)) {
            echo $content;
            return;
        }

        include $layoutFile;
    }

    /**
     * Renderiza JSON para respostas de API.
     */
    public static function json(array $data, int $status = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Redireciona para outra URL.
     */
    public static function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Escapa HTML para output seguro.
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Inclui um componente parcial (sem layout).
     */
    public static function partial(string $component, array $data = []): string
    {
        $file = VIEWS_PATH . '/components/' . str_replace('.', '/', $component) . '.php';
        if (!file_exists($file)) return '';
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }
}
