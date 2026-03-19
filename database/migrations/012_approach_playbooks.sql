-- ============================================================
-- Migration 012: Approach Playbooks & Script History
-- Stores uploaded reference documents (books, playbooks, frameworks)
-- and persists generated scripts for refinement history
-- ============================================================

-- Playbooks: uploaded documents that teach approach style
CREATE TABLE IF NOT EXISTS approach_playbooks (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    title       TEXT NOT NULL,
    description TEXT DEFAULT '',
    file_name   TEXT NOT NULL,
    file_type   TEXT NOT NULL DEFAULT 'txt',      -- txt, md, pdf, docx
    content     TEXT NOT NULL DEFAULT '',          -- extracted text content
    chunks      TEXT NOT NULL DEFAULT '[]',        -- JSON array of text chunks for context injection
    status      TEXT NOT NULL DEFAULT 'processing', -- processing | ready | error
    active      INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_playbooks_tenant ON approach_playbooks(tenant_id, active);

-- Script history: persists generated scripts for refinement
CREATE TABLE IF NOT EXISTS approach_scripts (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    lead_id     TEXT REFERENCES leads(id) ON DELETE CASCADE,
    channel     TEXT NOT NULL DEFAULT 'whatsapp',  -- whatsapp, linkedin, email, coldCall
    tone        TEXT NOT NULL DEFAULT 'consultivo', -- consultivo, direto, elegante, humano, autoridade, etc.
    script      TEXT NOT NULL DEFAULT '',
    context     TEXT NOT NULL DEFAULT '{}',         -- JSON: lead snapshot, playbook_ids used, tone, custom instructions
    version     INTEGER NOT NULL DEFAULT 1,
    parent_id   TEXT DEFAULT NULL,                  -- previous version (refinement chain)
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_scripts_lead ON approach_scripts(tenant_id, lead_id, channel);
