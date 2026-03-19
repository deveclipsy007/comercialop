-- ============================================================
-- Operon Intelligence Platform — WhatsApp Module Tables
-- ============================================================

-- ─── WhatsApp Integrations (Settings per Tenant) ─────────────
CREATE TABLE IF NOT EXISTS whatsapp_integrations (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL UNIQUE REFERENCES tenants(id) ON DELETE CASCADE,
    instance_name   TEXT NOT NULL,
    base_url        TEXT NOT NULL,
    api_key         TEXT NOT NULL,
    webhook_secret  TEXT,
    status          TEXT DEFAULT 'disconnected', -- disconnected | connecting | connected | error
    active          INTEGER DEFAULT 1,
    last_sync_at    TEXT,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── WhatsApp Conversations ─────────────────────────────────
CREATE TABLE IF NOT EXISTS whatsapp_conversations (
    id                   TEXT PRIMARY KEY,
    tenant_id            TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    integration_id       TEXT NOT NULL REFERENCES whatsapp_integrations(id) ON DELETE CASCADE,
    remote_jid           TEXT NOT NULL,
    display_name         TEXT,
    phone                TEXT,
    last_message_preview TEXT,
    last_message_at      TEXT,
    unread_count         INTEGER DEFAULT 0,
    active               INTEGER DEFAULT 1,
    created_at           TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at           TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(tenant_id, remote_jid)
);

-- ─── WhatsApp Messages ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id              TEXT PRIMARY KEY,
    conversation_id TEXT NOT NULL REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    remote_id       TEXT NOT NULL, -- message ID from Evolution API
    direction       TEXT NOT NULL, -- incoming | outgoing
    body            TEXT,
    message_type    TEXT DEFAULT 'text', -- text | image | audio | file
    timestamp       INTEGER NOT NULL,
    status          TEXT DEFAULT 'received', -- pending | received | read
    media_url       TEXT,
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(conversation_id, remote_id)
);

-- ─── WhatsApp Lead Links (Bridge table) ─────────────────────
CREATE TABLE IF NOT EXISTS whatsapp_lead_links (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    conversation_id TEXT NOT NULL REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
    lead_id         TEXT NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    link_type       TEXT DEFAULT 'manual', -- manual | auto
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(tenant_id, conversation_id),
    UNIQUE(tenant_id, lead_id)
);

-- ─── WhatsApp Integration Logs ──────────────────────────────
CREATE TABLE IF NOT EXISTS whatsapp_integration_logs (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    integration_id  TEXT REFERENCES whatsapp_integrations(id) ON DELETE SET NULL,
    operation       TEXT NOT NULL, -- instance_create | qr_fetch | webhook | sync
    direction       TEXT NOT NULL, -- inbound | outbound
    status          TEXT NOT NULL, -- success | error
    payload         TEXT,          -- JSON
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── WhatsApp Conversation Analyses (AI Results) ───────────
CREATE TABLE IF NOT EXISTS whatsapp_conversation_analyses (
    id              TEXT PRIMARY KEY,
    conversation_id TEXT NOT NULL REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
    analysis        TEXT NOT NULL, -- JSON results from IA
    version         INTEGER DEFAULT 1,
    tokens_used     INTEGER DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── Indexes ─────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_wa_conv_tenant ON whatsapp_conversations(tenant_id);
CREATE INDEX IF NOT EXISTS idx_wa_msg_conv ON whatsapp_messages(conversation_id, timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_wa_logs_tenant ON whatsapp_integration_logs(tenant_id, created_at DESC);
