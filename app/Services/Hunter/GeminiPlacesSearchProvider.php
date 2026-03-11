<?php

declare(strict_types=1);

namespace App\Services\Hunter;

use App\Services\AI\GeminiProvider;
use App\Helpers\AIResponseParser;

class GeminiPlacesSearchProvider implements HunterSearchProviderInterface
{
    private GeminiProvider $gemini;

    public function __construct()
    {
        $this->gemini = new GeminiProvider();
    }

    public function search(string $segment, string $location, array $filters = []): array
    {
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

        $response = $this->gemini->generateJson($systemPrompt, $userPrompt, ['google_search' => true]);

        if (AIResponseParser::hasError($response)) {
            error_log('[Hunter] Gemini Search Provider falhou: ' . json_encode($response));
            return [];
        }

        return $response['results'] ?? ($response['leads'] ?? []);
    }
}
