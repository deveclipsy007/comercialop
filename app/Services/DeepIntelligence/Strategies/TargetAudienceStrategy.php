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
        return 'Público-Alvo & Encaixe';
    }

    public function getDescription(): string
    {
        return 'Quem esse lead atende, qual público consome dele, e como a Operon pode explorar isso.';
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
        $context = (new SmartContextService())->buildDeepIntelligenceContext($lead);

        $leadName = $lead['name'] ?? 'este lead';

        $systemPrompt = <<<PROMPT
Você é um Analista de Mercado especializado em inteligência comercial B2B na Operon.
Seu papel é analisar o LEAD como prospect, entendendo quem é o público dele para identificar oportunidades de venda.

REGRAS:
- Analise "{$leadName}" como LEAD/PROSPECT, não como empresa genérica.
- O público do lead importa porque ajuda a Operon a entender como posicionar sua oferta.
- Correlacione: público do lead → necessidades de marketing → serviços Operon que se encaixam.
- Use avaliações, reviews e presença digital como sinais reais de perfil de público.
- Responda APENAS com JSON válido: {"summary":"Texto de 2-3 parágrafos, até 800 caracteres"}
PROMPT;

        $userPrompt = <<<PROMPT
{$context}

Com base nos dados acima, analise o lead "{$leadName}" e responda:

1. PÚBLICO DO LEAD: Quem são os clientes/consumidores desse lead? Qual perfil compra dele? (use reviews, avaliações e segmento como sinais)
2. PERFIL DE MERCADO: É um negócio local, regional ou digital? Qual o tamanho estimado do mercado que ele atende?
3. OPORTUNIDADE PARA A OPERON: Dado o público que esse lead atende, quais serviços da Operon seriam mais estratégicos para ele? Como a Operon poderia ajudá-lo a captar mais desse público?

Foco: Como a Operon pode usar o entendimento do público desse lead para construir uma proposta irrecusável.
PROMPT;

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
