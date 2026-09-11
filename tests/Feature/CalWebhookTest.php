<?php

use App\Models\User;
use App\Models\WebsiteAudit;
use Illuminate\Testing\TestResponse;

function sendCalWebhook(array $event, string $secret = 'cal-secret'): TestResponse
{
    $payload = json_encode($event, JSON_THROW_ON_ERROR);

    return test()->call('POST', route('cal.webhook'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CAL_SIGNATURE_256' => hash_hmac('sha256', $payload, $secret),
    ], content: $payload);
}

beforeEach(function (): void {
    config(['services.cal.webhook_secret' => 'cal-secret']);
});

it('marks a claimed onboarding user call as booked from Cal.com', function (): void {
    $user = User::factory()->create();
    WebsiteAudit::factory()->for($user)->create(['claimed_at' => now()]);

    sendCalWebhook([
        'triggerEvent' => 'BOOKING_CREATED',
        'payload' => ['attendees' => [['email' => $user->email]]],
    ])->assertOk()->assertJson(['received' => true]);

    expect($user->fresh()->onboarding_call_booking_started_at)->not->toBeNull()
        ->and($user->fresh()->onboarding_call_booked_at)->not->toBeNull()
        ->and($user->fresh()->onboarding_call_completed_at)->toBeNull();
});

it('updates cancelled and completed onboarding calls', function (): void {
    $user = User::factory()->create([
        'onboarding_call_booking_started_at' => now()->subDay(),
        'onboarding_call_booked_at' => now()->subDay(),
    ]);
    WebsiteAudit::factory()->for($user)->create(['claimed_at' => now()]);

    sendCalWebhook([
        'triggerEvent' => 'BOOKING_CANCELLED',
        'payload' => ['attendees' => [['email' => $user->email]]],
    ])->assertOk();

    expect($user->fresh()->onboarding_call_booked_at)->toBeNull();

    sendCalWebhook([
        'triggerEvent' => 'MEETING_ENDED',
        'attendees' => [['email' => $user->email]],
    ])->assertOk();

    expect($user->fresh()->onboarding_call_booked_at)->not->toBeNull()
        ->and($user->fresh()->onboarding_call_completed_at)->not->toBeNull();
});

it('rejects Cal.com webhooks with an invalid signature', function (): void {
    sendCalWebhook([
        'triggerEvent' => 'BOOKING_CREATED',
        'payload' => ['attendees' => [['email' => 'person@example.test']]],
    ], 'wrong-secret')->assertBadRequest();
});

it('does not update users who did not join through Get started', function (): void {
    $user = User::factory()->create();

    sendCalWebhook([
        'triggerEvent' => 'BOOKING_CREATED',
        'payload' => ['attendees' => [['email' => $user->email]]],
    ])->assertOk();

    expect($user->fresh()->onboarding_call_booked_at)->toBeNull();
});
