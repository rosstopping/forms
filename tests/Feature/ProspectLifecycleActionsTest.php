<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;
use App\Models\User;

it('provides manual pause resume temperature and score controls', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['status' => 'contacted']);

    $this->actingAs($admin)->patch(route('admin.prospects.lifecycle', $prospect), ['action' => 'pause'])->assertRedirect();
    expect($prospect->outreachState->fresh()->automation_status)->toBe(ProspectAutomationStatus::Paused);

    $this->patch(route('admin.prospects.lifecycle', $prospect), ['action' => 'resume'])->assertRedirect();
    $this->patch(route('admin.prospects.lifecycle', $prospect), ['action' => 'force_hot'])->assertRedirect();
    $state = $prospect->outreachState->fresh();
    expect($state->automation_status)->toBe(ProspectAutomationStatus::Paused)
        ->and($state->lifecycle_state)->toBe(ProspectLifecycleState::NeedsPersonalisedVideo)
        ->and($state->sequence_step)->toBe(ProspectSequenceStep::AwaitingPersonalisedVideo)
        ->and($prospect->fresh()->lead_temperature)->toBe('hot');

    $this->patch(route('admin.prospects.lifecycle', $prospect), ['action' => 'adjust_score', 'score_delta' => 7, 'reason' => 'Direct conversation'])->assertRedirect();
    expect($prospect->outreachState->fresh()->engagement_score)->toBe(7);
    $this->patch(route('admin.prospects.lifecycle', $prospect), ['action' => 'reset_score', 'reason' => 'Start again'])->assertRedirect();
    expect($prospect->outreachState->fresh()->engagement_score)->toBe(0);
});

it('supports protected manual outcomes and contact later dates', function (): void {
    $this->freezeTime();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['scheduled_send_at' => now()->addHour()]);

    $this->actingAs($admin)->patch(route('admin.prospects.lifecycle', $prospect), [
        'action' => 'mark_future_opportunity',
        'future_opportunity_at' => now('Europe/London')->addMonth()->format('Y-m-d\TH:i'),
    ])->assertRedirect();

    $state = $prospect->outreachState->fresh();
    expect($state->lifecycle_state)->toBe(ProspectLifecycleState::FutureOpportunity)
        ->and($state->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($state->future_opportunity_at)->not->toBeNull()
        ->and($prospect->fresh()->scheduled_send_at)->toBeNull()
        ->and($prospect->activities()->where('type', 'status_manually_changed')->exists())->toBeTrue();

    $this->patch(route('admin.prospects.lifecycle', $prospect), ['action' => 'force_hot'])
        ->assertSessionHasErrors('lifecycle_action');
    expect($prospect->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::FutureOpportunity);
});

it('blocks non administrators and validates lifecycle actions', function (): void {
    $prospect = Prospect::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($member)->patch(route('admin.prospects.lifecycle', $prospect), ['action' => 'pause'])->assertForbidden();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->patch(route('admin.prospects.lifecycle', $prospect), ['action' => 'adjust_score'])->assertSessionHasErrors(['score_delta', 'reason']);
});
