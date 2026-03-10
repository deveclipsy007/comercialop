<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\Lead;
use App\Models\TokenQuota;
use App\Services\AI\GeminiProvider;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

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
            'search' => $_GET['q'] ?? null,
        ]);
        echo json_encode(['leads' => $leads, 'total' => count($leads)]);
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
            echo json_encode(['error' => 'tokens_depleted', 'message' => 'Tokens diários esgotados.']);
            return;
        }

        $tokens->consume('copilot_message', $tenantId);

        $gemini = new GeminiProvider();
        $systemPrompt = "Você é o Copilot da Operon Intelligence, um assistente de vendas B2B. Responda de forma concisa e comercialmente relevante em português brasileiro.";
        $reply = $gemini->generate($systemPrompt, $message);

        echo json_encode(['reply' => $reply]);
    }
}
