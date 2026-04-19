<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Controllers\Admin;

use App\Domain\Identity\Actions\CreateUser;
use App\Domain\Identity\Actions\DeactivateUser;
use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Actions\ReactivateUser;
use App\Domain\Identity\Actions\ResetUserPasswordByEmail;
use App\Domain\Identity\Actions\ResetUserPasswordDirect;
use App\Domain\Identity\Actions\UpdateUserDetails;
use App\Domain\Identity\Actions\UpdateUserRole;
use App\Domain\Identity\Http\Requests\CreateUserRequest;
use App\Domain\Identity\Http\Requests\InviteUserRequest;
use App\Domain\Identity\Http\Requests\SetPasswordRequest;
use App\Domain\Identity\Http\Requests\UpdateOrgUserRoleRequest;
use App\Domain\Identity\Http\Requests\UpdateUserDetailsRequest;
use App\Domain\Identity\Http\Resources\UserResource;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Organization\Models\Office;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrgUserController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $query = User::query()->with(['roles:id,name', 'organization:id,name,acronym']);
        if ($actor->organization_id !== null) {
            $query->where('organization_id', $actor->organization_id);
        }

        return Inertia::render('Identity/Users/Index', [
            'users' => UserResource::collection($query->orderBy('name')->paginate(25)->withQueryString()),
            'organization_id' => $actor->organization_id,
            'is_super_admin' => $actor->hasRole('super-admin'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        $actor = $request->user();
        $actorOrgId = $actor->organization_id;
        $isSuperAdmin = $actor->hasRole('super-admin');

        $offices = $actorOrgId === null
            ? Office::orderBy('name')->get(['id', 'name'])
            : Office::where('organization_id', $actorOrgId)->orderBy('name')->get(['id', 'name']);

        $allowedRoles = $isSuperAdmin
            ? ['super-admin', 'acc-reviewer', 'grm-data-operator', 'org-admin', 'grm-officer', 'organization-officer', 'complainant']
            : ['organization-officer', 'grm-officer'];

        return Inertia::render('Identity/Users/Create', [
            'default_role' => 'organization-officer',
            'allowed_roles' => $allowedRoles,
            'offices' => $offices,
            'organizations' => $isSuperAdmin
                ? \App\Domain\Organization\Models\Organization::orderBy('name')->get(['id', 'name'])
                : [],
            'is_super_admin' => $isSuperAdmin,
            'actor_organization_id' => $actorOrgId,
        ]);
    }

    public function invite(InviteUserRequest $request, InviteUser $invite, UserPolicy $policy): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);
        abort_unless($policy->grantRole($request->user(), $role), 403);

        $actor = $request->user();
        $orgId = $actor->hasRole('super-admin')
            ? ($data['organization_id'] ?? $actor->organization_id)
            : $actor->organization_id;
        unset($data['organization_id']);

        $invite([
            ...$data,
            'organization_id' => $orgId,
            'roles' => [$role],
        ], $actor);

        return redirect()->route('admin.org.users.index')->with('success', 'Invite sent.');
    }

    public function store(CreateUserRequest $request, CreateUser $create, UserPolicy $policy): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);
        abort_unless($policy->grantRole($request->user(), $role), 403);

        $actor = $request->user();
        $orgId = $actor->hasRole('super-admin')
            ? ($data['organization_id'] ?? $actor->organization_id)
            : $actor->organization_id;
        unset($data['organization_id']);

        $create([
            ...$data,
            'organization_id' => $orgId,
            'roles' => [$role],
        ], $actor);

        return redirect()->route('admin.org.users.index')->with('success', 'User created.');
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);
        $actor = $request->user();

        return Inertia::render('Identity/Users/Show', [
            'user' => UserResource::make($user->load(['roles:id,name', 'organization:id,name,acronym', 'office:id,name'])),
            'capabilities' => [
                'can_edit' => $actor->can('update', $user),
                'can_assign_roles' => $actor->can('assignRoles', $user),
                'can_reset_password' => $actor->can('resetPassword', $user),
                'can_deactivate' => $actor->can('deactivate', $user),
                'can_reassign_org' => $actor->hasRole('super-admin'),
            ],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);
        $actor = $request->user();
        $actorOrgId = $actor->organization_id;
        $isSuperAdmin = $actor->hasRole('super-admin');

        $offices = $actorOrgId !== null
            ? Office::where('organization_id', $actorOrgId)->orderBy('name')->get(['id', 'name'])
            : Office::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Identity/Users/Edit', [
            'user' => UserResource::make($user->load(['roles:id,name', 'organization:id,name,acronym'])),
            'offices' => $offices,
            'organizations' => $isSuperAdmin
                ? \App\Domain\Organization\Models\Organization::orderBy('name')->get(['id', 'name'])
                : [],
            'is_super_admin' => $isSuperAdmin,
        ]);
    }

    public function update(UpdateUserDetailsRequest $request, User $user, UpdateUserDetails $action): RedirectResponse
    {
        $this->authorize('update', $user);
        $action($user, $request->validated());

        return redirect()->route('admin.org.users.show', $user)->with('success', 'Details updated.');
    }

    public function editRole(Request $request, User $user): Response
    {
        $this->authorize('assignRoles', $user);

        $actor = $request->user();
        $isSuperAdmin = $actor->hasRole('super-admin');

        $allowedRoles = $isSuperAdmin
            ? \Spatie\Permission\Models\Role::where('name', '!=', 'super-admin')
                ->orderBy('name')
                ->pluck('name')
                ->toArray()
            : ['organization-officer', 'grm-officer'];

        return Inertia::render('Identity/Users/EditRole', [
            'user' => UserResource::make($user->load('roles:id,name')),
            'allowed_roles' => $allowedRoles,
            'is_self' => $actor->id === $user->id,
            'is_super_admin' => $isSuperAdmin,
        ]);
    }

    public function updateRole(UpdateOrgUserRoleRequest $request, User $user, UpdateUserRole $update, UserPolicy $policy): RedirectResponse
    {
        $this->authorize('assignRoles', $user);
        abort_if($request->user()->id === $user->id, 403, 'Cannot change your own role.');
        $role = $request->validated()['role'];
        abort_unless($policy->grantRole($request->user(), $role), 403);
        $update($user, [$role], $request->user());

        return redirect()->route('admin.org.users.show', $user)->with('success', 'Role updated.');
    }

    public function resetPassword(User $user): Response
    {
        $this->authorize('resetPassword', $user);

        return Inertia::render('Identity/Users/ResetPassword', [
            'user' => UserResource::make($user),
        ]);
    }

    public function sendResetEmail(User $user, ResetUserPasswordByEmail $action): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        try {
            $action($user);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Password reset email sent.');
    }

    public function setPassword(SetPasswordRequest $request, User $user, ResetUserPasswordDirect $action): RedirectResponse
    {
        $this->authorize('resetPassword', $user);
        $action($user, $request->validated()['password']);

        return redirect()->route('admin.org.users.show', $user)->with('success', 'Password updated.');
    }

    public function deactivate(User $user, DeactivateUser $action): RedirectResponse
    {
        $this->authorize('deactivate', $user);
        $action($user, request()->user());

        return redirect()->route('admin.org.users.index')->with('success', "{$user->name} has been deactivated.");
    }

    public function reactivate(User $user, ReactivateUser $action): RedirectResponse
    {
        $this->authorize('deactivate', $user);
        $action($user, request()->user());

        return redirect()->route('admin.org.users.index')->with('success', "{$user->name} has been reactivated.");
    }
}
