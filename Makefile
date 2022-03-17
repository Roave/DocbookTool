.PHONY: *

CLEAR_CONFIG_CACHE=rm -f storage/app/vars/*
OPTS=

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

build: ## Builds the development image needed to run tests etc.
	docker buildx build --load --target=development --tag=test-image .

test: build ## Run the unit and integration tests
	docker run --rm --entrypoint=php test-image vendor/bin/phpunit

cs: build ## Run coding standards checks
	docker run --rm --entrypoint=php test-image vendor/bin/phpcs

static-analysis: build ## Run the static analysis checks
	docker run --rm --entrypoint=php test-image vendor/bin/psalm
