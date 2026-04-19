<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Http\Requests\SaveRoleRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => Role::withCount('permissions')->orderBy('name')->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'permission_count' => $r->permissions_count,
                    'user_count' => \DB::table('model_has_roles')
                        ->where('role_id', $r->id)
                        ->where('model_type', \App\Domain\Identity\Models\User::class)
                        ->count(),
                    'is_protected' => in_array($r->name, ['super-admin', 'complainant'], true),
                ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => null,
            'selected' => [],
            'grouped_permissions' => $this->groupedPermissions(),
        ]);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();

        $role = Role::create(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        $role->load('permissions:id,name');

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_protected' => in_array($role->name, ['super-admin', 'complainant'], true),
            ],
            'selected' => $role->permissions->pluck('name'),
            'grouped_permissions' => $this->groupedPermissions(),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $request->validated();

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }

    /**
     * Groups permissions by resource (the segment before the first dot).
     * `grievance.viewAny`, `grievance.transition` → one group "grievance".
     *
     * @return Collection<int, array{resource: string, permissions: list<string>}>
     */
    private function groupedPermissions(): Collection
    {
        return Permission::orderBy('name')->pluck('name')
            ->groupBy(fn (string $name) => explode('.', $name, 2)[0])
            ->map(fn ($names, $resource) => [
                'resource' => (string) $resource,
                'permissions' => $names->values()->all(),
            ])
            ->values();
    }
}
