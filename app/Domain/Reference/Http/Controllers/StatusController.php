<?php

declare(strict_types=1);

namespace App\Domain\Reference\Http\Controllers;

use App\Domain\Reference\Models\Status;

class StatusController extends ReferenceController
{
    protected function model(): string
    {
        return Status::class;
    }

    protected function key(): string
    {
        return 'statuses';
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueName($id)],
            'description' => ['nullable', 'string'],
            'category_type' => ['nullable', 'string', 'max:50'],
        ];
    }
}
