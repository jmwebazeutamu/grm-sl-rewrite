<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\AttachmentSource;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceAttachment;
use App\Domain\Identity\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadAttachment
{
    public const MAX_PER_GRIEVANCE = 20;
    public const MAX_PER_REQUEST = 5;
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, GrievanceAttachment>
     */
    public function __invoke(Grievance $grievance, array $files, ?string $description, User $uploader): array
    {
        if (count($files) === 0) {
            throw new DomainException('No files to upload.');
        }

        if (count($files) > self::MAX_PER_REQUEST) {
            throw new DomainException('Cannot upload more than '.self::MAX_PER_REQUEST.' files at once.');
        }

        $existing = $grievance->attachments()->count();
        if ($existing + count($files) > self::MAX_PER_GRIEVANCE) {
            throw new DomainException('This grievance already has '.$existing.' files. Limit is '.self::MAX_PER_GRIEVANCE.'.');
        }

        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (! in_array($ext, self::ALLOWED_EXT, true)) {
                throw new DomainException('File type not allowed: '.$file->getClientOriginalName());
            }
            if ($file->getSize() > self::MAX_BYTES) {
                throw new DomainException('File exceeds 10 MB: '.$file->getClientOriginalName());
            }
        }

        return DB::transaction(function () use ($grievance, $files, $description, $uploader) {
            $created = [];
            $storedPaths = [];

            try {
                foreach ($files as $file) {
                    $ext = strtolower($file->getClientOriginalExtension());
                    $stored = Str::uuid()->toString().'.'.$ext;
                    $dir = "grievance-attachments/{$grievance->id}";
                    $path = $file->storeAs($dir, $stored, 'local');
                    $storedPaths[] = $path;

                    $created[] = GrievanceAttachment::create([
                        'grievance_id' => $grievance->id,
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_filename' => $stored,
                        'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                        'size_bytes' => $file->getSize() ?: 0,
                        'uploaded_by_id' => $uploader->id,
                        'source' => AttachmentSource::Officer->value,
                        'description' => $description,
                    ]);
                }
            } catch (\Throwable $e) {
                foreach ($storedPaths as $p) {
                    Storage::disk('local')->delete($p);
                }
                throw $e;
            }

            return $created;
        });
    }
}
