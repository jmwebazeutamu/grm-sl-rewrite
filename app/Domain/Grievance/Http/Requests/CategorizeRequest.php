<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategorizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(['corruption', 'administrative'])],
            'classified_organization_id' => ['required', 'integer', 'exists:organization,id'],
        ];
    }
}
