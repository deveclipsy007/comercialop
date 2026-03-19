<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiApiKey;
use App\Models\AiProviderConfig;
use App\Core\Database;

/**
 * Factory para criar providers de IA com base na configuração do banco.
 * Resolve: operação → provider config → chave API → instância do provider.
 * Suporta fallback chain por priority.
 */
class AIProviderFactory
{
    /**
     * Cria o provider mais adequado para a operação.
     * Cadeia de resolução:
     *   1. Config DB por operação+tenant
     *   2. Config DB 'default'+tenant
     *   3. Distribuição ponderada (tenants.settings.ai_distribution)
     *   4. Config .env (padrão: Gemini)
     */
    public static function make(string $operation, string $tenantId): GeminiProvider|OpenAIProvider
    {
        // 1. Tentar configs do DB (com fallback chain)
        $configs = AiProviderConfig::getForOperation($operation, $tenantId);

        foreach ($configs as $cfg) {
            $provider = self::tryBuildProvider($cfg['provider'], $cfg['model'], $tenantId);
            if ($provider !== null) {
                return $provider;
            }
        }

        // 2. Tentar distribuição ponderada
        $provider = self::tryDistributionRouting($tenantId);
        if ($provider !== null) {
            return $provider;
        }

        // 3. Fallback: Gemini com chave do DB ou .env
        $geminiKey = AiApiKey::getDecryptedKey('gemini', $tenantId);
        $model = config('services.gemini.model', 'gemini-2.0-flash');
        return new GeminiProvider($geminiKey ?: null, $model);
    }

    /**
     * Cria provider com fallback automático.
     * Tenta todos os providers configurados em ordem de priority.
     */
    public static function makeWithFallback(string $operation, string $tenantId): GeminiProvider|OpenAIProvider
    {
        // Mesmo que make() — já tem fallback chain embutido
        return self::make($operation, $tenantId);
    }

    /**
     * Tenta construir um provider específico. Retorna null se sem chave.
     */
    private static function tryBuildProvider(string $providerName, string $model, string $tenantId): GeminiProvider|OpenAIProvider|null
    {
        $key = AiApiKey::getDecryptedKey($providerName, $tenantId);

        if (empty($key)) {
            return null;
        }

        if ($providerName === 'gemini') {
            return new GeminiProvider($key, $model);
        }

        if ($providerName === 'openai') {
            return new OpenAIProvider('openai', $key, $model);
        }

        if ($providerName === 'grok') {
            return new OpenAIProvider('grok', $key, $model);
        }

        return null;
    }

    /**
     * Roteamento baseado na distribuição ponderada (Gemini/OpenAI %).
     */
    private static function tryDistributionRouting(string $tenantId): GeminiProvider|OpenAIProvider|null
    {
        try {
            $tenant = Database::selectFirst('SELECT settings FROM tenants WHERE id = ?', [$tenantId]);
            if (!$tenant || empty($tenant['settings'])) {
                return null;
            }

            $settings = json_decode($tenant['settings'], true);
            $dist = $settings['ai_distribution'] ?? null;

            if (!$dist || !isset($dist['gemini'], $dist['openai'])) {
                return null;
            }

            // Weighted random selection
            $rand = random_int(1, 100);
            $selectedProvider = $rand <= (int)$dist['gemini'] ? 'gemini' : 'openai';

            $key = AiApiKey::getDecryptedKey($selectedProvider, $tenantId);
            if (empty($key)) {
                // Se o selecionado não tem chave, tentar o outro
                $fallback = $selectedProvider === 'gemini' ? 'openai' : 'gemini';
                $key = AiApiKey::getDecryptedKey($fallback, $tenantId);
                if (empty($key)) {
                    return null;
                }
                $selectedProvider = $fallback;
            }

            if ($selectedProvider === 'gemini') {
                return new GeminiProvider($key, config('services.gemini.model', 'gemini-2.0-flash'));
            }

            return new OpenAIProvider('openai', $key, config('services.openai.model', 'gpt-4o'));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
