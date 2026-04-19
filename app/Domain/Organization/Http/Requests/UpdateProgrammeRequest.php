<?php

declare(strict_types=1);

namespace App\Domain\Organization\Http\Requests;

use App\Domain\Organization\Enums\ProgrammeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgrammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $orgId = $this->route('organization')?->id ?? $this->route('organization');
        $programmeId = $this->route('programme')?->id ?? $this->route('programme');

        return [
            'name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('programme', 'name')
                    ->where(fn ($q) => $q->where('organization_id', $orgId)->whereNull('deleted_at'))
                    ->ignore($programmeId),
            ],
            'acronym' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::enum(ProgrammeStatus::class)],
        ];
    }
}
