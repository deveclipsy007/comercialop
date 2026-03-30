-- Migration 018: Email Module — Accounts, Campaigns, Templates, Sequences, Logs
-- Run: sqlite3 storage/database.sqlite < database/migrations/018_email.sql

-- ── Email Accounts (SMTP connections per user) ──────────────────
CREATE TABLE IF NOT EXISTS email_accounts (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id         TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email_address   TEXT NOT NULL,
    display_name    TEXT DEFAULT '',
    smtp_host       TEXT NOT NULL DEFAULT '',
    smtp_port       INTEGER NOT NULL DEFAULT 587,
    smtp_encryption TEXT NOT NULL DEFAULT 'tls',      -- tls | ssl | none
    smtp_username   TEXT NOT NULL DEFAULT '',
    smtp_password   TEXT NOT NULL DEFAULT '',          -- encrypted at app layer
    imap_host       TEXT DEFAULT '',
    imap_port       INTEGER DEFAULT 993,
    daily_limit     INTEGER NOT NULL DEFAULT 50,       -- safety: max per day
    hourly_limit    INTEGER NOT NULL DEFAULT 15,       -- safety: max per hour
    delay_seconds   INTEGER NOT NULL DEFAULT 30,       -- delay between sends
    warmup_enabled  INTEGER NOT NULL DEFAULT 1,        -- 1 = active warmup
    warmup_day      INTEGER NOT NULL DEFAULT 0,        -- current warmup day
    is_verified     INTEGER NOT NULL DEFAULT 0,
    is_active       INTEGER NOT NULL DEFAULT 1,
    sent_today      INTEGER NOT NULL DEFAULT 0,
    sent_this_hour  INTEGER NOT NULL DEFAULT 0,
    last_sent_at    TEXT,
    last_reset_date TEXT,
    reputation_score REAL DEFAULT 100.0,               -- 0-100 health score
    settings        TEXT DEFAULT '{}',                  -- JSON extra config
    created_at      TEXT DEFAULT (datetime('now')),
    updated_at      TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_email_accounts_tenant ON email_accounts(tenant_id);
CREATE INDEX IF NOT EXISTS idx_email_accounts_user ON email_accounts(user_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_email_accounts_unique ON email_accounts(tenant_id, user_id, email_address);

-- ── Email Templates (reusable email bodies) ─────────────────────
CREATE TABLE IF NOT EXISTS email_templates (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    created_by      TEXT REFERENCES users(id),
    name            TEXT NOT NULL DEFAULT 'Novo Template',
    subject         TEXT NOT NULL DEFAULT '',
    body            TEXT NOT NULL DEFAULT '',
    category        TEXT DEFAULT 'custom',              -- prospecting, follow_up, proposal, custom
    variables       TEXT DEFAULT '[]',                   -- JSON: ["nome","empresa",...]
    is_shared       INTEGER NOT NULL DEFAULT 1,          -- visible to team
    use_count       INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT DEFAULT (datetime('now')),
    updated_at      TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_email_templates_tenant ON email_templates(tenant_id);

-- ── Email Campaigns ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS email_campaigns (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    created_by      TEXT REFERENCES users(id),
    account_id      TEXT REFERENCES email_accounts(id),
    name            TEXT NOT NULL DEFAULT 'Nova Campanha',
    description     TEXT DEFAULT '',
    status          TEXT NOT NULL DEFAULT 'draft',       -- draft, active, paused, completed, cancelled
    campaign_type   TEXT NOT NULL DEFAULT 'one_time',    -- one_time, sequence
    target_filter   TEXT DEFAULT '{}',                   -- JSON: filter criteria for leads
    lead_ids        TEXT DEFAULT '[]',                   -- JSON: explicit lead IDs
    total_leads     INTEGER NOT NULL DEFAULT 0,
    sent_count      INTEGER NOT NULL DEFAULT 0,
    opened_count    INTEGER NOT NULL DEFAULT 0,
    clicked_count   INTEGER NOT NULL DEFAULT 0,
    replied_count   INTEGER NOT NULL DEFAULT 0,
    bounced_count   INTEGER NOT NULL DEFAULT 0,
    scheduled_at    TEXT,
    started_at      TEXT,
    completed_at    TEXT,
    settings        TEXT DEFAULT '{}',                   -- JSON: timezone, send_window, etc
    created_at      TEXT DEFAULT (datetime('now')),
    updated_at      TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_email_campaigns_tenant ON email_campaigns(tenant_id);
CREATE INDEX IF NOT EXISTS idx_email_campaigns_status ON email_campaigns(tenant_id, status);

-- ── Campaign Steps (sequence steps) ─────────────────────────────
CREATE TABLE IF NOT EXISTS email_campaign_steps (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    campaign_id     TEXT NOT NULL REFERENCES email_campaigns(id) ON DELETE CASCADE,
    template_id     TEXT REFERENCES email_templates(id),
    step_order      INTEGER NOT NULL DEFAULT 1,
    subject         TEXT DEFAULT '',
    body            TEXT DEFAULT '',
    delay_days      INTEGER NOT NULL DEFAULT 0,          -- days after previous step
    delay_hours     INTEGER NOT NULL DEFAULT 0,
    condition_type  TEXT DEFAULT 'always',                -- always, not_opened, not_replied, not_clicked
    is_active       INTEGER NOT NULL DEFAULT 1,
    sent_count      INTEGER NOT NULL DEFAULT 0,
    opened_count    INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT DEFAULT (datetime('now')),
    updated_at      TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_campaign_steps_campaign ON email_campaign_steps(campaign_id);

-- ── Email Log (every email sent) ────────────────────────────────
CREATE TABLE IF NOT EXISTS email_log (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    account_id      TEXT REFERENCES email_accounts(id),
    campaign_id     TEXT REFERENCES email_campaigns(id),
    step_id         TEXT REFERENCES email_campaign_steps(id),
    lead_id         TEXT REFERENCES leads(id),
    user_id         TEXT REFERENCES users(id),
    to_email        TEXT NOT NULL,
    to_name         TEXT DEFAULT '',
    from_email      TEXT NOT NULL,
    subject         TEXT NOT NULL DEFAULT '',
    body            TEXT NOT NULL DEFAULT '',
    status          TEXT NOT NULL DEFAULT 'queued',      -- queued, sending, sent, failed, bounced
    error_message   TEXT,
    message_id      TEXT,                                 -- SMTP message-id for tracking
    tracking_token  TEXT UNIQUE,                          -- token for open/click tracking
    opened_at       TEXT,
    clicked_at      TEXT,
    replied_at      TEXT,
    bounced_at      TEXT,
    scheduled_at    TEXT,
    sent_at         TEXT,
    created_at      TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_email_log_tenant ON email_log(tenant_id);
CREATE INDEX IF NOT EXISTS idx_email_log_campaign ON email_log(campaign_id);
CREATE INDEX IF NOT EXISTS idx_email_log_lead ON email_log(lead_id);
CREATE INDEX IF NOT EXISTS idx_email_log_status ON email_log(tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_email_log_tracking ON email_log(tracking_token);
