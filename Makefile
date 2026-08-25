.PHONY: build install shell validate lint test test-coverage ci audit bench
DOCKER_COMPOSE ?= docker compose
PHP = $(DOCKER_COMPOSE) run --rm php
build:
	$(DOCKER_COMPOSE) build
install: build
	$(PHP) composer install
shell:
	$(DOCKER_COMPOSE) run --rm php bash
validate:
	$(PHP) composer validate --strict
lint:
	$(PHP) composer lint
test:
	$(PHP) composer test
test-coverage:
	$(PHP) composer test:coverage
audit:
	$(PHP) composer audit
bench:
	$(PHP) composer bench
ci:
	$(PHP) composer ci
