<?php

declare(strict_types=1);

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Notification\Contracts\SmsDispatchResult;
use App\Domain\Notification\Contracts\SmsGateway;
use App\Domain\Notification\Models\SmsInbox;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    config(['notifications.inbound_sms_secret' => null]);
});

class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{to: string, body: string}> */
    public array $sent = [];

    public function send(string $to, string $message): SmsDispatchResult
    {
        $this->sent[] = ['to' => $to, 'body' => $message];

        return SmsDispatchResult::accepted('fake');
    }
}

it('replies with the case status for a valid STATUS command', function (): void {
    $gw = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $gw);

    $grievance = Grievance::factory()->inState(GrievanceState::InProgress)->create([
        'g_number' => 'GRM-2026-000777',
    ]);

    postJson(route('webhooks.sms.inbound'), [
        'from' => '+23276000000',
        'text' => "STATUS {$grievance->g_number}",
    ])->assertOk();

    expect($gw->sent)->toHaveCount(1);
    expect($gw->sent[0]['to'])->toBe('+23276000000');
    expect($gw->sent[0]['body'])->toContain('in progress');
    expect(SmsInbox::where('parsed_command', 'STATUS')->exists())->toBeTrue();
});

it('replies with a not-found message for an unknown g_number', function (): void {
    $gw = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $gw);

    postJson(route('webhooks.sms.inbound'), [
        'from' => '+23276000000',
        'text' => 'STATUS GRM-2099-999999',
    ])->assertOk();

    expect($gw->sent[0]['body'])->toContain('No case found');
});

it('replies with help for unrecognized messages', function (): void {
    $gw = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $gw);

    postJson(route('webhooks.sms.inbound'), [
        'from' => '+23276000000',
        'text' => 'hi',
    ])->assertOk();

    expect($gw->sent[0]['body'])->toContain('STATUS <your reference number>');
});

it('rejects webhook without shared secret when configured', function (): void {
    config(['notifications.inbound_sms_secret' => 'letmein']);
    app()->instance(SmsGateway::class, new FakeSmsGateway);

    postJson(route('webhooks.sms.inbound'), [
        'from' => '+23276000000',
        'text' => 'hi',
    ])->assertForbidden();
});

it('accepts webhook with matching shared secret', function (): void {
    config(['notifications.inbound_sms_secret' => 'letmein']);
    app()->instance(SmsGateway::class, new FakeSmsGateway);

    postJson(route('webhooks.sms.inbound'), [
        'from' => '+23276000000',
        'text' => 'hi',
    ], ['X-Inbound-Secret' => 'letmein'])->assertOk();
});
