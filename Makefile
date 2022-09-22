.PHONY: *

CLEAR_CONFIG_CACHE=rm -f storage/app/vars/*
OPTS=

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

build: ## Builds the development image needed to run tests etc.
	docker buildx build --load --target=development --tag=ghcr.io/roave/docbooktool:test-image .

build-tested: ## Builds the image and runs the tests but does not tag it
	docker buildx build --target=tested --progress=plain .

test: build ## Run the unit and integration tests
	docker run --rm --entrypoint=php ghcr.io/roave/docbooktool:test-image vendor/bin/phpunit

cs: build ## Run coding standards checks
	docker run --rm --entrypoint=php ghcr.io/roave/docbooktool:test-image vendor/bin/phpcs

static-analysis: build ## Run the static analysis checks
	docker run --rm --entrypoint=php ghcr.io/roave/docbooktool:test-image vendor/bin/psalm

test-output: ## Write the test fixture outputs to build/ directory - useful for manual visual inspection
	rm -Rf build
	mkdir -p build
	docker buildx build --output=build --target=test-output --tag=ghcr.io/roave/docbooktool:test-image .

production: ## Build and tag a production image
	docker buildx build --load --target=production  --tag=ghcr.io/roave/docbooktool:latest .
