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

## Scenarios and metrics

`config/scenarios.php` - prompt, response format (`text` / `json_object` / `json_schema`), params and auto metrics.
Built in: `digest-article` (score 1-10 + announcement up to 200 chars).
`bench.metrics` in `config/bench.php` maps metric name to class (`max_length`, `score_error`, `language_match`);
new metric = class implementing `App\Contracts\MetricInterface` + one line. After changes: `make sync`.

## Typical flow

```bash
# 1. dataset: from URLs (downloaded and cleaned by the worker) or a file
php artisan bench:dataset:fetch-urls https://site/a https://site/b --name="News"
php artisan bench:dataset:import articles.csv --name="News"   # columns: title,text,url,expected_score,...
php artisan bench:dataset:list 1                              # fetch status per item

# 2. run (queue; worker must be up) or --sync inline
php artisan bench:run --dataset=1 --models='groq:*' --models='gemini:*flash*' --repeats=2 --dry-run
php artisan bench:run --dataset=1 --sync

# 3. results
php artisan bench:report 1
```

## Commands

```bash
php artisan bench:models:sync [--provider=groq] [--discover]
php artisan bench:models:list [--provider=groq] [--enabled]
php artisan bench:models:enable 'groq:qwen/*' '*flash*' [--disable]
php artisan bench:models:ping [--provider=groq] [--model=groq:qwen/qwen3-32b]
php artisan bench:scenarios:sync
php artisan bench:dataset:import {file} --name=|--dataset=
php artisan bench:dataset:fetch-urls {urls*} [--file=urls.txt] --name=|--dataset=
php artisan bench:dataset:refetch {dataset} [--failed] [--download]
php artisan bench:dataset:list [dataset]
php artisan bench:run --dataset=|--input=|--input-file= [--scenario=] [--models=*] [--repeats=] [--limit=] [--sync] [--dry-run]
php artisan bench:report {run}
php artisan bench:runs
php artisan bench:limits [--provider=]
```

Rate limits: per provider+model RPM/RPD/TPM from config, min pause between requests to one provider,
429/5xx retried with backoff (`Retry-After` respected), model is skipped until 00:00 UTC once the daily quota is gone.
If a model rejects `response_format`, the request is repeated without it and JSON is extracted from text.

`make help` lists make targets. `make check` = Pint + PHPStan + tests.

## Notes

- No Laravel migrations: schema is `database/schema.sql` (raw SQL). Changes for existing DBs: `database/patches/*.sql`.
