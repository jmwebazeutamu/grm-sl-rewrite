<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClosureActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'closure_comment' => ['required', 'string', 'min:10', 'max:2000'],
            'outcome' => ['required', 'in:satisfied,dissatisfied'],
        ];
    }
}
