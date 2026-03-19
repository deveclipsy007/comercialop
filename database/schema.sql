-- ============================================================
-- Operon Intelligence Platform — Database Schema
-- Compatible: SQLite (dev) + PostgreSQL (prod)
-- ============================================================

PRAGMA foreign_keys = ON;

-- ─── Tenants (Agencies) ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenants (
    id          TEXT PRIMARY KEY,
    name        TEXT NOT NULL,
    slug        TEXT UNIQUE NOT NULL,
    plan        TEXT NOT NULL DEFAULT 'starter', -- starter | pro | elite
    settings    TEXT NOT NULL DEFAULT '{}',       -- JSON: agency context, colors, etc.
    active      INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── Users ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    email       TEXT NOT NULL,
    password    TEXT NOT NULL,
    role        TEXT NOT NULL DEFAULT 'agent', -- admin | agent | viewer
    active      INTEGER NOT NULL DEFAULT 1,
    wl_color    TEXT DEFAULT '#a3e635',
    wl_logo     TEXT,
    wl_features TEXT,
    wl_allow_setup INTEGER DEFAULT 0,
    preferences TEXT DEFAULT '{}',
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(tenant_id, email)
);

-- ─── Leads ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leads (
    id                   TEXT PRIMARY KEY,
    tenant_id            TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name                 TEXT NOT NULL,
    segment              TEXT NOT NULL,
    website              TEXT,
    phone                TEXT,
    email                TEXT,
    address              TEXT,
    pipeline_status      TEXT NOT NULL DEFAULT 'new', -- new | contacted | qualified | proposal | closed_won | closed_lost
    priority_score       INTEGER NOT NULL DEFAULT 0,
    fit_score            INTEGER NOT NULL DEFAULT 0,
    manual_score_override INTEGER,
    analysis             TEXT,    -- JSON: AI analysis result
    pagespeed_data       TEXT,    -- JSON: PageSpeed data
    cnpj_data            TEXT,    -- JSON: CNPJ enrichment
    social_presence      TEXT,    -- JSON: {linkedin, instagram, facebook}
    human_context        TEXT,    -- JSON: {temperature, timingStatus, objectionCategory, notes}
    tags                 TEXT,    -- JSON array
    google_maps_url      TEXT,    -- URL do Google Maps do local
    rating               REAL,    -- Nota/avaliação média (1.0-5.0)
    review_count         INTEGER, -- Quantidade de avaliações
    reviews              TEXT,    -- JSON array de reviews/testemunhos
    opening_hours        TEXT,    -- Horário de abertura
    closing_hours        TEXT,    -- Horário de fechamento
    category             TEXT,    -- Categoria do negócio (ex: Restaurante, Salão)
    enrichment_data      TEXT,    -- JSON catch-all para campos extras futuros
    assigned_to          TEXT REFERENCES users(id),
    next_followup_at     TEXT,
    created_at           TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at           TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── Lead Activities (Timeline) ──────────────────────────────
CREATE TABLE IF NOT EXISTS lead_activities (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    lead_id     TEXT NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    user_id     TEXT REFERENCES users(id),
    type        TEXT NOT NULL, -- note | call | email | whatsapp | stage_change | ai_analysis
    title       TEXT NOT NULL,
    content     TEXT,
    metadata    TEXT, -- JSON extra data
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── Token Quotas ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS token_quotas (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL UNIQUE REFERENCES tenants(id) ON DELETE CASCADE,
    tokens_used INTEGER NOT NULL DEFAULT 0,
    tokens_limit INTEGER NOT NULL DEFAULT 100,
    tier        TEXT NOT NULL DEFAULT 'starter',
    reset_at    TEXT NOT NULL DEFAULT (datetime('now', '+1 day')),
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── Token Logs ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS token_logs (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    operation   TEXT NOT NULL,
    tokens_used INTEGER NOT NULL,
    lead_id     TEXT REFERENCES leads(id),
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── Agency Settings (extended context for AI) ───────────────
CREATE TABLE IF NOT EXISTS agency_settings (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL UNIQUE REFERENCES tenants(id) ON DELETE CASCADE,
    agency_name     TEXT,
    agency_city     TEXT,
    agency_niche    TEXT,
    offer_summary   TEXT,
    differentials   TEXT, -- JSON array
    services        TEXT, -- JSON array of {name, price}
    icp_profile     TEXT,
    cases           TEXT, -- JSON array
    custom_context  TEXT,
    updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── Scheduled Followups ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS followups (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    lead_id     TEXT NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    user_id     TEXT REFERENCES users(id),
    title       TEXT NOT NULL,
    description TEXT,
    scheduled_at TEXT NOT NULL,
    completed   INTEGER NOT NULL DEFAULT 0,
    completed_at TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── Indexes ─────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_leads_tenant ON leads(tenant_id);
CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(tenant_id, pipeline_status);
CREATE INDEX IF NOT EXISTS idx_leads_score  ON leads(tenant_id, priority_score DESC);
CREATE INDEX IF NOT EXISTS idx_activities_lead ON lead_activities(lead_id);
CREATE INDEX IF NOT EXISTS idx_token_logs_tenant ON token_logs(tenant_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_followups_tenant ON followups(tenant_id, scheduled_at);

-- ─── Agenda Events (Compromissos e Lembretes gerais) ─────────
CREATE TABLE IF NOT EXISTS agenda_events (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id     TEXT NOT NULL REFERENCES users(id),
    title       TEXT NOT NULL,
    description TEXT,
    event_type  TEXT NOT NULL DEFAULT 'reminder', -- reminder | appointment
    start_time  TEXT NOT NULL,
    end_time    TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_agenda_events_tenant ON agenda_events(tenant_id, start_time);

-- ─── Custom Fields (011) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS custom_fields (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    field_name  TEXT NOT NULL,
    field_label TEXT NOT NULL,
    field_type  TEXT NOT NULL DEFAULT 'text',
    options     TEXT,
    required    INTEGER NOT NULL DEFAULT 0,
    active      INTEGER NOT NULL DEFAULT 1,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(tenant_id, field_name)
);
CREATE INDEX IF NOT EXISTS idx_custom_fields_tenant ON custom_fields(tenant_id, active, sort_order);

CREATE TABLE IF NOT EXISTS custom_field_values (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    lead_id         TEXT NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    custom_field_id TEXT NOT NULL REFERENCES custom_fields(id) ON DELETE CASCADE,
    value           TEXT,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(lead_id, custom_field_id)
);
CREATE INDEX IF NOT EXISTS idx_custom_field_values_lead ON custom_field_values(lead_id);
