<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Resources;

use App\Domain\Grievance\Models\GrievanceAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GrievanceAttachment */
class AttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $ext = pathinfo($this->original_name, PATHINFO_EXTENSION);

        return [
            'id' => $this->id,
            'grievance_id' => $this->grievance_id,
            'original_name' => $this->original_name,
            'extension' => strtolower($ext ?: ''),
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'size_formatted' => $this->formattedFileSize(),
            'source' => $this->source?->value ?? 'submission',
            'description' => $this->description,
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => $this->uploadedBy ? [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
