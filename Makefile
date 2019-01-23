.DEFAULT_GOAL := help
SHELL := /bin/bash

.PHONY: build cs cs-ci it install me-cry test test-ci

## -----

## Initialize a project from scratch
install: build

## PHP specific build
build:
	composer validate
	composer install

## Apply coding standard
cs:
	vendor/bin/php-cs-fixer fix --config=.php_cs --diff --verbose

## Check coding standard
cs-ci:
	vendor/bin/php-cs-fixer fix --config=.php_cs --diff --verbose --dry-run --stop-on-violation

## Perform precommit task (build translation, cs, test, ...)
it: build cs test

## alias for "it" ;)
me-cry: it

## Run application unit tests
test-unit:
	vendor/bin/phpunit --testsuite unit

## Run application functional tests
test-functional:
	vendor/bin/phpunit --testsuite functional --no-coverage

## Run application tests
test: test-unit test-functional

## Run CI-compliant application tests
test-ci: test

## -----

# APPLICATION
APPLICATION := $(shell (cat package.json 2>/dev/null || cat composer.json) | grep "\"name\"" | head -1 | cut -d\" -f 4 )

# COLORS
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
WHITE  := $(shell tput -Txterm setaf 7)
RESET  := $(shell tput -Txterm sgr0)

TARGET_MAX_CHAR_NUM=20
## Show this help
help:
	@echo '# ${YELLOW}${APPLICATION}${RESET}'
	@echo ''
	@echo 'Usage:'
	@echo '  ${YELLOW}make${RESET} ${GREEN}<target>${RESET}'
	@echo ''
	@echo 'Targets:'
	@awk '/^[a-zA-Z\-\_0-9]+:/ { \
		helpMessage = match(lastLine, /^## (.*)/); \
		if (helpMessage) { \
			helpCommand = substr($$1, 0, index($$1, ":")); \
			gsub(":", " ", helpCommand); \
			helpMessage = substr(lastLine, RSTART + 3, RLENGTH); \
			printf "  ${YELLOW}%-$(TARGET_MAX_CHAR_NUM)s${RESET} ${GREEN}%s${RESET}\n", helpCommand, helpMessage; \
		} \
	} \
	{ lastLine = $$0 }' $(MAKEFILE_LIST) | sort