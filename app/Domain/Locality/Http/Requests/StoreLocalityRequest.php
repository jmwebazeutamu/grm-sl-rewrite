<?php

declare(strict_types=1);

namespace App\Domain\Locality\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy enforced at route middleware
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('locality', 'name')->where('section_id', $this->integer('section_id')),
            ],
            'section_id' => ['required', 'integer', 'exists:section,id'],
        ];
    }
}
