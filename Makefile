.PHONY: dev dev-list dev-test
export SERVICES

dev:
	@node scripts/dev.mts

dev-list:
	@node scripts/dev.mts --list

dev-test:
	@node --test scripts/dev.test.mjs
