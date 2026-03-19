<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\AnalysisTrace;
use App\Models\CompanyProfile;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeEmbedding;
use App\Services\Knowledge\KnowledgeIndexingService;

/**
 * Módulo Knowledge Base — CRUD do perfil de empresa + pipeline RAG.
 *
 * Rotas:
 *   GET  /knowledge              → index()        — painel principal
 *   POST /knowledge/profile      → saveProfile()  — salva perfil e re-indexa
 *   POST /knowledge/reindex      → reindex()      — força re-indexação sem alterar dados
 *   GET  /knowledge/status       → getStatus()    — JSON com status atual (polling)
 *   POST /knowledge/document/:id/delete → deleteDocument() — remove doc específico
 */
class KnowledgeController
{
    private KnowledgeIndexingService $indexer;

    public function __construct()
    {
        $this->ensureTablesExist();
        $this->indexer = new KnowledgeIndexingService();
    }

    /**
     * Aplica a migration 004_rag_module.sql automaticamente se as tabelas não existirem.
     * Usa `CREATE TABLE IF NOT EXISTS`, portanto é idempotente.
     */
    private function ensureTablesExist(): void
    {
        try {
            // Testa se a tabela principal existe (SQLite)
            $exists = Database::selectFirst(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='company_profiles'",
                []
            );

            if ($exists) return; // já existe, nada a fazer

            // Aplica migration
            $sqlPath = defined('ROOT_PATH')
                ? ROOT_PATH . '/database/migrations/004_rag_module.sql'
                : dirname(__DIR__, 2) . '/database/migrations/004_rag_module.sql';

            if (!file_exists($sqlPath)) {
                error_log('[KnowledgeController] Migration file not found: ' . $sqlPath);
                return;
            }

            $sql = file_get_contents($sqlPath);

            // Remove comentários de linha e executa statement por statement
            $clean = preg_replace('/--[^\n]*/', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
                Database::execute($stmt, []);
            }

            error_log('[KnowledgeController] Migration 004_rag_module aplicada automaticamente.');
        } catch (\Throwable $e) {
            // Se falhar, deixa o fluxo continuar — o erro de PDO vai ser exibido normalmente
            error_log('[KnowledgeController] ensureTablesExist() falhou: ' . $e->getMessage());
        }
    }

    // ─── GET /knowledge ──────────────────────────────────────────────────────

    public function index(): void
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { View::redirect('/login'); }

        $profile   = CompanyProfile::findByTenant($tenantId);
        $documents = KnowledgeDocument::allByTenant($tenantId);
        $chunks    = KnowledgeChunk::countByTenant($tenantId);
        $traces    = AnalysisTrace::recentByTenant($tenantId, 20);

        // Estatísticas rápidas por doc_type
        $docsByType = [];
        foreach ($documents as $doc) {
            $docsByType[$doc['doc_type']] = ($docsByType[$doc['doc_type']] ?? 0) + 1;
        }

        View::render('knowledge/index', [
            'active'      => 'knowledge',
            'profile'     => $profile,
            'documents'   => $documents,
            'chunks'      => $chunks,
            'docsByType'  => $docsByType,
            'traces'      => $traces,
            'traceCount'  => AnalysisTrace::countByTenant($tenantId),
            'hasIndex'    => KnowledgeEmbedding::existsForTenant($tenantId),
            'flash'       => Session::getFlash('success') ?? Session::getFlash('error'),
        ]);
    }

    // ─── POST /knowledge/profile ─────────────────────────────────────────────

    public function saveProfile(): void
    {
        // Limpar qualquer output anterior (PHP warnings/deprecation notices)
        if (ob_get_level()) ob_end_clean();
        ob_start();

        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { ob_end_clean(); $this->jsonError('Não autorizado', 401); }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        // ── Coleta e sanitiza campos do formulário ──────────────────────────

        // Campos JSON que chegam como arrays de texto (um por linha)
        $differentials = $this->parseTextareaToArray($_POST['differentials'] ?? '');
        $icpSegment    = $this->parseTextareaToArray($_POST['icp_segment']   ?? '');
        $icpPainPoints = $this->parseTextareaToArray($_POST['icp_pain_points'] ?? '');

        // Serviços: array associativo {name, description, price_range}
        $services = [];
        foreach ($_POST['services'] ?? [] as $svc) {
            $name = trim($svc['name'] ?? '');
            if ($name !== '') {
                $services[] = [
                    'name'        => $name,
                    'description' => trim($svc['description'] ?? ''),
                    'price_range' => trim($svc['price_range'] ?? ''),
                ];
            }
        }

        // Cases: array associativo {client, result, niche, timeframe}
        $cases = [];
        foreach ($_POST['cases'] ?? [] as $c) {
            $client = trim($c['client'] ?? '');
            if ($client !== '') {
                $cases[] = [
                    'client'    => $client,
                    'result'    => trim($c['result']    ?? ''),
                    'niche'     => trim($c['niche']     ?? ''),
                    'timeframe' => trim($c['timeframe'] ?? ''),
                ];
            }
        }

        // Objections: array associativo {objection, response}
        $objections = [];
        foreach ($_POST['objection_responses'] ?? [] as $o) {
            $obj = trim($o['objection'] ?? '');
            if ($obj !== '') {
                $objections[] = [
                    'objection' => $obj,
                    'response'  => trim($o['response'] ?? ''),
                ];
            }
        }

        // Competitors: array associativo {name, weakness, how_to_win}
        $competitors = [];
        foreach ($_POST['competitors'] ?? [] as $comp) {
            $name = trim($comp['name'] ?? '');
            if ($name !== '') {
                $competitors[] = [
                    'name'       => $name,
                    'weakness'   => trim($comp['weakness']   ?? ''),
                    'how_to_win' => trim($comp['how_to_win'] ?? ''),
                ];
            }
        }

        $data = [
            // Identidade
            'agency_name'          => trim($_POST['agency_name']         ?? ''),
            'agency_city'          => trim($_POST['agency_city']         ?? ''),
            'agency_state'         => trim($_POST['agency_state']        ?? ''),
            'agency_niche'         => trim($_POST['agency_niche']        ?? ''),
            'founding_year'        => trim($_POST['founding_year']       ?? ''),
            'team_size'            => trim($_POST['team_size']           ?? ''),
            'website_url'          => trim($_POST['website_url']         ?? ''),
            // Oferta
            'offer_summary'        => trim($_POST['offer_summary']       ?? ''),
            'offer_price_range'    => trim($_POST['offer_price_range']   ?? ''),
            'services'             => $services,
            'guarantees'           => trim($_POST['guarantees']          ?? ''),
            'delivery_timeline'    => trim($_POST['delivery_timeline']   ?? ''),
            // Posicionamento
            'differentials'        => $differentials,
            'unique_value_prop'    => trim($_POST['unique_value_prop']   ?? ''),
            'awards_recognition'   => trim($_POST['awards_recognition']  ?? ''),
            // ICP
            'icp_profile'          => trim($_POST['icp_profile']         ?? ''),
            'icp_segment'          => $icpSegment,
            'icp_company_size'     => trim($_POST['icp_company_size']    ?? ''),
            'icp_ticket_range'     => trim($_POST['icp_ticket_range']    ?? ''),
            'icp_pain_points'      => $icpPainPoints,
            // Prova social
            'cases'                => $cases,
            'portfolio_url'        => trim($_POST['portfolio_url']       ?? ''),
            // Comercial
            'objection_responses'  => $objections,
            'competitors'          => $competitors,
            'pricing_justification'=> trim($_POST['pricing_justification'] ?? ''),
            // Contexto livre
            'custom_context'       => trim($_POST['custom_context']      ?? ''),
        ];

        // ── Salva perfil e dispara indexação ────────────────────────────────

        $profileId = CompanyProfile::upsert($tenantId, $data);
        $indexResult = $this->indexer->indexTenant($tenantId);

        if ($indexResult['success']) {
            $this->jsonSuccess([
                'message'        => 'Perfil salvo e indexado com sucesso.',
                'chunks_indexed' => $indexResult['chunks_indexed'],
                'docs_created'   => $indexResult['docs_created'],
                'profile_id'     => $profileId,
            ]);
        } else {
            $this->jsonError(
                'Perfil salvo, mas indexação falhou: ' . ($indexResult['error'] ?? 'erro desconhecido'),
                200  // HTTP 200 — dados salvos, só o RAG falhou
            );
        }
    }

    // ─── POST /knowledge/reindex ─────────────────────────────────────────────

    public function reindex(): void
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { $this->jsonError('Não autorizado', 401); }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $profile = CompanyProfile::findByTenant($tenantId);
        if (!$profile) {
            $this->jsonError('Nenhum perfil encontrado. Salve o perfil primeiro.');
        }

        $result = $this->indexer->indexTenant($tenantId);

        if ($result['success']) {
            $this->jsonSuccess([
                'message'        => 'Re-indexação concluída.',
                'chunks_indexed' => $result['chunks_indexed'],
                'chunks_failed'  => $result['chunks_failed'],
            ]);
        } else {
            $this->jsonError('Falha na re-indexação: ' . ($result['error'] ?? 'erro desconhecido'));
        }
    }

    // ─── GET /knowledge/status ───────────────────────────────────────────────

    public function getStatus(): void
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { $this->jsonError('Não autorizado', 401); }

        $profile = CompanyProfile::findByTenant($tenantId);

        if (!$profile) {
            $this->jsonSuccess([
                'status'       => 'no_profile',
                'chunks_count' => 0,
                'has_index'    => false,
                'last_indexed' => null,
            ]);
            return;
        }

        $this->jsonSuccess([
            'status'          => $profile['indexing_status'],
            'chunks_count'    => (int) ($profile['chunks_count'] ?? 0),
            'profile_version' => (int) ($profile['profile_version'] ?? 1),
            'last_indexed'    => $profile['last_indexed_at'],
            'indexing_error'  => $profile['indexing_error'],
            'has_index'       => KnowledgeEmbedding::existsForTenant($tenantId),
            'doc_count'       => KnowledgeDocument::countByTenant($tenantId),
        ]);
    }

    // ─── POST /knowledge/document/:id/delete ─────────────────────────────────

    public function deleteDocument(string $docId): void
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { $this->jsonError('Não autorizado', 401); }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token CSRF inválido');
        }

        $doc = KnowledgeDocument::findById($docId);
        if (!$doc || $doc['tenant_id'] !== $tenantId) {
            $this->jsonError('Documento não encontrado', 404);
        }

        KnowledgeDocument::deleteById($docId);

        $this->jsonSuccess(['message' => 'Documento removido.']);
    }

    // ─── POST /knowledge/extract-document ────────────────────────────────────

    public function extractDocument(): void
    {
        // Limpar qualquer output anterior (PHP warnings/deprecation notices)
        if (ob_get_level()) ob_end_clean();
        ob_start();

        $tenantId = Session::get('tenant_id');
        if (!$tenantId) { ob_end_clean(); $this->jsonError('Não autorizado', 401); }

        if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            ob_end_clean();
            $this->jsonError('Nenhum documento enviado ou erro no upload.');
        }

        $file = $_FILES['document'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['txt', 'md', 'csv', 'pdf', 'docx'];

        if (!in_array($ext, $allowed)) {
            $this->jsonError('Formato não suportado. Use: ' . implode(', ', $allowed));
        }

        // Limitar tamanho (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $this->jsonError('Arquivo muito grande. Máximo 5MB.');
        }

        // Extrair texto do documento
        $text = $this->extractTextFromFile($file['tmp_name'], $ext);

        if (empty(trim($text))) {
            $this->jsonError('Não foi possível extrair texto do documento.');
        }

        // Limitar a 15.000 caracteres para não exceder contexto da IA
        $text = mb_substr($text, 0, 15000);

        // Enviar para IA extrair dados estruturados
        error_log('[KnowledgeController] extractDocument tenantId=' . $tenantId);
        $provider = \App\Services\AI\AIProviderFactory::make('knowledge_extraction', $tenantId);
        error_log('[KnowledgeController] provider=' . $provider->getProviderName() . '/' . $provider->getModel());

        $systemPrompt = <<<PROMPT
Você é um especialista em análise de documentos corporativos. Sua tarefa é extrair informações estratégicas de um documento fornecido e organizá-las nos campos abaixo.

REGRAS:
1. Extraia APENAS o que está presente ou claramente implícito no documento.
2. NÃO invente informações que não existem no texto.
3. Se um campo não tiver informação correspondente no documento, retorne string vazia.
4. Para campos de array, retorne array vazio se não houver dados.
5. Seja preciso na extração. Priorize fidelidade ao texto original.
PROMPT;

        $userPrompt = <<<PROMPT
DOCUMENTO ENVIADO:
---
{$text}
---

Extraia as seguintes informações do documento acima e retorne APENAS um JSON válido:

{
  "agency_name": "Nome da empresa/agência",
  "agency_niche": "Nicho principal de atuação",
  "agency_city": "Cidade",
  "agency_state": "Estado (sigla)",
  "offer_summary": "Resumo da oferta/proposta da empresa",
  "offer_price_range": "Faixa de preço se mencionada",
  "unique_value_prop": "Proposta de valor única",
  "guarantees": "Garantias oferecidas",
  "delivery_timeline": "Prazo de entrega/resultados",
  "differentials": ["Diferencial 1", "Diferencial 2"],
  "services": [{"name": "Serviço 1", "description": "Descrição", "price_range": "Preço"}],
  "icp_profile": "Descrição narrativa do cliente ideal",
  "icp_segment": ["Segmento 1", "Segmento 2"],
  "icp_company_size": "Porte ideal do cliente",
  "icp_ticket_range": "Faixa de ticket/investimento",
  "icp_pain_points": ["Dor 1", "Dor 2"],
  "cases": [{"client": "Cliente", "result": "Resultado", "niche": "Nicho", "timeframe": "Prazo"}],
  "objection_responses": [{"objection": "Objeção comum", "response": "Resposta ideal"}],
  "competitors": [{"name": "Concorrente", "weakness": "Ponto fraco", "how_to_win": "Como ganhar"}],
  "pricing_justification": "Justificativa de preço",
  "custom_context": "Qualquer informação estratégica extra (metodologia, playbooks, rituais, linguagem da marca, etc.)"
}
PROMPT;

        try {
            $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt);
            $result = $meta['parsed'] ?? $meta;

            if (\App\Helpers\AIResponseParser::hasError($result)) {
                $errDetail = \App\Helpers\AIResponseParser::getErrorMessage($result);
                error_log('[KnowledgeController] AI parse error: ' . $errDetail);
                error_log('[KnowledgeController] Raw AI response: ' . substr($meta['text'] ?? '', 0, 500));
                $this->jsonError('A IA não conseguiu processar o documento. Tente um formato diferente.');
            }

            // Registrar consumo de tokens (não pode quebrar o fluxo)
            try {
                $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];
                (new \App\Services\TokenService())->consume(
                    'knowledge_index', $tenantId,
                    Session::get('id'),
                    $provider->getProviderName(),
                    $provider->getModel(),
                    (int)($usage['input'] ?? 0), (int)($usage['output'] ?? 0)
                );
            } catch (\Throwable $tokenErr) {
                error_log('[KnowledgeController] Token tracking failed (non-fatal): ' . $tokenErr->getMessage());
            }

            $this->jsonSuccess([
                'message'  => 'Documento analisado com sucesso. Revise os campos antes de salvar.',
                'extracted' => $result,
                'filename'  => $file['name'],
            ]);
        } catch (\Throwable $e) {
            error_log('[KnowledgeController] extractDocument error: ' . $e->getMessage());
            $this->jsonError('Erro ao processar documento: ' . $e->getMessage());
        }
    }

    /**
     * Extrai texto bruto de um arquivo baseado na extensão.
     */
    private function extractTextFromFile(string $path, string $ext): string
    {
        return match ($ext) {
            'txt', 'md', 'csv' => file_get_contents($path) ?: '',
            'pdf'  => $this->extractTextFromPdf($path),
            'docx' => $this->extractTextFromDocx($path),
            default => '',
        };
    }

    /**
     * Extrai texto de PDF via regex simples (sem dependências externas).
     */
    private function extractTextFromPdf(string $path): string
    {
        $content = file_get_contents($path);
        if (!$content) return '';

        // Extrair streams de texto do PDF
        $text = '';
        if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                // Tentar decompress zlib
                $decoded = @gzuncompress($stream);
                if ($decoded === false) $decoded = @gzinflate($stream);
                if ($decoded === false) $decoded = $stream;

                // Extrair texto entre parênteses (PDF text objects)
                if (preg_match_all('/\(([^)]+)\)/', $decoded, $textMatches)) {
                    $text .= implode(' ', $textMatches[1]) . "\n";
                }
                // Extrair texto de Tj/TJ operators
                if (preg_match_all('/\[([^\]]+)\]\s*TJ/i', $decoded, $tjMatches)) {
                    foreach ($tjMatches[1] as $tj) {
                        if (preg_match_all('/\(([^)]+)\)/', $tj, $subMatches)) {
                            $text .= implode('', $subMatches[1]) . ' ';
                        }
                    }
                }
            }
        }

        // Se não extraiu texto, tentar texto plano no conteúdo
        if (empty(trim($text))) {
            $text = preg_replace('/[^\x20-\x7E\xA0-\xFF\n\r\t]/', '', $content);
        }

        return trim($text);
    }

    /**
     * Extrai texto de DOCX via zip + XML.
     */
    private function extractTextFromDocx(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return '';

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) return '';

        // Remover XML tags e extrair texto
        $text = strip_tags($xml);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function parseTextareaToArray(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($line) => $line !== ''
        ));
    }

    private function jsonSuccess(array $data, int $code = 200): never
    {
        if (ob_get_level()) ob_end_clean();
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, ...$data]);
        exit;
    }

    private function jsonError(string $message, int $code = 400): never
    {
        if (ob_get_level()) ob_end_clean();
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
