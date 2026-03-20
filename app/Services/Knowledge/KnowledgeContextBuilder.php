<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Constrói o bloco de contexto empresarial injetado nos prompts de IA.
 *
 * Substitui (com fallback) o bloco estático do SmartContextService.
 *
 * Responsabilidades:
 *   1. Derivar a query de retrieval baseada no tipo de operação + dados do lead
 *   2. Recuperar chunks relevantes via RAGRetrievalService
 *   3. Formatar chunks em bloco estruturado legível para o LLM
 *   4. Expor metadados de retrieval para rastreabilidade (AnalysisTrace)
 *
 * Thread-safety: seguro para uso simultâneo — sem estado compartilhado entre requests.
 */
class KnowledgeContextBuilder
{
    // Templates de query por operação — orientam o retrieval semântico
    private const QUERY_TEMPLATES = [
        'lead_analysis'         => 'análise diagnóstico digital presença online {segment} {city}',
        'deep_analysis'         => 'proposta de valor serviços oferta diferencial ICP {segment}',
        'operon_diagnostico'    => 'diagnóstico de perda problemas dores cliente {segment}',
        'operon_potencial'      => 'potencial comercial serviços ticket médio proposta {segment}',
        'operon_autoridade'     => 'autoridade local cases prova social concorrentes {segment}',
        'operon_script'         => 'script abordagem objeções argumentos comerciais {segment}',
        'script_variations'     => 'scripts whatsapp email linkedin cold call objeções {segment}',
        'spin_questions'        => 'dores problemas implicações necessidades cliente {segment}',
        'copilot_message'       => 'serviços oferta ICP diferenciais argumentos vendas',
        'followup'              => 'follow-up próxima ação argumentos objeções {segment}',
        'meridian_niche'        => 'nicho mercado ICP segmentos oferta diferenciais posicionamento {segment}',
        'default'               => 'empresa serviços ICP oferta diferenciais {segment} {city}',
    ];

    private RAGRetrievalService $retrieval;

    // Metadados do último retrieval — lidos por SmartContextService para AnalysisTrace
    private array $lastRetrievalMeta = [];

    public function __construct()
    {
        $this->retrieval = new RAGRetrievalService();
    }

    /**
     * Constrói o bloco de contexto empresarial para um dado lead/operação.
     *
     * @return array [
     *   'context'   => string,           // bloco formatado para o LLM
     *   'source'    => string,           // 'rag' | 'legacy' | 'default'
     *   'chunk_ids' => string[],         // ids dos chunks usados (para trace)
     * ]
     */
    public function buildContext(
        string $query,
        array  $lead,
        string $tenantId,
        string $operation = 'default'
    ): array {
        $chunks = $this->retrieval->retrieve($query, $tenantId, topK: 5);

        if (empty($chunks)) {
            $this->lastRetrievalMeta = ['source' => 'default', 'chunk_ids' => []];
            return ['context' => '', 'source' => 'default', 'chunk_ids' => []];
        }

        $source    = $chunks[0]['source'] ?? 'rag';
        $chunkIds  = array_column($chunks, 'chunk_id');
        $context   = $this->formatChunks($chunks, $lead);

        $this->lastRetrievalMeta = compact('source', 'chunk_ids');

        return compact('context', 'source', 'chunk_ids');
    }

    /**
     * Deriva a query de retrieval a partir do tipo de operação e dados do lead.
     * Quanto mais específica a query, mais relevantes os chunks recuperados.
     */
    public function deriveQuery(string $operation, array $lead): string
    {
        $template = self::QUERY_TEMPLATES[$operation] ?? self::QUERY_TEMPLATES['default'];

        $segment = $lead['segment'] ?? '';
        $city    = $lead['address'] ?? '';

        // Extrai apenas a cidade do endereço completo se possível
        if (str_contains($city, ',')) {
            $city = trim(explode(',', $city)[0]);
        }

        // Limpa espaços extras caso segment ou city estejam vazios
        return trim(preg_replace('/\s+/', ' ',
            str_replace(['{segment}', '{city}'], [$segment, $city], $template)
        ));
    }

    /**
     * Retorna os metadados do último retrieval realizado.
     * Usado por SmartContextService para popular AnalysisTrace.
     */
    public function getLastRetrievalMeta(): array
    {
        return $this->lastRetrievalMeta;
    }

    // ─── Formatação ────────────────────────────────────────────────

    /**
     * Agrupa chunks por doc_type e renderiza bloco de contexto estruturado.
     *
     * Exemplo de saída:
     * ===== CONTEXTO DA EMPRESA =====
     * [IDENTIDADE] A empresa se chama Nexus Digital...
     * [ICP] Nosso cliente ideal tem 10-50 funcionários...
     * [SERVIÇOS] Gestão de tráfego: R$2.000/mês...
     * [CASES] Cliente Advocacia Silva: +40% de leads em 90 dias...
     * ================================
     */
    private function formatChunks(array $chunks, array $lead): string
    {
        $labels = [
            'identity'     => 'IDENTIDADE',
            'services'     => 'SERVIÇOS',
            'differentials'=> 'DIFERENCIAIS',
            'icp'          => 'ICP',
            'cases'        => 'CASES',
            'objections'   => 'OBJEÇÕES',
            'competitors'  => 'CONCORRENTES',
            'custom'       => 'CONTEXTO ADICIONAL',
            'legacy'       => 'PERFIL DA AGÊNCIA',
        ];

        // Agrupa por doc_type, mantendo ordem de relevância dentro de cada tipo
        $byType = [];
        foreach ($chunks as $chunk) {
            $byType[$chunk['doc_type']][] = $chunk['content'];
        }

        $lines = ['===== CONTEXTO DA EMPRESA (use para personalizar a análise) ====='];

        foreach ($byType as $docType => $contents) {
            $label = $labels[$docType] ?? strtoupper($docType);
            foreach ($contents as $content) {
                $lines[] = "[{$label}] " . trim($content);
            }
        }

        // Complementa com dados do lead para ancorar a análise
        $lines[] = '';
        $lines[] = '=== LEAD EM ANÁLISE ===';
        $lines[] = 'Nome: ' . ($lead['name'] ?? 'N/D');
        $lines[] = 'Segmento: ' . ($lead['segment'] ?? 'N/D');
        if (!empty($lead['address'])) $lines[] = 'Localização: ' . $lead['address'];
        if (!empty($lead['website'])) $lines[] = 'Site: ' . $lead['website'];
        $lines[] = '=================';

        return implode("\n", $lines);
    }
}
