<?php

namespace App\Services\DeepIntelligence\Strategies;

use App\Core\Session;
use App\Helpers\AIResponseParser;
use App\Services\AI\AIProviderFactory;
use App\Services\DeepIntelligence\IntelligenceStrategyInterface;
use App\Services\SmartContextService;
use App\Services\TokenService;

class SocialPresenceStrategy implements IntelligenceStrategyInterface
{
    public function getKey(): string
    {
        return 'social_presence';
    }

    public function getName(): string
    {
        return 'Redes Sociais & Autoridade';
    }

    public function getDescription(): string
    {
        return 'Analisa Instagram e LinkedIn do lead para entender posicionamento, autoridade e como conectar nossa proposta.';
    }

    public function getIcon(): string
    {
        return 'share';
    }

    public function getColor(): string
    {
        return '#06B6D4';
    }

    public function getEstimatedTokens(): int
    {
        return 4;
    }

    public function execute(array $lead, string $tenantId): array
    {
        $social = $lead['social_presence'] ?? [];
        if (is_string($social)) {
            $social = json_decode($social, true) ?? [];
        }

        $instagram = trim((string) ($social['instagram'] ?? ''));
        $linkedin = trim((string) ($social['linkedin'] ?? ''));

        if ($instagram === '' && $linkedin === '') {
            throw new \Exception('Adicione ou descubra ao menos um perfil de Instagram ou LinkedIn antes de gerar esta análise.');
        }

        $context = (new SmartContextService())->buildDeepIntelligenceContext(
            $lead,
            [],
            ['include_social_signals' => false]
        );

        $leadName = $lead['name'] ?? 'este lead';
        $agencyName = trim((string) ((new SmartContextService())->loadCompanyProfile($tenantId)['name'] ?? 'sua empresa'));

        $profilesBlock = [];
        if ($instagram !== '') {
            $profilesBlock[] = "Instagram informado: {$instagram}";
        }
        if ($linkedin !== '') {
            $profilesBlock[] = "LinkedIn informado: {$linkedin}";
        }

        $systemPrompt = <<<PROMPT
Você é um Analista de Social Intelligence B2B da {$agencyName}.

MISSÃO:
- Analisar os perfis públicos de Instagram e LinkedIn do lead como sinais complementares de posicionamento, autoridade, clareza de oferta e maturidade comercial.
- Traduzir essa leitura em oportunidade comercial prática para a {$agencyName}.

REGRAS:
- Use somente o que for verificável nos perfis públicos ou em pesquisa pública disponível.
- NÃO invente seguidores, engajamento, frequência de postagem ou dados privados.
- Se um ponto não puder ser confirmado, diga isso com clareza.
- Correlacione SEMPRE: presença social do lead -> percepção de mercado -> oportunidade para a {$agencyName}.
- Responda APENAS com JSON válido:
{
  "items": ["Insight 1", "Insight 2", "Insight 3", "Insight 4"],
  "strategy": "Texto de 1-2 parágrafos explicando como a {$agencyName} deve usar essa leitura social na abordagem."
}
PROMPT;

        $userPrompt = <<<PROMPT
{$context}

===== PERFIS SOCIAIS INFORMADOS =====
{$this->formatProfilesBlock($profilesBlock)}

Analise os perfis sociais públicos do lead "{$leadName}" e responda:

1. POSICIONAMENTO: O que os perfis comunicam sobre oferta, autoridade e proposta de valor?
2. ESTRUTURA: Existe clareza comercial, bio útil, CTA, prova, coerência ou sinais de improviso?
3. OPORTUNIDADE: Como a {$agencyName} pode usar essa leitura para construir uma abordagem mais forte?
4. ALERTAS: O que está fraco, ausente ou não confirmado nos perfis?

Os itens precisam ser objetivos e acionáveis.
PROMPT;

        $provider = AIProviderFactory::make('lead_social_analysis', $tenantId);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt, [
            'temperature' => 0.2,
            'max_tokens' => 2200,
            'google_search' => true,
        ]);
        $result = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $tokens = new TokenService();
        $tokens->consume(
            'lead_social_analysis',
            $tenantId,
            Session::get('id'),
            $provider->getProviderName(),
            $provider->getModel(),
            $usage['input'],
            $usage['output']
        );

        if (AIResponseParser::hasError($result)) {
            throw new \Exception('Erro na IA: ' . ($result['error'] ?? 'Desconhecido'));
        }

        $items = $result['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            $items = [
                'Perfis analisados, mas a IA não retornou insights estruturados suficientes.',
            ];
        }

        return [
            'items' => array_values(array_filter(array_map(static fn($item) => trim((string) $item), $items))),
            'strategy' => trim((string) ($result['strategy'] ?? '')),
        ];
    }

    private function formatProfilesBlock(array $lines): string
    {
        if (empty($lines)) {
            return 'Nenhum perfil informado.';
        }

        return implode("\n", array_map(static fn($line) => '- ' . $line, $lines));
    }
}
