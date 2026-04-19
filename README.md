# GRM-SL Rewrite Workspace

Laravel 11 + Vue 3 + Inertia 2 rewrite of the legacy GRM Sierra Leone application.

## Why a rewrite

The legacy app (`../`) runs on Laravel 8 and Vue 2 — both EOL with no security patches. Controllers and models have grown past 1,000 lines, there are two parallel Vue apps sharing no code, and the core grievance domain has zero automated tests. Incremental upgrades would take longer than a clean rebuild that preserves the data model and business rules.

## Stack (locked)

| Layer | Version | Why |
|---|---|---|
| PHP | 8.3 | Laravel 11 minimum; typed properties and readonly support. |
| Laravel | 11.x | Current LTS-adjacent release; streamlined bootstrap. |
| Node | 20 LTS | Long-term Vite/TypeScript support. |
| Vue | 3.4 | Composition API + `<script setup>` + TypeScript. |
| Inertia | 2.x | Works with Vue 3; ships polling, prefetch, deferred props. |
| Vite | 5 | Replaces Laravel Mix. |
| Tailwind | 3.4 | No AdminLTE/Bootstrap sprawl. |
| Sanctum | 4 | API token auth. |
| Fortify | 1.x | Username/password against `person` table (legacy-compatible). |
| Spatie Permission | 6 | RBAC. |
| Pest | 3 | PHP tests. |
| Vitest | 1 | JS/Vue unit tests. |
| Laravel Dusk | 8 | Inertia E2E. |
| PHPStan (Larastan) | 2 | Level 6 from day one. |

No AdminLTE, no BootstrapVue, no Laravel Mix, no second frontend app.

## Architecture

```
rewrite/
├── app/
│   ├── Domain/              # Business domain, one dir per bounded context
│   │   ├── Locality/        # reference module template — Phase 2 starts here
│   │   │   ├── Actions/     # Single-purpose invokables (CreateLocality, etc.)
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   ├── Requests/
│   │   │   │   └── Resources/
│   │   │   ├── Models/
│   │   │   ├── Policies/
│   │   │   ├── Services/    # Multi-step orchestration
│   │   │   └── Tests/       # Feature + unit per module
│   │   ├── Grievance/       # Phase 3
│   │   ├── Organization/
│   │   ├── Identity/        # users, roles, permissions
│   │   └── Notification/    # email + SMS + in-app fan-out
│   ├── Http/                # app-wide middleware, shared requests
│   ├── Providers/
│   └── Support/             # framework-level helpers (no business logic)
├── resources/js/
│   ├── Layouts/             # AppLayout, AuthLayout, PublicLayout
│   ├── Pages/               # mirrors app/Domain/*/Http/Controllers structure
│   ├── Components/          # shared UI
│   ├── composables/         # useForm, useFilters, etc.
│   └── lib/                 # api client, sanitizer, formatters
└── tests/                   # app-wide integration + Dusk
```

### Non-negotiables

1. **Thin controllers.** Controllers receive a `FormRequest`, dispatch an Action, return a Resource or Inertia render. Business logic never lives in a controller.
2. **Policies on every mutating route.** Route middleware: `can:action,resource`. CI fails if a route is declared without one.
3. **Validation at the boundary.** Every controller method takes a typed `FormRequest`. No `$request->all()`.
4. **Queues for side effects.** Email, SMS, Excel export, reCAPTCHA verification all dispatched to Redis queue.
5. **Typed props to Vue.** `HandleInertiaRequests` contract is defined in TypeScript (`resources/js/types/inertia.d.ts`).
6. **One frontend.** Public vs admin is a layout concern, not a separate build.
7. **Tests required.** PR cannot merge without passing Pest + Vitest + PHPStan lvl 6 + Pint + ESLint.

## First-time setup

This directory contains only the decisions — framework scaffolding must be materialized locally.

```bash
cd rewrite

# One-time: install framework files
composer create-project laravel/laravel:^11.0 . --prefer-dist --no-interaction --remove-vcs
# Accept prompts to overwrite our composer.json/package.json — then restore ours:
git checkout composer.json package.json vite.config.ts tsconfig.json

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run dev
```

## Data migration

Legacy tables keep their names (`person`, `grievance`, `grievance_*`, `organization`, `region`, …). Models set `protected $table` explicitly where names don't match Laravel convention. Migration scripts live in `database/migrations/legacy/` and run once, in order, against a read replica of prod.

## Phase roadmap

See `docs/roadmap.md`.
