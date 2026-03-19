<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TokenQuota;
use App\Services\AI\CostCalculator;

class TokenService
{
    /**
     * Consome tokens para uma operação. Lança exceção se saldo insuficiente.
     *
     * @param string      $operation         Nome da operação (config/operon.php)
     * @param string      $tenantId          Tenant que consome
     * @param string|null $userId            Usuário que disparou (para tracking)
     * @param string|null $provider          Provedor usado (gemini, openai, grok)
     * @param string|null $model             Modelo usado (gemini-2.0-flash, gpt-4o, etc)
     * @param int         $realInputTokens   Tokens reais de input (da API)
     * @param int         $realOutputTokens  Tokens reais de output (da API)
     *
     * @throws \RuntimeException se saldo insuficiente ou rate limit
     */
    public function consume(
        string  $operation,
        string  $tenantId,
        ?string $userId = null,
        ?string $provider = null,
        ?string $model = null,
        int     $realInputTokens = 0,
        int     $realOutputTokens = 0
    ): int {
        $weights = config('operon.token_weights');
        $weight  = $weights[$operation] ?? $weights['default'];
        $cost    = $weight['total'];

        // Rate limiting: 5s entre chamadas da mesma operação/tenant
        $cacheKey = "rate_{$tenantId}_{$operation}";
        $ratePath = STORAGE_PATH . '/cache/' . md5($cacheKey) . '.txt';
        if (file_exists($ratePath)) {
            $lastCall = (int) file_get_contents($ratePath);
            if (time() - $lastCall < config('operon.rate_limit_seconds', 5)) {
                throw new \RuntimeException('Aguarde antes de fazer nova análise. Intervalo mínimo: 5 segundos.');
            }
        }

        // Verificar e debitar quota
        $success = TokenQuota::consume($tenantId, $cost);
        if (!$success) {
            throw new \RuntimeException("Cota diária de tokens esgotada. Recarrega à meia-noite (horário de Brasília).");
        }

        // Registrar rate limit
        file_put_contents($ratePath, time());

        // Calcular custo estimado em USD
        $estimatedCost = 0.0;
        if ($model && ($realInputTokens > 0 || $realOutputTokens > 0)) {
            $estimatedCost = CostCalculator::estimate($model, $realInputTokens, $realOutputTokens);
        }

        // Log da operação com dados expandidos
        TokenQuota::logEntry($tenantId, $operation, $cost, [
            'user_id'            => $userId,
            'provider'           => $provider,
            'model'              => $model,
            'real_tokens_input'  => $realInputTokens,
            'real_tokens_output' => $realOutputTokens,
            'estimated_cost_usd' => $estimatedCost,
        ]);

        return $cost;
    }

    /**
     * Verifica se há tokens suficientes sem consumir.
     */
    public function hasSufficient(string $operation, string $tenantId): bool
    {
        $weights = config('operon.token_weights');
        $cost    = ($weights[$operation] ?? $weights['default'])['total'];
        $balance = TokenQuota::getBalance($tenantId);
        return $balance['remaining'] >= $cost;
    }

    /**
     * Retorna o saldo atual de tokens.
     */
    public function getBalance(string $tenantId): array
    {
        return TokenQuota::getBalance($tenantId);
    }

    /**
     * Retorna custo de uma operação.
     */
    public function getCost(string $operation): int
    {
        $weights = config('operon.token_weights');
        return ($weights[$operation] ?? $weights['default'])['total'];
    }
}
