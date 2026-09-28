<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectEngagementEventType;
use App\Enums\ProspectLifecycleState;
use App\Models\Prospect;
use App\Services\ProspectEngagementScorer;
use Illuminate\Support\Facades\Mail;

it('cools stale leads once without restarting outreach or deleting history', function (string $temperature): void {
    Mail::fake();
    $this->freezeTime();
    $prospect = Prospect::factory()->create(['lead_temperature' => $temperature]);
    $prospect->outreachState()->update([
        'last_engagement_at' => now()->subDays(14)->subSecond(),
        'engagement_score' => 20,
        'temperature_override' => $temperature,
        'lifecycle_state' => ProspectLifecycleState::NeedsPersonalisedVideo,
        'automation_status' => ProspectAutomationStatus::Paused,
    ]);
    $this->artisan('outreach:cool-inactive')->assertSuccessful();
    $this->artisan('outreach:cool-inactive')->assertSuccessful();
    expect($prospect->fresh()->lead_temperature)->toBe('cold')
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(20)
        ->and($prospect->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::Cold)
        ->and($prospect->outreachState->fresh()->automation_status)->toBe(ProspectAutomationStatus::Paused)
        ->and($prospect->outreachState->fresh()->next_action_at)->toBeNull()
        ->and($prospect->activities()->where('type', 'lead_temperature_cooled')->count())->toBe(1);
    Mail::assertNothingSent();
})->with(['hot', 'warm']);

it('keeps leads warm at the boundary and uses creation time when no interaction is recorded', function (): void {
    $this->freezeTime();
    $recent = Prospect::factory()->create(['lead_temperature' => 'warm']);
    $recent->outreachState()->update(['last_engagement_at' => now()->subDays(14)]);
    $legacy = Prospect::factory()->create(['lead_temperature' => 'hot', 'created_at' => now()->subDays(15)]);
    $deleted = Prospect::factory()->create(['lead_temperature' => 'hot', 'created_at' => now()->subDays(15)]);
    $deleted->delete();
    $this->artisan('outreach:cool-inactive')->assertSuccessful();
    expect($recent->fresh()->lead_temperature)->toBe('warm')
        ->and($legacy->fresh()->lead_temperature)->toBe('cold')
        ->and($deleted->refresh()->lead_temperature)->toBe('hot');
});

it('preserves replied lifecycle and stopped automation when cooling', function (): void {
    $prospect = Prospect::factory()->create(['lead_temperature' => 'hot', 'status' => 'replied']);
    $prospect->outreachState()->update([
        'last_engagement_at' => now()->subDays(15),
        'lifecycle_state' => ProspectLifecycleState::Replied,
        'automation_status' => ProspectAutomationStatus::Stopped,
    ]);
    $this->artisan('outreach:cool-inactive')->assertSuccessful();
    expect($prospect->fresh()->lead_temperature)->toBe('cold')
        ->and($prospect->fresh()->status)->toBe('replied')
        ->and($prospect->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::Replied)
        ->and($prospect->outreachState->fresh()->automation_status)->toBe(ProspectAutomationStatus::Stopped);
});

it('ignores scanners but allows a genuine interaction to warm a cooled lead again', function (): void {
    config(['outreach.ignored_engagement_sources' => ['scanner']]);
    $prospect = Prospect::factory()->create(['lead_temperature' => 'hot']);
    $lastInteraction = now()->subDays(15)->startOfSecond();
    $prospect->outreachState()->update(['last_engagement_at' => $lastInteraction, 'engagement_score' => 20]);
    $scorer = app(ProspectEngagementScorer::class);
    $scorer->record($prospect, ProspectEngagementEventType::EmailOpened, 'scanner-before', source: 'scanner');
    expect($prospect->outreachState->fresh()->last_engagement_at->equalTo($lastInteraction))->toBeTrue();
    $this->artisan('outreach:cool-inactive')->assertSuccessful();
    $scorer->record($prospect->fresh(), ProspectEngagementEventType::EmailOpened, 'scanner-after', source: 'scanner');
    expect($prospect->fresh()->lead_temperature)->toBe('cold');
    $scorer->record($prospect->fresh(), ProspectEngagementEventType::AuditClicked, 'real-click');
    expect($prospect->fresh()->lead_temperature)->toBe('hot');
});
