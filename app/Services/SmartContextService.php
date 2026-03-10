<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Monta o contexto dinâmico de agência injetado em TODOS os prompts de IA.
 * É o "núcleo estratégico" que personaliza a IA para cada agência/tenant.
 */
class SmartContextService
{
    // Contexto padrão da agência (lido do banco em produção)
    private array $agencyDefaults = [
        'name'            => 'Operon Agency',
        'offer_title'     => 'Marketing Digital & Presença Online',
        'offer_base_price' => 'R$ 1.500 - R$ 8.000/mês',
        'offer_services'  => ['SEO Local', 'Gestão de Tráfego', 'Criação de Sites', 'CRM & Automação', 'Redes Sociais'],
        'unique_proposal' => 'Transformamos negócios locais em líderes digitais da sua região em 90 dias',
        'differentials'   => [
            'Resultados mensuráveis em 30 dias ou devolvemos o primeiro mês',
            'Especialistas no mercado local com cases comprovados',
            'Suporte dedicado 7 dias por semana',
        ],
        'icp'             => ['PMEs locais com 2-50 funcionários', 'Ticket médio acima de R$ 3.000/mês', 'Segmentos: saúde, educação, varejo, serviços'],
    ];

    /**
     * Constrói o bloco de contexto para análises Operon 4D.
     */
    public function buildOperonContext(array $lead, array $agencySettings = []): string
    {
        $agency = array_merge($this->agencyDefaults, $agencySettings);

        $services = implode(', ', $agency['offer_services']);
        $diffs    = implode("\n• ", $agency['differentials']);
        $icps     = implode("\n• ", $agency['icp']);

        $painPoints   = is_array($lead['analysis']['diagnosis'] ?? null) ? implode('; ', $lead['analysis']['diagnosis']) : 'N/D';
        $opportunities = is_array($lead['analysis']['opportunities'] ?? null) ? implode('; ', $lead['analysis']['opportunities']) : 'N/D';
        $humanCtx     = $lead['human_context']['context'] ?? 'Sem contexto adicional';

        return <<<CONTEXT
=== CONTEXTO DA AGÊNCIA (use para personalizar scripts e diagnósticos) ===
Agência: {$agency['name']}
Oferta: {$agency['offer_title']}
Preço base: {$agency['offer_base_price']}
Serviços: {$services}
Proposta única: {$agency['unique_proposal']}

Diferenciais competitivos:
• {$diffs}

Perfil de Cliente Ideal (ICP):
• {$icps}

=== DADOS DO LEAD ===
Nome: {$lead['name']}
Segmento: {$lead['segment']}
Porte: {$lead['size']}
Site: {$lead['website']}
Localização: {$lead['address']}
Dores identificadas: {$painPoints}
Oportunidades: {$opportunities}
Contexto do vendedor: {$humanCtx}
CONTEXT;
    }

    /**
     * Constrói contexto para análise geral de lead.
     */
    public function buildLeadContext(array $lead, array $agencySettings = []): string
    {
        $agency   = array_merge($this->agencyDefaults, $agencySettings);
        $services = implode("\n• ", $agency['offer_services']);
        $tags     = is_array($lead['tags']) ? implode(', ', $lead['tags']) : '';
        $score    = $lead['fit_score'] ?? 0;
        $maturity = $lead['analysis']['digitalMaturity'] ?? 'Desconhecida';
        $humanCtx = $lead['human_context']['context'] ?? 'Sem contexto';

        return <<<CONTEXT
=== IDENTIDADE DA AGÊNCIA ===
{$agency['name']}
Proposta: {$agency['unique_proposal']}
Diferenciais: {$agency['differentials'][0]}

=== PERFIL DE CLIENTE IDEAL (ICP) ===
• {$agency['icp'][0]}

=== TABELA DE SERVIÇOS ===
• {$services}

=== PERFIL DO LEAD ===
Nome: {$lead['name']}
Segmento: {$lead['segment']}
Porte: {$lead['size']}
Maturidade Digital: {$maturity}
Score de Fit: {$score}/100
Site: {$lead['website']}
Localização: {$lead['address']}
Tags: {$tags}
Contexto manual do vendedor: {$humanCtx}
CONTEXT;
    }
}
