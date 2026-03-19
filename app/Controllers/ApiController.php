<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\Lead;
use App\Models\TokenQuota;
use App\Services\AI\AIProviderFactory;
use App\Services\TokenService;

class ApiController
{
    public function __construct()
    {
        header('Content-Type: application/json');
        if (!Session::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    // GET /api/tokens
    public function tokens(): void
    {
        $tenantId = Session::get('tenant_id');
        echo json_encode(TokenQuota::getBalance($tenantId));
    }

    // GET /api/leads
    public function leads(): void
    {
        $tenantId = Session::get('tenant_id');
        $leads = Lead::allByTenant($tenantId, [
            'limit'  => (int) ($_GET['limit'] ?? 50),
            'search' => $_GET['search'] ?? $_GET['q'] ?? null,
        ]);
        echo json_encode($leads);
    }

    // POST /api/copilot
    public function copilot(): void
    {
        $tenantId = Session::get('tenant_id');
        $body     = $this->readJsonBody();

        if (!Session::validateCsrf($body['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'Token inválido']);
            return;
        }

        $message = trim($body['message'] ?? '');
        if (empty($message)) {
            echo json_encode(['error' => 'Mensagem vazia']);
            return;
        }

        $tokens = new TokenService();
        if (!$tokens->hasSufficient('copilot_message', $tenantId)) {
            echo json_encode(['error' => 'tokens_depleted', 'message' => 'Créditos esgotados.']);
            return;
        }

        // Lead context
        $leadId = trim($body['lead_id'] ?? '');
        $leadContext = '';
        $leadData = null;
        if ($leadId) {
            $leadData = Lead::findByTenant($leadId, $tenantId);
            if ($leadData) {
                $humanCtx = $this->normalizeJsonField($leadData['human_context'] ?? []);
                $analysis = $this->normalizeJsonField($leadData['analysis'] ?? []);
                $leadContext = "\n\n--- CONTEXTO DO LEAD SELECIONADO ---\n";
                $leadContext .= "Nome: {$leadData['name']}\n";
                $leadContext .= "Segmento: {$leadData['segment']}\n";
                if ($leadData['phone']) $leadContext .= "Telefone: {$leadData['phone']}\n";
                if ($leadData['email']) $leadContext .= "Email: {$leadData['email']}\n";
                if ($leadData['website']) $leadContext .= "Website: {$leadData['website']}\n";
                $leadContext .= "Pipeline: {$leadData['pipeline_status']}\n";
                if ($leadData['address']) $leadContext .= "Endereço: {$leadData['address']}\n";
                $temp = $humanCtx['temperature'] ?? '';
                if ($temp) $leadContext .= "Temperatura: {$temp}\n";
                $notes = $humanCtx['notes'] ?? '';
                if ($notes) $leadContext .= "Notas do closer: {$notes}\n";
                if (!empty($analysis)) $leadContext .= "Análise IA: " . substr(json_encode($analysis, JSON_UNESCAPED_UNICODE), 0, 500) . "\n";
                $leadContext .= "--- FIM DO CONTEXTO ---\n";
            }
        }

        $vaultContext = $this->buildVaultContext($tenantId, $message);

        // Response filter/focus
        $filter = trim($body['filter'] ?? '');
        $filterInstruction = '';
        $filterMap = [
            'closing' => 'Foque sua resposta em estratégias de fechamento de venda. Dê técnicas práticas, argumentos de urgência e próximos passos para fechar negócio.',
            'objections' => 'Foque em antecipar e responder objeções do prospect. Liste as objeções prováveis e como contorná-las com argumentos comerciais sólidos.',
            'followup' => 'Foque em estratégia de follow-up. Sugira mensagens, timing ideal e abordagem para reengajar o lead sem ser invasivo.',
            'diagnosis' => 'Foque em diagnóstico comercial do lead. Analise o cenário, identifique dores, oportunidades e recomende abordagem estratégica.',
            'potential' => 'Foque em analisar o potencial comercial. Avalie fit, timing, budget provável e priorize recomendações práticas.',
            'whatsapp' => 'Gere uma mensagem profissional para WhatsApp. Tom conversacional, direto, sem ser invasivo. Pronta para copiar e enviar.',
            'strategic' => 'Faça uma análise estratégica completa. Considere concorrência, timing de mercado, posicionamento e recomendações de alto nível.',
            'summary' => 'Dê um resumo prático e direto. Bullet points, sem enrolação. Foque no que é acionável agora.',
        ];
        if ($filter && isset($filterMap[$filter])) {
            $filterInstruction = "\n\nINSTRUÇÃO DE FOCO: " . $filterMap[$filter];
        }

        // Conversation history (last messages for context)
        $history = $body['history'] ?? [];
        $historyText = '';
        if (!empty($history) && is_array($history)) {
            $recentHistory = array_slice($history, -6); // Last 6 messages
            foreach ($recentHistory as $h) {
                $role = ($h['role'] ?? '') === 'user' ? 'Usuário' : 'Assistente';
                $historyText .= "{$role}: " . substr($h['content'] ?? '', 0, 300) . "\n";
            }
            if ($historyText) {
                $historyText = "\n\nHISTÓRICO RECENTE DA CONVERSA:\n" . $historyText;
            }
        }

        $provider = AIProviderFactory::make('copilot_message', $tenantId);

        $systemPrompt = "Você é o Operon Intelligence, um assistente estratégico de vendas B2B de alto nível. Você é consultivo, direto, prático e focado em resultado. Responda sempre em português brasileiro, de forma concisa e comercialmente relevante. Se o usuário selecionou um lead como contexto, use essas informações para personalizar e enriquecer suas respostas. Se houver um bloco chamado CONTEXTO REAL DO VAULT, trate-o como a fonte factual para análises de grupo, funil, estágio, segmento, temperatura e comparação entre leads. Não diga que só possui dados de um único lead quando o contexto do Vault listar vários leads.{$leadContext}{$vaultContext}{$filterInstruction}{$historyText}";

        $meta = $provider->generateWithMeta($systemPrompt, $message);
        $reply = $meta['text'] ?? '';
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $tokens->consume(
            'copilot_message', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

        echo json_encode(['reply' => $reply]);
    }

    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function buildVaultContext(string $tenantId, string $message): string
    {
        $intent = $this->detectVaultIntent($tenantId, $message);
        if (!$intent['needs_context']) {
            return '';
        }

        $totalLeads = Lead::count($tenantId);
        if ($totalLeads === 0) {
            return "\n\n--- CONTEXTO REAL DO VAULT ---\nNenhum lead encontrado no Vault.\n--- FIM DO CONTEXTO DO VAULT ---\n";
        }

        $pipelineStats = Lead::pipelineStats($tenantId);
        $tempStats = [
            'HOT'  => $this->countTemperatureLeads($tenantId, 'HOT'),
            'WARM' => $this->countTemperatureLeads($tenantId, 'WARM'),
            'COLD' => $this->countTemperatureLeads($tenantId, 'COLD'),
        ];

        $topSegments = Database::select(
            "SELECT segment, COUNT(*) as cnt
             FROM leads
             WHERE tenant_id = ? AND TRIM(COALESCE(segment, '')) <> ''
             GROUP BY segment
             ORDER BY cnt DESC, segment ASC
             LIMIT 5",
            [$tenantId]
        );

        $focusLabel = 'Visão geral do Vault';
        $focusCount = $totalLeads;
        $focusLeads = $this->fetchLeadRows(
            'SELECT * FROM leads WHERE tenant_id = ? ORDER BY priority_score DESC, updated_at DESC LIMIT ?',
            [$tenantId, 10]
        );
        $focusInstruction = 'O pedido envolve múltiplos leads. Analise padrões, prioridades e próximas ações usando os dados abaixo.';

        if ($intent['stage'] !== null) {
            $focusLabel = 'Estágio ' . $this->getStageDisplayLabel($intent['stage']);
            $focusCount = (int) ($pipelineStats[$intent['stage']] ?? 0);
            $focusLeads = $this->fetchLeadRows(
                'SELECT * FROM leads WHERE tenant_id = ? AND pipeline_status = ? ORDER BY priority_score DESC, updated_at DESC LIMIT ?',
                [$tenantId, $intent['stage'], 12]
            );
            $focusInstruction = 'O usuário pediu análise por estágio. Responda com visão do grupo, prioridades e ações para o conjunto inteiro.';
        } elseif ($intent['temperature'] !== null) {
            $focusLabel = 'Temperatura ' . $intent['temperature'];
            $focusCount = $tempStats[$intent['temperature']] ?? 0;
            $focusLeads = $this->fetchLeadRows(
                'SELECT * FROM leads WHERE tenant_id = ? AND human_context LIKE ? ORDER BY priority_score DESC, updated_at DESC LIMIT ?',
                [$tenantId, '%"temperature":"' . $intent['temperature'] . '"%', 12]
            );
            $focusInstruction = 'O usuário pediu análise por temperatura. Responda com estratégia para esse grupo específico.';
        } elseif ($intent['segment'] !== null) {
            $focusLabel = 'Segmento ' . $intent['segment'];
            $focusCount = $this->countSegmentLeads($tenantId, $intent['segment']);
            $focusLeads = $this->fetchLeadRows(
                'SELECT * FROM leads WHERE tenant_id = ? AND segment LIKE ? ORDER BY priority_score DESC, updated_at DESC LIMIT ?',
                [$tenantId, '%' . $intent['segment'] . '%', 12]
            );
            $focusInstruction = 'O usuário pediu análise por segmento. Responda com padrões do segmento, prioridades e ações por grupo.';
        } elseif ($intent['comparison']) {
            $focusInstruction = 'O usuário pediu comparação entre leads, segmentos, prioridades ou funil. Responda em nível de portfólio comercial.';
        }

        $context = "\n\n--- CONTEXTO REAL DO VAULT ---\n";
        $context .= "Total de leads no Vault: {$totalLeads}\n";
        $context .= "Funil atual: " . $this->formatPipelineStats($pipelineStats) . "\n";
        $context .= "Temperaturas: HOT {$tempStats['HOT']} | WARM {$tempStats['WARM']} | COLD {$tempStats['COLD']}\n";

        if (!empty($topSegments)) {
            $segmentsText = array_map(
                fn(array $row) => trim((string) $row['segment']) . ' (' . (int) $row['cnt'] . ')',
                $topSegments
            );
            $context .= "Top segmentos: " . implode(' | ', $segmentsText) . "\n";
        }

        $context .= "Filtro interpretado: {$focusLabel}\n";
        $context .= "Quantidade encontrada neste filtro: {$focusCount}\n";
        $context .= $focusInstruction . "\n";

        if (!empty($focusLeads)) {
            $context .= "Leads mais relevantes deste recorte:\n";
            foreach ($focusLeads as $lead) {
                $context .= $this->formatLeadSummary($lead) . "\n";
            }
        } else {
            $context .= "Nenhum lead encontrado para esse recorte específico.\n";
        }

        $context .= "Use esse contexto como base factual. Se faltar algum dado, diga exatamente o que falta, sem ignorar os leads listados acima.\n";
        $context .= "--- FIM DO CONTEXTO DO VAULT ---\n";

        return $context;
    }

    private function detectVaultIntent(string $tenantId, string $message): array
    {
        $normalized = $this->normalizeText($message);
        $comparison = str_contains($normalized, 'compar')
            || str_contains($normalized, 'funil')
            || str_contains($normalized, 'pipeline')
            || str_contains($normalized, 'prioridade')
            || str_contains($normalized, 'segmento')
            || str_contains($normalized, 'temperatura');
        $mentionsGroup = str_contains($normalized, 'meus leads')
            || preg_match('/\bleads\b/', $normalized) === 1
            || str_contains($normalized, 'vault')
            || str_contains($normalized, 'estagio')
            || str_contains($normalized, 'estagios')
            || str_contains($normalized, 'funil')
            || str_contains($normalized, 'pipeline');
        $canUseVaultFilters = $comparison || $mentionsGroup;

        $stage = $canUseVaultFilters ? $this->resolveStageFromMessage($normalized) : null;
        $temperature = $canUseVaultFilters ? $this->resolveTemperatureFromMessage($normalized) : null;
        $segment = ($canUseVaultFilters || str_contains($normalized, 'segmento'))
            ? $this->resolveSegmentFromMessage($tenantId, $message, $normalized)
            : null;

        return [
            'needs_context' => $stage !== null || $temperature !== null || $segment !== null || $comparison || $mentionsGroup,
            'comparison'    => $comparison,
            'stage'         => $stage,
            'temperature'   => $temperature,
            'segment'       => $segment,
        ];
    }

    private function resolveStageFromMessage(string $normalizedMessage): ?string
    {
        $map = [
            'closed_lost' => ['perdidos', 'perdido', 'closed lost'],
            'closed_won'  => ['ganhos', 'ganho', 'closed won'],
            'qualified'   => ['qualificados', 'qualificado', 'qualified'],
            'contacted'   => ['contatados', 'contatado', 'contactados', 'contactado', 'contacted'],
            'proposal'    => ['propostas', 'proposta', 'em proposta', 'proposal'],
            'new'         => ['novos', 'novo', 'prospeccao', 'new'],
        ];

        foreach ($map as $stage => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/\b' . preg_quote($alias, '/') . '\b/', $normalizedMessage) === 1) {
                    return $stage;
                }
            }
        }

        return null;
    }

    private function resolveTemperatureFromMessage(string $normalizedMessage): ?string
    {
        $map = [
            'HOT'  => ['hot', 'quente', 'quentes'],
            'WARM' => ['warm', 'morno', 'morna', 'mornos', 'mornas'],
            'COLD' => ['cold', 'frio', 'fria', 'frios', 'frias'],
        ];

        foreach ($map as $temperature => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/\b' . preg_quote($alias, '/') . '\b/', $normalizedMessage) === 1) {
                    return $temperature;
                }
            }
        }

        return null;
    }

    private function resolveSegmentFromMessage(string $tenantId, string $originalMessage, string $normalizedMessage): ?string
    {
        $segments = Database::select(
            "SELECT segment, COUNT(*) as cnt
             FROM leads
             WHERE tenant_id = ? AND TRIM(COALESCE(segment, '')) <> ''
             GROUP BY segment
             ORDER BY cnt DESC, segment ASC
             LIMIT 80",
            [$tenantId]
        );

        usort($segments, function (array $a, array $b): int {
            return strlen($this->normalizeText((string) $b['segment'])) <=> strlen($this->normalizeText((string) $a['segment']));
        });

        foreach ($segments as $row) {
            $segment = trim((string) ($row['segment'] ?? ''));
            if ($segment === '') {
                continue;
            }

            if (str_contains($normalizedMessage, $this->normalizeText($segment))) {
                return $segment;
            }
        }

        if (preg_match('/segmento\s+(.+?)(?:\s+e\s+|\?|$)/iu', $originalMessage, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    private function fetchLeadRows(string $sql, array $bindings): array
    {
        $rows = Database::select($sql, $bindings);
        return array_map(fn(array $row) => $this->decodeLeadRow($row), $rows);
    }

    private function decodeLeadRow(array $row): array
    {
        $row['analysis'] = $this->normalizeJsonField($row['analysis'] ?? []);
        $row['human_context'] = $this->normalizeJsonField($row['human_context'] ?? []);
        $row['priority_score'] = (int) ($row['priority_score'] ?? 0);
        return $row;
    }

    private function formatLeadSummary(array $lead): string
    {
        $humanCtx = $this->normalizeJsonField($lead['human_context'] ?? []);
        $analysis = $this->normalizeJsonField($lead['analysis'] ?? []);
        $temperature = strtoupper((string) ($humanCtx['temperature'] ?? 'COLD'));
        $notes = trim((string) ($humanCtx['notes'] ?? ''));
        $summary = trim((string) ($analysis['summary'] ?? $analysis['executive_summary'] ?? ''));
        $segment = trim((string) ($lead['segment'] ?? '')) ?: 'Sem segmento';
        $stage = $this->getStageDisplayLabel((string) ($lead['pipeline_status'] ?? 'new'));

        $parts = [
            'Nome: ' . trim((string) ($lead['name'] ?? 'Sem nome')),
            'Segmento: ' . $segment,
            'Estágio: ' . $stage,
            'Score: ' . (int) ($lead['priority_score'] ?? 0),
            'Temperatura: ' . $temperature,
        ];

        if ($notes !== '') {
            $parts[] = 'Notas: ' . $this->truncateText($notes, 90);
        }

        if ($summary !== '') {
            $parts[] = 'Resumo IA: ' . $this->truncateText($summary, 110);
        }

        return '- ' . implode(' | ', $parts);
    }

    private function formatPipelineStats(array $stats): string
    {
        $orderedStages = ['new', 'contacted', 'qualified', 'proposal', 'closed_won', 'closed_lost'];
        $parts = [];

        foreach ($orderedStages as $stage) {
            $parts[] = $this->getStageDisplayLabel($stage) . ' ' . (int) ($stats[$stage] ?? 0);
        }

        return implode(' | ', $parts);
    }

    private function getStageDisplayLabel(string $stage): string
    {
        return match ($stage) {
            'new'         => 'Novos',
            'contacted'   => 'Contatados',
            'qualified'   => 'Qualificados',
            'proposal'    => 'Proposta',
            'closed_won'  => 'Ganhos',
            'closed_lost' => 'Perdidos',
            default       => $stage,
        };
    }

    private function countTemperatureLeads(string $tenantId, string $temperature): int
    {
        $row = Database::selectFirst(
            'SELECT COUNT(*) as c FROM leads WHERE tenant_id = ? AND human_context LIKE ?',
            [$tenantId, '%"temperature":"' . $temperature . '"%']
        );

        return (int) ($row['c'] ?? 0);
    }

    private function countSegmentLeads(string $tenantId, string $segment): int
    {
        $row = Database::selectFirst(
            'SELECT COUNT(*) as c FROM leads WHERE tenant_id = ? AND segment LIKE ?',
            [$tenantId, '%' . $segment . '%']
        );

        return (int) ($row['c'] ?? 0);
    }

    private function truncateText(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if (strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(substr($text, 0, $limit - 3)) . '...';
    }

    private function normalizeText(string $text): string
    {
        $text = function_exists('mb_strtolower')
            ? mb_strtolower($text, 'UTF-8')
            : strtolower($text);

        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
