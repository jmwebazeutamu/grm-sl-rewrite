<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Events\UserRoleAssigned;
use App\Domain\Identity\Http\Requests\StoreUserRequest;
use App\Domain\Identity\Http\Requests\UpdateUserRolesRequest;
use App\Domain\Identity\Http\Resources\UserResource;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('username'),
            ])
            ->allowedSorts(['name', 'created_at'])
            ->defaultSort('name')
            ->with('roles:id,name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => UserResource::collection($users),
            'filters' => $request->only('filter'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', [
            'roles' => Role::orderBy('name')->pluck('name'),
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request, InviteUser $invite, UserPolicy $policy): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        // Reject any role the actor isn't allowed to grant.
        foreach ($data['roles'] ?? [] as $role) {
            abort_unless($policy->grantRole($request->user(), $role), 403);
        }

        $user = $invite($data, $request->user());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User invited. A password-set email has been sent.');
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('Admin/Users/Show', [
            'user' => UserResource::make($user->load('roles:id,name')),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function updateRoles(
        UpdateUserRolesRequest $request,
        User $user,
        UserPolicy $policy,
    ): RedirectResponse {
        $this->authorize('assignRoles', $user);

        $roles = $request->validated()['roles'] ?? [];

        foreach ($roles as $role) {
            abort_unless($policy->grantRole($request->user(), $role), 403);
        }

        // Super-admins can't be stripped of super-admin by a non-super-admin.
        if (
            $user->hasRole('super-admin')
            && ! in_array('super-admin', $roles, true)
            && ! $request->user()->hasRole('super-admin')
        ) {
            abort(403, 'Only a super-admin can remove the super-admin role.');
        }

        $user->syncRoles($roles);

        UserRoleAssigned::dispatch($user, $roles, $request->user());

        return back()->with('success', 'Roles updated.');
    }
}
