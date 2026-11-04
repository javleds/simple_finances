.PHONY: dev dev-list dev-test
export SERVICES

dev:
	@node scripts/dev.mts

dev-list:
	@node scripts/dev.mts --list

dev-test:
	@node --test scripts/dev.test.mjs scripts/finance-dev.test.mjs

.PHONY: chat-config chat-setup dev-chat chat-down
chat-config:
	@node scripts/chat.mjs config
chat-setup:
	@node scripts/chat.mjs setup
dev-chat:
	@node scripts/chat.mjs up
chat-down:
	@node scripts/chat.mjs down
