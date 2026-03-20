-- Meridian: Análise Estratégica de Nichos & ICP
-- Histórico de análises para consulta e comparação

CREATE TABLE IF NOT EXISTS meridian_analyses (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    user_id TEXT,
    niche TEXT NOT NULL,
    sub_niche TEXT,
    analysis_data TEXT,          -- JSON com a análise completa
    adherence_score INTEGER,     -- 0-100 aderência ao perfil da empresa
    potential_score INTEGER,     -- 0-100 potencial do nicho
    status TEXT DEFAULT 'completed',
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_meridian_tenant ON meridian_analyses(tenant_id);
CREATE INDEX IF NOT EXISTS idx_meridian_niche ON meridian_analyses(tenant_id, niche);
