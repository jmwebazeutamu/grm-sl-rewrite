<?php

declare(strict_types=1);

namespace App\Domain\Reference\Http\Controllers;

use App\Domain\Reference\Models\Satisfaction;

class SatisfactionController extends ReferenceController
{
    protected function model(): string
    {
        return Satisfaction::class;
    }

    protected function key(): string
    {
        return 'satisfactions';
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueName($id)],
        ];
    }
}
