<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrgClassifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'org_classification_id' => ['required', 'integer', 'exists:org_grievance_types,id'],
        ];
    }
}
