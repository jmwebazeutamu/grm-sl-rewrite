.PHONY: install dev build test stan pint ci fresh

install:
	composer install
	npm ci

dev:
	php artisan serve & npm run dev

build:
	npm run build

test:
	vendor/bin/pest --parallel

stan:
	vendor/bin/phpstan analyse --memory-limit=2G

pint:
	vendor/bin/pint

ci: pint stan test
	npm run lint && npm run typecheck && npm run test && npm run build

fresh:
	php artisan migrate:fresh --seed
