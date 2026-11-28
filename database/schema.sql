-- AI Model Bench schema (SQLite). No Laravel migrations: apply with `make db-init`.
PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------------------
-- Laravel infrastructure: cache (rate limiter), queue, sessions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cache (
    key        TEXT PRIMARY KEY NOT NULL,
    value      TEXT NOT NULL,
    expiration INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS cache_expiration_index ON cache (expiration);

CREATE TABLE IF NOT EXISTS cache_locks (
    key        TEXT PRIMARY KEY NOT NULL,
    owner      TEXT NOT NULL,
    expiration INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS cache_locks_expiration_index ON cache_locks (expiration);

CREATE TABLE IF NOT EXISTS jobs (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    queue        TEXT NOT NULL,
    payload      TEXT NOT NULL,
    attempts     INTEGER NOT NULL,
    reserved_at  INTEGER,
    available_at INTEGER NOT NULL,
    created_at   INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS jobs_queue_index ON jobs (queue);

CREATE TABLE IF NOT EXISTS job_batches (
    id             TEXT PRIMARY KEY NOT NULL,
    name           TEXT NOT NULL,
    total_jobs     INTEGER NOT NULL,
    pending_jobs   INTEGER NOT NULL,
    failed_jobs    INTEGER NOT NULL,
    failed_job_ids TEXT NOT NULL,
    options        TEXT,
    cancelled_at   INTEGER,
    created_at     INTEGER NOT NULL,
    finished_at    INTEGER
);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid       TEXT NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue      TEXT NOT NULL,
    payload    TEXT NOT NULL,
    exception  TEXT NOT NULL,
    failed_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS failed_jobs_connection_queue_failed_at_index ON failed_jobs (connection, queue, failed_at);

CREATE TABLE IF NOT EXISTS sessions (
    id            TEXT PRIMARY KEY NOT NULL,
    user_id       INTEGER,
    ip_address    TEXT,
    user_agent    TEXT,
    payload       TEXT NOT NULL,
    last_activity INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS sessions_user_id_index ON sessions (user_id);
CREATE INDEX IF NOT EXISTS sessions_last_activity_index ON sessions (last_activity);

-- ---------------------------------------------------------------------------
-- Bench domain
-- ---------------------------------------------------------------------------

-- Source of truth is config/bench.php; rows are upserted by bench:models:sync.
CREATE TABLE IF NOT EXISTS providers (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    code        TEXT NOT NULL UNIQUE,           -- key from config/bench.php providers
    name        TEXT NOT NULL,
    enabled     INTEGER NOT NULL DEFAULT 1,
    limits_json TEXT                            -- override of config limits
);

CREATE TABLE IF NOT EXISTS models (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    provider_id    INTEGER NOT NULL REFERENCES providers(id),
    model_id       TEXT NOT NULL,               -- ID as the provider API expects it
    display_name   TEXT,
    context_window INTEGER,
    supports_json  INTEGER,                     -- NULL = unknown
    is_free        INTEGER NOT NULL DEFAULT 1,
    source         TEXT NOT NULL DEFAULT 'catalog', -- config | catalog
    available      INTEGER NOT NULL DEFAULT 1,  -- 0 = removed from config / provider catalog
    enabled        INTEGER NOT NULL DEFAULT 0,
    enabled_by_user INTEGER NOT NULL DEFAULT 0, -- 1 = enabled flag was set manually (UI/CLI), sync must not touch it
    limits_json    TEXT,
    synced_at      TEXT,
    created_at     TEXT NOT NULL,
    updated_at     TEXT NOT NULL,
    UNIQUE (provider_id, model_id)
);

CREATE TABLE IF NOT EXISTS scenarios (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    slug            TEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL,
    system_prompt   TEXT NOT NULL,
    user_template   TEXT NOT NULL,              -- placeholders: {{input}}, {{title}}, {{meta.*}}
    response_format TEXT NOT NULL DEFAULT 'text', -- text | json_object | json_schema
    json_schema     TEXT,
    params_json     TEXT,                       -- temperature, max_tokens, top_p
    judge_rubric    TEXT,
    metrics_json    TEXT,                       -- auto metrics: name => {metric, ...options}
    source          TEXT NOT NULL DEFAULT 'config', -- config | ui
    available       INTEGER NOT NULL DEFAULT 1,
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS datasets (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    description TEXT,
    created_at  TEXT NOT NULL,
    updated_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS dataset_items (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    dataset_id            INTEGER NOT NULL REFERENCES datasets(id) ON DELETE CASCADE,
    title                 TEXT,
    input                 TEXT NOT NULL DEFAULT '',
    source_url            TEXT,
    source_html           TEXT,
    expected_score        INTEGER,
    expected_announcement TEXT,
    expected_json         TEXT,
    meta_json             TEXT,
    fetch_status          TEXT,                 -- pending | ok | failed | manual (NULL = imported text)
    fetch_error           TEXT,
    created_at            TEXT NOT NULL,
    updated_at            TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS dataset_items_dataset_index ON dataset_items (dataset_id);
CREATE UNIQUE INDEX IF NOT EXISTS dataset_items_dataset_url_unique ON dataset_items (dataset_id, source_url);

CREATE TABLE IF NOT EXISTS runs (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    scenario_id       INTEGER NOT NULL REFERENCES scenarios(id),
    dataset_id        INTEGER REFERENCES datasets(id),
    adhoc_input       TEXT,
    repeats           INTEGER NOT NULL DEFAULT 1,
    status            TEXT NOT NULL,            -- pending | running | done | failed | cancelled
    scenario_snapshot TEXT NOT NULL,
    started_at        TEXT,
    finished_at       TEXT,
    created_at        TEXT NOT NULL,
    updated_at        TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS run_models (
    run_id   INTEGER NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
    model_id INTEGER NOT NULL REFERENCES models(id),
    PRIMARY KEY (run_id, model_id)
);

CREATE TABLE IF NOT EXISTS results (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id            INTEGER NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
    model_id          INTEGER NOT NULL REFERENCES models(id),
    dataset_item_id   INTEGER REFERENCES dataset_items(id),
    attempt_no        INTEGER NOT NULL DEFAULT 1,
    status            TEXT NOT NULL,            -- pending | ok | error | rate_limited
    request_json      TEXT NOT NULL,
    raw_response      TEXT,
    content           TEXT,
    parsed_json       TEXT,
    json_mode         TEXT,                     -- native | fallback | none
    json_valid        INTEGER,
    schema_valid      INTEGER,
    latency_ms        INTEGER,
    ttft_ms           INTEGER,
    prompt_tokens     INTEGER,
    completion_tokens INTEGER,
    http_status       INTEGER,
    error_message     TEXT,
    retries           INTEGER NOT NULL DEFAULT 0,
    created_at        TEXT NOT NULL,
    updated_at        TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS results_run_model_index ON results (run_id, model_id);

CREATE TABLE IF NOT EXISTS evaluations (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    result_id      INTEGER NOT NULL REFERENCES results(id) ON DELETE CASCADE,
    type           TEXT NOT NULL,               -- auto | human | judge
    metric         TEXT NOT NULL,
    value          REAL,
    details        TEXT,
    judge_model_id INTEGER REFERENCES models(id),
    created_at     TEXT NOT NULL,
    updated_at     TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS evaluations_result_type_index ON evaluations (result_id, type);

CREATE TABLE IF NOT EXISTS pairwise_votes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id      INTEGER NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
    result_a_id INTEGER NOT NULL REFERENCES results(id),
    result_b_id INTEGER NOT NULL REFERENCES results(id),
    winner      TEXT NOT NULL,                  -- a | b | tie | both_bad
    created_at  TEXT NOT NULL
);
