-- ============================================================
-- Migration 016: Playbook de Vendas
-- Módulos, blocos de conteúdo e progresso de leitura
-- ============================================================

-- ─── Módulos do Playbook ────────────────────────────────────
CREATE TABLE IF NOT EXISTS playbook_modules (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    title       TEXT NOT NULL,
    description TEXT DEFAULT '',
    icon        TEXT DEFAULT 'menu_book',
    color       TEXT DEFAULT '#E1FB15',
    sort_order  INTEGER NOT NULL DEFAULT 0,
    is_published INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_playbook_modules_tenant ON playbook_modules(tenant_id);

-- ─── Blocos de Conteúdo do Playbook ─────────────────────────
-- Tipos: text, video, document, checklist, script, tip
CREATE TABLE IF NOT EXISTS playbook_blocks (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    module_id   TEXT NOT NULL REFERENCES playbook_modules(id) ON DELETE CASCADE,
    type        TEXT NOT NULL DEFAULT 'text',  -- text | video | document | checklist | script | tip
    title       TEXT NOT NULL DEFAULT '',
    content     TEXT NOT NULL DEFAULT '',       -- HTML/Markdown para text, URL para video/document, JSON para checklist
    metadata    TEXT DEFAULT '{}',              -- JSON: extra config por tipo
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_playbook_blocks_module ON playbook_blocks(module_id);
CREATE INDEX IF NOT EXISTS idx_playbook_blocks_tenant ON playbook_blocks(tenant_id);

-- ─── Progresso de Leitura por Usuário ───────────────────────
CREATE TABLE IF NOT EXISTS playbook_progress (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    user_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    module_id   TEXT NOT NULL REFERENCES playbook_modules(id) ON DELETE CASCADE,
    block_id    TEXT REFERENCES playbook_blocks(id) ON DELETE CASCADE,
    completed   INTEGER NOT NULL DEFAULT 0,
    completed_at TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(user_id, block_id)
);

CREATE INDEX IF NOT EXISTS idx_playbook_progress_user ON playbook_progress(user_id, tenant_id);
