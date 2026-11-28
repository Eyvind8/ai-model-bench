-- Apply to a DB created before stage 2:
--   docker compose run --rm --no-deps -T app sh -c 'sqlite3 database/database.sqlite < database/patches/2026-11-28-stage2-scenarios.sql'
ALTER TABLE scenarios ADD COLUMN metrics_json TEXT;
ALTER TABLE scenarios ADD COLUMN source TEXT NOT NULL DEFAULT 'config';
ALTER TABLE scenarios ADD COLUMN available INTEGER NOT NULL DEFAULT 1;
