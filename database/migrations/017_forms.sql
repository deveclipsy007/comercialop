-- Migration 017: Dynamic Qualification Forms
-- Formulários dinâmicos de qualificação de leads

CREATE TABLE IF NOT EXISTS qualification_forms (
    id TEXT PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    created_by TEXT NOT NULL,
    title TEXT NOT NULL DEFAULT 'Novo Formulário',
    description TEXT DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft', -- draft, published, archived
    public_slug TEXT UNIQUE,
    settings TEXT DEFAULT '{}', -- JSON: theme, branding, redirect_url, etc.
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS form_questions (
    id TEXT PRIMARY KEY,
    form_id TEXT NOT NULL,
    tenant_id TEXT NOT NULL,
    section_title TEXT DEFAULT '', -- agrupamento de seções
    label TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'short_text', -- short_text, long_text, single_choice, multiple_choice, number, date, select, checkbox, rating, email, phone, url
    options TEXT DEFAULT '[]', -- JSON array para choices/select
    placeholder TEXT DEFAULT '',
    help_text TEXT DEFAULT '',
    is_required INTEGER DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    metadata TEXT DEFAULT '{}', -- JSON: conditional logic, validation rules, etc.
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES qualification_forms(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS form_responses (
    id TEXT PRIMARY KEY,
    form_id TEXT NOT NULL,
    tenant_id TEXT NOT NULL,
    lead_id TEXT, -- nullable: pode ser preenchido sem lead vinculado
    filled_by TEXT, -- user_id do vendedor (null se preenchido pelo lead via link público)
    respondent_name TEXT DEFAULT '',
    respondent_email TEXT DEFAULT '',
    answers TEXT NOT NULL DEFAULT '{}', -- JSON: { question_id: answer_value }
    source TEXT NOT NULL DEFAULT 'public', -- public, internal
    score INTEGER DEFAULT 0, -- pontuação calculada de qualificação
    ai_summary TEXT DEFAULT '', -- resumo gerado por IA das respostas
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES qualification_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_forms_tenant ON qualification_forms(tenant_id);
CREATE INDEX IF NOT EXISTS idx_forms_slug ON qualification_forms(public_slug);
CREATE INDEX IF NOT EXISTS idx_form_questions_form ON form_questions(form_id);
CREATE INDEX IF NOT EXISTS idx_form_responses_form ON form_responses(form_id);
CREATE INDEX IF NOT EXISTS idx_form_responses_lead ON form_responses(lead_id);
