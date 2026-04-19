# Module template

Every domain module follows the Locality layout. Copy its directory, rename, and fill in.

```
app/Domain/{ModuleName}/
├── Actions/             # Single-purpose invokables — one class, one method (__invoke).
│   ├── Create{Entity}.php
│   ├── Update{Entity}.php
│   └── Delete{Entity}.php      # only when delete has side effects; else controller calls $model->delete()
├── Http/
│   ├── Controllers/            # Thin. FormRequest in, Resource/Redirect out. No business logic.
│   │   └── {Entity}Controller.php
│   ├── Requests/               # One per mutating endpoint.
│   │   ├── Store{Entity}Request.php
│   │   └── Update{Entity}Request.php
│   └── Resources/
│       └── {Entity}Resource.php
├── Models/                     # Lean Eloquent models. No business methods.
│   └── {Entity}.php
├── Policies/                   # REQUIRED. One policy per top-level resource.
│   └── {Entity}Policy.php
├── Services/                   # Multi-step orchestration (e.g. GrievanceWorkflow).
│   └── (optional)
└── Tests/
    └── (Feature tests live in /tests/Feature/{Module}/; unit in /tests/Unit/{Module}/)
```

## Rules

1. **Controllers ≤ 100 lines.** If you're near the limit, extract an Action or Service.
2. **Policies are mandatory.** Register in `AuthServiceProvider::$policies`. Routes must declare `can:`.
3. **Form requests are mandatory.** No `$request->all()`, ever.
4. **Resources for JSON output.** Never return Eloquent models directly from a controller.
5. **Actions are invokables.** `public function __invoke(...)`. Testable in isolation.
6. **No cross-domain imports inside `Models/`.** If module A needs B's data, import via Service/Action at the Http layer.
7. **Tests for every endpoint.** Forbidden path + happy path minimum.

## Route pattern

```php
Route::resource('entities', EntityController::class)->middleware([
    'index'   => 'can:viewAny,App\Domain\Module\Models\Entity',
    'create'  => 'can:create,App\Domain\Module\Models\Entity',
    'store'   => 'can:create,App\Domain\Module\Models\Entity',
    'show'    => 'can:view,entity',
    'edit'    => 'can:update,entity',
    'update'  => 'can:update,entity',
    'destroy' => 'can:delete,entity',
]);
```

## Permission naming

`{resource}.{ability}` — e.g. `grievance.create`, `locality.delete`. Seeded in `RolePermissionSeeder`.
