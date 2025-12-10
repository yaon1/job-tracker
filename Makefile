.PHONY: dev api web test lint up

api:
	cd api && composer serve

web:
	cd web && npm run dev

test:
	cd api && vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress
	cd web && npm run typecheck

lint:
	cd api && vendor/bin/php-cs-fixer fix --dry-run --diff
	cd web && npm run lint && npm run format:check

up:
	docker compose up -d --build
