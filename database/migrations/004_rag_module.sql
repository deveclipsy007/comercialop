-- ============================================================
-- Migration 004: RAG Knowledge Module
-- Compatível: SQLite (dev) | PostgreSQL (prod, notas nos comentários)
--
-- Decisão: agency_settings NÃO é dropada.
-- Serve de fallback quando o tenant ainda não indexou conhecimento.
-- company_profiles convive com agency_settings; SmartContextService
-- usa RAG quando disponível e cai para agency_settings se não.
-- ============================================================

-- ─── Perfil de empresa enriquecido ───────────────────────────
-- Substitui em escopo (não em schema) a tabela agency_settings.
-- Possui versionamento e controle de status de indexação.
CREATE TABLE IF NOT EXISTS company_profiles (
    id                    TEXT PRIMARY KEY,
    tenant_id             TEXT NOT NULL UNIQUE REFERENCES tenants(id) ON DELETE CASCADE,

    -- Identidade
    agency_name           TEXT,
    agency_city           TEXT,
    agency_state          TEXT,
    agency_niche          TEXT,
    founding_year         TEXT,
    team_size             TEXT,           -- "1-5" | "6-20" | "21-50" | "50+"
    website_url           TEXT,

    -- Oferta
    offer_summary         TEXT,
    offer_price_range     TEXT,           -- "R$ 1.500 - R$ 8.000/mês"
    services              TEXT,           -- JSON: [{name, description, price_range}]
    guarantees            TEXT,           -- declaração de garantia em texto
    delivery_timeline     TEXT,           -- "Resultados em 30 dias"

    -- Posicionamento
    differentials         TEXT,           -- JSON: ["diferencial 1", "diferencial 2"]
    unique_value_prop     TEXT,           -- parágrafo de proposta de valor única
    awards_recognition    TEXT,           -- prêmios e reconhecimentos

    -- ICP — Perfil de Cliente Ideal
    icp_profile           TEXT,           -- descrição narrativa do ICP
    icp_segment           TEXT,           -- JSON: ["saúde","varejo","tech"]
    icp_company_size      TEXT,           -- "2-50 funcionários"
    icp_ticket_range      TEXT,           -- "R$ 3.000 - R$ 15.000/mês"
    icp_pain_points       TEXT,           -- JSON: ["dor 1", "dor 2"]

    -- Prova Social
    cases                 TEXT,           -- JSON: [{client, result, niche, timeframe}]
    testimonials          TEXT,           -- JSON: [{author, role, text}]
    portfolio_url         TEXT,

    -- Gestão Comercial
    objection_responses   TEXT,           -- JSON: [{objection, response}]
    competitors           TEXT,           -- JSON: [{name, weakness, how_to_win}]
    pricing_justification TEXT,           -- argumento para justificar o preço

    -- Contexto livre
    custom_context        TEXT,           -- campo livre para contexto adicional

    -- Controle de indexação RAG
    indexing_status       TEXT NOT NULL DEFAULT 'pending',
    -- pending = aguardando indexação
    -- processing = indexação em andamento
    -- indexed = indexado com sucesso
    -- error = falhou, ver indexing_error
    indexing_error        TEXT,
    profile_version       INTEGER NOT NULL DEFAULT 1,
    last_indexed_at       TEXT,
    chunks_count          INTEGER NOT NULL DEFAULT 0,

    created_at            TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at            TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ─── Documentos de conhecimento ──────────────────────────────
-- Cada seção do perfil da empresa vira um documento.
-- doc_type: identity | services | differentials | icp | cases |
--           objections | competitors | custom
CREATE TABLE IF NOT EXISTS knowledge_documents (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    profile_id      TEXT NOT NULL REFERENCES company_profiles(id) ON DELETE CASCADE,
    profile_version INTEGER NOT NULL DEFAULT 1,
    doc_type        TEXT NOT NULL,
    title           TEXT NOT NULL,
    content         TEXT NOT NULL,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_kdocs_tenant  ON knowledge_documents(tenant_id);
CREATE INDEX IF NOT EXISTS idx_kdocs_profile ON knowledge_documents(profile_id);
CREATE INDEX IF NOT EXISTS idx_kdocs_type    ON knowledge_documents(tenant_id, doc_type);

-- ─── Chunks de texto ─────────────────────────────────────────
-- Fragmentos de ~300 palavras com overlap de 50 palavras.
-- Cada chunk recebe um embedding individual.
CREATE TABLE IF NOT EXISTS knowledge_chunks (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    document_id     TEXT NOT NULL REFERENCES knowledge_documents(id) ON DELETE CASCADE,
    doc_type        TEXT NOT NULL,     -- copiado do doc para queries rápidas
    chunk_index     INTEGER NOT NULL DEFAULT 0,
    content         TEXT NOT NULL,
    word_count      INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_kchunks_tenant   ON knowledge_chunks(tenant_id);
CREATE INDEX IF NOT EXISTS idx_kchunks_document ON knowledge_chunks(document_id);
CREATE INDEX IF NOT EXISTS idx_kchunks_doctype  ON knowledge_chunks(tenant_id, doc_type);

-- ─── Embeddings vetoriais ─────────────────────────────────────
-- Um registro por chunk. Embedding armazenado como JSON TEXT.
-- SQLite: sem tipo vetor nativo → json_decode() no PHP.
-- PostgreSQL prod: troque TEXT por vector(768) e use operador <=>
--   para cosine similarity nativa via pgvector extension.
CREATE TABLE IF NOT EXISTS knowledge_embeddings (
    id          TEXT PRIMARY KEY,
    tenant_id   TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    chunk_id    TEXT NOT NULL UNIQUE REFERENCES knowledge_chunks(id) ON DELETE CASCADE,
    model       TEXT NOT NULL DEFAULT 'text-embedding-004',
    dimensions  INTEGER NOT NULL DEFAULT 768,
    embedding   TEXT NOT NULL,   -- JSON: [0.123, -0.456, ...]
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_kemb_tenant ON knowledge_embeddings(tenant_id);

-- ─── Rastreabilidade de análises ─────────────────────────────
-- Toda chamada de IA que usa contexto RAG gera um trace.
-- Permite auditoria: qual empresa, quais chunks, qual resultado.
CREATE TABLE IF NOT EXISTS analysis_traces (
    id              TEXT PRIMARY KEY,
    tenant_id       TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    lead_id         TEXT REFERENCES leads(id) ON DELETE SET NULL,
    operation       TEXT NOT NULL,
    -- Fonte do contexto: rag | legacy | default
    context_source  TEXT NOT NULL DEFAULT 'rag',
    query_text      TEXT,
    -- JSON: [{chunk_id, doc_type, score}]
    chunks_used     TEXT,
    provider        TEXT,
    model           TEXT,
    latency_ms      INTEGER DEFAULT 0,
    token_cost      INTEGER DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_traces_tenant ON analysis_traces(tenant_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_traces_lead   ON analysis_traces(lead_id);
