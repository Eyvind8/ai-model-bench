# ТЗ: AI Model Bench - стенд для тестування і порівняння LLM

Версія: 0.1 (чернетка) · Дата: 2026-11-27

## 1. Мета

Локальний стенд, де можна прогнати однакове завдання через кілька LLM і порівняти їх за якістю, швидкістю, стабільністю і дотриманням формату. Перший етап - тільки безкоштовні API-тарифи.

Основний прикладний кейс: **дайджест статей** - модель оцінює важливість статті (1-10) і пише анонс до 200 символів у строгому JSON. Плюс довільні сценарії-шаблони.

## 2. Скоуп

### Входить у MVP
- Провайдери: Google AI Studio (Gemini/Gemma), Groq, OpenRouter (`:free`), Cloudflare Workers AI.
- Автопідтягування списку моделей з API провайдера.
- Сценарії (system prompt + шаблон user prompt + очікуваний формат відповіді).
- Датасети (набори вхідних текстів, напр. статті), у т.ч. імпорт статей за URL з очищенням тексту.
- Еталонні оцінки (score 1-10 від людини) для елементів датасету.
- Прогони (run): сценарій × датасет × набір моделей × N повторів.
- Метрики: автоматичні, ручна оцінка, LLM-as-judge, узгодженість score.
- Веб-UI (side-by-side порівняння, таблиця лідерів) + CLI (батч-прогони).
- Rate limiting під free-ліміти кожного провайдера.

### Не входить у MVP
- Платні моделі (архітектура має дозволяти додати без переписування).
- Стрімінг відповідей у UI.
- Мультикористувацький режим, авторизація.
- Vision/audio, tool calling.

## 3. Стек

- PHP 8.3, Laravel 12.
- HTTP: Guzzle (через Laravel HTTP Client).
- БД: SQLite (`database/database.sqlite`). **Без Laravel-міграцій** - схема в `database/schema.sql`, накатується `make db-init`.
- Черги: `database` driver (прогони виконуються воркером у фоні).
- UI: Blade + Alpine.js + Tailwind (CDN або Vite), графіки - Chart.js.
- Тести: Pest/PHPUnit, `Http::fake()` для провайдерів.
- Витяг статей: `fivefilters/readability.php` (очищення HTML → текст), `league/html-to-markdown` опційно.
- Оточення: Docker Compose (див. розділ 11.1).
- Якість коду: Laravel Pint, PHPStan (level 6+).

## 4. Провайдери

Усі чотири підтримують OpenAI-сумісний `POST {base_url}/chat/completions`, тому один клієнт + конфіг на провайдера.

### 4.0 Підключення через конфіг (масштабованість)

Єдина точка підключення - `config/bench.php`:

- `drivers.clients` - мапа `назва драйвера => клас` (`LlmClientInterface`). Зараз `openai` (OpenAI-сумісний протокол). Новий протокол (Anthropic, native Gemini) = новий клас + рядок у мапі.
- `drivers.catalogs` - мапа драйверів автопошуку моделей (`ModelCatalogInterface`): `openai`, `openrouter`, `cloudflare`.
- `providers.<code>` - провайдер: `driver`, `catalog` (або `null`), `base_url`, `api_key`, `headers`, `exclude`, `limits`, `model_limits`, `models`.
- `providers.<code>.models` - **моделі підключаються тут**: `model_id => [name, context_window, json, free, enabled, limits]`.

Новий провайдер або модель = запис у конфігу, без змін у коді. `bench:models:sync` переносить конфіг у БД (`source=config`, увімкнені за замовчуванням). `bench:models:sync --discover` додатково тягне каталоги провайдерів (`source=catalog`, вимкнені за замовчуванням) - щоб знайти нові ID і перенести потрібні в конфіг. Модель, видалена з конфігу/каталогу, стає `available=0`, історія результатів зберігається.

| Провайдер | `base_url` | Ключ у `.env` | Список моделей |
| --- | --- | --- | --- |
| Google AI Studio | `https://generativelanguage.googleapis.com/v1beta/openai` | `GEMINI_API_KEY` | `GET /models` |
| Groq | `https://api.groq.com/openai/v1` | `GROQ_API_KEY` | `GET /models` |
| OpenRouter | `https://openrouter.ai/api/v1` | `OPENROUTER_API_KEY` | `GET /models`, фільтр `:free` / `pricing.prompt == 0` |
| Cloudflare Workers AI | `https://api.cloudflare.com/client/v4/accounts/{ACCOUNT_ID}/ai/v1` | `CF_API_TOKEN`, `CF_ACCOUNT_ID` | `GET /accounts/{id}/ai/models/search?task=Text Generation` |

**Важливо:** ID моделей зі списку-джерела (`gemini-3.5-flash-lite`, `llama-4-scout-...` тощо) не перевірені - стартовий набір у конфігу, перевірка через `bench:models:ping`, актуальні ID через `--discover`.

### 4.1 Ліміти (стартові значення, редагуються в конфігу/UI)

| Провайдер | RPM | RPD | TPM | Примітка |
| --- | --- | --- | --- | --- |
| Gemini Flash-Lite | 15 | 500 | - | задається per-model |
| Gemini Flash | 5 | 20 | - | per-model |
| Groq | 30 | ~1000 | per-model | читати заголовки `x-ratelimit-*` |
| OpenRouter free | 20 | 50 | - | без поповнення балансу |
| Cloudflare | - | 10 000 neurons/день | - | облік по neurons |

Вимоги до rate limiter:
- Ліміти на рівні **провайдер + модель** (RPM, RPD, TPM).
- Лічильники в Laravel Cache (`RateLimiter`), денні - з reset по таймзоні провайдера (UTC за замовчуванням).
- Мінімальна пауза між запитами до одного провайдера - конфігурована (дефолт 3 с).
- На `429`: читати `Retry-After` / `x-ratelimit-reset-*`, retry з exponential backoff + jitter, максимум 3 спроби. Після вичерпання RPD - модель позначається `exhausted` до кінця доби, решта прогону йде далі.
- Прогноз перед запуском: "прогін = X запитів, поточний залишок RPD = Y" - попередження, якщо не влізе.

## 5. Доменна модель і схема БД

```sql
-- database/schema.sql
CREATE TABLE providers (
    id          INTEGER PRIMARY KEY,
    code        TEXT NOT NULL UNIQUE,          -- gemini | groq | openrouter | cloudflare
    name        TEXT NOT NULL,
    base_url    TEXT NOT NULL,
    enabled     INTEGER NOT NULL DEFAULT 1,
    limits_json TEXT                            -- дефолтні ліміти провайдера
);

CREATE TABLE models (
    id              INTEGER PRIMARY KEY,
    provider_id     INTEGER NOT NULL REFERENCES providers(id),
    model_id        TEXT NOT NULL,              -- ID як в API провайдера
    display_name    TEXT,
    context_window  INTEGER,
    supports_json   INTEGER NOT NULL DEFAULT 0, -- response_format json_object/json_schema
    is_free         INTEGER NOT NULL DEFAULT 1,
    enabled         INTEGER NOT NULL DEFAULT 0,
    limits_json     TEXT,                       -- override лімітів
    synced_at       TEXT,
    UNIQUE (provider_id, model_id)
);

CREATE TABLE scenarios (
    id              INTEGER PRIMARY KEY,
    slug            TEXT NOT NULL UNIQUE,
    name            TEXT NOT NULL,
    system_prompt   TEXT NOT NULL,
    user_template   TEXT NOT NULL,              -- з плейсхолдерами {{input}}
    response_format TEXT NOT NULL DEFAULT 'text', -- text | json_object | json_schema
    json_schema     TEXT,                       -- JSON Schema для валідації
    params_json     TEXT,                       -- temperature, max_tokens, top_p
    judge_rubric    TEXT,                       -- критерії для LLM-as-judge
    created_at      TEXT NOT NULL,
    updated_at      TEXT NOT NULL
);

CREATE TABLE datasets (
    id          INTEGER PRIMARY KEY,
    name        TEXT NOT NULL,
    description TEXT,
    created_at  TEXT NOT NULL
);

CREATE TABLE dataset_items (
    id          INTEGER PRIMARY KEY,
    dataset_id  INTEGER NOT NULL REFERENCES datasets(id) ON DELETE CASCADE,
    title       TEXT,
    input       TEXT NOT NULL,                  -- очищений текст статті
    source_url  TEXT,                           -- якщо імпортовано за URL
    source_html TEXT,                           -- сирий HTML, для повторного очищення
    expected_score INTEGER,                     -- еталонний score 1-10 від людини
    expected_announcement TEXT,                 -- еталонний анонс, опційно
    expected_json TEXT,                         -- еталон для довільних сценаріїв
    meta_json   TEXT,                           -- автор, дата, сайт, мова
    fetch_status TEXT,                          -- ok | failed | manual
    fetch_error TEXT
);

CREATE TABLE runs (
    id           INTEGER PRIMARY KEY,
    scenario_id  INTEGER NOT NULL REFERENCES scenarios(id),
    dataset_id   INTEGER REFERENCES datasets(id), -- NULL для ad-hoc промпту
    adhoc_input  TEXT,
    repeats      INTEGER NOT NULL DEFAULT 1,
    status       TEXT NOT NULL,                 -- pending | running | done | failed | cancelled
    scenario_snapshot TEXT NOT NULL,            -- копія сценарію на момент запуску
    started_at   TEXT,
    finished_at  TEXT,
    created_at   TEXT NOT NULL
);

CREATE TABLE run_models (
    run_id   INTEGER NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
    model_id INTEGER NOT NULL REFERENCES models(id),
    PRIMARY KEY (run_id, model_id)
);

CREATE TABLE results (
    id               INTEGER PRIMARY KEY,
    run_id           INTEGER NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
    model_id         INTEGER NOT NULL REFERENCES models(id),
    dataset_item_id  INTEGER REFERENCES dataset_items(id),
    attempt_no       INTEGER NOT NULL DEFAULT 1, -- номер повтору для узгодженості
    status           TEXT NOT NULL,              -- ok | error | rate_limited | timeout
    request_json     TEXT NOT NULL,
    raw_response     TEXT,
    content          TEXT,
    parsed_json      TEXT,
    json_valid       INTEGER,                    -- NULL якщо формат text
    schema_valid     INTEGER,
    latency_ms       INTEGER,
    ttft_ms          INTEGER,                    -- на майбутнє (стрімінг)
    prompt_tokens    INTEGER,
    completion_tokens INTEGER,
    http_status      INTEGER,
    error_message    TEXT,
    retries          INTEGER NOT NULL DEFAULT 0,
    created_at       TEXT NOT NULL
);
CREATE INDEX idx_results_run_model ON results(run_id, model_id);

CREATE TABLE evaluations (
    id          INTEGER PRIMARY KEY,
    result_id   INTEGER NOT NULL REFERENCES results(id) ON DELETE CASCADE,
    type        TEXT NOT NULL,                  -- auto | human | judge
    metric      TEXT NOT NULL,                  -- напр. announcement_length_ok, overall, relevance
    value       REAL,
    details     TEXT,                           -- коментар / обґрунтування судді
    judge_model_id INTEGER REFERENCES models(id),
    created_at  TEXT NOT NULL
);
CREATE INDEX idx_evaluations_result ON evaluations(result_id, type);

CREATE TABLE pairwise_votes (                   -- сліпе порівняння A/B
    id           INTEGER PRIMARY KEY,
    run_id       INTEGER NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
    result_a_id  INTEGER NOT NULL REFERENCES results(id),
    result_b_id  INTEGER NOT NULL REFERENCES results(id),
    winner       TEXT NOT NULL,                 -- a | b | tie | both_bad
    created_at   TEXT NOT NULL
);
```

## 6. Сценарії

### 6.1 Вбудований: `digest-article`
- System: оцінити важливість статті для дайджесту 1-10 і написати анонс до 200 символів, повернути строго JSON.
- Schema:
  ```json
  {
    "type": "object",
    "required": ["score", "announcement"],
    "properties": {
      "score": {"type": "integer", "minimum": 1, "maximum": 10},
      "announcement": {"type": "string", "maxLength": 200}
    },
    "additionalProperties": false
  }
  ```
- Параметри: `temperature=0.2`, `max_tokens=300`.

### 6.2 Користувацькі сценарії
CRUD у UI. Плейсхолдери в `user_template`: `{{input}}`, `{{title}}`, `{{meta.*}}`.

### 6.3 Імпорт статей за URL
- Вхід: один URL, список URL (textarea / файл), або CSV з колонками `url,expected_score`.
- Завантаження через HTTP-клієнт: таймаут 15 с, User-Agent без особистих даних, редіректи, ліміт розміру 5 MB, тільки `text/html`.
- Очищення через Readability: заголовок, основний текст, автор, дата публікації, мова. Скрипти, меню, реклама, коментарі відкидаються.
- Мінімальна довжина тексту (дефолт 500 символів) - інакше `fetch_status=failed` з причиною (paywall, JS-рендер, порожня сторінка).
- Обрізання тексту до конфігурованого ліміту символів (дефолт 12 000), щоб не вилітати за TPM free-тарифів. Факт обрізання пишеться в `meta_json`.
- Сирий HTML зберігається - можна перечистити без повторного завантаження.
- Дублікати (той самий URL у датасеті) пропускаються.
- Невдалий URL можна дозаповнити вручну (вставити текст) - `fetch_status=manual`.
- Імпорт у черзі, прогрес видно в UI.

### 6.4 Еталонна розмітка
- Сторінка розмітки датасету: стаття зліва, поля `expected_score` (1-10) і опційно `expected_announcement` справа, навігація "далі/назад", гарячі клавіші 1-9/0.
- Розмітку можна імпортувати з CSV.
- Прогрес розмітки: "розмічено 34 з 50".
- Еталон не показується моделям і судді.

### 6.5 Ad-hoc режим (playground)
Ввів промпт/текст → вибрав моделі → отримав відповіді поруч. Зберігається як run без датасету.

### 6.6 JSON-режим
- Якщо модель `supports_json` - передаємо `response_format` (`json_schema`, fallback `json_object`).
- Якщо ні або провайдер повернув помилку на `response_format` - запит без нього, JSON витягується з тексту (зняти ```json-обгортку, знайти перший `{...}`), результат позначається `json_mode=fallback`.
- Метрики фіксують і "валідний JSON", і "валідний за схемою" окремо.

## 7. Метрики

### 7.1 Автоматичні (рахуються одразу після відповіді)
| Метрика | Опис |
| --- | --- |
| `latency_ms` | час запиту, p50/p95 по моделі |
| `tokens` | prompt/completion з `usage` |
| `json_valid` | відповідь парситься як JSON |
| `schema_valid` | проходить JSON Schema (`opis/json-schema` або `justinrainbow/json-schema`) |
| `announcement_length_ok` | для дайджесту: довжина ≤ 200 (mb_strlen) |
| `language_match` | мова анонсу = мова статті (простий детектор) |
| `error_rate` | частка error/timeout/rate_limited |
| `score_abs_error` | \|score - expected_score\| по кожній відповіді |

### 7.1.1 Точність відносно еталону (по моделі, якщо датасет розмічено)
| Метрика | Опис |
| --- | --- |
| MAE | середня абсолютна похибка score |
| `within_1` | % відповідей, де \|score - expected\| ≤ 1 |
| Spearman | кореляція рангів моделі з еталоном (чи правильно модель впорядковує статті за важливістю) |
| Bias | середнє (score - expected): модель систематично завищує чи занижує |
| Top-K overlap | перетин топ-K статей моделі і еталону (K задається, дефолт 5) - що реально потрапить у дайджест |

### 7.2 Ручна оцінка
- Оцінка 1-5 по кожній відповіді + коментар.
- **Сліпий режим A/B**: дві відповіді від випадкових моделей без назв, вибір "A краще / B краще / нічия / обидві погані". Рейтинг моделей - Elo (або Bradley-Terry) по `pairwise_votes`.

### 7.3 LLM-as-judge
- Модель-суддя вибирається в UI перед запуском оцінки. Дефолт не задається (вирішимо пізніше), архітектура дозволяє підключити як суддю і платну модель.
- Валідація судді: на розміченому датасеті показується кореляція оцінок судді з еталоном - щоб бачити, чи можна йому довіряти.
- Оцінює кожну відповідь за `judge_rubric` сценарію (дефолт для дайджесту: точність, інформативність, стиль анонсу, адекватність score) - кожен критерій 1-10 + коротке обґрунтування, відповідь у JSON.
- Захист від упередженості: суддя не бачить назву моделі; суддя не оцінює власні відповіді (попередження в UI, якщо так); опційно pairwise-режим зі зміною порядку A/B.
- Оцінки судді витрачають ліміти - враховуються rate limiter-ом і прогнозом.

### 7.4 Узгодженість score
- Прогін з `repeats > 1`: для кожної моделі std/розкид score по одній статті між повторами.
- Між моделями: середнє відхилення score від медіани всіх моделей, кореляція (Spearman) між моделями.
- Візуалізація: heatmap "стаття × модель" зі score, окремий стовпчик з еталоном.

## 8. Веб-UI

| Сторінка | Що є |
| --- | --- |
| **Dashboard** | останні прогони, лідерборд по моделях, залишки денних лімітів по провайдерах |
| **Моделі** | список по провайдерах, sync, вкл/викл, редагування лімітів, тест "ping" |
| **Сценарії** | CRUD, превʼю промпту з підставленим прикладом |
| **Датасети** | CRUD, імпорт CSV/JSON/JSONL, імпорт за URL (з прогресом і статусами), ручне додавання статті, перегляд очищеного тексту |
| **Розмітка** | еталонні score/анонси по датасету (розділ 6.4) |
| **Playground** | ad-hoc запит у кілька моделей, відповіді колонками |
| **Новий прогін** | сценарій + датасет + моделі + повтори + прогноз запитів |
| **Прогін** | прогрес (polling), матриця "елемент × модель", клік - деталі (request/raw/parsed/метрики), ручна оцінка |
| **Порівняння** | зведена таблиця метрик по моделях, графіки latency/якість, heatmap score |
| **Сліпе A/B** | голосування, Elo-рейтинг |
| **Експорт** | CSV/JSON результатів прогону |

## 9. CLI (artisan)

```
bench:models:sync [--provider=gemini] [--discover]  # конфіг -> БД, --discover: + каталоги API
bench:models:list [--provider=] [--enabled]
bench:models:enable {patterns*} [--disable]    # напр. groq:qwen/* або *flash*
bench:models:ping [--provider=] [--model=]     # перевірити доступність і ключі
bench:dataset:import {file} --name=            # CSV/JSON/JSONL
bench:dataset:fetch-urls {dataset_id} {file|url...}  # імпорт статей за URL
bench:dataset:refetch {dataset_id} [--failed]  # повторне завантаження/очищення
bench:run --scenario=digest-article --dataset=1 --models=groq:*,gemini:gemini-*-flash-lite --repeats=3 [--sync]
bench:judge {run_id} --judge=gemini:...        # LLM-оцінка прогону
bench:report {run_id} --format=table|csv|json  # зведення
bench:limits                                   # поточні залишки лімітів
```

## 10. Архітектура (SOLID)

```
app/
  Contracts/
    LlmClientInterface.php          # chat(ProviderConfig, ChatRequest): ChatResponse
    ModelCatalogInterface.php       # listModels(ProviderConfig): ModelInfo[]
    RateLimiterInterface.php
    ResponseParserInterface.php
    MetricInterface.php             # compute(Result): ?Evaluation
    JudgeInterface.php
  Llm/
    OpenAiCompatibleClient.php      # драйвер `openai` для всіх 4 провайдерів
    ProviderHttp.php                # auth, таймаути, мапінг помилок, маскування ключа
    ProviderConfig.php              # readonly DTO провайдера з конфігу
    ProviderRegistry.php            # config/bench.php providers -> ProviderConfig
    DriverResolver.php              # назва драйвера -> клас з bench.drivers
    Catalogs/
      OpenAiModelsCatalog.php       # Gemini, Groq
      OpenRouterCatalog.php         # фільтр free
      CloudflareCatalog.php
    Dto/ChatRequest.php, ChatResponse.php, Usage.php
    Exceptions/RateLimitedException.php, ProviderException.php
  RateLimit/
    ProviderRateLimiter.php         # RPM/RPD/TPM per provider+model
    RetryPolicy.php                 # backoff + Retry-After
  Bench/
    RunPlanner.php                  # розкладає run на задачі, прогноз запитів
    RunExecutor.php                 # оркестратор
    PromptRenderer.php              # шаблон + плейсхолдери
    Parsing/JsonResponseParser.php, TextResponseParser.php
    Validation/JsonSchemaValidator.php
  Articles/
    ArticleFetcherInterface.php     # (в Contracts) fetch(url): FetchedPage
    HttpArticleFetcher.php          # завантаження з лімітами
    ReadabilityExtractor.php        # HTML -> Article (title, text, meta)
    ArticleTextNormalizer.php       # пробіли, обрізання до ліміту
    ArticleImporter.php             # оркестратор імпорту в датасет
  Metrics/
    MetricRegistry.php
    LatencyMetric.php, JsonValidMetric.php, SchemaValidMetric.php,
    AnnouncementLengthMetric.php, ScoreDeviationMetric.php, ...
  Evaluation/
    LlmJudge.php
    EloCalculator.php
    ConsistencyCalculator.php
    ReferenceAccuracyCalculator.php # MAE, within_1, Spearman, bias, top-K
  Jobs/
    ExecuteRunItemJob.php           # одна пара (item × model × attempt)
    FetchArticleJob.php
  Enums/
    ModelSource.php, RunStatus.php, ResultStatus.php, ResponseFormat.php, EvaluationType.php
```

Правила:
- Залежності тільки через конструктор, на інтерфейси.
- Новий провайдер/модель = тільки конфіг. Новий протокол = новий клас драйвера + рядок у `bench.drivers`.
- Нова метрика = новий клас `MetricInterface`, реєстрація в `MetricRegistry`.
- Контролери тонкі: валідація (FormRequest) → сервіс → view.
- Кожен запит/відповідь зберігається повністю (`request_json`, `raw_response`) для відтворюваності; `scenario_snapshot` фіксує промпт на момент прогону.

## 11. Конфіг і безпека

- Ключі тільки в `.env`, `.env.example` без значень. В БД ключі не зберігаються, в логах маскуються.
- `config/bench.php`: провайдери, дефолтні ліміти, паузи, таймаути (дефолт 60 с), retry.
- Попередження в UI: на free-тарифах Google і частини OpenRouter-моделей дані запитів можуть використовуватись для навчання - не відправляти конфіденційні тексти.
- Fetcher статей: тільки `http/https`, блок приватних/локальних IP (SSRF), щоб не можна було смикнути внутрішні адреси.

### 11.1 Docker

`docker-compose.yml`, сервіси:

| Сервіс | Образ | Роль |
| --- | --- | --- |
| `app` | свій `Dockerfile` на `php:8.3-fpm-alpine` (ext: pdo_sqlite, intl, mbstring, dom, zip, opcache) + composer | Laravel |
| `web` | `nginx:alpine` | віддає UI на `http://localhost:8011` |
| `worker` | той самий образ, що `app` | `php artisan queue:work --tries=1 --timeout=120` |

- Код монтується volume-ом (dev), SQLite-файл у `./database` - переживає перезапуск.
- `.env` підхоплюється через `env_file`.
- `Makefile` з короткими командами: `make up`, `make down`, `make sh`, `make db-init` (накат `schema.sql`), `make test`, `make lint`, `make sync` (`bench:models:sync`).
- UID/GID користувача прокидаються в образ, щоб файли в volume не ставали root-овими.

## 12. Тестування

- Unit: `ReadabilityExtractor` на збережених HTML-фікстурах, `ReferenceAccuracyCalculator`, `PromptRenderer`, `JsonResponseParser` (обгортки ```json, сміття навколо JSON), метрики, `EloCalculator`, `ConsistencyCalculator`, `RetryPolicy`.
- Feature: `OpenAiCompatibleClient` з `Http::fake()` (200, 429 з Retry-After, 500, timeout), каталоги моделей на фікстурах відповідей кожного провайдера, повний run на фейкових відповідях.
- Опційний smoke-тест на реальних API за прапорцем `BENCH_LIVE_TESTS=1`.

## 13. Етапи

| Етап | Результат |
| --- | --- |
| **1. Каркас** | Docker Compose + Makefile, Laravel, schema.sql, конфіг провайдерів, `OpenAiCompatibleClient`, `bench:models:sync`, `bench:models:ping` |
| **2. Прогони** | сценарії, датасети, імпорт за URL, `bench:run`, rate limiter + retry, черга, автометрики |
| **3. UI** | моделі, сценарії, датасети, розмітка еталону, playground, сторінка прогону, порівняння |
| **4. Оцінка** | точність відносно еталону, ручна оцінка, сліпе A/B + Elo, LLM-as-judge, узгодженість, heatmap |
| **5. Полірування** | експорт, прогноз лімітів, dashboard, документація README |

## 14. Критерії приймання MVP

- `make up && make db-init` піднімає стенд з нуля, UI відкривається на `localhost:8011`.
- `bench:models:sync` підтягує моделі всіх 4 провайдерів при наявності ключів; без ключа провайдер пропускається з попередженням.
- Імпорт 20 URL новинних статей: для звичайних сайтів текст очищено коректно, сторінки з paywall/JS позначені `failed` з причиною і доступні для ручного заповнення.
- На розміченому датасеті порівняльна таблиця показує MAE, within_1, Spearman, bias і top-K по кожній моделі.
- Прогін `digest-article` на датасеті з 10 статей × 5 моделей × 3 повтори завершується без падіння навіть при 429 (з retry або статусом `rate_limited`).
- Для кожної відповіді видно: сирий запит/відповідь, розпарсений JSON, валідність, латентність, токени.
- Порівняльна таблиця показує по моделях: % валідного JSON, % за схемою, p50/p95 latency, error rate, середній score, розкид score, оцінку судді, ручну оцінку, Elo.
- Тести зелені, Pint і PHPStan без помилок.

## 15. Рішення

| Питання | Рішення |
| --- | --- |
| Парсер URL → очищений текст | потрібен, розділ 6.3 |
| Еталонні score від людини | так, розділи 6.4, 7.1.1 |
| Оточення | Docker Compose, розділ 11.1 |
| Модель-суддя за замовчуванням | відкрито, вирішимо після перших прогонів (кандидата обрати за кореляцією з еталоном) |
