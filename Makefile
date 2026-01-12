.PHONY: composer npm artisan

composer:
	docker compose -f docker-compose.prod.yml run --rm tooling composer $(ARGS)

npm:
	docker compose -f docker-compose.prod.yml run --rm tooling npm $(ARGS)

artisan:
	docker compose -f docker-compose.prod.yml exec php php artisan $(ARGS)
