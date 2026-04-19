<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $role = $this->route('role');
        $id = is_object($role) ? $role->id : null;

        return [
            'name' => [
                'required', 'string', 'max:100',
                'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('roles', 'name')->ignore($id),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
