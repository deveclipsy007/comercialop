<?php

declare(strict_types=1);

namespace App\Services\Hunter;

use App\Models\HunterResult;
use App\Services\AI\GeminiProvider;
use App\Helpers\AIResponseParser;

class HunterEnrichmentService
{
    private GeminiProvider $gemini;

    public function __construct()
    {
        $this->gemini = new GeminiProvider();
    }

    public function enrich(string $resultId, string $tenantId): bool
    {
        $result = HunterResult::findById($resultId, $tenantId);
        if (!$result) return false;

        // Se já tem email e instagram e site, pular
        if (!empty($result['email']) && !empty($result['instagram']) && !empty($result['website'])) {
            return true;
        }

        $systemPrompt = "Você é um pesquisador de dados OSINT. Sua função é buscar dados públicos (Site, Email comercial, Instagram, Telefone) de uma empresa específica e retornar APENAS formato JSON.";

        $userPrompt = <<<PROMPT
Encontre os contatos públicos da seguinte empresa usando o Google Search:
Nome: {$result['name']}
Segmento: {$result['segment']}
Endereço: {$result['address']}
Website Atual: {$result['website']}

Preencha as lacunas. Se não achar, retorne null.
Retorne EXATAMENTE este JSON:
{
    "website": "url",
    "email": "email",
    "instagram": "url_ou_arroba",
    "phone": "telefone"
}
PROMPT;

        $response = $this->gemini->generateJson($systemPrompt, $userPrompt, ['google_search' => true]);

        if (AIResponseParser::hasError($response)) {
            return false;
        }

        // Atualizar no banco
        $updateSql = 'UPDATE hunter_results SET ';
        $params = [];
        
        $fields = ['website', 'email', 'instagram', 'phone'];
        foreach ($fields as $f) {
            if (!empty($response[$f]) && empty($result[$f])) {
                $updateSql .= "{$f} = ?, ";
                $params[] = $response[$f];
            }
        }

        if (!empty($params)) {
             $updateSql .= "updated_at = datetime('now') WHERE id = ? AND tenant_id = ?";
             $params[] = $resultId;
             $params[] = $tenantId;
             
             \App\Core\Database::execute($updateSql, $params);
        }

        return true;
    }
}
