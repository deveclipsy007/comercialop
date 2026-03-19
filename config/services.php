<?php

return [
    // Provider ativo: gemini | openai | grok
    'operon_provider' => env('OPERON_PROVIDER', 'gemini'),
    'operon_model'    => env('OPERON_MODEL', 'gemini-2.0-flash'),

    'gemini' => [
        'key'      => env('GEMINI_API_KEY', ''),
        'model'    => env('OPERON_MODEL', 'gemini-2.0-flash'),
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/',
    ],

    'openai' => [
        'key'      => env('OPENAI_API_KEY', ''),
        'model'    => env('OPENAI_MODEL', 'gpt-4o'),
        'endpoint' => 'https://api.openai.com/v1/chat/completions',
    ],

    'grok' => [
        'key'      => env('GROK_API_KEY', ''),
        'model'    => env('GROK_MODEL', 'grok-2'),
        'endpoint' => 'https://api.x.ai/v1/chat/completions', // Compatível com OpenAI SDK
    ],

    'pagespeed' => [
        'key'      => env('PAGESPEED_API_KEY', ''),
        'endpoint' => 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed',
    ],

    'brasil_api' => [
        'cnpj_endpoint' => 'https://brasilapi.com.br/api/cnpj/v1/',
    ],

    'google_places' => [
        'key'      => env('GOOGLE_PLACES_API_KEY', ''),
        'endpoint' => 'https://maps.googleapis.com/maps/api/place/',
    ],

    // ── Embeddings (RAG) ─────────────────────────────────────────
    // Selecione o provider: 'gemini' (padrão, free) ou 'openai' (pago)
    // Gemini usa a mesma GEMINI_API_KEY do LLM — sem chave extra.
    'embedding' => [
        'provider'         => env('EMBEDDING_PROVIDER', 'gemini'),
        'gemini_model'     => 'text-embedding-004',          // 768 dims, free
        'openai_model'     => 'text-embedding-3-small',      // 1536 dims, pago
        'gemini_endpoint'  => 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent',
        'openai_endpoint'  => 'https://api.openai.com/v1/embeddings',
    ],

    // WhatsApp Evolution API
    'evolution' => [
        'base_url'   => env('EVOLUTION_BASE_URL', 'https://evolution-api-tjz9.srv1483958.hstgr.cloud'),
        'api_key'    => env('EVOLUTION_API_KEY', 'd27XLzQwOrjicQKQCVWQoipRoWKDnhpF'),
        'manager_url'=> 'https://evolution-api-tjz9.srv1483958.hstgr.cloud/manager',
    ],
];
