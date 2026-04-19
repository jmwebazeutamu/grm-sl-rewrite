# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

GRM (Grievance Response Management) System for Sierra Leone. Full-stack Laravel 8 + Inertia.js + Vue 2 application with modular architecture using `nwidart/laravel-modules`.

## Build & Development Commands

```bash
# Install dependencies
composer install
npm install

# Asset compilation
npm run dev           # Development build
npm run watch         # Watch mode
npm run hot           # Hot module replacement
npm run prod          # Production build

# Development server
php artisan serve

# Database
php artisan migrate
php artisan migrate:fresh --seed

# Testing
./vendor/bin/phpunit                              # All tests
./vendor/bin/phpunit tests/Unit                    # Unit tests
./vendor/bin/phpunit tests/Feature                 # Feature tests
./vendor/bin/phpunit --filter=TestClassName         # Single test class
./vendor/bin/phpunit --filter=test_method_name      # Single test method

# Module commands
php artisan module:list                            # List all modules
php artisan module:make ModuleName                 # Create new module
php artisan module:migrate ModuleName              # Run module migrations
```

## Architecture

### Modular Structure (nwidart/laravel-modules)

The application is organized into self-contained modules under `Modules/`. Each module has its own controllers, models (in `Entities/`), migrations, routes, Vue components, and tests. Key domain modules:

- **Grm** — Core GRM functionality
- **GrmGrievance** — Grievance case management
- **GrmOrganization** — Organization management
- **GrmLocality** — Location/locality management
- **RolePermission** — Role-based access control (Spatie)
- **User** — User management
- **Dashboard** — Dashboard views
- **Content/Block/Layout** — CMS-style page building
- **Media** — File/media management
- **Notification/Message** — Messaging and notifications
- **Taxonomy** — Categorization system
- **Field** — Dynamic form field definitions

Module status (enabled/disabled) is tracked in `modules_statuses.json`.

### Two Separate Frontend Apps

- **Backend admin**: `resources/js/` → compiles to `public/backend/` (Inertia.js + Vue 2)
- **Public frontend**: `resources/jsFrontend/` → compiles to `public/frontend/`
- Build configs: `webpack.backend.mix.js` and `webpack.frontend.mix.js`

### Authentication & Authorization

- Laravel Jetstream + Fortify for auth scaffolding
- Sanctum for API token authentication
- Spatie `laravel-permission` for roles and permissions
- API controllers in `app/Http/Controllers/API/`

### Data Layer Conventions

- Models use **EloquentFilter** (`ModelFilters/`) for query filtering
- **Searchable** trait for full-text search on models
- **Eloquent Sluggable** for URL-friendly slugs
- The `User` model maps to the `person` database table (not `users`)
- Model observers in `Observers/` directories for lifecycle hooks

### Key Patterns

- Module models live in `Modules/<Name>/Entities/` (not `Models/`)
- Helper functions in `app/Helpers/Helpers.php`
- Custom validation rules in `app/Rules/`
- Reusable traits in `app/Traits/` and per-module `Traits/`
- Routes split across `routes/web.php`, `routes/api.php`, and per-module `Routes/`

## Configuration Notes

- Timezone: `Africa/Freetown`
- Session driver: `database`
- Queue: `sync` (synchronous)
- Code style: StyleCI with Laravel preset
- PSR-4 namespaces: `App\` → `app/`, `Modules\` → `Modules/`
