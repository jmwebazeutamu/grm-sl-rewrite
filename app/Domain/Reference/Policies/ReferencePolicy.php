<?php

declare(strict_types=1);

namespace App\Domain\Reference\Policies;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared policy for reference/lookup resources. Concrete subclasses only
 * declare the permission prefix (e.g. "grievance_type", "priority").
 */
abstract class ReferencePolicy
{
    abstract protected function prefix(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->prefix().'.viewAny');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->prefix().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->prefix().'.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->prefix().'.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->prefix().'.delete');
    }
}
