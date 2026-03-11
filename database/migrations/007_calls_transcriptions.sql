-- Migration for Calls / Transcriptions
CREATE TABLE IF NOT EXISTS calls (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id TEXT NOT NULL,
    lead_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    title TEXT,
    audio_path TEXT,
    duration INTEGER,
    status TEXT NOT NULL DEFAULT 'uploading',
    language TEXT,
    transcript_raw TEXT,
    transcript_clean TEXT,
    analysis_data TEXT,
    error_message TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY(lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_calls_tenant_lead ON calls(tenant_id, lead_id);
CREATE INDEX IF NOT EXISTS idx_calls_status ON calls(status);
