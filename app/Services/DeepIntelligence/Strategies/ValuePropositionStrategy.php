<?php

namespace App\Services\DeepIntelligence\Strategies;

use App\Services\DeepIntelligence\IntelligenceStrategyInterface;
use App\Core\Session;
use App\Services\AI\AIProviderFactory;
use App\Services\SmartContextService;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

class ValuePropositionStrategy implements IntelligenceStrategyInterface
{
    public function getKey(): string
    {
        return 'value_proposition';
    }

    public function getName(): string
    {
        return 'Perfil & Oportunidade';
    }

    public function getDescription(): string
    {
        return 'Análise do lead como prospect: quem é, o que faz, qual o encaixe com a Operon.';
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
        $context = (new SmartContextService())->buildDeepIntelligenceContext($lead);

        $leadName = $lead['name'] ?? 'este lead';

        $systemPrompt = <<<PROMPT
Você é um Consultor de Inteligência Comercial B2B da Operon.
Sua especialidade é analisar leads para identificar oportunidades reais de venda.

REGRAS:
- O FOCO PRINCIPAL é o lead "{$leadName}" como prospect da Operon.
- Use os dados da empresa/negócio do lead apenas como apoio para entender o contexto.
- Correlacione SEMPRE: quem é o lead → o que ele faz → o que a Operon pode vender para ele.
- Seja pragmático, direto e comercialmente útil.
- Responda APENAS com JSON válido: {"summary":"Texto de 2-3 parágrafos, até 800 caracteres"}
PROMPT;

        $userPrompt = <<<PROMPT
{$context}

Com base nos dados acima, responda sobre o lead "{$leadName}":

1. QUEM É esse lead? (perfil público, tipo de negócio, público que ele atende, porte estimado)
2. O QUE TEM DE INTERESSANTE nesse lead para a Operon? (sinais de oportunidade, gaps digitais, potencial de crescimento)
3. COMO A OPERON SE ENCAIXA? (quais serviços fariam sentido, por que esse lead precisa da Operon)

Gere uma análise que responda à pergunta: "O que existe de oportunidade nesse lead específico e como a Operon pode se encaixar nele?"
PROMPT;

        $provider = AIProviderFactory::make('lead_offerings_analysis', $tenantId);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt);
        $result = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $tokens = new TokenService();
        $tokens->consume(
            'lead_offerings_analysis', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

        if (AIResponseParser::hasError($result)) {
            throw new \Exception("Erro na IA: " . ($result['error'] ?? 'Desconhecido'));
        }

        return ['content' => $result['summary'] ?? ''];
    }
}
