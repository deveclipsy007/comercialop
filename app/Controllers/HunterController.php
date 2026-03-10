<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\Lead;
use App\Services\AI\GeminiProvider;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

class HunterController
{
    public function index(): void
    {
        Session::requireAuth();
        View::render('hunter/index', ['active' => 'hunter', 'results' => [], 'query' => '']);
    }

    public function search(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!Session::validateCsrf($body['_csrf'] ?? $_POST['_csrf'] ?? '')) {
            echo json_encode(['error' => 'Token inválido']); return;
        }

        $tokens  = new TokenService();
        if (!$tokens->hasSufficient('hunter_search', $tenantId)) {
            echo json_encode(['error' => 'tokens_depleted', 'message' => 'Tokens diários esgotados.']); return;
        }

        $segment = trim($body['segment'] ?? '');
        $city    = trim($body['city'] ?? '');
        $radius  = (int) ($body['radius'] ?? 5);

        if (empty($segment) || empty($city)) {
            echo json_encode(['error' => 'Segmento e cidade são obrigatórios.']); return;
        }

        $tokens->consume('hunter_search', $tenantId);

        $gemini = new GeminiProvider();
        $systemPrompt = "Você é um especialista em prospecção B2B. Encontre empresas reais do segmento especificado na cidade indicada.";
        $userPrompt = <<<PROMPT
Use o Google Search para encontrar de 8 a 12 empresas reais do segmento "{$segment}" na cidade de "{$city}" (raio de {$radius}km).

Para cada empresa encontrada, retorne dados reais encontrados online.

Retorne APENAS JSON válido:
{"leads":[{"name":"string","segment":"string","address":"string","phone":"string","website":"string","instagram":"string","estimated_score":number,"reason":"string"}]}
PROMPT;

        $result = $gemini->generateJson($systemPrompt, $userPrompt, ['google_search' => true]);

        if (AIResponseParser::hasError($result)) {
            echo json_encode(['error' => 'Erro na busca. Tente novamente.']); return;
        }

        echo json_encode($result);
    }
}
