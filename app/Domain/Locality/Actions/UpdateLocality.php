<?php

declare(strict_types=1);

namespace App\Domain\Locality\Actions;

use App\Domain\Locality\Models\Locality;

class UpdateLocality
{
    /**
     * @param  array{name: string, section_id: int}  $data
     */
    public function __invoke(Locality $locality, array $data): Locality
    {
        $locality->update($data);

        return $locality->refresh();
    }
}
