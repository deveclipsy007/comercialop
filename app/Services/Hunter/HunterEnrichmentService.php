<?php

declare(strict_types=1);

namespace App\Services\Hunter;

use App\Models\HunterResult;
use App\Core\Session;
use App\Services\AI\AIProviderFactory;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

class HunterEnrichmentService
{

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

        $provider = AIProviderFactory::make('hunter', $tenantId);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt, ['google_search' => true]);
        $response = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $tokens = new TokenService();
        $tokens->consume(
            'hunter', $tenantId, Session::get('id'),
            $provider->getProviderName(), $provider->getModel(),
            $usage['input'], $usage['output']
        );

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
