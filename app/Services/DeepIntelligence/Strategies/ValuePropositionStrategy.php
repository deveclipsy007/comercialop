<?php

namespace App\Services\DeepIntelligence\Strategies;

use App\Services\DeepIntelligence\IntelligenceStrategyInterface;
use App\Services\AI\GeminiProvider;
use App\Services\SmartContextService;
use App\Helpers\AIResponseParser;

class ValuePropositionStrategy implements IntelligenceStrategyInterface
{
    public function getKey(): string
    {
        return 'value_proposition';
    }

    public function getName(): string
    {
        return 'O que vende / Proposta de Valor';
    }

    public function getDescription(): string
    {
        return 'Descreve de forma clara os principais serviços, produtos e a proposta de valor do lead.';
    }

    public function getIcon(): string
    {
        return 'storefront';
    }

    public function getColor(): string
    {
        return 'mint';
    }

    public function getEstimatedTokens(): int
    {
        return 3;
    }

    public function execute(array $lead, string $tenantId): array
    {
        $context = (new SmartContextService())->buildLeadContext($lead);
        $gemini = new GeminiProvider();

        $systemPrompt = "Você é um Consultor de Vendas B2B experiente.\nSeja extremamente pragmático e direto.\nResponda APENAS com um JSON simples: {\"summary\":\"Texto explicativo até 500 caracteres\"}";
        $userPrompt = "Baseado nos dados desta empresa:\n" . $context . "\n\nDescreva em 1 parágrafo claro o que essa empresa vende e qual sua proposta de valor principal. Não use jargões difíceis, seja fácil de ler.";

        $result = $gemini->generateJson($systemPrompt, $userPrompt);

        if (AIResponseParser::hasError($result)) {
            throw new \Exception("Erro na IA: " . ($result['error'] ?? 'Desconhecido'));
        }

        return ['content' => $result['summary'] ?? ''];
    }
}
