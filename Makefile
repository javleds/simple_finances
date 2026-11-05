.PHONY: dev dev-list dev-test
export SERVICES

dev:
	@node scripts/dev.mts

dev-list:
	@node scripts/dev.mts --list

dev-test:
	@node --test scripts/dev.test.mjs scripts/finance-dev.test.mjs scripts/migrations.test.mjs

.PHONY: chat-config chat-setup dev-chat chat-down
chat-config:
	@node scripts/chat.mjs config
chat-setup:
	@node scripts/chat.mjs setup
dev-chat:
	@node scripts/chat.mjs up
chat-down:
	@node scripts/chat.mjs down

MIGRATION_COMPOSE = docker compose --env-file .env -f docker-compose.yml -f docker-compose.chat.yml -f docker-compose.chat.local.yml --profile chat

.PHONY: migrate migrate-chat migrate-finances
migrate:
	@$(MAKE) migrate-finances
	@$(MAKE) migrate-chat

migrate-chat:
	@$(MIGRATION_COMPOSE) build agent-migrate
	@$(MIGRATION_COMPOSE) up -d --wait agent-postgres
	@$(MIGRATION_COMPOSE) run --rm --no-deps agent-migrate

migrate-finances:
	@bash apps/finance/scripts/migrate.sh
