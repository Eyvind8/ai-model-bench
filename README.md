# AI Model Bench

Local bench for testing and comparing LLMs (free API tiers first): same task across several models, compared by quality, speed, stability and output format.

Spec: [TZ.md](TZ.md).

## Quick start

```bash
make build
make db-init        # database/database.sqlite from database/schema.sql + sync models from config
make up             # app + nginx (http://localhost:8011) + queue worker
```

Port can be changed: `BENCH_PORT=8012 make up`.

## API keys

Add to `.env` (free, no card):

| Variable | Where to get |
| --- | --- |
| `GEMINI_API_KEY` | aistudio.google.com → Get API key |
| `GROQ_API_KEY` | console.groq.com → API Keys |
| `OPENROUTER_API_KEY` | openrouter.ai → Keys |
| `CF_API_TOKEN`, `CF_ACCOUNT_ID` | dash.cloudflare.com → Workers AI → Use REST API |

Then `make restart` (worker picks up new env) and `make ping`.

Free tiers of Google and some OpenRouter models may use requests for training - do not send confidential texts.

## Connecting models

Everything is in `config/bench.php`:

```php
'providers' => [
    'groq' => [
        'driver' => 'openai',          // client driver from bench.drivers.clients
        'catalog' => 'openai',         // discovery driver, null = config models only
        'base_url' => 'https://api.groq.com/openai/v1',
        'api_key' => env('GROQ_API_KEY'),
        'limits' => ['rpm' => 30, 'rpd' => 1000, 'min_interval_ms' => 2000],
        'models' => [
            'qwen/qwen3-32b' => ['name' => 'Qwen3 32B', 'json' => true],
        ],
    ],
],
```

- New model / new OpenAI-compatible provider - add an entry, run `make sync`.
- New protocol - class implementing `App\Contracts\LlmClientInterface` + one line in `bench.drivers.clients`.
- Find current model IDs: `make discover` (catalog models are stored disabled), `make models`.

## Commands

```bash
php artisan bench:models:sync [--provider=groq] [--discover]
php artisan bench:models:list [--provider=groq] [--enabled]
php artisan bench:models:enable 'groq:qwen/*' '*flash*' [--disable]
php artisan bench:models:ping [--provider=groq] [--model=groq:qwen/qwen3-32b]
```

`make help` lists make targets. `make check` = Pint + PHPStan + tests.

## Notes

- No Laravel migrations: schema is `database/schema.sql` (raw SQL).
