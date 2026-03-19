-- ============================================================
-- Operon Intelligence Platform — WhatsApp Intelligence Hub
-- Migration 009: 1:N Lead Links + Analysis Types + Interest Score
-- ============================================================
-- NOTE: ALTER TABLE ADD COLUMN statements are handled in PHP
-- (WhatsAppController::ensureIntelligenceMigration) because
-- SQLite does not support ADD COLUMN IF NOT EXISTS.
-- This file handles only the table rebuild for 1:N lead links.
-- ============================================================

-- ─── Rebuild whatsapp_lead_links for 1:N (conv → N leads) ─────────

-- Rename old table (safe: IF EXISTS not needed, we check in PHP)
ALTER TABLE whatsapp_lead_links RENAME TO _whatsapp_lead_links_old;

-- Create new table: UNIQUE on (tenant, conversation, lead) allows N leads per conversation
CREATE TABLE whatsapp_lead_links (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    conversation_id TEXT NOT NULL REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
    lead_id         TEXT NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    link_type       TEXT DEFAULT 'manual',
    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(tenant_id, conversation_id, lead_id)
);

-- Migrate existing data
INSERT OR IGNORE INTO whatsapp_lead_links (id, tenant_id, conversation_id, lead_id, link_type, created_at)
    SELECT id, tenant_id, conversation_id, lead_id, link_type, created_at
    FROM _whatsapp_lead_links_old;

-- Drop old table
DROP TABLE IF EXISTS _whatsapp_lead_links_old;

-- Indexes
CREATE INDEX IF NOT EXISTS idx_wa_links_conv ON whatsapp_lead_links(tenant_id, conversation_id);
CREATE INDEX IF NOT EXISTS idx_wa_links_lead ON whatsapp_lead_links(tenant_id, lead_id);
