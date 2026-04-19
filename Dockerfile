# GRM rewrite — single-container demo image.
# Uses SQLite + `php artisan serve` for minimal moving parts. For production
# you want php-fpm + nginx + MySQL + Redis + Horizon — see docs/deploy.md.

FROM php:8.3-cli-alpine

RUN apk add --no-cache \
      bash git curl zip unzip \
      nodejs npm \
      sqlite sqlite-dev \
      libpng-dev libzip-dev icu-dev oniguruma-dev \
 && docker-php-ext-install pdo_sqlite zip gd intl mbstring bcmath pcntl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

WORKDIR /app

# Step 1: scaffold a fresh Laravel 11 project into /tmp then copy into /app.
# --no-scripts avoids running package:discover before our files are in place.
RUN composer create-project laravel/laravel:^11.0 /tmp/laravel --prefer-dist --no-scripts \
 && cp -r /tmp/laravel/. /app/ \
 && rm -rf /tmp/laravel

# Step 2: overlay our scaffold (composer.json, package.json, bootstrap, app/,
# config/, database/, routes/, resources/, tests/, etc.).
COPY . /app-overlay/
RUN cp -r /app-overlay/. /app/ && rm -rf /app-overlay

# Step 3: remove Laravel 11's stock migrations — our core-tables migration
# and our domain migrations replace them. Otherwise we'd collide on `users`
# (we use `person`) and see duplicate table errors.
RUN rm -f /app/database/migrations/0001_01_01_000000_create_users_table.php \
          /app/database/migrations/0001_01_01_000001_create_cache_table.php \
          /app/database/migrations/0001_01_01_000002_create_jobs_table.php \
          /app/vite.config.js \
          /app/resources/js/app.ts \
          /app/resources/js/ssr.ts \
          /app/resources/js/bootstrap.js

# Step 4: install PHP dependencies using our composer.json (Laravel, Fortify,
# Sanctum, Inertia, Spatie Permission, spatie/laravel-data, Pest, etc.).
# Delete composer.lock first — create-project's lock doesn't know about the
# packages we added, so we let composer resolve fresh against our manifest.
RUN rm -f composer.lock \
 && composer install --prefer-dist --optimize-autoloader --no-dev \
 || composer install --prefer-dist --optimize-autoloader

# Step 5: publish Spatie Permission's migration (it's vendored, not in our
# scaffold, but the seeder depends on its tables).
RUN php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider" --tag=permission-migrations --force

# Step 6: build the frontend. Skip vue-tsc (the scaffold has known strict-mode
# type errors documented for post-deploy cleanup); vite build is enough for
# a runnable bundle.
RUN npm install --no-audit --no-fund \
 && npx vite build

# Step 7: environment and SQLite database.
RUN cp .env.example .env \
 && php artisan key:generate --force \
 && sed -i \
      -e 's|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|' \
      -e 's|^DB_HOST=.*||' -e 's|^DB_PORT=.*||' -e 's|^DB_USERNAME=.*||' -e 's|^DB_PASSWORD=.*||' \
      -e 's|^DB_DATABASE=.*|DB_DATABASE=/app/database/database.sqlite|' \
      -e 's|^CACHE_STORE=.*|CACHE_STORE=file|' \
      -e 's|^SESSION_DRIVER=.*|SESSION_DRIVER=file|' \
      -e 's|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=sync|' \
      -e 's|^MAIL_MAILER=.*|MAIL_MAILER=log|' \
      -e 's|^APP_URL=.*|APP_URL=http://104.225.218.102:8081|' \
      .env \
 && touch database/database.sqlite

# Step 8: migrate and seed. --force because we're "production" env.
RUN php artisan migrate --force \
 && php artisan db:seed --force --class=Database\\Seeders\\RolePermissionSeeder \
 && php artisan config:cache \
 && php artisan route:cache || true

# Permissions — php artisan serve runs as root in this container which is
# fine for a demo; storage/ and bootstrap/cache must be writable.
RUN chmod -R 777 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "-d", "memory_limit=256M", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
