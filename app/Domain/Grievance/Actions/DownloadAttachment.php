<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Models\GrievanceAttachment;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAttachment
{
    public function __invoke(GrievanceAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->path)) {
            throw new FileNotFoundException($attachment->path);
        }

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
        ]);
    }
}
