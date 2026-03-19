<?php

namespace App\Services\DeepIntelligence\Strategies;

use App\Services\DeepIntelligence\IntelligenceStrategyInterface;
use App\Core\Session;
use App\Services\AI\AIProviderFactory;
use App\Services\SmartContextService;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

class CompetitorsStrategy implements IntelligenceStrategyInterface
{
    public function getKey(): string
    {
        return 'competitors';
    }

    public function getName(): string
    {
        return 'Concorrentes Imediatos';
    }

    public function getDescription(): string
    {
        return 'Lista os maiores concorrentes e a tipologia de concorrência que este lead enfrenta.';
    }

    public function getIcon(): string
    {
        return 'swords';
    }

    public function getColor(): string
    {
        return '#F59E0B';
    }

    public function getEstimatedTokens(): int
    {
        return 3;
    }

    public function execute(array $lead, string $tenantId): array
    {
        $context = (new SmartContextService())->buildLeadContext($lead);

        $systemPrompt = "Você é um Analista de Mercado B2B.\nResponda APENAS com um JSON simples: {\"competitors\":[\"Concorrente A ou Tipologia A\", \"Concorrente B ou Tipologia B\"]}";
        $userPrompt = "Empresa:\nNome: {$lead['name']}\nSegmento: {$lead['segment']}\nSite: {$lead['website']}\nEndereço: {$lead['address']}\n\nCom base nesse segmento e negócio local/nacional, liste até 4 concorrentes diretos ou tipologias exatas de concorrentes (como eles se parecem). Seja pragmático.";

        $provider = AIProviderFactory::make('lead_competitors_analysis', $tenantId);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt);
        $result = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $tokens = new TokenService();
        $tokens->consume(
            'lead_competitors_analysis', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

        if (AIResponseParser::hasError($result)) {
            throw new \Exception("Erro na IA: " . ($result['error'] ?? 'Desconhecido'));
        }

        return ['items' => $result['competitors'] ?? []];
    }
}
