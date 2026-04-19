<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Actions;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceSubmitted;
use App\Domain\Grievance\Models\Beneficiary;
use App\Domain\Grievance\Models\Complainer;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Enums\AttachmentSource;
use App\Domain\Grievance\Models\GrievanceAttachment;
use App\Domain\Grievance\Models\GrievanceStatusHistory;
use App\Domain\Grievance\Models\Suspect;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SubmitGrievance
{
    public function __construct(private readonly AssignGrievanceNumber $assignNumber)
    {
    }

    /**
     * @param  array{
     *   summary: string,
     *   description?: ?string,
     *   grievance_type_id: int,
     *   how_reported_id?: ?int,
     *   priority_id?: ?int,
     *   is_anonymous?: bool,
     *   region_id?: ?int, district_id?: ?int, chiefdom_id?: ?int, section_id?: ?int, locality_id?: ?int,
     *   complainer?: array<string, mixed>,
     *   suspects?: array<int, array<string, mixed>>,
     *   beneficiaries?: array<int, array<string, mixed>>,
     * }  $data
     * @param  array<int, UploadedFile>  $attachments
     */
    public function __invoke(array $data, array $attachments = []): Grievance
    {
        return DB::transaction(function () use ($data, $attachments): Grievance {
            $now = now();

            $grievance = Grievance::create([
                'g_number' => ($this->assignNumber)((int) $now->year),
                'summary' => $data['summary'],
                'description' => $data['description'] ?? null,
                'grievance_type_id' => $data['grievance_type_id'],
                'how_reported_id' => $data['how_reported_id'] ?? null,
                'priority_id' => $data['priority_id'] ?? null,
                'state' => GrievanceState::Submitted->value,
                'is_anonymous' => $data['is_anonymous'] ?? false,
                'region_id' => $data['region_id'] ?? null,
                'district_id' => $data['district_id'] ?? null,
                'chiefdom_id' => $data['chiefdom_id'] ?? null,
                'section_id' => $data['section_id'] ?? null,
                'locality_id' => $data['locality_id'] ?? null,
                'implementing_organization_id' => $data['implementing_organization_id'] ?? null,
                'programme_id' => $data['programme_id'] ?? null,
                'received_at' => $now,
            ]);

            $isAnonymous = (bool) ($data['is_anonymous'] ?? false);
            if (! $isAnonymous && ! empty($data['complainer'])) {
                $grievance->complainer()->create($data['complainer']);
            }

            foreach (($data['suspects'] ?? []) as $suspect) {
                $isBeneficiary = (bool) ($suspect['is_beneficiary'] ?? false);
                if (! $isBeneficiary) {
                    $suspect['programme_id'] = null;
                    $suspect['implementing_organization_id'] = null;
                    $suspect['beneficiary_id_number'] = null;
                }
                $suspect['is_beneficiary'] = $isBeneficiary;
                $grievance->suspects()->create($suspect);
            }

            foreach (($data['beneficiaries'] ?? []) as $beneficiary) {
                $grievance->beneficiaries()->create($beneficiary);
            }

            foreach ($attachments as $file) {
                $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
                $stored = \Illuminate\Support\Str::uuid()->toString().'.'.$ext;
                $path = $file->storeAs("grievance-attachments/{$grievance->id}", $stored, 'local');
                GrievanceAttachment::create([
                    'grievance_id' => $grievance->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'stored_filename' => $stored,
                    'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?: 0,
                    'uploaded_by_id' => auth()->id(),
                    'source' => AttachmentSource::Submission->value,
                ]);
            }

            GrievanceStatusHistory::create([
                'grievance_id' => $grievance->id,
                'from_state' => null,
                'to_state' => GrievanceState::Submitted->value,
                'note' => 'Submitted',
                'actor_id' => auth()->id(),
                'occurred_at' => $now,
            ]);

            GrievanceSubmitted::dispatch($grievance);

            return $grievance->fresh(['complainer', 'suspects', 'beneficiaries', 'attachments']);
        });
    }
}
