.PHONY: help install test stan lint fix check clean

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
