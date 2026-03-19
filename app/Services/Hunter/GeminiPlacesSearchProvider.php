<?php

declare(strict_types=1);

namespace App\Services\Hunter;

use App\Core\Session;
use App\Services\AI\AIProviderFactory;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;

class GeminiPlacesSearchProvider implements HunterSearchProviderInterface
{
    public function search(string $segment, string $location, array $filters = []): array
    {
        $tenantId = $filters['tenant_id'] ?? (Session::get('tenant_id') ?: '');
        $radius = $filters['radius'] ?? 5;
        $maxResults = $filters['max_results'] ?? 10;
        
        // Build exclusion instruction if provided
        $exclusions = $filters['exclusions'] ?? [];
        $exclusionText = !empty($exclusions) ? "Evite retornar empresas com as seguintes características: " . implode(', ', $exclusions) . "." : "";

        $systemPrompt = "Você é uma ferramenta operando como um buscador inteligente B2B de alta precisão. Sua função é utilizar o Google Search para encontrar empresas REAIS no radar especificado. Retorne EXATAMENTE os dados exigidos.";
        
        $userPrompt = <<<PROMPT
Sua missão é procurar por "{$segment}" na região de "{$location}" (raio aproximado de {$radius}km).
Tente encontrar até {$maxResults} resultados de empresas estabelecidas e operacionais reais.

{$exclusionText}

Para cada empresa encontrada, extraia a fundo as informações públicas disponíveis online.
Retorne um JSON estritamente válido e NADA MAIS, usando a seguinte estrutura:

{
  "results": [
    {
      "name": "Nome Real da Empresa",
      "address": "Endereço completo",
      "city": "Cidade",
      "phone": "Telefone encontrado (se houver)",
      "website": "URL oficial (se houver)",
      "instagram": "URL ou @ do instagram (se achável)",
      "email": "email publico de contato (se houver)",
      "google_rating": 4.8,
      "google_reviews": 120,
      "segment": "Segmento ou subcategoria exata da empresa"
    }
  ]
}

Não inclua textos explicativos, formatadores markdown (como ```json) ou qualquer outra coisa fora o JSON cru.
PROMPT;

        $provider = AIProviderFactory::make('hunter', $tenantId);
        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt, ['google_search' => true]);
        $response = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        if (!empty($tenantId)) {
            $tokens = new TokenService();
            $tokens->consume(
                'hunter', $tenantId, Session::get('id'),
                $provider->getProviderName(), $provider->getModel(),
                $usage['input'], $usage['output']
            );
        }

        if (AIResponseParser::hasError($response)) {
            error_log('[Hunter] Search Provider falhou: ' . json_encode($response));
            return [];
        }

        return $response['results'] ?? ($response['leads'] ?? []);
    }
}
