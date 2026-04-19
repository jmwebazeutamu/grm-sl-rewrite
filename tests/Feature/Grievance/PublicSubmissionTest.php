<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Events\GrievanceSubmitted;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Reference\Models\GrievanceType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\post;

beforeEach(function (): void {
    // With no RECAPTCHA_SECRET set, the rule is a no-op — lets tests run
    // without hitting the live Google endpoint.
    config(['services.recaptcha.secret' => null]);
    Storage::fake('local');
});

it('accepts an anonymous submission with a summary and type', function (): void {
    Event::fake([GrievanceSubmitted::class]);
    $type = GrievanceType::factory()->create();

    $response = post(route('grievances.public.store'), [
        'summary' => 'Late payment of teacher salaries',
        'description' => 'The September salaries are two weeks late across the district.',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
    ]);

    $grievance = Grievance::where('summary', 'Late payment of teacher salaries')->firstOrFail();
    $response->assertRedirect(route('grievances.public.confirmation', ['g_number' => $grievance->g_number]));

    expect($grievance->is_anonymous)->toBeTrue();
    expect($grievance->state)->toBe(GrievanceState::Submitted);
    expect($grievance->g_number)->toStartWith('GRM-'.now()->year);
    expect($grievance->complainer)->toBeNull();

    Event::assertDispatched(GrievanceSubmitted::class);
});

it('accepts an identified submission with complainer details', function (): void {
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Water supply disruption',
        'grievance_type_id' => $type->id,
        'is_anonymous' => false,
        'complainer' => [
            'first_name' => 'Aminata',
            'last_name' => 'Sesay',
            'email' => 'aminata@example.sl',
        ],
        'recaptcha_token' => 'dev',
    ])->assertRedirect();

    $grievance = Grievance::where('summary', 'Water supply disruption')->firstOrFail();
    expect($grievance->complainer)->not->toBeNull();
    expect($grievance->complainer->first_name)->toBe('Aminata');
    expect($grievance->complainer->email)->toBe('aminata@example.sl');
});

it('rejects a submission without a required complainer when not anonymous', function (): void {
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Something',
        'grievance_type_id' => $type->id,
        'is_anonymous' => false,
        'recaptcha_token' => 'dev',
    ])->assertSessionHasErrors('complainer');
});

it('stores attachments on the configured disk', function (): void {
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'With evidence',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'attachments' => [UploadedFile::fake()->create('evidence.pdf', 200, 'application/pdf')],
        'recaptcha_token' => 'dev',
    ])->assertRedirect();

    $grievance = Grievance::where('summary', 'With evidence')->firstOrFail();
    expect($grievance->attachments)->toHaveCount(1);
    Storage::disk('local')->assertExists($grievance->attachments->first()->path);
});

it('writes a status history row for the initial submission', function (): void {
    $type = GrievanceType::factory()->create();

    post(route('grievances.public.store'), [
        'summary' => 'Log me',
        'grievance_type_id' => $type->id,
        'is_anonymous' => true,
        'recaptcha_token' => 'dev',
    ])->assertRedirect();

    $grievance = Grievance::where('summary', 'Log me')->firstOrFail();
    expect($grievance->statusHistory)->toHaveCount(1);
    expect($grievance->statusHistory->first()->to_state)->toBe(GrievanceState::Submitted);
    expect($grievance->statusHistory->first()->from_state)->toBeNull();
});

it('generates sequential grievance numbers for the year', function (): void {
    $type = GrievanceType::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        post(route('grievances.public.store'), [
            'summary' => "Case {$i}",
            'grievance_type_id' => $type->id,
            'is_anonymous' => true,
            'recaptcha_token' => 'dev',
        ])->assertRedirect();
    }

    $numbers = Grievance::orderBy('id')->pluck('g_number')->all();
    expect($numbers)->toHaveCount(3);
    $year = now()->year;
    expect($numbers[0])->toBe("GRM-{$year}-000001");
    expect($numbers[1])->toBe("GRM-{$year}-000002");
    expect($numbers[2])->toBe("GRM-{$year}-000003");
});
