-- Migration: Sample Add Settings Index
-- Created at: 20250506112500

-- UP
-- Add an index to the settings table to improve lookup performance
CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(setting_key);

-- END UP

-- DOWN
-- Remove the index if we need to roll back
DROP INDEX IF EXISTS idx_settings_key ON settings;

-- END DOWN