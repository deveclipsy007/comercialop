<?php

declare(strict_types=1);

namespace App\Services\Hunter;

use App\Models\CompanyProfile;
use App\Models\HunterResult;
use App\Models\HunterResultAnalysis;
use App\Core\Session;
use App\Services\AI\AIProviderFactory;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

class HunterAiAnalyzer
{

    /**
     * @param string $resultId The HunterResult ID
     * @param string $tenantId The Tenant ID
     * @return bool True if successful, false otherwise
     */
    public function analyze(string $resultId, string $tenantId): bool
    {
        $result = HunterResult::findById($resultId, $tenantId);
        if (!$result) return false;

        $profile = CompanyProfile::findByTenant($tenantId);
        
        $myAgencyContext = "Temos uma agência B2B genérica. Preste serviços de marketing e vendas.";
        if ($profile) {
            $myAgencyContext = "Nossa agência: {$profile['agency_name']} (Nicho: {$profile['agency_niche']}).\n";
            $myAgencyContext .= "Nossa Oferta: {$profile['offer_summary']}\n";
            $myAgencyContext .= "Nosso Cliente Ideal (ICP): {$profile['icp_profile']}\n";
            if (!empty($profile['services'])) {
                $myAgencyContext .= "Nossos Serviços Principais:\n";
                foreach ($profile['services'] as $svc) {
                    $myAgencyContext .= "- {$svc['name']}: {$svc['description']}\n";
                }
            }
        }

        $systemPrompt = "Você é um analista comercial Sênior (B2B). Sua missão é analisar um lead (uma empresa prospect) encontrado online e determinar quão boa oportunidade ela é para a NOSSA AGÊNCIA, baseando-se no nosso Perfil de Cliente Ideal (ICP).";

        $userPrompt = <<<PROMPT
Aqui está o contexto da NOSSA AGÊNCIA:
{$myAgencyContext}

---------------------
Aqui estão os dados da EMPRESA PROSPECT encontrada:
Nome: {$result['name']}
Segmento: {$result['segment']}
Endereço: {$result['address']}
Website: {$result['website']}
Telefone: {$result['phone']}
Instagram: {$result['instagram']}
Avaliação Google: {$result['google_rating']} ({$result['google_reviews']} reviews)

Com base nisso e na sua base de conhecimento (você pode inferir o quão madura digitalmente deve ser uma empresa com esse perfil), gere uma análise comercial sobre por que deveríamos (ou não) prospectar essa empresa.

Retorne EXATAMENTE UM JSON válido com a seguinte estrutura e NÃO INCLUA NADA FORA DO JSON:
{
    "executive_summary": "Resumo comercial em 1-2 parágrafos sobre a oportunidade",
    "pain_points": [
        "Dor provável 1 (ex: Site parece datado ou inexistente)",
        "Dor provável 2 (ex: Baixo volume de reviews para o tempo de mercado)"
    ],
    "opportunities": [
        "Oportunidade 1 (O que podemos vender para eles?)",
        "Oportunidade 2"
    ],
    "recommended_approach": "Qual canal usar e como iniciar a conversa de forma matadora baseada nas dores.",
    "icp_match_score": 85, 
    "priority_score": 75,
    "priority_level": "hot" // Pode ser "hot", "warm" ou "cold"
}

Importante: 
- icp_match_score vai de 0 a 100.
- priority_score vai de 0 a 100.
PROMPT;

        $provider = AIProviderFactory::make('hunter', $tenantId);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt, []);
        $response = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        // Registrar consumo de tokens
        $tokens = new TokenService();
        $tokens->consume(
            'hunter', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

        if (AIResponseParser::hasError($response)) {
            error_log('[HunterAiAnalyzer] Falhou ao analisar lead ' . $resultId . ': ' . json_encode($response));
            return false;
        }

        HunterResultAnalysis::createOrUpdate($tenantId, $resultId, [
             'executive_summary'    => $response['executive_summary'] ?? '',
             'pain_points'          => $response['pain_points'] ?? [],
             'opportunities'        => $response['opportunities'] ?? [],
             'recommended_approach' => $response['recommended_approach'] ?? '',
             'icp_match_score'      => (int) ($response['icp_match_score'] ?? 0),
             'priority_score'       => (int) ($response['priority_score'] ?? 0),
             'priority_level'       => $response['priority_level'] ?? 'cold'
        ]);

        return true;
    }
}
