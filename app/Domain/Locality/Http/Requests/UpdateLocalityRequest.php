<?php

declare(strict_types=1);

namespace App\Domain\Locality\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $localityId = (int) $this->route('locality')->id;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('locality', 'name')
                    ->where('section_id', $this->integer('section_id'))
                    ->ignore($localityId),
            ],
            'section_id' => ['required', 'integer', 'exists:section,id'],
        ];
    }
}
