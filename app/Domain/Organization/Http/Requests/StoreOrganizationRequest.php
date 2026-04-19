<?php

declare(strict_types=1);

namespace App\Domain\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:200',
                Rule::unique('organization', 'name')->where('parent_id', $this->input('parent_id')),
            ],
            'acronym' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:organization,id'],
            'grievance_type_ids' => ['array'],
            'grievance_type_ids.*' => ['integer', 'exists:grievance_type,id'],
        ];
    }
}
