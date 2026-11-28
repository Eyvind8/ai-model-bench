DC  = docker compose
RUN = $(DC) run --rm --no-deps -T app
DB  = database/database.sqlite

export UID := $(shell id -u)
export GID := $(shell id -g)

.PHONY: help build up down restart logs sh db-init db-reset test lint stan check sync discover ping models limits runs

help: ## Show commands
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

build: ## Build PHP image
	$(DC) build

up: ## Start app, nginx (localhost:8011) and queue worker
	$(DC) up -d

down: ## Stop containers
	$(DC) down

restart: ## Restart containers (picks up .env / config changes in worker)
	$(DC) restart

logs: ## Follow container logs
	$(DC) logs -f --tail=100

sh: ## Shell in app container
	$(DC) exec app sh

db-init: ## Create SQLite DB from database/schema.sql if missing, then sync models
	@if [ -f $(DB) ] && [ -s $(DB) ]; then echo "$(DB) exists, skip (use make db-reset)"; \
	else $(RUN) sh -c 'sqlite3 $(DB) < database/schema.sql' && echo "created $(DB)"; fi
	$(MAKE) sync

db-reset: ## DROP all data: recreate DB from schema.sql
	@read -p "Delete $(DB) with all results? [y/N] " ans && [ "$$ans" = "y" ]
	rm -f $(DB)
	$(MAKE) db-init

test: ## Run tests
	$(RUN) php artisan test --compact

lint: ## Fix code style (Pint)
	$(RUN) vendor/bin/pint

stan: ## Static analysis (Larastan)
	$(RUN) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

check: lint stan test ## Lint + stan + tests

sync: ## Sync models (config/bench.php) and scenarios (config/scenarios.php)
	$(RUN) php artisan bench:models:sync
	$(RUN) php artisan bench:scenarios:sync

discover: ## Sync + discover models from provider catalog APIs
	$(RUN) php artisan bench:models:sync --discover

ping: ## Ping enabled models
	$(RUN) php artisan bench:models:ping

models: ## List models
	$(RUN) php artisan bench:models:list

limits: ## Remaining free-tier quota of enabled models
	$(RUN) php artisan bench:limits

runs: ## Recent runs
	$(RUN) php artisan bench:runs
