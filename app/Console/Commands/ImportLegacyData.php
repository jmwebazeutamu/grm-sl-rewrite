<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Grievance\Enums\ActionType;
use App\Domain\Grievance\Enums\AttachmentSource;
use App\Domain\Grievance\Enums\FeedbackRating;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Beneficiary;
use App\Domain\Grievance\Models\Classification;
use App\Domain\Grievance\Models\Complainer;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceAction;
use App\Domain\Grievance\Models\GrievanceAttachment;
use App\Domain\Grievance\Models\GrievanceFeedback;
use App\Domain\Grievance\Models\GrievanceStatusHistory;
use App\Domain\Grievance\Models\Suspect;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use stdClass;

class ImportLegacyData extends Command
{
    protected $signature = 'grm:import-legacy
        {--users : Import users only}
        {--grievances : Import grievances + children only}
        {--timeline : Import action_taken + resolution → grievance_action only}
        {--feedback : Import feedback only}
        {--attachments : Import attachments only}
        {--all : Import everything (default if no flag)}
        {--dry-run : Read legacy data and print counts without writing}
        {--legacy-storage-path= : Host path containing legacy storage/app (needed only for attachments)}';

    protected $description = 'Import grievance data from the legacy Laravel 8 MariaDB database.';

    /** @var array<int,int> legacy_person_id => new_user_id */
    private array $userMap = [];

    /** @var array<int,int> legacy_grievance_id => new_grievance_id */
    private array $grievanceMap = [];

    /** @var array<string,array<int,true>> reference table name => set of valid ids */
    private array $validFk = [];

    public function handle(): int
    {
        $this->configureLegacyConnection();

        try {
            DB::connection('mysql_legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to legacy DB: '.$e->getMessage());
            $this->line('Set LEGACY_DB_HOST / LEGACY_DB_PORT / LEGACY_DB_DATABASE / LEGACY_DB_USERNAME / LEGACY_DB_PASSWORD in env.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $this->loadValidFkSets();
        $this->hydrateUserMap();
        $this->hydrateGrievanceMap();

        $sections = $this->pickSections();
        foreach ($sections as $section) {
            $this->line('');
            $this->info("─── {$section} ───");
            $method = 'import'.ucfirst($section);
            $this->$method($dry);
        }

        if ($dry) {
            $this->line('');
            $this->warn('Dry-run: no changes written.');
        }

        return self::SUCCESS;
    }

    private function configureLegacyConnection(): void
    {
        config(['database.connections.mysql_legacy' => [
            'driver' => 'mysql',
            'host' => env('LEGACY_DB_HOST', 'grm_db'),
            'port' => (string) env('LEGACY_DB_PORT', '3306'),
            'database' => env('LEGACY_DB_DATABASE', 'grm_prod2023'),
            'username' => env('LEGACY_DB_USERNAME', 'root'),
            'password' => env('LEGACY_DB_PASSWORD', ''),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
        ]]);
    }

    /** @return list<string> */
    private function pickSections(): array
    {
        $flags = ['users', 'grievances', 'timeline', 'feedback', 'attachments'];
        $picked = array_values(array_filter($flags, fn ($f) => $this->option($f)));
        if ($picked === [] || $this->option('all')) {
            return $flags;
        }

        return $picked;
    }

    private function loadValidFkSets(): void
    {
        $map = [
            'grievance_type' => 'grievance_type',
            'how_reported' => 'how_reported',
            'priority' => 'priority',
            'region' => 'region',
            'district' => 'district',
            'chiefdom' => 'chiefdom',
            'section' => 'section',
            'locality' => 'locality',
            'organization' => 'organization',
            'office' => 'office',
            'programme' => 'programme',
            'case_concept' => 'case_concept',
            'area' => 'area',
            'access' => 'access',
        ];
        foreach ($map as $key => $table) {
            try {
                $this->validFk[$key] = DB::table($table)->pluck('id')->flip()->all();
            } catch (\Throwable $e) {
                $this->validFk[$key] = [];
            }
        }
    }

    private function hydrateUserMap(): void
    {
        $legacyByUsername = DB::connection('mysql_legacy')
            ->table('person')
            ->select('id', 'username')
            ->get()
            ->keyBy('username');
        $rewriteByUsername = User::pluck('id', 'username');
        foreach ($legacyByUsername as $username => $row) {
            if (isset($rewriteByUsername[$username])) {
                $this->userMap[$row->id] = (int) $rewriteByUsername[$username];
            }
        }
    }

    private function hydrateGrievanceMap(): void
    {
        $legacyByGnumber = DB::connection('mysql_legacy')
            ->table('grievance')
            ->select('id', 'g_number')
            ->get()
            ->keyBy('g_number');
        $rewriteByGnumber = Grievance::pluck('id', 'g_number');
        foreach ($legacyByGnumber as $gNumber => $row) {
            if (isset($rewriteByGnumber[$gNumber])) {
                $this->grievanceMap[$row->id] = (int) $rewriteByGnumber[$gNumber];
            }
        }
    }

    private function nullIfMissing(?int $id, string $key): ?int
    {
        if ($id === null) {
            return null;
        }

        return isset($this->validFk[$key][$id]) ? $id : null;
    }

    /* ───────── Users ───────── */

    private function importUsers(bool $dry): void
    {
        $persons = DB::connection('mysql_legacy')
            ->table('person')
            ->select('id', 'name', 'username', 'password', 'email_verified_at', 'employee_id', 'enabled')
            ->get();

        $employees = DB::connection('mysql_legacy')
            ->table('employee')
            ->select('id', 'organization_id', 'office_id')
            ->get()
            ->keyBy('id');

        $roleAssignments = DB::connection('mysql_legacy')
            ->table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', 'App\\Models\\User')
            ->select('mhr.model_id', 'r.name')
            ->get()
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        $rewriteRoles = Role::pluck('id', 'name');

        $created = 0;
        $updated = 0;
        $skippedRoles = [];

        foreach ($persons as $p) {
            $emp = $employees->get($p->employee_id);
            $orgId = $emp ? $this->nullIfMissing($emp->organization_id, 'organization') : null;
            $officeId = $emp ? $this->nullIfMissing($emp->office_id, 'office') : null;
            $email = (is_string($p->username) && str_contains($p->username, '@')) ? $p->username : null;

            $attrs = [
                'name' => $p->name ?? $p->username,
                'email' => $email,
                'organization_id' => $orgId,
                'office_id' => $officeId,
                'is_active' => (bool) ($p->enabled ?? 1),
            ];

            if ($dry) {
                $exists = User::where('username', $p->username)->exists();
                $exists ? $updated++ : $created++;

                continue;
            }

            $user = User::where('username', $p->username)->first();
            if ($user) {
                $user->fill($attrs);
                if ($p->email_verified_at !== null) {
                    $user->email_verified_at = $p->email_verified_at;
                }
                $user->save();
                $updated++;
            } else {
                $user = new User(['username' => $p->username] + $attrs);
                $user->password = $p->password;
                if ($p->email_verified_at !== null) {
                    $user->email_verified_at = $p->email_verified_at;
                }
                $user->save();
                $created++;
            }
            $this->userMap[$p->id] = $user->id;

            $legacyRoles = $roleAssignments->get($p->id, []);
            $toSync = [];
            foreach ($legacyRoles as $name) {
                if ($rewriteRoles->has($name)) {
                    $toSync[] = $name;
                } else {
                    $skippedRoles[$name] = ($skippedRoles[$name] ?? 0) + 1;
                }
            }
            if ($toSync !== []) {
                $user->syncRoles($toSync);
            }
        }

        $this->info("Users: {$created} created, {$updated} updated");
        if ($skippedRoles !== []) {
            $this->warn('Skipped unmapped roles (not present in rewrite):');
            foreach ($skippedRoles as $name => $count) {
                $this->warn("  · '{$name}' ({$count} assignments)");
            }
        }
    }

    /* ───────── Grievances + children ───────── */

    private function importGrievances(bool $dry): void
    {
        $rows = DB::connection('mysql_legacy')->table('grievance')->get();
        $complainers = DB::connection('mysql_legacy')->table('complainer')->get()->keyBy('grievance_id');
        $suspects = DB::connection('mysql_legacy')->table('suspect')->get()->groupBy('grievance_id');
        $beneficiaries = DB::connection('mysql_legacy')->table('beneficiary')->get()->groupBy('grievance_id');
        $classifications = DB::connection('mysql_legacy')->table('classification')->get()->keyBy('grievance_id');

        $created = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            if (isset($this->grievanceMap[$r->id])) {
                $skipped++;

                continue;
            }

            if ($dry) {
                $this->grievanceMap[$r->id] = 0;
            }

            $classification = $classifications->get($r->id);
            $state = $this->deriveState($r, $classification);

            $attrs = [
                'g_number' => $r->g_number,
                'summary' => $r->summary !== '' ? $r->summary : '(imported)',
                'description' => $r->description,
                'state' => $state,
                'is_anonymous' => false,
                'grievance_type_id' => $this->nullIfMissing($r->grievance_type_id, 'grievance_type'),
                'how_reported_id' => $this->nullIfMissing($r->how_reported_id, 'how_reported'),
                'priority_id' => $this->nullIfMissing($r->priority_id, 'priority'),
                'region_id' => $this->nullIfMissing($r->region_id, 'region'),
                'district_id' => $this->nullIfMissing($r->district_id, 'district'),
                'chiefdom_id' => $this->nullIfMissing($r->chiefdom_id, 'chiefdom'),
                'section_id' => $this->nullIfMissing($r->section_id, 'section'),
                'locality_id' => $this->nullIfMissing($r->locality_id, 'locality'),
                'review_comment' => $r->review_comment,
                'received_at' => $r->received_on ?? $r->date_created,
                'reviewed_at' => $r->date_reviewed,
                'reviewed_by_id' => $this->userMap[$r->modified_by_id] ?? null,
                'resolved_at' => $r->date_resolved,
                'closed_at' => $r->date_confirmed,
                'classified_organization_id' => $classification ? $this->nullIfMissing($classification->organization_id, 'organization') : null,
                'classified_programme_id' => $classification ? $this->nullIfMissing($classification->programme_id, 'programme') : null,
                'classified_case_concept_id' => $classification ? $this->nullIfMissing($classification->case_concept_id, 'case_concept') : null,
                'classified_area_id' => $classification ? $this->nullIfMissing($classification->responsible_area_id, 'area') : null,
                'access_id' => $classification ? $this->nullIfMissing($classification->access_id, 'access') : null,
            ];
            $createdById = $this->userMap[$r->created_by_id] ?? null;
            $updatedById = $this->userMap[$r->modified_by_id] ?? null;

            if ($dry) {
                $created++;

                continue;
            }

            DB::transaction(function () use ($r, $attrs, $createdById, $updatedById, $complainers, $suspects, $beneficiaries, $classification, $state) {
                $g = new Grievance($attrs);
                $g->created_by_id = $createdById;
                $g->updated_by_id = $updatedById;
                $g->save();
                $this->grievanceMap[$r->id] = $g->id;

                if ($c = $complainers->get($r->id)) {
                    Complainer::create([
                        'grievance_id' => $g->id,
                        'first_name' => $c->first_name,
                        'last_name' => $c->last_name,
                        'gender' => $this->normalizeGender($c->gender),
                        'email' => $c->email,
                        'phone_number' => $c->phone_number,
                        'address' => $c->address,
                        'organization_id' => $this->nullIfMissing($c->organization_id, 'organization'),
                        'other_organization' => $c->other_oga_specify,
                        'region_id' => $this->nullIfMissing($c->region_id, 'region'),
                        'district_id' => $this->nullIfMissing($c->district_id, 'district'),
                        'chiefdom_id' => $this->nullIfMissing($c->chiefdom_id, 'chiefdom'),
                        'section_id' => $this->nullIfMissing($c->section_id, 'section'),
                        'locality_id' => $this->nullIfMissing($c->locality_id, 'locality'),
                    ]);
                }

                foreach ($suspects->get($r->id, collect()) as $s) {
                    Suspect::create([
                        'grievance_id' => $g->id,
                        'first_name' => $s->first_name,
                        'last_name' => $s->last_name,
                        'title' => $s->title,
                        'gender' => $this->normalizeGender($s->gender),
                        'email' => $s->email,
                        'phone_number' => $s->phone_number,
                        'address' => $s->address,
                        'organization_id' => $this->nullIfMissing($s->organization_id, 'organization'),
                        'other_organization' => $s->other_oga_specify,
                        'region_id' => $this->nullIfMissing($s->region_id, 'region'),
                        'district_id' => $this->nullIfMissing($s->district_id, 'district'),
                        'chiefdom_id' => $this->nullIfMissing($s->chiefdom_id, 'chiefdom'),
                        'section_id' => $this->nullIfMissing($s->section_id, 'section'),
                        'locality_id' => $this->nullIfMissing($s->locality_id, 'locality'),
                    ]);
                }

                foreach ($beneficiaries->get($r->id, collect()) as $b) {
                    Beneficiary::create([
                        'grievance_id' => $g->id,
                        'name' => $b->name,
                        'gender' => $this->normalizeGender($b->gender),
                        'phone_number' => $b->phone_number,
                        'household_id' => $b->house_hold_id,
                        'implementing_agency_id' => $this->nullIfMissing($b->implementing_agency_id, 'organization'),
                        'social_programme_id' => $this->nullIfMissing($b->social_programme_id, 'programme'),
                    ]);
                }

                if ($classification) {
                    Classification::create([
                        'grievance_id' => $g->id,
                        'organization_id' => $this->nullIfMissing($classification->organization_id, 'organization'),
                        'programme_id' => $this->nullIfMissing($classification->programme_id, 'programme'),
                        'case_concept_id' => $this->nullIfMissing($classification->case_concept_id, 'case_concept'),
                        'responsible_area_id' => $this->nullIfMissing($classification->responsible_area_id, 'area'),
                        'access_id' => $this->nullIfMissing($classification->access_id, 'access'),
                    ]);
                }

                $this->synthesizeStatusHistory($g, $r, $state);
            });

            $created++;
        }

        $this->info("Grievances: {$created} created, {$skipped} skipped (already present)");
    }

    private function deriveState(stdClass $r, ?stdClass $classification): GrievanceState
    {
        $resolved = $r->date_resolved !== null;
        $confirmed = $r->date_confirmed !== null;
        $reviewed = ((int) $r->is_reviewed) === 1 || $r->date_reviewed !== null;

        if ($resolved && $confirmed) {
            return GrievanceState::Closed;
        }
        if ($resolved) {
            return GrievanceState::Resolved;
        }
        if ($reviewed && $classification !== null) {
            return GrievanceState::InProgress;
        }
        if ($reviewed) {
            return GrievanceState::UnderReview;
        }

        return GrievanceState::Submitted;
    }

    private function synthesizeStatusHistory(Grievance $g, stdClass $r, GrievanceState $finalState): void
    {
        $transitions = [];
        $from = GrievanceState::Submitted;
        $actor = $this->userMap[$r->modified_by_id] ?? null;

        if ($r->date_reviewed !== null) {
            $transitions[] = [$from, GrievanceState::UnderReview, $r->date_reviewed, $actor];
            $from = GrievanceState::UnderReview;
        }
        if ($finalState === GrievanceState::InProgress
            || $finalState === GrievanceState::Resolved
            || $finalState === GrievanceState::Closed) {
            $transitions[] = [$from, GrievanceState::InProgress, $r->date_reviewed ?? $r->date_created, $actor];
            $from = GrievanceState::InProgress;
        }
        if ($r->date_resolved !== null) {
            $transitions[] = [$from, GrievanceState::Resolved, $r->date_resolved, $actor];
            $from = GrievanceState::Resolved;
        }
        if ($finalState === GrievanceState::Closed && $r->date_confirmed !== null) {
            $transitions[] = [$from, GrievanceState::Closed, $r->date_confirmed, $actor];
        }

        foreach ($transitions as [$fromState, $toState, $at, $act]) {
            GrievanceStatusHistory::create([
                'grievance_id' => $g->id,
                'from_state' => $fromState,
                'to_state' => $toState,
                'note' => 'Imported from legacy',
                'actor_id' => $act,
                'occurred_at' => $at,
            ]);
        }
    }

    private function normalizeGender(?string $g): ?string
    {
        return match ($g) {
            'Male' => 'male',
            'Female' => 'female',
            'Prefer not to say' => 'other',
            default => null,
        };
    }

    /* ───────── Timeline ───────── */

    private function importTimeline(bool $dry): void
    {
        $actions = DB::connection('mysql_legacy')
            ->table('action_taken as a')
            ->join('classification as c', 'c.id', '=', 'a.classification_id')
            ->select(
                'a.id', 'a.classification_id', 'a.analysis_comments',
                'a.created_by_id', 'a.date_created', 'c.grievance_id'
            )
            ->get();

        $resolutions = DB::connection('mysql_legacy')
            ->table('resolution as r')
            ->join('action_taken as a', 'a.id', '=', 'r.action_taken_id')
            ->join('classification as c', 'c.id', '=', 'a.classification_id')
            ->select(
                'r.id', 'r.summary', 'r.commissioner_comments',
                'r.created_by_id', 'r.date_created', 'c.grievance_id'
            )
            ->get();

        $created = 0;
        foreach ($actions as $a) {
            $gid = $this->grievanceMap[$a->grievance_id] ?? null;
            if ($gid === null) {
                continue;
            }
            if ($dry) {
                $created++;

                continue;
            }
            GrievanceAction::forceCreate([
                'grievance_id' => $gid,
                'type' => ActionType::Update,
                'body' => $a->analysis_comments ?? '(no comment)',
                'assigned_to_id' => null,
                'created_by_id' => $this->userMap[$a->created_by_id] ?? null,
                'updated_by_id' => $this->userMap[$a->created_by_id] ?? null,
                'created_at' => $a->date_created,
                'updated_at' => $a->date_created,
            ]);
            $created++;
        }

        foreach ($resolutions as $r) {
            $gid = $this->grievanceMap[$r->grievance_id] ?? null;
            if ($gid === null) {
                continue;
            }
            if ($dry) {
                $created++;

                continue;
            }
            $body = trim(($r->summary ?? '').(($r->commissioner_comments ?? '') !== '' ? "\n\n".$r->commissioner_comments : ''));
            GrievanceAction::forceCreate([
                'grievance_id' => $gid,
                'type' => ActionType::Resolve,
                'body' => $body !== '' ? $body : '(no summary)',
                'assigned_to_id' => null,
                'created_by_id' => $this->userMap[$r->created_by_id] ?? null,
                'updated_by_id' => $this->userMap[$r->created_by_id] ?? null,
                'created_at' => $r->date_created,
                'updated_at' => $r->date_created,
            ]);
            $created++;
        }

        $this->info("Timeline: {$created} grievance_action rows inserted");
    }

    /* ───────── Feedback ───────── */

    private function importFeedback(bool $dry): void
    {
        $rows = DB::connection('mysql_legacy')->table('feedback')->get();
        $created = 0;
        foreach ($rows as $f) {
            $gid = $this->grievanceMap[$f->grievance_id] ?? null;
            if ($gid === null) {
                continue;
            }
            if (GrievanceFeedback::where('grievance_id', $gid)->exists()) {
                continue;
            }
            if ($dry) {
                $created++;

                continue;
            }
            GrievanceFeedback::create([
                'grievance_id' => $gid,
                'rating' => $this->satisfactionToRating((int) $f->satisfaction_id),
                'comment' => $f->comments,
                'channel' => 'web',
                'submitted_at' => $f->contacted_on ?? $f->date_created,
            ]);
            $created++;
        }
        $this->info("Feedback: {$created} rows inserted");
    }

    private function satisfactionToRating(int $satisfactionId): FeedbackRating
    {
        return match ($satisfactionId) {
            1 => FeedbackRating::Satisfied,
            2 => FeedbackRating::Neutral,
            3 => FeedbackRating::Dissatisfied,
            default => FeedbackRating::Neutral,
        };
    }

    /* ───────── Attachments ───────── */

    private function importAttachments(bool $dry): void
    {
        try {
            $rows = DB::connection('mysql_legacy')
                ->table('mediables as mbl')
                ->join('media as m', 'm.id', '=', 'mbl.media_id')
                ->where('mbl.mediable_type', 'like', '%Grievance%')
                ->select(
                    'mbl.mediable_id as legacy_grievance_id',
                    'm.id as media_id', 'm.disk', 'm.file_name',
                    'm.mime_type', 'm.size', 'm.upload_folder', 'm.user_id'
                )
                ->get();
        } catch (\Throwable $e) {
            $this->warn('No media/mediables tables or query failed: '.$e->getMessage());

            return;
        }

        if ($rows->isEmpty()) {
            $this->info('Attachments: 0 legacy media rows linked to grievances, nothing to import.');

            return;
        }

        $legacyRoot = $this->option('legacy-storage-path');
        if ($legacyRoot === null || ! is_dir($legacyRoot)) {
            $this->warn('Attachments: --legacy-storage-path not set or not a directory; skipping file copy.');
            $this->warn('  Tip: pass the host path that contains the legacy storage/app tree.');

            return;
        }

        $targetDisk = Storage::disk(config('filesystems.default'));
        $created = 0;
        $missing = 0;
        foreach ($rows as $r) {
            $gid = $this->grievanceMap[$r->legacy_grievance_id] ?? null;
            if ($gid === null) {
                continue;
            }
            $sourcePath = $this->resolveLegacyMediaPath($legacyRoot, $r);
            if (! is_file($sourcePath)) {
                $missing++;

                continue;
            }
            $ext = pathinfo($r->file_name, PATHINFO_EXTENSION);
            $newPath = 'grievances/'.$gid.'/legacy/'.Str::uuid().($ext ? '.'.$ext : '');
            if ($dry) {
                $created++;

                continue;
            }
            $targetDisk->put($newPath, file_get_contents($sourcePath));
            GrievanceAttachment::create([
                'grievance_id' => $gid,
                'disk' => config('filesystems.default'),
                'path' => $newPath,
                'original_name' => $r->file_name,
                'stored_filename' => basename($newPath),
                'mime_type' => $r->mime_type,
                'size_bytes' => (int) $r->size,
                'uploaded_by_id' => $this->userMap[$r->user_id] ?? null,
                'source' => AttachmentSource::Officer,
            ]);
            $created++;
        }
        $this->info("Attachments: {$created} copied, {$missing} source files missing");
    }

    private function resolveLegacyMediaPath(string $root, stdClass $r): string
    {
        $dir = $r->upload_folder !== null && $r->upload_folder !== ''
            ? rtrim($r->upload_folder, '/').'/'
            : '';
        $root = rtrim($root, '/');
        $candidates = [
            "{$root}/{$dir}{$r->media_id}/{$r->file_name}",
            "{$root}/public/{$dir}{$r->media_id}/{$r->file_name}",
            "{$root}/{$dir}".sha1((string) $r->media_id)."/{$r->file_name}",
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        return $candidates[0];
    }
}
