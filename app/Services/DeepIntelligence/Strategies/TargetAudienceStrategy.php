<?php

namespace App\Services\DeepIntelligence\Strategies;

use App\Services\DeepIntelligence\IntelligenceStrategyInterface;
use App\Core\Session;
use App\Services\AI\AIProviderFactory;
use App\Services\SmartContextService;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

class TargetAudienceStrategy implements IntelligenceStrategyInterface
{
    public function getKey(): string
    {
        return 'target_audience';
    }

    public function getName(): string
    {
        return 'Público-Alvo Ideal';
    }

    public function getDescription(): string
    {
        return 'Análise detalhada de quem são os potenciais compradores deste negócio.';
    }

    public function getIcon(): string
    {
        return 'groups';
    }

    public function getColor(): string
    {
        return '#60A5FA';
    }

    public function getEstimatedTokens(): int
    {
        return 3;
    }

    public function execute(array $lead, string $tenantId): array
    {
        $context = (new SmartContextService())->buildLeadContext($lead);

        $systemPrompt = "Você é um Consultor de Vendas B2B experiente.\nSeja extremamente pragmático e direto.\nResponda APENAS com um JSON simples: {\"summary\":\"Texto de 1 a 2 parágrafos\"}";
        $userPrompt = "Baseado nos dados desta empresa:\n" . $context . "\n\nDescreva detalhadamente quem é o público-alvo ou perfil de comprador ideal deste lead. Quem costuma comprar os serviços ou produtos deles?";

        $provider = AIProviderFactory::make('lead_clients_analysis', $tenantId);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt);
        $result = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $tokens = new TokenService();
        $tokens->consume(
            'lead_clients_analysis', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

        if (AIResponseParser::hasError($result)) {
            throw new \Exception("Erro na IA: " . ($result['error'] ?? 'Desconhecido'));
        }

        return ['content' => $result['summary'] ?? ''];
    }
}
