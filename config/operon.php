<?php

return [
    // ── Token Economy ──────────────────────────────────────────────
    'token_limits' => [
        'starter' => (int) env('TOKEN_TIER_STARTER', 100),
        'pro'     => (int) env('TOKEN_TIER_PRO', 500),
        'elite'   => (int) env('TOKEN_TIER_ELITE', 2000),
    ],

    'token_timezone' => env('TOKEN_TIMEZONE', 'America/Sao_Paulo'),

    // Pesos: cada operação consome input + output tokens
    'token_weights' => [
        'lead_analysis'                   => ['input' => 2,  'output' => 5,  'total' => 7],
        'deep_analysis'                   => ['input' => 5,  'output' => 15, 'total' => 20],
        'deal_insights'                   => ['input' => 1,  'output' => 4,  'total' => 5],
        'script_variations'               => ['input' => 1,  'output' => 6,  'total' => 7],
        'operon_diagnostico'              => ['input' => 3,  'output' => 8,  'total' => 11],
        'operon_potencial'                => ['input' => 3,  'output' => 8,  'total' => 11],
        'operon_autoridade'               => ['input' => 3,  'output' => 8,  'total' => 11],
        'operon_script'                   => ['input' => 3,  'output' => 8,  'total' => 11],
        'audio_strategy'                  => ['input' => 2,  'output' => 10, 'total' => 12],
        'spin_questions'                  => ['input' => 1,  'output' => 3,  'total' => 4],
        'copilot_message'                 => ['input' => 1,  'output' => 2,  'total' => 3],
        'social_analysis'                 => ['input' => 2,  'output' => 6,  'total' => 8],
        'hunter'                          => ['input' => 5,  'output' => 10, 'total' => 15],
        'lead_offerings_analysis'         => ['input' => 1,  'output' => 2,  'total' => 3],
        'lead_clients_analysis'           => ['input' => 1,  'output' => 2,  'total' => 3],
        'lead_competitors_analysis'       => ['input' => 1,  'output' => 2,  'total' => 3],
        'lead_social_analysis'            => ['input' => 2,  'output' => 3,  'total' => 5],
        'lead_social_discovery'           => ['input' => 1,  'output' => 2,  'total' => 3],
        'lead_sales_potential_analysis'   => ['input' => 1,  'output' => 2,  'total' => 3],
        // RAG / Knowledge operations
        'knowledge_index'                 => ['input' => 0,  'output' => 0,  'total' => 2],  // por chunk indexado
        'embedding_query'                 => ['input' => 0,  'output' => 0,  'total' => 1],  // por query de retrieval
        // WhatsApp Intelligence Hub
        'wa_summary'                      => ['input' => 2,  'output' => 5,  'total' => 7],
        'wa_next_message'                 => ['input' => 1,  'output' => 4,  'total' => 5],
        'wa_strategic'                    => ['input' => 4,  'output' => 8,  'total' => 12],
        'wa_interest_score'               => ['input' => 3,  'output' => 5,  'total' => 8],
        // Extension cockpit
        'extension_page_analysis'         => ['input' => 2,  'output' => 6,  'total' => 8],
        'extension_qualification'         => ['input' => 2,  'output' => 4,  'total' => 6],
        'extension_visual_analysis'       => ['input' => 3,  'output' => 6,  'total' => 9],
        'extension_copilot'               => ['input' => 1,  'output' => 3,  'total' => 4],
        'default'                         => ['input' => 1,  'output' => 2,  'total' => 3],
    ],

    // ── Score Algorithm ────────────────────────────────────────────
    'stage_bonuses' => [
        'NEW'       => 0,
        'ANALYZED'  => 5,
        'CONTACTED' => 15,
        'PROPOSAL'  => 25,
        'CLOSED'    => 40,
        'LOST'      => -10,
    ],

    'quick_adjustments' => [
        'client_showed_interest' => ['label' => 'Demonstrou interesse',  'delta' => +10],
        'responded'              => ['label' => 'Respondeu',              'delta' => +8],
        'has_budget'             => ['label' => 'Tem orçamento',          'delta' => +15],
        'requested_proposal'     => ['label' => 'Pediu proposta',         'delta' => +12],
        'no_interest'            => ['label' => 'Sem interesse',          'delta' => -20],
        'no_response'            => ['label' => 'Sem resposta',           'delta' => -5],
        'competitor_chosen'      => ['label' => 'Escolheu concorrente',   'delta' => -15],
    ],

    // ── AI Pricing (USD por 1M tokens) ─────────────────────────────
    'ai_pricing' => [
        'gemini-2.0-flash'       => ['input_per_mtok' => 0.10,  'output_per_mtok' => 0.40],
        'gemini-2.0-flash-lite'  => ['input_per_mtok' => 0.075, 'output_per_mtok' => 0.30],
        'gemini-1.5-pro'         => ['input_per_mtok' => 1.25,  'output_per_mtok' => 5.00],
        'gemini-1.5-flash'       => ['input_per_mtok' => 0.075, 'output_per_mtok' => 0.30],
        'gpt-4o'                 => ['input_per_mtok' => 2.50,  'output_per_mtok' => 10.00],
        'gpt-4o-mini'            => ['input_per_mtok' => 0.15,  'output_per_mtok' => 0.60],
        'grok-2'                 => ['input_per_mtok' => 2.00,  'output_per_mtok' => 10.00],
        'grok-3-mini'            => ['input_per_mtok' => 0.30,  'output_per_mtok' => 0.50],
    ],

    // ── AI Safety ─────────────────────────────────────────────────
    'rate_limit_seconds' => 5, // Segundos entre chamadas da mesma operação/tenant

    // O que o usuário vê — NUNCA o modelo real
    'public_model_badge' => 'Operon Intelligence',

    // ── Funil de Vendas ───────────────────────────────────────────
    'pipeline_stages' => ['NEW', 'ANALYZED', 'CONTACTED', 'PROPOSAL', 'CLOSED', 'LOST'],

    'kanban_columns' => [
        'NEW'       => ['label' => 'PROSPECÇÃO',   'color' => 'slate'],
        'ANALYZED'  => ['label' => 'QUALIFICAÇÃO', 'color' => 'teal'],
        'CONTACTED' => ['label' => 'CONTATO',      'color' => 'blue'],
        'PROPOSAL'  => ['label' => 'PROPOSTA',     'color' => 'amber'],
        'CLOSED'    => ['label' => 'FECHADO',      'color' => 'emerald'],
        'LOST'      => ['label' => 'PERDIDO',      'color' => 'red'],
    ],
];
