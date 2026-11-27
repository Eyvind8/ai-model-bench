# AI Model Bench

Laravel 13 / PHP 8.3 bench for comparing LLMs. Spec: `TZ.md` (Ukrainian), usage: `README.md`.

## Run everything in Docker

Local PHP lacks pdo_sqlite/dom. Use make targets or `docker compose run --rm --no-deps -T app <cmd>`:

- `make test` - PHPUnit (`php artisan test --compact`)
- `make lint` - Pint, `make stan` - Larastan level 6, `make check` - all three
- UI: http://localhost:8011 (`BENCH_PORT`), port 8099 is taken by local phpmyadmin

## Rules

- No Laravel migrations. Schema lives in `database/schema.sql` (idempotent `CREATE ... IF NOT EXISTS`); tests load it via `Tests\Concerns\LoadsSchema`.
- Providers and models are connected only through `config/bench.php`. Drivers are resolved by name via `App\Llm\DriverResolver` from `bench.drivers`. Do not hardcode provider codes in code.
- Tests use `$this->useTestProviders()` for deterministic provider config and `Http::fake()`; never hit real APIs in tests.
- `.env` is not readable by the agent; ask the user to edit keys.
- SOLID/DRY per global rules: constructor injection, readonly DTOs, enums, thin commands/controllers.
