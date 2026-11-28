-- Apply to a DB created before this change:
--   docker compose run --rm --no-deps -T app sh -c 'sqlite3 database/database.sqlite < database/patches/2026-11-28-models-enabled-by-user.sql'
ALTER TABLE models ADD COLUMN enabled_by_user INTEGER NOT NULL DEFAULT 0;
