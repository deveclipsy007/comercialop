<?php

declare(strict_types=1);

namespace App\Controllers;

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
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];

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
                $humanCtx = json_decode($leadData['human_context'] ?? '{}', true);
                $analysis = json_decode($leadData['analysis'] ?? '{}', true);
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

        $systemPrompt = "Você é o Operon Intelligence, um assistente estratégico de vendas B2B de alto nível. Você é consultivo, direto, prático e focado em resultado. Responda sempre em português brasileiro, de forma concisa e comercialmente relevante. Se o usuário selecionou um lead como contexto, use essas informações para personalizar e enriquecer suas respostas.{$leadContext}{$filterInstruction}{$historyText}";

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
}
