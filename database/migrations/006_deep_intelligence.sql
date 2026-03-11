-- Migration for Deep Intelligence Runs
CREATE TABLE IF NOT EXISTS lead_deep_intelligence_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id TEXT NOT NULL,
    lead_id INTEGER NOT NULL,
    intelligence_type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    token_usage INTEGER DEFAULT 0,
    result_data TEXT,
    context_used TEXT,
    error_message TEXT,
    requested_by INTEGER,
    created_at DATETIME,
    updated_at DATETIME,
    completed_at DATETIME,
    FOREIGN KEY(lead_id) REFERENCES leads(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_deep_runs_tenant_lead ON lead_deep_intelligence_runs(tenant_id, lead_id);
CREATE INDEX IF NOT EXISTS idx_deep_runs_type ON lead_deep_intelligence_runs(intelligence_type);
