<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
