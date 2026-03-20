<?php

declare(strict_types=1);

namespace App\Services\Meridian;

use App\Services\AI\AIProviderFactory;
use App\Services\SmartContextService;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

/**
 * Motor de análise estratégica de nichos de mercado.
 * Cruza o perfil completo da empresa com o nicho informado
 * para gerar inteligência comercial contextualizada.
 */
class MeridianAnalyzer
{
    private SmartContextService $contextService;

    public function __construct()
    {
        $this->contextService = new SmartContextService();
    }

    /**
     * Executa análise completa de nicho baseada no contexto da empresa.
     *
     * @return array{success: bool, analysis?: array, usage?: array, error?: string}
     */
    public function analyze(string $niche, string $tenantId, array $options = []): array
    {
        $companyContext = $this->contextService->buildNicheContext($tenantId);

        $subNiche = trim($options['sub_niche'] ?? '');
        $region   = trim($options['region'] ?? '');
        $focus    = trim($options['focus'] ?? '');

        $nicheDescription = $niche;
        if ($subNiche) $nicheDescription .= " (subnicho: {$subNiche})";
        if ($region)   $nicheDescription .= " — região: {$region}";

        $systemPrompt = $this->buildSystemPrompt($companyContext);
        $userPrompt   = $this->buildUserPrompt($nicheDescription, $focus);

        try {
            // Meridian generates very large responses — extend PHP timeout
            set_time_limit(180);

            $provider = AIProviderFactory::make('meridian_niche', $tenantId);
            $result   = $provider->generateJsonWithMeta($systemPrompt, $userPrompt, [
                'max_tokens' => 8192,
                'temperature' => 0.7,
            ]);

            // Token tracking
            $tokens = new TokenService();
            $tokens->consume(
                'meridian',
                $tenantId,
                null,   // userId
                null,   // provider
                null,   // model
                $result['usage']['input'] ?? 0,
                $result['usage']['output'] ?? 0
            );

            $parsed = $result['parsed'] ?? [];

            return [
                'success'  => true,
                'analysis' => $parsed,
                'usage'    => $result['usage'] ?? [],
            ];
        } catch (\Throwable $e) {
            error_log('[MeridianAnalyzer] Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildSystemPrompt(string $companyContext): string
    {
        return <<<PROMPT
Você é um estrategista sênior de inteligência comercial B2B com 20+ anos de experiência em análise de mercado, posicionamento estratégico e expansão comercial.

Sua missão: produzir uma ANÁLISE PROFUNDA E EXAUSTIVA de um nicho de mercado, sempre cruzada com o PERFIL REAL da empresa abaixo. Nada superficial. Nada genérico. O nível de profundidade deve ser equivalente a um relatório de consultoria estratégica de alto nível.

{$companyContext}

REGRAS INEGOCIÁVEIS:
1. PROFUNDIDADE MÁXIMA — Cada bloco deve ter análise densa e detalhada. Mínimo 5-8 itens por array. Textos explicativos devem ter pelo menos 2-3 frases com raciocínio claro.
2. CONTEXTUALIZAÇÃO OBRIGATÓRIA — Cada insight DEVE mencionar elementos concretos do perfil da empresa (serviços, preço, diferenciais, cases, ICP, posicionamento). Se disser "a empresa pode oferecer X", diga QUAL serviço específico.
3. HONESTIDADE ESTRATÉGICA — Se o nicho não encaixa, diga claramente. Se há riscos, detalhe. Não force aderência onde não existe.
4. PRATICIDADE — Cada insight deve ser acionável. O gestor deve ler e saber exatamente o que fazer.
5. ESPECIFICIDADE — Nomes de cargos reais, faixas de preço concretas, canais específicos, exemplos de mensagens, tipos de conteúdo detalhados.
6. Responda SEMPRE em português brasileiro.
7. Retorne EXCLUSIVAMENTE um JSON válido, sem markdown, sem texto fora do JSON.

FORMATO DE RESPOSTA (JSON — TODOS os campos são obrigatórios e devem ser densos):
{
  "niche_overview": {
    "name": "Nome do nicho analisado",
    "description": "Visão geral detalhada do nicho — o que é, como funciona o mercado, quem são os players, qual é a dinâmica atual. Mínimo 4-5 frases.",
    "market_size": "Estimativa qualitativa do tamanho do mercado com justificativa (ex: 'Grande — O Brasil tem mais de 350 mil clínicas odontológicas ativas segundo o CFO, com crescimento de 12% ao ano')",
    "growth_trend": "crescente|estável|decrescente",
    "growth_reasoning": "Explicação detalhada da tendência de crescimento com fatores que sustentam essa leitura. 2-3 frases.",
    "digital_maturity": "baixa|média|alta",
    "digital_maturity_detail": "Análise detalhada do nível de digitalização do nicho — uso de redes sociais, presença online, investimento em marketing digital, maturidade tecnológica. 2-3 frases.",
    "competition_level": "baixa|média|alta",
    "competition_detail": "Quem são os concorrentes típicos que atendem esse nicho, como é a saturação, onde há espaço. 2-3 frases.",
    "seasonality": "Sazonalidade do nicho — meses mais quentes e frios, eventos que impactam a demanda",
    "average_ticket": "Ticket médio que empresas desse nicho costumam investir em serviços similares aos da empresa",
    "regulation_notes": "Regulamentações, restrições ou particularidades legais que impactam a venda para esse nicho"
  },
  "adherence": {
    "score": 0-100,
    "reasoning": "Análise detalhada de por que esse score, cruzando pelo menos 4 elementos do perfil da empresa (serviços, diferenciais, cases, ICP, preço). Mínimo 3-4 frases.",
    "strengths": ["Mínimo 5 pontos fortes da empresa para esse nicho — cada um deve referenciar um elemento concreto do perfil (serviço, case, diferencial, etc.)"],
    "gaps": ["Mínimo 3-5 lacunas ou riscos — honesto e específico, com indicação de como cada gap pode ser endereçado"],
    "fit_factors": ["Fatores objetivos que tornam a empresa uma boa escolha para esse nicho — experiência prévia, capacidade técnica, alinhamento de preço, localização, etc."]
  },
  "potential": {
    "score": 0-100,
    "reasoning": "Análise detalhada do potencial comercial — tamanho da oportunidade, facilidade de entrada, margem esperada, volume de prospects. Mínimo 3-4 frases.",
    "revenue_estimate": "Estimativa qualitativa de potencial de receita se a empresa atacar esse nicho (ex: '3-8 clientes em 6 meses com ticket médio de R$X = potencial de R$Y/mês')",
    "time_to_first_deal": "Estimativa de tempo até fechar o primeiro negócio nesse nicho e por quê"
  },
  "ideal_icp": {
    "profile": "Descrição rica e detalhada do cliente ideal DENTRO desse nicho — quem é, como pensa, o que valoriza, qual seu dia a dia, quais são suas frustrações. Mínimo 4-5 frases.",
    "company_size": "Porte ideal com justificativa (ex: '5-20 funcionários — grandes o suficiente para investir, pequenos o suficiente para decidir rápido')",
    "revenue_range": "Faixa de faturamento ideal do cliente-alvo",
    "decision_maker": "Cargo/perfil do decisor com detalhes — formação típica, idade, mentalidade, como toma decisões",
    "influencers": ["Outros perfis que influenciam a decisão dentro desse tipo de empresa"],
    "budget_range": "Faixa de investimento típica que esse perfil aceita pagar — com justificativa",
    "buying_signals": ["Mínimo 6 sinais concretos de que um prospect desse nicho está pronto para comprar — cada um com exemplo prático"],
    "red_flags": ["Sinais de que um prospect desse nicho NÃO vale a pena — evitar desperdício de tempo"],
    "where_they_are": ["Onde esses clientes ideais estão (LinkedIn, Instagram, eventos, associações, grupos, etc.) — específico o suficiente para prospectar"]
  },
  "pain_points": [
    {
      "pain": "Dor específica e detalhada do nicho",
      "severity": "alta|média|baixa",
      "frequency": "Quão comum é essa dor no nicho — 'muito comum|comum|específica'",
      "company_solution": "Como ESPECIFICAMENTE a empresa resolve isso — cite o serviço, diferencial ou capacidade exata",
      "impact": "Impacto financeiro/operacional que essa dor causa no negócio do prospect"
    }
  ],
  "entry_opportunities": [
    {
      "opportunity": "Oportunidade concreta e específica de entrada",
      "approach": "Como aproveitar — passo a passo prático",
      "difficulty": "fácil|média|difícil",
      "investment": "Investimento necessário (tempo/dinheiro) para explorar essa oportunidade",
      "expected_return": "Retorno esperado se bem executada"
    }
  ],
  "value_proposition": {
    "main_argument": "Argumento central de valor — a frase que resume por que a empresa é a escolha certa para esse nicho. Deve ser específica e poderosa.",
    "elevator_pitch": "Pitch de 30 segundos específico para esse nicho — pronto para uso",
    "supporting_points": ["Mínimo 5 argumentos de apoio — cada um conectado a um elemento real do perfil da empresa"],
    "proof_elements": ["Cases, dados, resultados, certificações ou credenciais que provam a capacidade — seja específico"],
    "emotional_triggers": ["Gatilhos emocionais que funcionam com esse nicho — medo de perder, desejo de crescer, status, segurança, etc."]
  },
  "objections": [
    {
      "objection": "Objeção provável e realista",
      "severity": "alta|média|baixa",
      "frequency": "Quão comum é essa objeção — 'quase sempre|frequente|eventual'",
      "counter": "Contra-argumento detalhado baseado no perfil da empresa — com prova ou case se possível",
      "prevention": "Como prevenir essa objeção antes que ela apareça na conversa"
    }
  ],
  "channels": [
    {
      "channel": "Canal de entrada específico",
      "effectiveness": "alta|média|baixa",
      "cost": "Custo relativo (baixo|médio|alto)",
      "approach_tip": "Dica prática de abordagem por esse canal — com exemplo de mensagem ou ação",
      "best_for": "Em que momento do funil esse canal funciona melhor"
    }
  ],
  "approach_strategy": {
    "recommended_type": "Tipo de abordagem recomendada com justificativa",
    "opening_angle": "Ângulo de abertura detalhado — como iniciar a conversa, qual gancho usar, que dor explorar primeiro",
    "key_message": "Mensagem-chave que deve permear toda a comunicação com esse nicho",
    "first_contact_script": "Modelo de primeira mensagem/abordagem pronta para uso (adaptável para WhatsApp, email ou LinkedIn)",
    "content_ideas": ["Mínimo 5 ideias de conteúdo específicas — tipo de conteúdo + tema + formato + onde publicar"],
    "nurturing_strategy": "Como nutrir leads desse nicho que ainda não estão prontos para comprar — sequência e cadência",
    "closing_tips": ["Dicas específicas para fechar negócios nesse nicho — o que funciona na hora H"]
  },
  "barriers": [
    {
      "barrier": "Barreira de entrada concreta",
      "severity": "alta|média|baixa",
      "type": "técnica|comercial|cultural|regulatória|financeira",
      "mitigation": "Como contornar — plano de ação específico",
      "timeline": "Tempo estimado para superar essa barreira"
    }
  ],
  "sub_niches": [
    {
      "name": "Subnicho específico",
      "potential": "alto|médio|baixo",
      "size": "Estimativa qualitativa do tamanho",
      "why": "Por que é interessante para a empresa — cruzar com perfil",
      "specific_angle": "Ângulo de abordagem específico para esse subnicho"
    }
  ],
  "positioning": {
    "recommended_angle": "Ângulo de posicionamento ideal — como a empresa deve se apresentar para esse nicho",
    "differentiation": "Como se diferenciar da concorrência nesse nicho — o que a empresa tem que outros não têm",
    "authority_path": "Caminho concreto para construir autoridade — ações em ordem cronológica",
    "brand_perception": "Como a empresa quer ser percebida por esse nicho — identidade aspiracional",
    "content_pillars": ["Pilares de conteúdo que sustentam a autoridade — temas recorrentes para se tornar referência"]
  },
  "competitive_landscape": {
    "direct_competitors": ["Tipos de concorrentes que já atendem esse nicho — quem são, como atuam"],
    "indirect_competitors": ["Alternativas que o nicho usa em vez de contratar empresas como a sua (ex: fazer internamente, freelancers, etc.)"],
    "competitive_advantages": ["Vantagens específicas da empresa sobre esses concorrentes — baseado no perfil"],
    "vulnerability_points": ["Onde a empresa é mais vulnerável na competição por esse nicho"]
  },
  "financial_analysis": {
    "estimated_cac": "Custo estimado de aquisição de cliente nesse nicho e justificativa",
    "estimated_ltv": "Lifetime value estimado e justificativa",
    "payback_period": "Tempo estimado de payback por cliente",
    "margin_potential": "Potencial de margem — alto/médio/baixo com justificativa",
    "pricing_strategy": "Estratégia de preço recomendada para esse nicho — como posicionar o preço considerando o ticket da empresa"
  },
  "priority_signals": [
    "Mínimo 6 sinais concretos que indicam que um prospect desse nicho está pronto para ser abordado — cada um com exemplo prático de como identificar"
  ],
  "quick_wins": [
    {
      "action": "Ação rápida que pode ser executada em até 7 dias",
      "expected_result": "Resultado esperado",
      "effort": "baixo|médio"
    }
  ],
  "verdict": {
    "recommendation": "atacar|explorar|evitar|monitorar",
    "confidence": "alta|média|baixa",
    "summary": "Resumo executivo denso — visão geral da oportunidade, riscos principais e potencial. Mínimo 4-5 frases.",
    "one_liner": "Frase de uma linha que resume a decisão — para referência rápida",
    "next_steps": ["Mínimo 6 próximos passos concretos e acionáveis, em ordem de prioridade — cada um com prazo sugerido"],
    "90_day_plan": "Plano resumido de 90 dias para entrada nesse nicho — mês 1, mês 2, mês 3 com ações-chave"
  }
}

INSTRUÇÕES DE VOLUME:
- pain_points: mínimo 6 dores
- entry_opportunities: mínimo 4 oportunidades
- objections: mínimo 5 objeções
- channels: mínimo 5 canais
- barriers: mínimo 3 barreiras
- sub_niches: mínimo 4 subnichos
- quick_wins: mínimo 3 ações rápidas
- priority_signals: mínimo 6 sinais
- buying_signals (dentro de ideal_icp): mínimo 6 sinais
- content_ideas (dentro de approach_strategy): mínimo 5 ideias
PROMPT;
    }

    private function buildUserPrompt(string $niche, string $focus): string
    {
        $prompt = "ANALISE EM PROFUNDIDADE o nicho: \"{$niche}\"\n\n";
        $prompt .= "Quero uma análise de nível consultoria estratégica — profunda, densa, prática e totalmente contextualizada com o perfil da minha empresa.\n\n";
        $prompt .= "CRUZE OBRIGATORIAMENTE:\n";
        $prompt .= "- Cada serviço da empresa com as dores do nicho\n";
        $prompt .= "- Cada diferencial com as lacunas do mercado\n";
        $prompt .= "- Os cases existentes com a credibilidade para esse nicho\n";
        $prompt .= "- O ICP atual com o perfil do nicho\n";
        $prompt .= "- O preço/ticket da empresa com o poder de compra do nicho\n";
        $prompt .= "- O posicionamento atual com o que esse nicho valoriza\n\n";
        $prompt .= "NÃO SEJA SUPERFICIAL. Cada bloco deve ter profundidade real — como se você estivesse sendo pago R$50.000 por esse relatório.\n";
        $prompt .= "Quero sair dessa análise sabendo EXATAMENTE se devo ou não investir nesse nicho, e se sim, exatamente como começar.\n";

        if ($focus) {
            $prompt .= "\n\nFOCO ADICIONAL PRIORITÁRIO: {$focus}\n";
            $prompt .= "Dê atenção ESPECIAL e profundidade extra a esse aspecto na análise. Dedique mais detalhes e exemplos.\n";
        }

        return $prompt;
    }
}
