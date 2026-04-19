<?php

declare(strict_types=1);

use App\Domain\Grievance\Actions\UploadAttachment;
use App\Domain\Grievance\Enums\AttachmentSource;
use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceAttachment;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Models\GrievanceType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function setupAttachmentPerms(): void
{
    $perms = [
        'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
        'grievance.create', 'grievance.update',
        'grievance.transition', 'grievance.assign', 'grievance.review',
    ];
    foreach ($perms as $p) {
        Permission::findOrCreate($p);
    }
    Role::findOrCreate('super-admin')->givePermissionTo(Permission::all());
    Role::findOrCreate('acc-reviewer')->givePermissionTo($perms);
    Role::findOrCreate('org-admin')->givePermissionTo($perms);
    Role::findOrCreate('grm-officer')->givePermissionTo($perms);
    Role::findOrCreate('organization-officer')->givePermissionTo(['grievance.viewAny', 'grievance.view', 'grievance.update', 'grievance.transition']);
}

function makeOrg(string $acronym): Organization
{
    return Organization::factory()->create(['acronym' => $acronym]);
}

function makeCase(Organization $owningOrg, ?User $assigned = null): Grievance
{
    $type = GrievanceType::factory()->create();

    return Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'grievance_type_id' => $type->id,
        'classified_organization_id' => $owningOrg->id,
        'assigned_officer_id' => $assigned?->id,
    ]);
}

beforeEach(function (): void {
    setupAttachmentPerms();
    Storage::fake('local');
    config(['services.recaptcha.secret' => null]);
});

//
// 1. Public submission files get source=submission, uploaded_by=null
//

it('marks files uploaded at submission with source=submission and uploaded_by=null', function (): void {
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Late salaries',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
        'attachments' => [UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf')],
    ])->assertRedirect();

    $attachment = GrievanceAttachment::first();
    expect($attachment)->not->toBeNull();
    expect($attachment->source)->toBe(AttachmentSource::Submission);
    expect($attachment->uploaded_by_id)->toBeNull();
    expect($attachment->path)->toStartWith('grievance-attachments/');
});

//
// 2. Officer can upload to a case they have access to
//

it('lets an officer upload files to a case they own', function (): void {
    $org = makeOrg('UP02');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeCase($org);

    actingAs($officer)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('report.pdf', 200, 'application/pdf')],
        'description' => 'Investigation notes',
    ])->assertRedirect();

    $a = $case->fresh()->attachments->first();
    expect($a->source)->toBe(AttachmentSource::Officer);
    expect($a->uploaded_by_id)->toBe($officer->id);
    expect($a->description)->toBe('Investigation notes');
    Storage::disk('local')->assertExists($a->path);
});

//
// 3. Upload rejects oversize files (>10 MB)
//

it('rejects files exceeding 10 MB', function (): void {
    $org = makeOrg('UP03');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $case = makeCase($org);

    actingAs($admin)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf')],
    ])->assertSessionHasErrors('files.0');

    expect($case->fresh()->attachments)->toHaveCount(0);
});

//
// 4. Upload rejects disallowed file types
//

it('rejects disallowed file types', function (): void {
    $org = makeOrg('UP04');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $case = makeCase($org);

    actingAs($admin)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('virus.exe', 100)],
    ])->assertSessionHasErrors('files.0');

    expect($case->fresh()->attachments)->toHaveCount(0);
});

//
// 5. Upload rejects more than 5 files at once
//

it('rejects more than 5 files in a single request', function (): void {
    $org = makeOrg('UP05');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $case = makeCase($org);

    $files = [];
    for ($i = 0; $i < 6; $i++) {
        $files[] = UploadedFile::fake()->create("f{$i}.pdf", 100, 'application/pdf');
    }

    actingAs($admin)->post(route('admin.grievances.attachments.store', $case), [
        'files' => $files,
    ])->assertSessionHasErrors('files');

    expect($case->fresh()->attachments)->toHaveCount(0);
});

//
// 6. Upload fails if total count would exceed 20
//

it('fails when total attachments would exceed 20', function (): void {
    $org = makeOrg('UP06');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $case = makeCase($org);

    for ($i = 0; $i < 18; $i++) {
        GrievanceAttachment::create([
            'grievance_id' => $case->id,
            'disk' => 'local',
            'path' => "grievance-attachments/{$case->id}/existing-{$i}.pdf",
            'original_name' => "existing-{$i}.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'source' => AttachmentSource::Submission->value,
        ]);
    }

    actingAs($admin)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [
            UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('c.pdf', 100, 'application/pdf'),
        ],
    ])->assertRedirect()->assertSessionHas('error');

    expect($case->fresh()->attachments)->toHaveCount(18);
});

//
// 7. Transaction rollback — one bad file aborts the whole upload
//

it('rolls back the whole upload if any file in the batch fails validation', function (): void {
    $org = makeOrg('UP07');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('org-admin');
    $case = makeCase($org);

    $action = app(UploadAttachment::class);
    try {
        $action($case, [
            UploadedFile::fake()->create('ok.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('bad.exe', 100),
        ], null, $officer);
        expect(false)->toBeTrue();
    } catch (\DomainException $e) {
        // expected
    }

    expect($case->fresh()->attachments)->toHaveCount(0);
});

//
// 8. Authorised user can download
//

it('lets a user with view access download an attachment', function (): void {
    $org = makeOrg('DL08');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeCase($org);

    actingAs($officer)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('share.pdf', 50, 'application/pdf')],
    ])->assertRedirect();

    $a = $case->fresh()->attachments->first();

    actingAs($officer)
        ->get(route('admin.grievances.attachments.download', [$case, $a]))
        ->assertOk();
});

//
// 9. User from another org cannot download
//

it('blocks download for a user in a different org', function (): void {
    $own = makeOrg('DL09');
    $other = makeOrg('DL09B');
    $officer = User::factory()->create(['organization_id' => $own->id]);
    $officer->assignRole('grm-officer');

    $outsider = User::factory()->create(['organization_id' => $other->id]);
    $outsider->assignRole('grm-officer');

    $case = makeCase($own);
    actingAs($officer)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('confidential.pdf', 50, 'application/pdf')],
    ]);
    $a = $case->fresh()->attachments->first();

    actingAs($outsider)
        ->get(route('admin.grievances.attachments.download', [$case, $a]))
        ->assertForbidden();
});

//
// 10. Guest redirected to login
//

it('redirects unauthenticated users to login on download', function (): void {
    $org = makeOrg('DL10');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeCase($org);
    actingAs($officer)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('x.pdf', 50, 'application/pdf')],
    ]);
    $a = $case->fresh()->attachments->first();

    app('auth')->forgetGuards();

    $this->get(route('admin.grievances.attachments.download', [$case, $a]))
        ->assertRedirect(route('login'));
});

//
// 11. Download returns the original filename (not the UUID)
//

it('serves the original filename rather than the stored UUID', function (): void {
    $org = makeOrg('DL11');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeCase($org);

    actingAs($officer)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('Quarterly-Report.pdf', 50, 'application/pdf')],
    ]);
    $a = $case->fresh()->attachments->first();

    $response = actingAs($officer)->get(route('admin.grievances.attachments.download', [$case, $a]));
    $response->assertOk();
    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('Quarterly-Report.pdf');
    expect($disposition)->not->toContain($a->stored_filename);
});

//
// 12. Missing-file returns 404 (not 500)
//

it('returns 404 when the stored file is missing from disk', function (): void {
    $org = makeOrg('DL12');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('grm-officer');
    $case = makeCase($org);

    $a = GrievanceAttachment::create([
        'grievance_id' => $case->id,
        'disk' => 'local',
        'path' => 'grievance-attachments/'.$case->id.'/nonexistent.pdf',
        'original_name' => 'nonexistent.pdf',
        'stored_filename' => 'nonexistent.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
        'source' => AttachmentSource::Submission->value,
    ]);

    actingAs($officer)
        ->get(route('admin.grievances.attachments.download', [$case, $a]))
        ->assertNotFound();
});

//
// 13. super-admin can delete attachment + file
//

it('allows super-admin to delete an attachment and its stored file', function (): void {
    $org = makeOrg('DL13');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('super-admin');
    $case = makeCase($org);

    actingAs($admin)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('kill-me.pdf', 100, 'application/pdf')],
    ]);
    $a = $case->fresh()->attachments->first();
    $path = $a->path;
    Storage::disk('local')->assertExists($path);

    actingAs($admin)
        ->delete(route('admin.grievances.attachments.destroy', [$case, $a]))
        ->assertRedirect();

    expect(GrievanceAttachment::find($a->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

//
// 14. org-admin cannot delete
//

it('does not allow org-admin to delete an attachment', function (): void {
    $org = makeOrg('DL14');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $case = makeCase($org);

    actingAs($admin)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('keep-me.pdf', 100, 'application/pdf')],
    ]);
    $a = $case->fresh()->attachments->first();

    actingAs($admin)
        ->delete(route('admin.grievances.attachments.destroy', [$case, $a]))
        ->assertForbidden();

    expect(GrievanceAttachment::find($a->id))->not->toBeNull();
});

//
// 15. Panel shows submission and officer files in separate sections
//

it('passes attachments to the show page split into both sources', function (): void {
    $org = makeOrg('PN15');
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $admin->assignRole('org-admin');
    $case = makeCase($org);

    GrievanceAttachment::create([
        'grievance_id' => $case->id,
        'disk' => 'local', 'path' => 'x/submitted.pdf',
        'original_name' => 'submitted.pdf', 'stored_filename' => 'sub.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 10,
        'source' => AttachmentSource::Submission->value,
    ]);
    GrievanceAttachment::create([
        'grievance_id' => $case->id,
        'disk' => 'local', 'path' => 'x/officer.pdf',
        'original_name' => 'officer.pdf', 'stored_filename' => 'off.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 20,
        'source' => AttachmentSource::Officer->value, 'uploaded_by_id' => $admin->id,
    ]);

    actingAs($admin)
        ->get(route('admin.grievances.show', $case))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Grievance/Admin/Show')
            ->has('attachments', 2)
            ->where('attachments', fn ($rows) => collect($rows)->pluck('source')->sort()->values()->all() === ['officer', 'submission'])
        );
});

//
// 16. organization-officer NOT assigned cannot upload
//

it('blocks unassigned organization-officer from uploading', function (): void {
    $org = makeOrg('PO16');
    $other = User::factory()->create(['organization_id' => $org->id]);
    $other->assignRole('organization-officer');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('organization-officer');

    $case = makeCase($org, $other);

    actingAs($officer)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('x.pdf', 50, 'application/pdf')],
    ])->assertForbidden();
});

//
// 17. organization-officer ASSIGNED to a case can upload
//

it('lets an assigned organization-officer upload', function (): void {
    $org = makeOrg('PO17');
    $officer = User::factory()->create(['organization_id' => $org->id]);
    $officer->assignRole('organization-officer');

    $case = makeCase($org, $officer);

    actingAs($officer)->post(route('admin.grievances.attachments.store', $case), [
        'files' => [UploadedFile::fake()->create('field-note.pdf', 50, 'application/pdf')],
    ])->assertRedirect();

    expect($case->fresh()->attachments)->toHaveCount(1);
});
