<?php

declare(strict_types=1);

namespace App\Domain\Organization\Http\Requests;

use App\Domain\Organization\Enums\ProgrammeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgrammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $orgId = $this->route('organization')?->id ?? $this->route('organization');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('programme', 'name')->where(
                    fn ($q) => $q->where('organization_id', $orgId)->whereNull('deleted_at'),
                ),
            ],
            'acronym' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::enum(ProgrammeStatus::class)],
        ];
    }
}
