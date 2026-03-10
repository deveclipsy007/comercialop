<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Parser robusto para respostas de IA que podem conter markdown ou JSON malformado.
 * Regra de Ouro: NUNCA usar json_decode direto em resposta de IA — sempre usar este helper.
 */
class AIResponseParser
{
    /**
     * Parseia a resposta de IA de forma robusta.
     * Suporta: JSON limpo, blocos ```json...```, primeiro {...} ou [...] no texto.
     */
    public static function parse(string $text): array
    {
        if (empty($text)) return [];

        $cleaned = trim($text);

        // 1. Remover blocos markdown ``` ... ```
        if (str_contains($cleaned, '```')) {
            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $cleaned, $matches)) {
                $cleaned = trim($matches[1]);
            }
        }

        // 2. Tentar JSON direto
        $result = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
            return $result;
        }

        // 3. Tentar extrair array [...] balanceado
        $startArr = strpos($text, '[');
        $endArr   = strrpos($text, ']');
        if ($startArr !== false && $endArr !== false && $endArr > $startArr) {
            $candidate = substr($text, $startArr, $endArr - $startArr + 1);
            $result    = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                return $result;
            }
        }

        // 4. Tentar extrair objeto {...} balanceado
        $startObj = strpos($text, '{');
        $endObj   = strrpos($text, '}');
        if ($startObj !== false && $endObj !== false && $endObj > $startObj) {
            $candidate = substr($text, $startObj, $endObj - $startObj + 1);
            $result    = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                return $result;
            }
        }

        // 5. Fallback: retornar com flag de erro para debugging
        error_log('[Operon AI] Could not parse response: ' . substr($text, 0, 300));
        return ['_parse_error' => true, 'raw' => $text];
    }

    /**
     * Verifica se o resultado tem erro de parse.
     */
    public static function hasError(array $result): bool
    {
        return isset($result['_parse_error']) && $result['_parse_error'] === true;
    }

    /**
     * Extrai campo aninhado com fallback.
     */
    public static function get(array $data, string $dotKey, mixed $default = null): mixed
    {
        $keys  = explode('.', $dotKey);
        $value = $data;
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value ?? $default;
    }
}
