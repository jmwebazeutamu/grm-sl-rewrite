<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        $actorOrgId = $this->user()?->organization_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('person', 'email')->ignore($userId)],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:150'],
            'office_id' => [
                'nullable', 'integer',
                Rule::exists('office', 'id')->where(function ($query) use ($actorOrgId): void {
                    if ($actorOrgId !== null) {
                        $query->where('organization_id', $actorOrgId);
                    }
                }),
            ],
            'organization_id' => ['nullable', 'integer', Rule::exists('organization', 'id')],
        ];
    }
}
