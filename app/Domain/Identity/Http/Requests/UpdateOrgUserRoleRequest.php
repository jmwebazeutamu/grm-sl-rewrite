<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateOrgUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        if ($this->user()?->hasRole('super-admin')) {
            $allowed = Role::where('name', '!=', 'super-admin')
                ->pluck('name')
                ->toArray();
        } else {
            $allowed = ['organization-officer', 'grm-officer'];
        }

        return [
            'role' => ['required', 'string', Rule::in($allowed)],
        ];
    }
}
