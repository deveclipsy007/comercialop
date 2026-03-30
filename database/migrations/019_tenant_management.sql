-- Migration 019: Enhanced Tenant Management
-- Adds per-tenant configuration fields for individual company management
-- Run: sqlite3 storage/database.sqlite < database/migrations/019_tenant_management.sql

-- Extra credits (beyond the tier default)
ALTER TABLE token_quotas ADD COLUMN credits_extra INTEGER NOT NULL DEFAULT 0;

-- Per-tenant feature flags (JSON array of enabled features)
ALTER TABLE tenants ADD COLUMN features_enabled TEXT DEFAULT NULL;

-- Per-tenant custom limits
ALTER TABLE tenants ADD COLUMN max_users INTEGER NOT NULL DEFAULT 10;
ALTER TABLE tenants ADD COLUMN max_leads INTEGER NOT NULL DEFAULT 5000;
ALTER TABLE tenants ADD COLUMN max_campaigns INTEGER NOT NULL DEFAULT 50;

-- Notes field for admin annotations
ALTER TABLE tenants ADD COLUMN admin_notes TEXT DEFAULT '';
