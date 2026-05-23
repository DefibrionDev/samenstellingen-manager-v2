.PHONY: help install test stan lint fix check clean ui ui-up ui-down ui-logs ui-build ui-test web-install

help: ## Toon beschikbare targets
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Composer install (dev)
	composer install

test: ## Draai PHPUnit
	vendor/bin/phpunit

stan: ## Draai PHPStan (level 8)
	vendor/bin/phpstan analyse

lint: ## Draai PHP CS Fixer (check-only)
	vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Pas PHP CS Fixer toe
	vendor/bin/php-cs-fixer fix

check: lint stan test ## Lint + stan + test (CI-gate)

clean: ## Verwijder caches en tmp-bestanden
	rm -rf .phpunit.cache .phpunit.result.cache .php-cs-fixer.cache tmp/*.sqlite tmp/*.log

ui-up: ## Start nginx + php-fpm containers (poort 8080)
	docker compose up -d

ui-down: ## Stop de UI-containers
	docker compose down

ui-logs: ## Volg de UI-container-logs
	docker compose logs -f

web-install: ## Installeer frontend-dependencies
	npm --prefix web install

ui-build: ## Bouw de frontend (output naar public/)
	npm --prefix web run build

ui-test: ## Draai frontend-tests
	npm --prefix web run test

ui: ## Start backend (Docker) + Vite dev-server parallel
	docker compose up -d
	npm --prefix web run dev
