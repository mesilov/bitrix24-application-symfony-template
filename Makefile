#!/usr/bin/env make

export COMPOSE_HTTP_TIMEOUT=120
export DOCKER_CLIENT_TIMEOUT=120
export COMPOSE_BAKE=true

# Export current user's UID and GID to be used by Docker for correct file permissions
export UID=$(shell id -u)
export GID=$(shell id -g)

# Default Docker commands, can be overridden if needed (different setups, old docker-compose, CI quirks, etc.)
DOCKER?=docker
DOCKER_COMPOSE?=docker compose

# Environment management
CADDY_ENV ?= dev

# Makefile quality-of-life improvements:
# - Default goal = help
# - Cleaner output (no Entering/Leaving directory noise)
.DEFAULT_GOAL := help
MAKEFLAGS += --no-print-directory

# - Export all vars for easier scripting
# - Set project root path
.EXPORT_ALL_VARIABLES:
ROOT_DIR := $(shell pwd)

%:
	@: # silence

help:
	@echo "-------------------------------------------------------------------------"
	@echo "                   Bitrix24 Application Template                         "
	@echo "-------------------------------------------------------------------------"
	@echo "📁 Init project folder structure"
	@echo "structure-init             - create all folders and set permissions"
	@echo ""
	@echo "🐳 Work with application containers"
	@echo "docker-build               - build containers"
	@echo "docker-up                  - run docker"
	@echo "docker-down                - stop docker"
	@echo "docker-restart             - restart all containers"
	@echo ""
	@echo "📦 Work with composer"
	@echo "composer-install           - install dependencies from composer"
	@echo "composer-update            - update dependencies from composer"
	@echo "composer-dumpautoload      - regenerate composer autoload file"
	@echo "composer                   - run composer and pass arguments"
	@echo ""

	@echo ""

# Load environment variables from .env.local if it exists
ifneq (,$(wildcard .env.local))
    include .env.local
    export
endif

.PHONY: structure-init
structure-init:
	mkdir -p volumes/database-backup
	chmod 0777 volumes/database-backup
	mkdir -p var/log/frankenphp
	chmod 0777 var/log/frankenphp

# work with docker
.PHONY: docker-build
docker-build:
	$(DOCKER_COMPOSE) build

.PHONY: docker-build-prod
docker-build-prod:
	$(DOCKER_COMPOSE) -f compose.yaml -f compose.prod.yaml build

.PHONY: docker-up
docker-up:
	$(DOCKER_COMPOSE) up -d

.PHONY: docker-up-prod
docker-up-prod:
	$(DOCKER_COMPOSE) -f compose.yaml -f compose.prod.yaml up -d

.PHONY: docker-down
docker-down:
	$(DOCKER_COMPOSE) down --remove-orphans

.PHONY: docker-down-prod
docker-down-prod:
	$(DOCKER_COMPOSE) -f compose.yaml -f compose.prod.yaml down --remove-orphans

.PHONY: docker-restart
docker-restart: docker-down docker-up

.PHONY: docker-restart-prod
docker-restart-prod: docker-down-prod docker-up-prod

# work with composer
.PHONY: composer-install
composer-install:
	$(DOCKER_COMPOSE) run --rm php composer install

.PHONY: composer-update
composer-update:
	$(DOCKER_COMPOSE) run --rm php composer update

.PHONY: composer-dumpautoload
composer-dumpautoload:
	$(DOCKER_COMPOSE) run --rm php composer dumpautoload
# call composer with any parameters
# make composer install
# make composer "install --no-dev"
.PHONY: composer
composer:
	$(DOCKER_COMPOSE) run --rm php composer $(filter-out $@,$(MAKECMDGOALS))

.PHONY: dev-app-dump-cache
dev-app-dump-cache:
	make composer-dumpautoload
	$(DOCKER_COMPOSE) run --rm php php bin/console cache:clear
.PHONY: app-migrations-make
app-migrations-make:
	make dev-app-dump-cache
	$(DOCKER_COMPOSE) run --rm php php bin/console make:migration --no-interaction

.PHONY: app-migrations-migrate
app-migrations-migrate:
	make dev-app-dump-cache
	$(DOCKER_COMPOSE) run --rm php php bin/console doctrine:migrations:migrate --no-interaction

pg-backup:
	$(DOCKER_COMPOSE) exec -it database pg_dump --format=c --verbose --file=/backup/cma_database_app_$(shell date "+%Y%m%dT%H%M%Sz%z").custom.pgdump
pg-restore:
	$(DOCKER_COMPOSE) exec -it database pg_restore --format=c --clean --single-transaction --verbose --dbname=${PGDATABASE} /backup/$(filter-out $@,$(MAKECMDGOALS))

.PHONY: validate-caddy-config
validate-caddy-config:
	@echo "🔍 Validating current Caddy configuration..."
	$(DOCKER_COMPOSE) run --rm --no-deps php frankenphp validate --config /etc/frankenphp/Caddyfile

# Monitoring & Debugging commands
.PHONY: logs-all
logs-all:
	@echo "📋 Showing logs from all containers..."
	$(DOCKER_COMPOSE) logs --tail=20

.PHONY: logs-php
logs-php:
	@echo "📋 Showing FrankenPHP logs..."
	$(DOCKER_COMPOSE) logs --tail=50 php

.PHONY: ps
ps:
	@echo "📊 Container status..."
	$(DOCKER_COMPOSE) ps
