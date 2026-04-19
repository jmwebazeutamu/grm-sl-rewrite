<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Requests;

use App\Domain\Grievance\Enums\GrievanceState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionGrievanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy-gated at route
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'state' => [
                'required', 'string',
                Rule::enum(GrievanceState::class),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toState(): GrievanceState
    {
        return GrievanceState::from((string) $this->string('state'));
    }
}
