<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignGrievanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route policy gate handles it
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'officer_id' => ['nullable', 'integer', 'exists:person,id'],
        ];
    }
}
