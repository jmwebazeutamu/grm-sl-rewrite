<?php

declare(strict_types=1);

namespace App\Domain\Locality\Actions;

use App\Domain\Locality\Models\Locality;

class CreateLocality
{
    /**
     * @param  array{name: string, section_id: int}  $data
     */
    public function __invoke(array $data): Locality
    {
        return Locality::create($data);
    }
}
