-- Migration 011: User preferences + Custom Fields per tenant
-- Run after 010_ai_management.sql

-- User-level preferences (notification settings, timezone override, etc.)
ALTER TABLE users ADD COLUMN preferences TEXT DEFAULT '{}';

-- Custom fields defined per tenant (show on lead forms)
CREATE TABLE IF NOT EXISTS custom_fields (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    field_name  TEXT NOT NULL,
    field_label TEXT NOT NULL,
    field_type  TEXT NOT NULL DEFAULT 'text', -- text | number | select | date | boolean
    options     TEXT,                          -- JSON array for select type
    required    INTEGER NOT NULL DEFAULT 0,
    active      INTEGER NOT NULL DEFAULT 1,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(tenant_id, field_name)
);

CREATE INDEX IF NOT EXISTS idx_custom_fields_tenant ON custom_fields(tenant_id, active, sort_order);

-- Custom field values stored per lead
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
