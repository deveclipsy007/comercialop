<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Calcula custo monetário estimado (USD) a partir de tokens reais e pricing por modelo.
 * Pricing configurável em config/operon.php['ai_pricing'].
 */
class CostCalculator
{
    /**
     * Pricing padrão por modelo (USD por 1M tokens).
     * Atualizado em março 2026. Override via config/operon.php.
     */
    private const DEFAULT_PRICING = [
        'gemini-2.0-flash'      => ['input_per_mtok' => 0.075,  'output_per_mtok' => 0.30],
        'gemini-2.5-flash'      => ['input_per_mtok' => 0.15,   'output_per_mtok' => 0.60],
        'gemini-2.5-pro'        => ['input_per_mtok' => 1.25,   'output_per_mtok' => 10.00],
        'gpt-4o'                => ['input_per_mtok' => 2.50,   'output_per_mtok' => 10.00],
        'gpt-4o-mini'           => ['input_per_mtok' => 0.15,   'output_per_mtok' => 0.60],
        'grok-2'                => ['input_per_mtok' => 2.00,   'output_per_mtok' => 10.00],
    ];

    /**
     * Estima custo em USD para uma chamada de IA.
     */
    public static function estimate(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::getPricing($model);

        $inputCost  = ($inputTokens  / 1_000_000) * $pricing['input_per_mtok'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output_per_mtok'];

        return round($inputCost + $outputCost, 8);
    }

    /**
     * Retorna pricing para um modelo. Config override > default.
     */
    public static function getPricing(string $model): array
    {
        // Tentar config override primeiro
        $configPricing = config('operon.ai_pricing', []);
        if (isset($configPricing[$model])) {
            return $configPricing[$model];
        }

        // Fallback para default interno
        if (isset(self::DEFAULT_PRICING[$model])) {
            return self::DEFAULT_PRICING[$model];
        }

        // Modelo desconhecido: usar pricing conservador
        return ['input_per_mtok' => 1.00, 'output_per_mtok' => 3.00];
    }

    /**
     * Retorna todos os modelos com pricing disponível.
     */
    public static function allPricing(): array
    {
        $configPricing = config('operon.ai_pricing', []);
        return array_merge(self::DEFAULT_PRICING, $configPricing);
    }
}
