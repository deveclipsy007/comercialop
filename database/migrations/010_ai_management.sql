-- Migration 010: AI Management — Chaves encriptadas, configs de provedor, token_logs expandido
-- Compatível com SQLite (table rename pattern para ALTER TABLE limitações)

-- 1. Tabela de chaves API encriptadas (AES-256-CBC)
CREATE TABLE IF NOT EXISTS ai_api_keys (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT REFERENCES tenants(id) ON DELETE CASCADE,
    provider        TEXT NOT NULL,
    encrypted_key   TEXT NOT NULL,
    label           TEXT,
    is_active       INTEGER NOT NULL DEFAULT 1,
    last_used_at    TEXT,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(tenant_id, provider)
);

-- 2. Configuração de provedor/modelo por operação
CREATE TABLE IF NOT EXISTS ai_provider_configs (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT REFERENCES tenants(id) ON DELETE CASCADE,
    operation       TEXT NOT NULL,
    provider        TEXT NOT NULL,
    model           TEXT NOT NULL,
    is_active       INTEGER NOT NULL DEFAULT 1,
    priority        INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

-- 3. Rebuild token_logs com colunas adicionais (SQLite safe: rename + recreate)
ALTER TABLE token_logs RENAME TO _token_logs_old_010;

CREATE TABLE token_logs (
    id                  TEXT PRIMARY KEY,
    tenant_id           TEXT NOT NULL,
    user_id             TEXT,
    operation           TEXT NOT NULL,
    tokens_used         INTEGER NOT NULL,
    provider            TEXT,
    model               TEXT,
    real_tokens_input   INTEGER DEFAULT 0,
    real_tokens_output  INTEGER DEFAULT 0,
    estimated_cost_usd  REAL DEFAULT 0.0,
    lead_id             TEXT,
    created_at          TEXT DEFAULT (datetime('now'))
);

INSERT INTO token_logs (id, tenant_id, operation, tokens_used, lead_id, created_at)
    SELECT id, tenant_id, operation, tokens_used, lead_id, created_at FROM _token_logs_old_010;

DROP TABLE IF EXISTS _token_logs_old_010;

-- 4. Índices
CREATE INDEX IF NOT EXISTS idx_token_logs_user ON token_logs(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_token_logs_tenant_date ON token_logs(tenant_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_token_logs_operation ON token_logs(operation);
CREATE INDEX IF NOT EXISTS idx_ai_keys_tenant ON ai_api_keys(tenant_id, provider);
CREATE INDEX IF NOT EXISTS idx_provider_configs_tenant ON ai_provider_configs(tenant_id, operation);
