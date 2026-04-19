<?php

declare(strict_types=1);

namespace App\Domain\Reference\Http\Controllers;

use App\Domain\Reference\Models\Priority;

class PriorityController extends ReferenceController
{
    protected function model(): string
    {
        return Priority::class;
    }

    protected function key(): string
    {
        return 'priorities';
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueName($id)],
            'description' => ['nullable', 'string'],
            'ranking' => ['nullable', 'integer', 'between:0,100'],
            'response_time_hours' => ['nullable', 'integer', 'min:1'],
            'resolution_time_hours' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
