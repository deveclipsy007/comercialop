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
        return 'Concorrentes & Posicionamento';
    }

    public function getDescription(): string
    {
        return 'Quem concorre com esse lead, e onde a Operon pode usar isso como argumento de venda.';
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
        $context = (new SmartContextService())->buildDeepIntelligenceContext($lead);

        $leadName = $lead['name'] ?? 'este lead';

        $systemPrompt = <<<PROMPT
Você é um Estrategista Competitivo e Consultor Comercial B2B da Operon.
Sua missão é analisar o cenário competitivo do LEAD como prospect, para que a Operon saiba como abordar esse lead com argumentos fortes.

REGRAS:
- Analise "{$leadName}" como PROSPECT da Operon. NÃO analise a Operon como se ela fosse o lead.
- Use rating, reviews e presença digital como sinais competitivos reais.
- Se o lead tem nota abaixo de 4.5 ou poucas avaliações, isso é argumento de venda para a Operon.
- Se concorrentes do lead têm melhor presença digital, isso é gap que a Operon pode resolver.
- Responda APENAS com JSON válido: {"competitors":["Item 1","Item 2","Item 3","Item 4"],"strategy":"Texto de 1-2 parágrafos com estratégia de abordagem, até 500 caracteres"}
PROMPT;

        $userPrompt = <<<PROMPT
{$context}

Com base nos dados acima, analise o cenário competitivo do lead "{$leadName}":

1. CONCORRENTES DIRETOS: Liste até 4 concorrentes ou tipologias de concorrentes desse lead (ex: "outras padarias artesanais da região", "salões de beleza com forte presença no Instagram")
2. VANTAGEM COMPETITIVA: O que esse lead tem de vantagem ou desvantagem vs. concorrentes? (use nota, reviews, presença digital como base)
3. ARGUMENTO OPERON: Como a Operon pode usar essa análise competitiva como argumento de venda? (ex: "Seus concorrentes investem em tráfego pago e você não", "Sua nota é 4.2 mas concorrentes têm 4.7")

Gere insights que a Operon possa usar DIRETAMENTE na abordagem comercial com esse lead.
PROMPT;

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

        return [
            'items'    => $result['competitors'] ?? [],
            'strategy' => $result['strategy'] ?? '',
        ];
    }
}
