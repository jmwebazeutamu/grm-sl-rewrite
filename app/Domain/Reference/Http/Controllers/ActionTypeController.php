<?php

declare(strict_types=1);

namespace App\Domain\Reference\Http\Controllers;

use App\Domain\Reference\Models\ActionType;

class ActionTypeController extends ReferenceController
{
    protected function model(): string
    {
        return ActionType::class;
    }

    protected function key(): string
    {
        return 'action-types';
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueName($id)],
            'description' => ['nullable', 'string'],
        ];
    }
}
