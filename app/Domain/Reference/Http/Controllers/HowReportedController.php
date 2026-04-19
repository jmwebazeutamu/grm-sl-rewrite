<?php

declare(strict_types=1);

namespace App\Domain\Reference\Http\Controllers;

use App\Domain\Reference\Models\HowReported;

class HowReportedController extends ReferenceController
{
    protected function model(): string
    {
        return HowReported::class;
    }

    protected function key(): string
    {
        return 'how-reported';
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueName($id)],
        ];
    }
}
