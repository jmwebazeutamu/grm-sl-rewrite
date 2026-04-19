<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Requests;

use App\Domain\Grievance\Enums\ActionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(ActionType::class)],
            'body' => ['required', 'string', 'max:10000'],
            'assigned_to_id' => ['nullable', 'integer', 'exists:person,id'],
        ];
    }

    public function toDomain(): array
    {
        return [
            'type' => ActionType::from((string) $this->string('type')),
            'body' => (string) $this->string('body'),
            'assigned_to_id' => $this->integer('assigned_to_id') ?: null,
        ];
    }
}
