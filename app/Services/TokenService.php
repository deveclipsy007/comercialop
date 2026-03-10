<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TokenQuota;

class TokenService
{
    /**
     * Consome tokens para uma operação. Lança exceção se saldo insuficiente.
     *
     * @throws \RuntimeException se saldo insuficiente
     */
    public function consume(string $operation, string $tenantId): int
    {
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

        // Log da operação
        TokenQuota::logEntry($tenantId, $operation, $cost);

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
