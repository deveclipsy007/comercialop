-- ============================================================
-- Migration 005: Hunter Protocol Module
-- ============================================================

-- Tabela 1: Histórico/Intent de Buscas
CREATE TABLE IF NOT EXISTS hunter_searches (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    term        TEXT,
    segment     TEXT,
    location    TEXT,
    filters     TEXT, -- JSON
    status      TEXT NOT NULL DEFAULT 'processing', -- pending | processing | finished | failed
    message     TEXT, -- Store failure messages here
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_hunter_searches_tenant ON hunter_searches(tenant_id, created_at DESC);

-- Tabela 2: Resultados brutos enriquecidos
CREATE TABLE IF NOT EXISTS hunter_results (
    id                TEXT PRIMARY KEY,
    tenant_id         TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    search_id         TEXT REFERENCES hunter_searches(id) ON DELETE SET NULL,
    name              TEXT NOT NULL,
    segment           TEXT,
    address           TEXT,
    city              TEXT,
    phone             TEXT,
    website           TEXT,
    email             TEXT,
    instagram         TEXT,
    google_rating     REAL,
    google_reviews    INTEGER,
    data_source       TEXT, -- e.g., 'google_places', 'gemini_search'
    is_saved          INTEGER NOT NULL DEFAULT 0,
    is_imported       INTEGER NOT NULL DEFAULT 0,
    imported_lead_id  TEXT REFERENCES leads(id) ON DELETE SET NULL,
    raw_source_data   TEXT, -- JSON: payload original do provider
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_hunter_results_tenant ON hunter_results(tenant_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_hunter_results_search ON hunter_results(search_id);

-- Tabela 3: Análise de IA gerada para um resultado
CREATE TABLE IF NOT EXISTS hunter_result_analysis (
    id                 TEXT PRIMARY KEY,
    tenant_id          TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    hunter_result_id   TEXT NOT NULL UNIQUE REFERENCES hunter_results(id) ON DELETE CASCADE,
    executive_summary  TEXT,
    pain_points        TEXT, -- JSON Array
    opportunities      TEXT, -- JSON Array
    recommended_approach TEXT,
    icp_match_score    INTEGER NOT NULL DEFAULT 0,
    priority_score     INTEGER NOT NULL DEFAULT 0,
    priority_level     TEXT NOT NULL DEFAULT 'cold', -- hot | warm | cold
    metadata           TEXT, -- JSON extra config
    created_at         TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at         TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Tabela 4: Presets de Busca
CREATE TABLE IF NOT EXISTS hunter_presets (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    filters     TEXT NOT NULL, -- JSON
    is_default  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Tabela 5: Listas customizadas do Hunter
CREATE TABLE IF NOT EXISTS hunter_lists (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    color       TEXT NOT NULL DEFAULT '#A1A1AA',
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Tabela 6: Itens nas Listas
CREATE TABLE IF NOT EXISTS hunter_list_items (
    id               TEXT PRIMARY KEY,
    tenant_id        TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    list_id          TEXT NOT NULL REFERENCES hunter_lists(id) ON DELETE CASCADE,
    hunter_result_id TEXT NOT NULL REFERENCES hunter_results(id) ON DELETE CASCADE,
    created_at       TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(list_id, hunter_result_id)
);
CREATE INDEX IF NOT EXISTS idx_hunter_list_items_tenant ON hunter_list_items(tenant_id);
