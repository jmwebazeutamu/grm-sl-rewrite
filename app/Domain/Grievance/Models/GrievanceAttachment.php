<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Grievance\Enums\AttachmentSource;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GrievanceAttachment extends Model
{
    protected $table = 'grievance_attachment';

    protected $fillable = [
        'grievance_id', 'disk', 'path',
        'original_name', 'stored_filename',
        'mime_type', 'size_bytes',
        'uploaded_by_id', 'source', 'description',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'source' => AttachmentSource::class,
        ];
    }

    public function grievance(): BelongsTo
    {
        return $this->belongsTo(Grievance::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function temporaryUrl(int $ttlMinutes = 15): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($ttlMinutes));
    }

    public function formattedFileSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
