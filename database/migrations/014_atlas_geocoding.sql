-- Migration 014: Add geocoding columns to leads for Atlas map
-- Enables real geographic positioning instead of random coordinates

ALTER TABLE leads ADD COLUMN latitude REAL;
ALTER TABLE leads ADD COLUMN longitude REAL;
ALTER TABLE leads ADD COLUMN geocoded_at TEXT;

CREATE INDEX IF NOT EXISTS idx_leads_geocoded ON leads(tenant_id, latitude, longitude)
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL;
