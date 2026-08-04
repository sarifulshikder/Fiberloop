# Fiberloop Docker Commands

.PHONY: up down restart build logs shell db-shell test pint

# Start all containers
up:
	docker-compose up -d

# Stop all containers
down:
	docker-compose down

# Restart all containers
restart:
	docker-compose restart

# Build containers
build:
	docker-compose build --no-cache

# View logs
logs:
	docker-compose logs -f

# Logs for app service
logs-app:
	docker-compose logs -f app

# Logs for queue service
logs-queue:
	docker-compose logs -f queue

# Enter app container shell
shell:
	docker-compose exec app sh

# Enter app container as root
shell-root:
	docker-compose exec -u root app sh

# Open PHP shell in app container
php-shell:
	docker-compose exec app php -a

# Run Composer commands
composer:
	docker-compose exec app composer $(args)

# Run Artisan commands
artisan:
	docker-compose exec app php artisan $(args)

# Run migrations
migrate:
	docker-compose exec app php artisan migrate

# Run migrations with seed
migrate-seed:
	docker-compose exec app php artisan migrate --seed

# Run database seeders
seed:
	docker-compose exec app php artisan db:seed

# Connect to PostgreSQL
db-shell:
	docker-compose exec postgres psql -U fiberloop -d fiberloop

# Run PHPUnit/Pest tests
test:
	docker-compose exec app composer test

# Run Pint
pint:
	docker-compose exec app ./vendor/bin/pint

# Run Pint with test mode (no changes)
pint-test:
	docker-compose exec app ./vendor/bin/pint --test

# Install PHP dependencies
composer-install:
	docker-compose exec app composer install --no-interaction

# Update PHP dependencies
composer-update:
	docker-compose exec app composer update

# Generate Laravel key
key-generate:
	docker-compose exec app php artisan key:generate

# Clear Laravel cache
cache-clear:
	docker-compose exec app php artisan cache:clear

# Clear config cache
config-clear:
	docker-compose exec app php artisan config:clear

# Clear view cache
view-clear:
	docker-compose exec app php artisan view:clear

# Clear all caches
clear:
	docker-compose exec app php artisan cache:clear && \
	docker-compose exec app php artisan config:clear && \
	docker-compose exec app php artisan view:clear && \
	docker-compose exec app php artisan route:clear

# Start queue worker
queue:
	docker-compose up -d queue

# Stop queue worker
queue-down:
	docker-compose stop queue

# View queue logs
queue-logs:
	docker-compose logs -f queue

# Build and start fresh
fresh:
	docker-compose down -v
	docker-compose build --no-cache
	docker-compose up -d

# Run all setup commands
setup: build up migrate key-generate
